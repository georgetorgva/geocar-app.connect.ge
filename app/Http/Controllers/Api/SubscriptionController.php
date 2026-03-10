<?php

namespace App\Http\Controllers\Api;

use App\Models\Admin\SiteMapModel;
use App\Models\Admin\OptionsModel;
use App\Models\Admin\PageModel;
use App\Models\Admin\TaxonomyModel;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

class SubscriptionController extends Controller
{
    public function subscribe(Request $request)
    {
        $response['success'] = false;
        $response['error'] = '';

        $input = $request->only([
            'email', 'position', 'company', 'investor_type', 'full_name', 'recaptcha_token', 'locale'
        ]);

        $rules = [
            'email' => 'bail|required|string|max:100|email:rfc',
            'full_name' => 'bail|required|string|max:250',
            'position' => 'bail|required|string|max:300',
            'company' => 'bail|required|string|max:300',
            'investor_type' => 'bail|required|string|max:300|exists:taxonomy,slug',
            'recaptcha_token' => 'bail|required|string',
            'locale' => 'bail|nullable|string|in:en,ka,ru,ge'
        ];

        $validator = \Validator::make($input, $rules);

        if ($validator->fails())
        {
            $response['error'] = $validator->errors()->first();

            return $response;
        }

        $email = strtolower($input['email']);
        $locale = $request->locale ?: config('app.locale', 'en');

        $subscriber = \DB::table('subscribers')->select(['status', 'id', 'token'])->where('email', $email)->first();

        $jsonInput = json_encode([
            'full_name' => $input['full_name'],
            'position' => $input['position'],
            'company' => $input['company'],
            'investor_type' => $input['investor_type']
        ], JSON_UNESCAPED_UNICODE);

        if ($subscriber)
        {
            if ($subscriber->status === 'active')
            {
                $response['error'] = 'unable to subscribe';

                return $response;
            }

            $recaptchaValidationResponse = self::recaptchaTokenIsValid($input['recaptcha_token']);

            if (!$recaptchaValidationResponse['success'])
            {
               $response['error'] = $recaptchaValidationResponse['error'] ?: 'invalid recaptcha token';

               return $response;
            }

            $sendConfirmationLinkResponse = self::sendConfirmationEmail($subscriber->token, $email);

            if (!$sendConfirmationLinkResponse['success'])
            {
                $response['error'] = $sendConfirmationLinkResponse['error'];

                return $response;
            }

            \DB::table('subscribers')->where('id', $subscriber->id)->update([
                'status' => 'pending',
                'info' => $jsonInput
            ]);

            $response['success'] = true;

            return $response;
        }

        $recaptchaValidationResponse = self::recaptchaTokenIsValid($input['recaptcha_token']);

        if (!$recaptchaValidationResponse['success'])
        {
            $response['error'] = $recaptchaValidationResponse['error'] ?: 'invalid recaptcha token';

            return $response;
        }

        $token = bin2hex(random_bytes(32));

        $sendConfirmationLinkResponse = self::sendConfirmationEmail($token, $email);

        if (!$sendConfirmationLinkResponse['success'])
        {
            $response['error'] = $sendConfirmationLinkResponse['error'];

            return $response;
        }

        \DB::table('subscribers')->insert([
            'email' => $input['email'],
            'token' => $token,
            'info' => $jsonInput,
            'status' => 'pending',
            'lang' => $locale
        ]);

        $response['success'] = true;

        return $response;
    }

    public function unsubscribe(Request $request)
    {
        $response['success'] = false;
        $response['error'] = '';

        $rules = [
            'email' => 'bail|required|string|email:rfc',
            'token' => 'bail|required|string|max:100'
        ];

        $input = $request->only([
            'email',
            'token'
        ]);

        $validator = \Validator::make($input, $rules);

        if ($validator->fails())
        {
            $response['error'] = $validator->errors()->first();

            return $response;
        }

        $email = strtolower($input['email']);

        $subscriber = \DB::table('subscribers')->select(['id'])->where('email', $email)->where('token', $input['token'])->where('status', 'active')->first();

        if (!$subscriber)
        {
            $response['error'] = 'subscriber not found';

            return $response;
        }

        \DB::table('subscribers')->where('id', $subscriber->id)->update(['status' => 'passive']);

        $response['success'] = true;

        return $response;
    }

