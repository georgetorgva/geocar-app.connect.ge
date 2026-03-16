<?php

namespace App\Http\Controllers\Admin;

use App\Models\Admin\OptionsModel;
use App\Models\Admin\PageModel;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rule;

use SendGrid\Mail\Mail;
use SendGrid\Mail\Personalization;
use SendGrid\Mail\To;
use SendGrid\Mail\Section;
use SendGrid\Mail\Subject;
use SendGrid\Mail\From;
use SendGrid\Mail\Header;

use App\Models\Subscription\Subscriber;

class Subscription extends Controller
{
    protected $mainModel;

    public function __construct()
    {
        $this->mainModel = new Subscriber;
    }

    public function getSubscribers(Request $request)
    {
        $input = $request->all();

        $response = $this->mainModel->getList($input);

        return $response;
    }

    public function getSubscriber(Request $request)
    {
        $response = $this->mainModel->getItem($request->id);

        return $response;
    }

    public function deleteSubscriber(Request $request)
    {
        return $this->mainModel->deleteItem(['id' => $request->id]);
    }

    public function updateSubscriber(Request $request)
    {
        $input = $request->all();

        if (!$request->id)
        {
            $rules = [
                'email' => ['bail','required', 'string', 'email:rfc', Rule::unique('subscribers', 'email')],
                'lang' => 'bail|nullable|string|size:2',
                'status' => 'bail|required|string|max:50'
            ];

            $validator = \Validator::make($input, $rules);

            if ($validator->fails())
            {
                return response(['message' => $validator->errors()->first()], 201);
            }
        }

        else
        {
            $rules = [
                'id' => ['bail','required', 'integer', 'min:1'],
            ];

            $validator = \Validator::make($input, $rules);

            if ($validator->fails())
            {
                return response(['message' => $validator->errors()->first()], 201);
            }
        }

        return $this->mainModel->updateItem($input);
    }

    public function exportSubscribers(Request $request)
    {
        return $this->mainModel->getAll();
    }

    public function getConfigs()
    {
        return config('subscription') ?? [];
    }

