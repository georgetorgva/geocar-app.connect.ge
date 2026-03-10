<?php

namespace App\Http\Controllers\Admin\Shop\services;

use Error;
use MailchimpTransactional\ApiClient;
use App\Http\Controllers\Api\ApiController;

class Email extends ApiController
{
    public static function sendEmail($params = [])
    {
        if (!_cv($params, 'to'))return ['error' => 'recipient email not set'];
        if (!_cv($params, 'subject'))return ['error' => 'subject not set'];

        $message = [
            "from_name" => env('MAIL_FROM_NAME'),
            "from_email" => env('MAIL_FROM_ADDRESS'),
            "to" => [
                [
                    "email" => $params['to'],
                    "type" => "to",
                ],
            ],
            "global_merge_vars" => $params['vars'] ?? [],
            "subject" => $params['subject'],
        ];

        try {
            $mailchimp = new ApiClient();
            $mailchimp->setApiKey(env('MAILCHIMP_API_KEY'));

            $mailchimpParams = [
                "template_name" => _cv($params, 'template'),
                "template_content" => $params['content'] ?? [
                        [
                            "name" => "main",
                            "content" => ""
                        ],
                    ],
                "message" => $message,
            ];
//            p($mailchimpParams);
            $response = $mailchimp->messages->sendTemplate($mailchimpParams);
//            p($response);
            return $response;

            if (isset($response[0]->status)) {
                return ['status' => $response[0]->status, 'fullInfo' => $response[0]];
            } else {
                return ['error' => 'error'];
            }

        } catch (Error $e) {
            return ['error' => $e->getMessage()];
        }
    }

}