    public function confirm(Request $request)
    {
        $input = $request->only(['payload']);

        $rules = [
            'payload' => 'bail|required|string'
        ];

        $validator = \Validator::make($input, $rules);

        if ($validator->fails())
        {
            return response(['error' => $validator->errors()->first()], 400);
        }

        try
        {
            $decryptedPayload = Crypt::decryptString($input['payload']);

            $decodedPayload = json_decode($decryptedPayload, 'payload') ?? [];

            $payloadRules = [
                'validUntil' => 'bail|required|integer|gt:0',
                'token' => 'bail|required|string',
                'email' => 'bail|required|string|email:rfc',
                'action' => 'bail|required|string|in:confirm'
            ];

            $payloadValidator = \Validator::make($decodedPayload, $payloadRules);

            if ($payloadValidator->fails())
            {
                return response(['error' => 'invalid payload'], 400);
            }

            $subscriber = \DB::table('subscribers')->select(['id', 'status'])->where('email', $decodedPayload['email'])->where('token', $decodedPayload['token'])->first();

            if (!$subscriber)
            {
                return response(['error' => 'invalid credentials'], 400);
            }

            $manageSubscriptionUrl = rtrim(env('WEBSITE_URL'), '/') . '/manage-subscription?';

            $queryParameters = [
                'token' => $decodedPayload['token'],
                'email' => $decodedPayload['email'],
                'action' => 'manage'
            ];

            if ($subscriber->status === 'active')
            {
                $redirectUrl = $manageSubscriptionUrl . http_build_query($queryParameters);

                return redirect()->away($redirectUrl);
            }

            $queryParameters['status'] = 'success';
            $queryParameters['action'] = 'confirm';

            $dateTime = new \DateTime('now', new \DateTimeZone('Asia/Tbilisi'));

            if ($decodedPayload['validUntil'] < $dateTime->getTimestamp())
            {
                $queryParameters['status'] = 'error';
                $queryParameters['message'] = 'confirmation link expired';

                $redirectUrl = $manageSubscriptionUrl . http_build_query($queryParameters);

                return redirect()->away($redirectUrl);
            }

            \DB::table('subscribers')->where('id', $subscriber->id)->update(['status' => 'active']);

            $redirectUrl = $manageSubscriptionUrl . http_build_query($queryParameters);

            $managementQueryParameters = [
                'action' => 'manage',
                'email' => $decodedPayload['email'],
                'token' => $decodedPayload['token']
            ];

            $manageSubscriptionLink = $manageSubscriptionUrl . http_build_query($managementQueryParameters);
            $managementTemplate = view('subscription.confirmed-message', ['manageSubscriptionLink' => $manageSubscriptionLink])->render();
            $subject = 'Subscription Confirmed';

            self::sendMessageBySendgrid($decodedPayload['email'], $managementTemplate, $subject);

            return redirect()->away($redirectUrl);
        }

        catch (DecryptException $exception)
        {
            $debugModeEnabled = env('APP_DEBUG');

            return response(['error' => $debugModeEnabled ? $exception->getMessage() : 'unprocessable payload'], 422);
        }
    }

    public function getData(Request $request)
    {
        $response['success'] = false;
        $response['error'] = '';

        $rules = [
            'email' => 'bail|required|string|email:rfc',
            'token' => 'bail|required|string|max:100'
        ];

        $input = $request->only([
            'email',
            'token'
        ]);

        $validator = \Validator::make($input, $rules);

        if ($validator->fails())
        {
            $response['error'] = $validator->errors()->first();

            return $response;
        }

        $email = strtolower($input['email']);

        $subscriber = \DB::table('subscribers')->select(['info', 'email'])->where('email', $email)->where('token', $input['token'])->where('status', 'active')->first();

        if (!$subscriber)
        {
            $response['error'] = 'subscriber not found';

            return $response;
        }

        $response['success'] = true;

        $response['data']['info'] = json_decode($subscriber->info, true) ?? [];
        $response['email'] = $subscriber->email;

        return $response;
    }

    public function updateData(Request $request)
    {
        $response['success'] = false;
        $response['error'] = '';

        $input = $request->only([
            'email',
            'position',
            'company',
            'investor_type',
            'full_name',
            'token',
            'locale'
        ]);

        $rules = [
            'email' => 'bail|required|string|max:100|email:rfc',
            'full_name' => 'bail|required|string|max:250',
            'position' => 'bail|required|string|max:300',
            'company' => 'bail|required|string|max:300',
            'investor_type' => 'bail|required|string|max:300|exists:taxonomy,slug',
            'token' => 'bail|required|string|max:100',
            'locale' => 'bail|nullable|string|in:en,ka,ru,ge'
        ];

        $validator = \Validator::make($input, $rules);

        if ($validator->fails())
        {
            $response['error'] = $validator->errors()->first();

            return $response;
        }

        $email = strtolower($input['email']);

        $subscriber = \DB::table('subscribers')->select('id')->where('email', $email)->where('token', $input['token'])->where('status', 'active')->first();

        if (!$subscriber)
        {
            $response['error'] = 'inactive subscriber';

            return $response;
        }

        $jsonInput = json_encode([
            'full_name' => $input['full_name'],
            'position' => $input['position'],
            'company' => $input['company'],
            'investor_type' => $input['investor_type']
        ], JSON_UNESCAPED_UNICODE);

        $updateData = ['info' => $jsonInput];

        if ($request->locale)
        {
            $updateData['lang'] = $request->locale;
        }

        \DB::table('subscribers')->where('id', $subscriber->id)->update($updateData);

        $response['success'] = true;

        return $response;
    }