    public function sendEmailToSubscribers(Request $request)
    {
        $response['success'] = false;
        $response['error'] = '';

        $input = $request->only(['mode', 'templateData', 'locale']);

        $rules = [
            'templateData' => 'bail|required|array',
            'locale' => 'bail|required|string|in:en,ka,ge,ru',
            'mode' => 'bail|required|string|in:real,test',
            'templateData.title.payload' => 'bail|required|string'
        ];

        $validator = \Validator::make($input, $rules);

        if ($validator->fails())
        {
            return response(['error' => $validator->errors()->first()], 201);
        }

        $apiConfigs = self::getSettings([
            'sendgrid_api_key',
            'sendgrid_sender_email',
            'sendgrid_sender_name'
        ], 'subscription_configurations');

        if (!$apiConfigs['success'])
        {
            $response['error'] = $apiConfigs['error'];

            return $response;
        }

        $recipients = [];

        if ($input['mode'] === 'real')
        {
            $environment = env('APP_ENV');

            if ($environment !== 'production')
            {
                $response['success'] = true;
                $response['message'] = 'fake send';

                return $response;
            }

            $recipients = \DB::table('subscribers')->select(['email', 'token'])
                                                   ->where('status', 'active')
                                                   ->where('lang', $input['locale'])
                                                   ->get()
                                                   ->pluck('email', 'token')
                                                   ->toArray();

            if (empty($recipients))
            {
                $response['error'] = 'there is no active subscription';

                return response($response, 201);
            }
        }

        else
        {
            $options = new OptionsModel;

            $testEmails = $options->getSetting('sendgrid_test_emails', 'subscription_configurations');

            if (empty($testEmails) || !is_string($testEmails))
            {
                $response['error'] = 'test emails not defined';

                return response($response, 201);
            }

            $testEmails = preg_split('/\s*[\n,]\s*/', $testEmails);

            $recipients = [];

            $sendgridEmailPattern = '/^[A-Za-z0-9\._%\+-]+@[A-Za-z0-9\.-]+\.[A-Za-z]{2,6}$/';

            foreach ($testEmails as $testEmail)
            {
                if (!is_string($testEmail) || !preg_match($sendgridEmailPattern, $testEmail)) continue;

                $randomTemporaryToken = bin2hex(random_bytes(32));

                $recipients[$randomTemporaryToken] = $testEmail;
            }

            if (empty($recipients))
            {
                $response['error'] = 'invalid test emails';

                return response($response, 201);
            }
        }

        $plainFieldTypes = ['text', 'title', 'textarea', 'editor'];

        $manageSubscriptionUrl = rtrim(env('WEBSITE_URL'), '/') . '/manage-subscription';

        app()->setLocale($input['locale']);

        $htmlContentForSendgrid = view('subscription.sendgrid-template', [
            'templateData' => $input['templateData'],
            'plainFieldTypes' => $plainFieldTypes,
            'manageSubscriptionUrl' => $manageSubscriptionUrl,
            'manageSubscriptionQueryStringPlaceholder' => '{{manageSubscriptionQueryString}}',
            'manageSubscriptionTitle' => tr('Manage Subscription')
        ])->render();

        $subject = $input['templateData']['title']['payload'];

        $recipients = array_chunk($recipients, 1000, true);
        $numOfRequestsToSend = count($recipients);

        $sendErrors = [];
        $authorizedUser = auth()->user();

        $parentLogId = self::saveParentLog($authorizedUser->id, $numOfRequestsToSend, $htmlContentForSendgrid);

        foreach ($recipients as $chunk)
        {
            $emails = array_values($chunk);

            $childLogItemId = self::saveChildLog($parentLogId, $emails);

            $sendResponse = self::sendMessageBySendgrid($chunk, $htmlContentForSendgrid, $subject, $apiConfigs['settings']);

            self::updateChildLog($childLogItemId, [
                'response' => $sendResponse,
                'success' => (int) $sendResponse['success']
            ]);

            if (!$sendResponse['success'])
            {
                $sendErrors[] = $sendResponse['error'];
            }
        }

        $errorsCount = count($sendErrors);
        $response['success'] = $errorsCount == 0;

        if (!$response['success'])
        {
            $response['error'] = 'Chunks accepted: ' . ($numOfRequestsToSend - $errorsCount) . ' | ' . 'Chunks rejected: ' . $errorsCount . ' | ' .  implode(' | ', $sendErrors);
        }

        return response($response, $response['success'] ? 200 : 201);
    }

    public function getContentData(Request $request)
    {
        $response['success'] = false;
        $response['error'] = '';
        $response['list'] = [];

        $rules = [
            'contentType' => 'bail|required|string',
            'locale' => 'bail|required|string|max:2',
            'limit' => 'bail|required|integer|min:1|max:10000',
            'searchText' => 'bail|nullable|string|max:500'
        ];

        $input = $request->only([
            'contentType',
            'locale',
            'limit',
            'searchText'
        ]);

        $validator = \Validator::make($input, $rules);

        if ($validator->fails())
        {
            $response['error'] = $validator->errors()->first();

            return $response;
        }

        $pageModel = new PageModel;

        $selectConfig = [
            'contentType' => $input['contentType'],
            'limit' => $input['limit'],
            'translate' => $input['locale'],
            'status' => 'published'
        ];

        if ($request->searchText)
        {
            $selectConfig['searchText'] = $input['searchText'];
        }

        $contentData = $pageModel->getPages($selectConfig);

        if (!empty($contentData['list']))
        {
            $response['list'] = $contentData['list'];
        }

        $response['success'] = true;

        return $response;
    }

    public function getConfigurationData(Request $request)
    {
        $fieldsConfig = config('subscription.configFields');

        if (empty($fieldsConfig)) return [];

        $options = new OptionsModel;
        $data = [];

        foreach ($fieldsConfig as $row)
        {
            foreach ($row as $field => $record)
            {
                $data[$field] = $options->getSetting($field, 'subscription_configurations');
            }
        }

        return $data;
    }

