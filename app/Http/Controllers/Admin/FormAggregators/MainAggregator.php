<?php

namespace App\Http\Controllers\Admin\FormAggregators;

//use http\Exception;
use App;
use App\Mail\sendFormBuilderForm;
use App\Models\User\User;
use Illuminate\Http\Request;
use App\Models\Admin\OptionsModel;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Validator;
use App\Models\Admin\OnlineFormsModel;

use App\Http\Controllers\Api\MailController;

class MainAggregator extends App\Http\Controllers\Api\ApiController
{

    static $confs = [];

    static function index($request = [])
    {
        $formType = _cv($request, ['formType']);
        self::$confs = $confs = config("adminpanel.onlineForms.{$formType}");
        if (!_cv($confs, 'function') || !is_callable(__NAMESPACE__ . '\\MainAggregator', $confs['function']))
            return false;

        return call_user_func([__NAMESPACE__ . '\\MainAggregator', $confs['function']], $request);

    }

    /////////// agregator methods

    // /// manipulate contactForm received data
    // static function contactForm($request = []){

    //     $toMails = [];
    //     if(_cv(self::$confs, 'toMails'))$toMails = explode(',', self::$confs['toMails']);


    //     if(!is_array($toMails) || !count($toMails)){
    //         $aggregator = new MainAggregator();
    //         $toMails = $aggregator->findToEmails();

    //     }

    //     $formName = ucfirst($request['formType']);

    //     $body = [];
    //     foreach ($request as $k=>$v){
    //         $body[ucfirst($k)] = strip_tags($v);
    //     }

    //     foreach ($toMails as $v){
    //         $v = trim($v);

    //         Mail::send('emailMainTemplate', ['data'=>['body'=>$body, 'formName'=>$formName]], function ($message)  use($v, $formName) {
    //             $message->to('likagavasheli05@gmail.com')->subject($formName.' '.tr('Web form'));
    //             $message->from(env('MAIL_FROM_ADDRESS', false), env('MAIL_FROM_NAME', false));
    //         });
    //     }

    // }


    /// manipulate contactForm received data
//    static function contactForm($request = [])
//    {
//
//        $options = new OptionsModel();
//        $data['contact_email_to'] = $options->getByKey('contact_email_to');
//        $data['contact_email_from'] = $options->getByKey('contact_email_from');
//
//        $data['name'] = $request['name'];
//        $data['email'] = $request['email'];
//        $data['message'] = $request['How-can-we-help'];
//
//        try {
//            $emailRecipients = array_map('trim', explode(',', $data['contact_email_to']));
//
//            Mail::send('contact-form', ['data' => $data], function ($message) use ($data, $emailRecipients) {
//                foreach ($emailRecipients as $email) {
//                    $message->to($email);
//                }
//                $message->subject(tr('Connect contact message'));
//                $message->from($data['contact_email_from'], $data['name']);
//            });
//
//            return response()->json(['success' => true, 'message' => tr('Email sent successfully')]);
//        } catch (\Exception $e) {
//            return response()->json(['error' => $e->getMessage()], 500);
//        }
//
//    }

    static function sendVacancy($request = [])
    {
        // $to_email = $options->getSetting('vacancyEmailTo');
        // $from_email = $options->getSetting('vacancyEmailFrom');
        $options = new OptionsModel();
        $data['to_email'] = $options->getByKey('vacancyEmail');
        $data['from_email'] = $options->getByKey('emailFrom');


        $data['to_email'] = explode(",", $data['to_email']);

        $data['name'] = $request['name'];
        $data['email'] = $request['email'];
        $data['phone'] = $request['phone'];
        $data['address'] = $request['address'];
        $data['file'] = $request['file'];
        $data['ageOption'] = $request['ageOption'];
        $data['agreement'] = $request['agreement'];
        $data['linkedin'] = $request['linkedin'];
        $data['minSalary'] = $request['minSalary'];
        $data['minTime'] = $request['minTime'];
        $data['workCountry'] = $request['workCountry'];
        $data['workExperience'] = $request['workExperience'];


        Mail::send('vacancyEmailTemp', ['data' => $data], function ($message) use ($data) {
            $message->to($data['to_email'])->subject
            (tr('LTB - Vacancy Message'));
            ;
            $message->from($data['from_email']);
        });


        if (Mail::fake()) {
            return response()->json(['StatusCode' => 0]);
        } else {
            return response()->json(['success' => tr('vacancy sent successfully!')]);
        }


    }