    public function sendManagementLink(Request $request)
    {
        $response['success'] = false;
        $response['error'] = '';

        $rules = [
            'email' => 'bail|required|string|email:rfc',
            'recaptcha_token' => 'bail|required|string'
        ];

        $input = $request->only([
            'email',
            'recaptcha_token'
        ]);

        $validator = \Validator::make($input, $rules);

        if ($validator->fails())
        {
            $response['error'] = $validator->errors()->first();

            return $response;
        }

        $email = strtolower($input['email']);

        $subscriber = \DB::table('subscribers')->select(['token'])->where('email', $email)->where('status', 'active')->first();

        if (!$subscriber)
        {
            $response['error'] = 'subscriber not found';

            return $response;
        }

        $recaptchaValidationResponse = self::recaptchaTokenIsValid($input['recaptcha_token']);

        if (!$recaptchaValidationResponse['success'])
        {
            $response['error'] = $recaptchaValidationResponse['error'] ?: 'invalid recaptcha token';

            return $response;
        }

        $websiteUrl = env('WEBSITE_URL');

        if (!$websiteUrl)
        {
            $response['error'] = 'website url not defined';

            return $response;
        }

        $websiteUrl = rtrim($websiteUrl, '/');

        $queryParameters = [
            'token' => $subscriber->token,
            'email' => $email,
            'action' => 'manage'
        ];

        $subscriptionManagementLink = $websiteUrl . '/manage-subscription?' . http_build_query($queryParameters);
        $renderedTemplate = view('subscription.manage', ['manageSubscriptionLink' => $subscriptionManagementLink])->render();
        $subject = 'Subscription Management';

        $sendMessageResponse = self::sendMessageBySendgrid($email, $renderedTemplate, $subject);

        $response['success'] = $sendMessageResponse['success'];
        $response['error'] = $sendMessageResponse['error'];

        return $response;
    }

    // meta methods

    private static function recaptchaTokenIsValid($token)
    {
        $options = new OptionsModel;

        $response['error'] = '';
        $response['success'] = false;

        $secret = $options->getSetting('website_recaptcha_secret_key');

        if (!$secret)
        {
            $response['error'] = 'secret not defined';

            return $response;
        }

        $endpoint = 'https://www.google.com/recaptcha/api/siteverify';

        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ];

        $body = [
            'secret' => $secret,
            'response' => $token,
        ];

        $apiResponse = Http::withHeaders($headers)->asForm()->post($endpoint, $body)->json();

        $response['success'] = $apiResponse['success'] ?? false;

        if (!$response['success'])
        {
            $response['error'] = $apiResponse['error-codes'][0] ?? 'invalid token';
        }

        return $response;
    }

    private static function sendConfirmationEmail($token, $email)
    {
        $appUrl = rtrim(env('APP_URL'), '/');

        $dateTime = new \DateTime('now', new \DateTimeZone('Asia/Tbilisi'));

        $payload = json_encode([
            'validUntil' => $dateTime->getTimestamp() + 86400,
            'token' => $token,
            'email' => $email,
            'action' => 'confirm'
        ], JSON_UNESCAPED_UNICODE);

        $encryptedPayload = Crypt::encryptString($payload);

        $confirmationLink = $appUrl . '/api/subscription/confirm?payload=' . urlencode($encryptedPayload);

        $subject = 'Subscription confirmation for Georgia Capital';
        $confirmationTemplate = view('subscription.confirm', ['confirmationLink' => $confirmationLink])->render();

        $sendMessageResponse = self::sendMessageBySendgrid($email, $confirmationTemplate, $subject);

        return [
            'success' => $sendMessageResponse['success'],
            'error' => $sendMessageResponse['error']
        ];
    }

    private static function sendMessageBySendgrid($email, $message, $subject, $mimeType = 'text/html')
    {
        $response['success'] = false;
        $response['error'] = '';

        $options = new OptionsModel;

        $apiKey = $options->getSetting('sendgrid_api_key', 'subscription_configurations');
        $senderEmail = $options->getSetting('sendgrid_sender_email', 'subscription_configurations');
        $senderName = $options->getSetting('sendgrid_sender_name', 'subscription_configurations');

        if (!$apiKey || !$senderEmail || !$senderName)
        {
            $response['error'] = 'api credentials not defined';

            return $response;
        }

        try
        {
            $sendgrid = new \SendGrid($apiKey);

            $from = new \SendGrid\Mail\From($senderEmail, $senderName);
            $to = new \SendGrid\Mail\To($email);
            $subject = new \SendGrid\Mail\Subject($subject);

            $content = new \SendGrid\Mail\Content($mimeType, $message);
            $mail = new \SendGrid\Mail\Mail($from, $to, $subject, $content);

            $apiResponse = $sendgrid->send($mail);
            $statusCode = $apiResponse->statusCode();

            if ($statusCode >= 200 && $statusCode < 300)
            {
                $response['success'] = true;

                return $response;
            }

            $response['error'] = 'unknown api error';
            $response['statusCode'] = $statusCode;
        }

        catch (\Exception $exception)
        {
            $debugModeEnabled = env('APP_DEBUG');

            $response['error'] = $debugModeEnabled ? $exception->getMessage() : 'fatal error';

            return $response;
        }

        return $response;
    }
}