    public function updateConfigurationData(Request $request)
    {
        $fieldsConfig = config('subscription.configFields');

        if (empty($fieldsConfig))
        {
            return response(['error' => 'fields not defined'], 201);
        }

        $fieldsToSelect = [];
        $rules = [];

        foreach ($fieldsConfig as $row)
        {
            foreach ($row as $field => $record)
            {
                $rules[$field] = $record['validationRule'] ?? ['bail', 'nullable', 'string'];

                $fieldsToSelect[] = $field;
            }
        }

        if (empty($rules))
        {
            return response(['error' => 'validation rules not defined'], 201);
        }

        $input = $request->only($fieldsToSelect);

        $validator = \Validator::make($input, $rules);

        if ($validator->fails())
        {
            return response(['error' => $validator->errors()->first()], 201);
        }

        $configsToInsert = [];
        $contentGroup = 'subscription_configurations';

        foreach ($input as $parameter => $value)
        {
            $newRecord = [
                'key' => $parameter,
                'value' => $value,
                'content_group' => $contentGroup,
                'data_type' => 'string',
                'revision' => ''
            ];

            $record = \DB::table('options')->select(['id'])
                                           ->where('key', $parameter)
                                           ->where('content_group', 'subscription_configurations')
                                           ->first();

            if (!$record)
            {
                $configsToInsert[] = $newRecord;
            }

            else
            {
                \DB::table('options')->where('id', $record->id)->update($newRecord);
            }
        }

        if (!empty($configsToInsert))
        {
            \DB::table('options')->insert($configsToInsert);
        }

        return ['success' => true];
    }

    // helpers

    private static function sendMessageBySendgrid($recipients, $message, $subject, $apiConfigs)
    {
        $response['success'] = false;
        $response['error'] = '';

        try
        {
            $sendgrid = new \SendGrid($apiConfigs['sendgrid_api_key']);

            $mail = new \SendGrid\Mail\Mail();
            $mail->setFrom($apiConfigs['sendgrid_sender_email'], $apiConfigs['sendgrid_sender_name']);
            $mail->setSubject($subject);

            $mail->addContent('text/html', $message);

            foreach ($recipients as $token => $recipient)
            {
                $personalization = new \SendGrid\Mail\Personalization();
                $personalization->addTo(new \SendGrid\Mail\To($recipient));

                $subscriptionControlString = 'action=manage&email=' . urlencode($recipient) . '&token=' . $token;

                $personalization->addDynamicTemplateData('{{manageSubscriptionQueryString}}', $subscriptionControlString);

                $mail->addPersonalization($personalization);
            }

            $apiResponse = $sendgrid->send($mail);

            $response['httpStatusCode'] = $apiResponse->statusCode();
            $response['apiResponseBody'] = $apiResponse->body();

            if ($response['httpStatusCode'] >= 200 && $response['httpStatusCode'] < 300)
            {
                $response['success'] = true;
            }

            else
            {
                $response['error'] = 'unknown api error';
            }
        }

        catch (\Exception $exception)
        {
            $response['error'] = $exception->getMessage();
        }

        return $response;
    }

    private static function getSettings($keys, $settingsGroup)
    {
        $optionsModel = new OptionsModel;

        $response['success'] = true;
        $response['error'] = '';
        $response['settings'] = [];

        foreach ($keys as $key)
        {
            $value = $optionsModel->getSetting($key, $settingsGroup);

            if (empty($value))
            {
                $response['success'] = false;

                $response['error'] = $key . ' not defined';

                break;
            }

            $response['settings'][$key] = $value;
        }

        return $response;
    }

    private static function saveParentLog($userId, $chunksCount, $messageBody)
    {
        $request = request();

        return \DB::table('subscription_log')->insertGetid([
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
            'user_id' => $userId,
            'chunks_count' => $chunksCount,
            'message_body' => $messageBody
        ]);
    }

    private function saveChildLog($parentId, $input)
    {
        return \DB::table('subscription_log_items')->insertGetid([
            'subscription_log_id' => $parentId,
            'input' => is_scalar($input) ? $input : json_encode($input, JSON_UNESCAPED_UNICODE)
        ]);
    }

    private function updateChildLog($subscriptionLogItemId, $record)
    {
        $recordToWrite = [];

        foreach ($record as $field => $value)
        {
            $recordToWrite[$field] = is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        \DB::table('subscription_log_items')->where('id', $subscriptionLogItemId)->update($recordToWrite);
    }
}