    /// manipulate subscribe form received data
    static function subscribeForm($request = [])
    {

        $user = User::where('email', $request['email'])->first();

        if ($user && $user->status) {
            $additional_info = json_decode($user->additional_info, true);
            if(isset($additional_info[$user->status]['agreements']) && !is_array($additional_info[$user->status]['agreements'])) $additional_info[$user->status]['agreements'] = [];


            if (_cv($additional_info, [$user->status, 'agreements', 'subscribeAgreement']) == 1) {
                $additional_info[$user->status]['agreements']['subscribeAgreement'] = 0;
            } else {
                $additional_info[$user->status]['agreements']['subscribeAgreement'] = 1;
            }
            User::where('id', $user->id)->update([
                'additional_info' => $additional_info
            ]);
        }

        $form = new OnlineFormsModel();
        $checkExist = $form->checkExist(['formType' => 'subscribeForm', 'value' => [$request['email']], 'findType' => 'AND']);

        if ($request['action'] == 'subscribe') {
            if ($checkExist == false) {
                $form->upd(['formType' => 'subscribeForm', 'email' => $request['email']]);
                return response(['status' => 'subscribed']);
            }
            return response(['status' => 'already_subscribed']);
        } else {
            if ($checkExist !== false) {
                $form->del('subscribeForm', $request['email']);
                return response(['status' => 'unsubscribed']);
            } else {
                return response(['status' => 'not_subscribed']);
            }
        }
    }

    /// manipulate checkListForm received data
    static function checkListForm($request = [])
    {
        //p($request);

        $toMails = [];
        if (_cv(self::$confs, 'toMails'))
            $toMails = explode(',', self::$confs['toMails']);

        //p(self::$confs);
        $emailsConfField = '';
        if (_cv($request, ['business']) == 'business' && _cv(self::$confs, 'toEmailConfs.business')) {
            $emailsConfField = self::$confs['toEmailConfs']['business'];
        } else {
            $emailsConfField = _cv(self::$confs, 'toEmailConfs.personal');
        }

        $aggregator = new MainAggregator();
        if (!is_array($toMails) || !count($toMails)) {
            $toMails = $aggregator->findToEmails($emailsConfField);
        }

        $formName = ucfirst($request['formType']);

        $body = [];
        foreach ($request as $k => $v) {
            if (is_array($v))
                continue;
            $body[ucfirst($k)] = strip_tags($v);
        }

        $items = '';
        if (_cv($request, ['items'])) {
            foreach ($request['items'] as $v) {
                $items .= _cv($v, 'title') . ', ';
            }
        }
        $body['items'] = $items;

        foreach ($toMails as $v) {
            $v = trim($v);

            Mail::send('emailMainTemplate', ['data' => ['body' => $body, 'formName' => $formName]], function ($message) use ($v, $formName) {
                $message->to($v)->subject($formName . ' ' . tr('Web form'));
                $message->from(env('MAIL_FROM_ADDRESS', false), env('MAIL_FROM_NAME', false));
            });
        }

    }

    //// find to emails
    public function findToEmails($confname = 'website_default_reply_emails')
    {
        $defaultConfname = 'website_default_reply_emails';
        $options = new App\Models\Admin\OptionsModel();
        $res = $options->getKeyValListBy(['content_group' => 'site_configurations']);

        $toEmails = [];
        if (_cv($res, [$confname])) {
            $toEmails = explode(',', $res[$confname]);
        } else if (_cv($res, [$defaultConfname])) {
            $toEmails = explode(',', $res[$defaultConfname]);

        }

        return $toEmails;
    }

    private function sendEmails($to=[], $tpl = '', $body = ''){
//p($to);
        if(!is_array($to))return false;

        foreach ($to as $k=>$v) {
            if (!filter_var($v, FILTER_VALIDATE_EMAIL)) unset($to[$k]);
            $to[$k] = trim($v);
        }
        if(!isset($to[0]))return false;

        try {
            return Mail::to($to)->send($tpl);
        } catch (Excepion $e) {
            return response($e->getMessage());
        }

    }

    public function formBuilderSendMail($data=[], $formConfs=[], $tpl = 'dynamic-email'){

        $options = new OptionsModel();
        $configEmail = $options->getByKey('contact_email_to');
        $emails = $formConfs['form_settings']['toEmails'] ? explode(',', str_replace(' ', '', $formConfs['form_settings']['toEmails'])) : explode(',', str_replace(' ', '', $configEmail));

        $sendFields = [];
        $exepts = ['dynamicForm','formType'];
        foreach ($data as $k=>$v){
            if(array_search($k, $exepts)!==false)continue;
            $title = ucwords( str_replace(['_','-',], [' ', ' '], $k) );

            if(empty($v)){
                $v = '-';
            }

            $sendFields[] = ['title'=>$title, 'text'=>$v];

            foreach ($sendFields as $key => $item) {
                if ($item['title'] === "G Recaptcha Response") {
                    unset($sendFields[$key]);
                }
            }
        }

        $sendFields = array_values($sendFields);

        try
        {
            $apiResponse = MailController::sendContact($emails, $sendFields);

//            if ($apiResponse['success'])
//            {
//                MailController::sendThankYouEmail($apiResponse['thankYouData']);
//            }

            return $apiResponse['success'];
        }

        catch (\Exception $exception)
        {
            return false;
        }
    }
}
