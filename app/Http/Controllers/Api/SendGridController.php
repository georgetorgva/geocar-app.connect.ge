<?php

namespace App\Http\Controllers\Api;

use SendGrid\Mail\Mail;
use SendGrid\Mail\Personalization;
use SendGrid\Mail\To;
use SendGrid\Mail\Section;
use SendGrid\Mail\Subject;
use SendGrid\Mail\From;
use SendGrid\Mail\Header;

use App\Models\Admin\WidgetModel;

class SendGridController
{
    public static function send($recipients, $templateData = [])
    {
        $response['success'] = true;

        $response['thankYouData'] = [];

        $templateId = env('SENDGRID_CONTACT_TEMPLATE_ID');
        $apiKey = env('SENDGRID_API_KEY');
        $mailFrom = env('MAIL_FROM_ADDRESS');
        $mailFromName = env('MAIL_FROM_NAME');

        try
        {
            $sendgrid = new \SendGrid($apiKey);
            $email = new Mail;

            $replyToEmail = self::getReplyToEmail('email', $templateData);

            if (!$replyToEmail) throw new \Exception('replyTo not defined');

            if (!is_array($recipients)) throw new \Exception('invalid data type');

            $email->setFrom($mailFrom, $mailFromName);
            $email->setReplyTo($replyToEmail);
            $email->setTemplateId($templateId);

            $recipients = self::getValidEmails($recipients);

            if (empty($recipients)) throw new \Exception('invalid emails');

            $placeholders = [
                'name' => 'string',
                'email' => 'string',
                'phone' => 'string',
                'comment' => 'string',
                'services' => 'array'
            ];

            $substitutions = [];

            $language = requestLan();

            app()->setLocale($language);

            $subject = tr('contact-form-subject');

            foreach ($templateData as $item)
            {
                $placeholderKey = strtolower($item['title'] ?? '');

                if (isset($placeholders[$placeholderKey]))
                {
                    $type = $placeholders[$placeholderKey];

                    if ($type === 'string')
                    {
                        $substitutions[$placeholderKey] = $item['text'] ?? '';

                        $labelPlaceholder = 'form_' . $placeholderKey;

                        $substitutions[$labelPlaceholder] = tr($labelPlaceholder);
                    }

                    elseif ($type === 'array')
                    {
                        $restructuredValues = [];

                        $arrayItems = $substitutions[$placeholderKey] = $item['text'] ?? [];

                        if (is_array($arrayItems))
                        {
                            $labelPlaceholder = 'form_' . $placeholderKey;
                            $arrayItemPlaceholder = $placeholderKey . '_item';

                            $substitutions[$labelPlaceholder] = tr($labelPlaceholder);

                            foreach ($arrayItems as $arrayItem)
                            {
                                $valueByLanguage = $arrayItem['title'][$language] ?? '';

                                if ($valueByLanguage)
                                {
                                    $restructuredValues[] = [
                                        $arrayItemPlaceholder => $valueByLanguage
                                    ];
                                }
                            }

                            $substitutions[$placeholderKey] = $restructuredValues;
                        }
                    }
                }
            }

            foreach ($recipients as $recipient)
            {
                $personalization = new Personalization();

                $to = new To($recipient);

                $personalization -> addSubstitution('subject', $subject);

                $personalization->addTo($to);

                foreach ($substitutions as $placeholder => $value)
                {
                    $personalization->addDynamicTemplateData($placeholder, $value);
                }

                $email -> addPersonalization($personalization);
            }

            $apiResponse = $sendgrid->send($email);

            $response['success'] = $apiResponse->statusCode() == 202;

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

        $templateId = env('SENDGRID_THANK_YOU_TEMPLATE_ID');
        $apiKey = env('SENDGRID_API_KEY');
        $mailFrom = env('MAIL_FROM_ADDRESS');
        $mailFromName = env('MAIL_FROM_NAME');

        $response['success'] = true;

        try
        {
            $widgetModel = new WidgetModel();

            $config = [
                'name' => 'thank_you',
                'lang' => $emailData['language']
            ];

            $widgetData = $widgetModel->getWidgetByName($config);

            $substitutions = [
                'cover' => $widgetData['cover'][0]['url'] ?? '',
                'text' => $widgetData['text'] ?? ''
            ];

            // if (!$cover || !$text) throw new \Exception('placeholders not defined');

            $sendgrid = new \SendGrid($apiKey);
            $email = new Mail;

            $email->setFrom($mailFrom, $mailFromName);
            $email->setTemplateId($templateId);

            $personalization = new Personalization();

            $to = new To($emailData['email']);

            $subject = tr('thank_you_subject');

            $personalization->addSubstitution('subject', $subject);

            $personalization->addTo($to);

            foreach ($substitutions as $placeholder => $value)
            {
                $personalization->addDynamicTemplateData($placeholder, $value);
            }

            $email -> addPersonalization($personalization);

            $apiResponse = $sendgrid->send($email);

            $response['success'] = $apiResponse->statusCode() == 202;
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

    public function test()
    {
//        $emailData = [
//            'email' => 'nodo@connect.ge',
//            'language' => 'ge'
//        ];
//
//        dd(self::sendThankYouEmail($emailData));
    }
}