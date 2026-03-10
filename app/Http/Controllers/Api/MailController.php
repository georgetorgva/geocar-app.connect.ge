<?php

namespace App\Http\Controllers\Api;

use App\Models\Admin\WidgetModel;
use Illuminate\Mail\SentMessage;

class MailController
{
    public static function sendContact($recipients, $templateData = [])
    {
        $response['success'] = true;
        $response['thankYouData'] = [];

        $mailFrom = env('MAIL_FROM_ADDRESS');
        $mailFromName = env('MAIL_FROM_NAME');
        $subject = tr('contact-form-subject');
        $template = 'contact-form';

        try
        {
            $replyToEmail = self::getReplyToEmail('email', $templateData);

            if (!$replyToEmail) throw new \Exception('replyTo not defined');

            if (!is_array($recipients)) throw new \Exception('invalid data type');

            $recipients = self::getValidEmails($recipients);

            if (empty($recipients)) throw new \Exception('invalid emails');

            $language = requestLan();

            app()->setLocale($language);

            $placeholders = [
                'name' => 'string',
                'email' => 'string',
                'phone' => 'string',
                'comment' => 'string',
                'services' => 'array'
            ];

            $restructuredData = [];

            foreach ($templateData as $item)
            {
                $placeholderKey = strtolower($item['title'] ?? '');

                if (isset($placeholders[$placeholderKey]))
                {
                    $type = $placeholders[$placeholderKey];

                    if ($type === 'string')
                    {
                        $labelPlaceholder = 'form_' . $placeholderKey;

                        $restructuredData[$labelPlaceholder] = $item['text'] ?? '';
                    }

                    elseif ($type === 'array')
                    {
                        $arrayItems = $item['text'] ?? [];

                        if (is_array($arrayItems))
                        {
                            $labelPlaceholder = 'form_' . $placeholderKey;

                            $restructuredData[$labelPlaceholder] = [];

                            foreach ($arrayItems as $arrayItem)
                            {
                                $valueByLanguage = $arrayItem['title'][$language] ?? '';

                                if ($valueByLanguage)
                                {
                                    $restructuredData[$labelPlaceholder][] = $valueByLanguage;
                                }
                            }
                        }
                    }
                }
            }

            $sendResponse = \Mail::send($template, ['data' => $restructuredData], function ($message) use ($replyToEmail, $subject, $mailFrom, $mailFromName, $recipients) {
                foreach ($recipients as $email) $message->to($email);

                $message->replyTo($replyToEmail);
                $message->subject($subject);
                $message->from($mailFrom, $mailFromName);
            });

            $response['success'] = $sendResponse instanceof SentMessage;

            if ($response['success'])
            {
                $response['thankYouData']['email'] = $replyToEmail;
                $response['thankYouData']['language'] = $language;
            }
        }

        catch (\Exception $exception)
        {
            $response['success'] = false;
            $response['message'] = $exception->getMessage();
        }

        return $response;
    }

    public static function sendThankYouEmail($emailData)
    {
        $response['success'] = true;

        $mailFromName = env('MAIL_FROM_NAME');
        $mailFrom = env('MAIL_FROM_ADDRESS');
        $template = 'thank-you-email';

        $response['success'] = true;

        try
        {
            $widgetModel = new WidgetModel();

            $config = [
                'name' => 'thank_you',
                'lang' => $emailData['language']
            ];

            $widgetData = $widgetModel->getWidgetByName($config);

            $restructuredData = [
                // 'cover' => $widgetData['cover'][0]['url'] ?? '',
                'text' => $widgetData['text'] ?? ''
            ];

            $subject = tr('thank_you_subject');
            $recipient = $emailData['email'];

            $sendResponse = \Mail::send($template, ['data' => $restructuredData], function ($message) use ($subject, $mailFrom, $mailFromName, $recipient) {
                $message->to($recipient);
                $message->subject($subject);
                $message->from($mailFrom, $mailFromName);
            });

            $response['success'] = $sendResponse instanceof SentMessage;
        }

        catch (\Exception $exception)
        {
            $response['success'] = false;
            $response['message'] = $exception->getMessage();
        }

        return $response;
    }

    public static function getValidEmails($emails)
    {
        $validEmails = [];

        foreach ($emails as $email)
        {
            $formatIsValid = self::emailFormatIsValid($email);

            if ($formatIsValid)
            {
                $validEmails[] = $email;
            }
        }

        return $validEmails;
    }

    public static function emailFormatIsValid($email)
    {
        if (!is_string($email)) return false;

        $sendgridEmailsPattern = '/[A-Za-z0-9\._%\+-]+@[A-Za-z0-9\.-]+\.[A-Za-z]{2,6}/';

        return preg_match($sendgridEmailsPattern, $email);
    }

    private static function getReplyToEmail($key, $formData)
    {
        foreach ($formData as $item)
        {
            $paramName = strtolower($item['title'] ?? '');

            if ($paramName === $key)
            {
                $email = (string) ($item['text'] ?? '');

                if (self::emailFormatIsValid($email)) return $email;
            }
        }

        return null;
    }
}