<?php

namespace App\Http\Controllers\Admin\User;

use App;
use App\Models\User\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Mail\sendRegisterEmail;
use Illuminate\Validation\Rule;
use App\Models\Shop\WalletsModel;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Mail\sendEmailVerification;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Models\Admin\Roles\RoleModel;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use App\Models\CustomModules\Ltb\CommentsModel;
use App\Http\Controllers\Admin\Shop\services\Email;
use App\Http\Controllers\Admin\Shop\services\LtbRequests;


class UserController extends Controller
{

    private $adminUserFields = [
        'username',
        'fullname',
        'role',
        'email',
        'phone',
    ];

    public function __construct()
    {
        //        $this->middleware('auth:api', ['except' => ['login']]);
    }
    public function index()
    {
        //        if (Gate::denies('viewUser')) {
        //            session()->flash('error', tr('you dont have permission To Access!'));
        ////            return redirect()->route('CMS.Dashboard');
        //        }
        $users = User::where('status', '!=', 'admin')->orderBy('id', 'DESC')->get();

        $RoleModel = new RoleModel();
        $roles = $RoleModel->getList();
        return response(['users' => $users, 'roles' => $roles]);

    }
    public function getAdmins()
    {
        $keyword = null;
        if (request('search')) {
            $keyword = request('search');
        }

        $users = User::where('status', 'admin')->orderBy('id', 'DESC')->get();
        $RoleModel = new RoleModel();
        $roles = $RoleModel->getList();
        return response(['users' => $users, 'roles' => $roles]);
    }

    public function update(Request $request)
    {
        if (!$this->validateMethodAction($request->all())) {
            return response(['success' => false, 'message' => 'permission denied'], 201);
        }

        if ($request->route()->getActionMethod() !== 'update') {
            return response(['success' => false, 'message' => 'permission denied'], 201);
        }

        request()->header('check');

        $uniqueCondition = '!=';

        if ($request->status == 'deleted') {
            $request->merge(['username' => date('ymdis') . "_" . $request->username]);
            $request->merge(['email' => date('ymdis') . "_" . $request->email]);
            $request->merge(['phone' => date('ymdis') . "_" . $request->phone]);
        }

        ///

        $rules = [
            //            'id'=>'number',
            //            'email' => ['required', 'string', 'email', 'max:191', Rule::unique('users')->where(function ($query) use ($uniqueCondition) {
            //                return $query->where('status', $uniqueCondition, 'admin');
            //            }), $request->id],
            'email' => 'required|email|unique:users,email,' . $request->id,
            'fullname' => 'required|string',
            'username' => 'required|unique:users,username,' . $request->id,
            'phone' => 'required|numeric',
        ];

        if ($request->avatar) {
            $rules['avatar'] = 'image';
        }
        if ($request->password || !$request->id) {
            $rules['password'] = 'required|min:6';
        }

        $validator = \Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response(['success' => false, 'message' => $validator->errors()->first()], 201);
        }

        $upd = [];
        foreach ($this->adminUserFields as $k => $v) {
            if (!isset($request[$v])) {
                continue;
            }

            $upd[$v] = $request[$v];
        }

        $user = User::updateOrCreate(['id' => $request->id], $upd);
        $user->role = $request->role ? $request->role : 10;

        if ($request->password && $request->password != '') {
            $hashedPassword = Hash::make($request->password);
            //            $request->merge(['password' => Hash::make($request->password)]);
            $user->password = $hashedPassword;
        }

        $user->status = $request->status ?: 'member';

        $user->save();

        return response($user->toArray());
    }

    public function updateProfile(Request $request)
    {

        $valid = [
            'fullname' => 'required|string',
            'phone' => 'required|numeric',
            'password' => 'password|required',
        ];
        if ($request->email != Auth::user()->email) {
            $valid['email'] = 'required|email|unique:users';
        } else {
            $valid['email'] = 'required|email';
        }
        if ($request->username != Auth::user()->username) {
            $valid['username'] = 'required|email|unique:users';
        } else {
            $valid['username'] = 'required';
        }
        if ($request->new_password) {
            $valid['new_password'] = 'min:8';
            $valid['confirm_password'] = 'same:new_password';
        }
        if ($request->avatar) {
            $valid['avatar'] = 'image';
        }
        request()->validate($valid);
        $values = [
            'fullname' => $request->fullname,
            'username' => $request->username,
            'phone' => $request->phone,
            'email' => $request->email,
            'avatar' => $request->avatar,
        ];

        if ($request->new_password) {
            $values['password'] = $request->new_password;
        }
        $user = User::update(Auth::user()->id, $values);
        if ($user) {
            session()->flash('success', tr('user edited successfully'));
            return redirect()->back();
        } else {
            session()->flash('error', 'error');
            return redirect()->back();
        }
    }

    public function getAddresses()
    {
        $addresses = User::getAddresses();
        return $addresses;
    }

    // update user phone

    private function updateUserPhone($params = [])
    {
        $data = $params;

        $formTypeRules = [
            'formType' => 'required|string',
        ];

        $formTypeValidator = Validator::make($data, $formTypeRules);

        if ($formTypeValidator->fails()) { return ['error' => 'invalid input', 'msg'=>$formTypeValidator->messages() ]; }

            switch ($data['formType']) {
                case 'validateCurrentPhone':
                    return self::validateCurrentPhone($data);
                    break;
                case 'verifyCodeForCurrentPhone':
                    return self::verifyCodeForCurrentPhone($data);
                    break;

                case 'validateNewPhone':
                    return self::validateNewPhone($data);
                    break;
                case 'verifyCodeForNewPhone':
                    return self::verifyCodeForNewPhone($data);
                    break;
            }

        return ['error' => 'unknown form type error'];
    }

    private function validateCurrentPhone($data)
    {
        $response['phoneIsValid'] = false;

        $rules = [
            'currentPhone' => ['bail', 'required', 'string', 'regex:/^\d{6,20}$/'],
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return $response;
        }

        $user = auth()->user();

        if ($user->phone !== $data['currentPhone']) {
            return $response;
        }

        $code = (string) random_int(1000, 9999);

        $sessionData = [
            'phone' => $user->phone,
            'codeSent' => time(),
            'code' => $code,
        ];

//        session()->put('validatedCurrentPhoneData', $sessionData);
        sessionSet('validatedCurrentPhoneData', $sessionData);

        self::sendSmsCode($data['currentPhone'], $code);

        $response['phoneIsValid'] = true;

        return $response;
    }

    private function verifyCodeForCurrentPhone($data)
    {
        $expirationTime = 300;

        $response['codeVerified'] = false;
        $response['codeExpired'] = false;

        $rules = [
            'code' => ['bail', 'required', 'string', 'regex:/^\d{4}$/'],
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails() || !sessionGet('validatedCurrentPhoneData')) {
            return $response;
        }

        $sessionData = sessionGet('validatedCurrentPhoneData');

        $response['codeExpired'] = time() > ($sessionData['codeSent'] + $expirationTime);

        if ($response['codeExpired']) {
            return $response;
        }

        if ($data['code'] !== $sessionData['code']) {
            return $response;
        }

        $response['codeVerified'] = true;

        sessionSet('verifiedCurrentPhone', $sessionData['phone']);

        return $response;
    }

    private function validateNewPhone($data)
    {
        $response['phoneIsValid'] = false;

        $rules = [
            'newPhone' => ['bail', 'required', 'string', 'regex:/^\d{6,20}$/', 'unique:users,phone'],
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return $response;
        }

        $code = (string) random_int(1000, 9999);

        $sessionData = [
            'phone' => $data['newPhone'],
            'codeSent' => time(),
            'code' => $code,
        ];

        sessionSet('validatedNewPhoneData', $sessionData);

        self::sendSmsCode($data['newPhone'], $code);

        $response['phoneIsValid'] = true;

        return $response;
    }

    private function verifyCodeForNewPhone($data)
    {
        $expirationTime = 300;

        $response['codeVerified'] = false;
        $response['codeExpired'] = false;

        $rules = [
            'code' => ['bail', 'required', 'string', 'regex:/^\d{4}$/'],
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails() || !sessionGet('validatedNewPhoneData') || !sessionGet('verifiedCurrentPhone')) {
            return $response;
        }

        $sessionData = sessionGet('validatedNewPhoneData');

        $response['codeExpired'] = time() > ($sessionData['codeSent'] + $expirationTime);

        if ($response['codeExpired']) {
            return $response;
        }

        if ($data['code'] !== $sessionData['code']) {
            return $response;
        }

        sessionSet('verifiedCurrentPhone', '');
        sessionSet('validatedNewPhoneData', '');
        sessionSet('validatedCurrentPhoneData', '');

        $user = auth()->user();

        $user->phone = $sessionData['phone'];

        $user->save();

        $response['codeVerified'] = true;

        return $response;
    }

    // update profile info

    public function updateProfileData($params = [])
    {

        $user = Auth::user();
        if (_cv($params, 'password') && _cv($params, 'current_password')) {

            $validator = Validator::make($params, [
                'password' => 'required|string|min:8',
            ]);
            if ($validator->fails()) {
                return $validator->errors();
            }
            $hashCheck = Hash::check($params['current_password'], $user['password']);
            if ($hashCheck) {
                $user->password = Hash::make($params['password']);
                $user->update();
            } else {
                return response(['error' => 'ახლანდელი პაროლი არ ემთხვევა'], 201);
            }
        } elseif (_cv($params, 'profilePicture')) {
            $additionalInfoUpd = json_decode($user->additional_info, true);
            $additionalInfoUpd[$params['status']]['profilePicture'][0] = $params['profilePicture'];
            User::where('id', $user->id)->update([
                'additional_info' => $additionalInfoUpd,
            ]);
        } elseif (_cv($params, 'currentPhone') || _cv($params, 'code') || _cv($params, 'newPhone')) {
            return $this->updateUserPhone($params);
        }
        return $user;
    }

    // user registration

    public function register(Request $request)
    {
        $data = $request->all();

        $formTypeRules = [
            'formType' => 'required|string',
        ];

        $formTypeValidator = Validator::make($data, $formTypeRules);

        if (!$formTypeValidator->fails()) {
            switch ($data['formType']) {
                case 'checkPhone':
                    return $this->validateUserByPhone($data);
                    break;
                case 'validateForm':
                    return $this->validateRegistrationForm($data);
                    break;
                case 'verifyEmail':
                    return $this->verifyEmail($data);
                    break;
            }
        }

        return ['error' => 'invalid input'];
    }

    private function validateRegistrationForm($data)
    {
        $codeExpirationTime = 300;
        $response['sesid_my'] = appSessionId();


        $rules = [
            'fullname' => 'bail|required|string|max:100',
            'email' => 'bail|required|email|max:128|unique:users,email',
            //            'phone' => ['bail', 'required', 'string', 'regex:/^\d{6,20}$/', 'unique:users,phone'],
            'phone' => [
                'bail',
                'required',
                'string',
                'regex:/^\d{6,20}$/', Rule::unique('users')->where(function ($query) use ($data) {
                    return $query->whereNotIn('status', ['admin', 'deleted']);
                })
            ],
            'additional_info.' . $data['status'] . '.privateId' => 'bail|required|string|max:50|unique:users,p_id|unique:users,username',
            'status' => 'bail|required|string|max:100',
            'additional_info.' . $data['status'] . '.agreements.ruleAgrement' => 'bail|required|boolean',
            'password' => 'required|string|min:8',
            'code' => ['bail', 'required', 'string', 'regex:/^\d{4}$/'],
        ];

        $primaryValidator = Validator::make($data, $rules);

        $allowedLegalStatuses = ['person', 'company', 'master'];

        $response['registered'] = false;
        $response['phoneNumbersDoNotMatch'] = false;
        $response['codeIsExpired'] = false;
        $response['codeIsWrong'] = false;
        $response['duplicates'] = array_keys($primaryValidator->errors()->toArray());
        $response['errors'] = $primaryValidator->errors()->toArray();

        if ($primaryValidator->fails()){
            $response['errors'] = $primaryValidator->messages();
            return $response;
        }

        if (!isset($data['additional_info'][$data['status']]['agreements']['ruleAgrement']) || !in_array($data['status'], $allowedLegalStatuses)){
            $response['errors'] = 'Agreement not accepted';
            return $response;
        }


            $additionalInfo = $data['additional_info'];

            if ($data['status'] === 'master') {
                $extraRulesByStatus = [
                    'additional_info.master.region' => 'bail|required|array|max:255',
                    'additional_info.master.workOccupation' => 'bail|required|array|max:300',
                ];

                $extraFieldsByStatusValidator = Validator::make($data, $extraRulesByStatus);

                if ($extraFieldsByStatusValidator->fails()) {
                    $response['errors'] = $extraFieldsByStatusValidator->errors()->toArray();
                    return $response;
                }

            }

        $sessionData = sessionGet("smsotp-{$response['sesid_my']}");


            if (!_cv($sessionData, 'code')){
                $response['errors'] = 'No OTP Code';
                return $response;
            }


//            $sessionData = session::get('registrationSmsCodeData');

            /// check phone number matching
            if ($data['phone'] !== _cv($sessionData,['phone'])){
                $response['phoneNumbersDoNotMatch'] = true;
                $response['error'] = 'phone number does not match';
                return $response;
            }


            // code expired
            if (time() > ($sessionData['sent'] + $codeExpirationTime) ){
                $response['codeIsExpired'] = true;
                $response['error'] = 'otp code expired';
                return $response;
            }

            // Wrong OTP code
            if ( $data['code'] !== _cv($sessionData,['code']) ){
                $response['codeIsWrong'] = true;
                $response['error'] = 'Wrong OTP code';
                return $response;
            }


            $insertArray = [
                'fullname' => $data['fullname'],
                'username' => $data['username'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'p_id' => $data['additional_info'][$data['status']]['privateId'] ?? null,
                'password' => Hash::make($data['password']),
                'additional_info' => _psqlupd($additionalInfo),
                'status' => $data['status'],
                'role' => 10
            ];

            $insertId = \DB::table('users')->insertGetId($insertArray);

//            $this->updContragents($insertId);

            if ($data['status'] == 'master' || $data['status'] == 'person') {
                $this->updPoints($insertId);
            }
            if ($data['status'] == 'master') {
                $this->updMasters($insertId);
            }

            if ($data['additional_info'][$data['status']]['agreements']['subscribeAgreement']) {
                \DB::table('forms')->insert(['name' => 'subscribeForm', 'data' => _psqlupd(['email' => $data['email']])]);
            }

            $response['registered'] = true;

            session::forget('registrationSmsCodeData');

        return $response;
    }

    private function validateUserByPhone($data)
    {
        $response['phoneIsValid'] = false;
        $response['codeSent'] = false;
        $response['sesid_my'] = appSessionId();

        $phoneRules = [
            //            'phone' => ['bail', 'required', 'string', 'regex:/^\d{6,20}$/', 'unique:users,phone'],
            'phone' => [
                'bail',
                'required',
                'string',
                'regex:/^\d{6,20}$/', Rule::unique('users')->where(function ($query) use ($data) {
                    return $query->whereNotIn('status', ['admin', 'deleted']);
                })
            ],
        ];

        $phoneValidator = Validator::make($data, $phoneRules);


        if ($phoneValidator->fails()) {
            $response['errors'] = $phoneValidator->messages();
            return $response;
        }

            $response['phoneIsValid'] = true;

            $code = (string) random_int(1000, 9999);

            $smsOfficeResponse = $this->sendSmsCode($data['phone'], $code);
//p($smsOfficeResponse);
            if(isset($smsOfficeResponse->ErrorCode) && $smsOfficeResponse->ErrorCode > 0){
                $response['errorLog'] = "ErrorCode: {$smsOfficeResponse->ErrorCode}; MSG: {$smsOfficeResponse->Message}";
            }

            if (!empty($smsOfficeResponse->Success)) {
                $response['codeSent'] = true;

                $sessionData = [
                    'code' => $code,
                    'sent' => time(),
                    'phone' => $data['phone'],
                    'ses' => $response['sesid_my'],
                ];

                sessionSet("smsotp-{$response['sesid_my']}", $sessionData);

            }


        return $response;
    }

    public function verifyEmail($data)
    {
        $data['code'] = _cv($data,['code']);
        if(_cv($data, 'p_id')){

            $emailCode = Str::random(12);
            $verification['code'] = $emailCode;
            $verification['sent'] = time();

            $user = User::where('p_id', $data['p_id']);
            $userInfo = $user->first();

            if(isset($userInfo->verification) && $tmp = json_decode($userInfo->verification, 1) ){
                if(isset($tmp['code'])) $verification['code'] = $tmp['code'];
            }else{
                $user->update([
                    'email_verified_at' => null,
                    'verification' => _psqlupd($verification)
                ]);
                $userInfo = $user->first();
            }

            if(!$userInfo) return response(['status' => 'error', 'message' => 'user not found']);


            $redirectUrl = env('APP_URL')."ge/authorization?page=signin&id={$userInfo->id}&code={$verification['code']}";
            
            Email::sendEmail(['to' => $userInfo->email, 'template' => 'email-verification', 'subject' => 'Email Verification', 'vars' => [
                ['name' => 'REDIRECT', 'content' => $redirectUrl]
            ]]);

            return response(['status'=>'success', 'message' => 'Code Send', 'code'=>$verification['code']]);
        }



        if(!_cv($data, 'id', 'nn') || !_cv($data, 'code')) return response(['status' => 'error', 'message' => 'Not exist required data to verify account']);
        $user = User::where('id', $data['id'])->first();

        if (!$user) {
            return response(['status' => 'error', 'message' => 'Code Not Valid']);
        } elseif($user->email_verified_at) {
            return response(['status' => 'success', 'message' => 'User already verified']);
        } else {
            $verifiation = json_decode($user['verification']);
            if(isset($verifiation->code) && $verifiation->code == $data['code']){
                if (($verifiation->sent + 6000000) > time()) {

                    User::where('id', $data['id'])->update([
                        'email_verified_at' => date('Y-m-d H:i:s'),
                        'verification' => null
                    ]);

                    Email::sendEmail(['to' => $user->email, 'template' => 'registration', 'subject' => 'Registred Successfuly', 'vars' => [
                        ['name' => 'fname', 'content' => $user->fullname]
                    ]]);

                    $this->updContragents($user->id);
                    return response(['status' => 'success', 'message' => 'Verifed']);
                } else {
                    return response(['status' => 'error', 'message' => 'Verification Expiration Date']);
                }
            } else {
                return response(['status' => 'error', 'message' => 'Code Not Valid']);
            }
        }

    }

    // reset password

    public function resetForgottenPassword(Request $request)
    {
        $data = $request->all();

        $formTypeRules = [
            'formType' => 'required|string',
        ];

        $formTypeValidator = Validator::make($data, $formTypeRules);

        if (!$formTypeValidator->fails()) {
            switch ($data['formType']) {
                case 'checkPhone':
                    return $this->userExists($data);
                    break;
                case 'validateSms':
                    return $this->validateSms($data);
                    break;
                case 'resetPassword':
                    return $this->changePassword($data);
                    break;
            }
        }

        return ['error' => 'invalid input'];
    }

    private function userExists($data)
    {
        $response['phoneIsValid'] = false;
        $response['codeSent'] = false;

        $phoneRules = [
            'phone' => 'required|string|max:9',
        ];

        $phoneValidator = Validator::make($data, $phoneRules);

        if (!$phoneValidator->fails()) {
            $count = \DB::table('users')->where('phone', $data['phone'])->where('status', '!=', 'admin')->count();

            if ($count) {
                $response['phoneIsValid'] = true;

                $code = (string) random_int(1000, 9999);

                $smsOfficeResponse = $this->sendSmsCode($data['phone'], $code);

                if (!empty($smsOfficeResponse->Success)) {
                    $response['codeSent'] = true;

                    $sessionData = [
                        'code' => $code,
                        'sent' => time(),
                        'phone' => $data['phone'],
                    ];

//                    session()->put('smsCodeData', $sessionData);
                    sessionSet('smsCodeData', $sessionData);
                }
            }
        }

        return $response;
    }

    private function validateSms($data)
    {
        $codeExpirationTime = 120; // in seconds

        $response['codeIsValid'] = false;
        $response['expired'] = false;
        $response['codeResent'] = false;

//        $sessionData = session()->has('smsCodeData') ? session()->get('smsCodeData') : null;

        $sessionData = sessionGet('smsCodeData');

        if ($sessionData !== false) {
            $response['expired'] = time() > ($sessionData['sent'] + $codeExpirationTime);

            if (!$response['expired']) {
                $codeRules = [
                    'code' => [
                        'required',
                        'string',
                        'regex:/^\d{4}$/',
                    ],
                ];

                $codeValidator = Validator::make($data, $codeRules);

                if (!$codeValidator->fails()) {
                    $response['codeIsValid'] = $sessionData['code'] === $data['code'];

                    if ($response['codeIsValid']) {
                        sessionSet('smsCodeData', '');
                        sessionSet('verifiedUserPhone', $sessionData['phone']);
//                        session()->forget('smsCodeData');
//                        session()->put('verifiedUserPhone', $sessionData['phone']);
                    }
                }
            } else {
                $code = (string) random_int(1000, 9999);

                $smsOfficeResponse = $this->sendSmsCode($sessionData['phone'], $code);

                if (!empty($smsOfficeResponse->Success)) {
                    $response['codeResent'] = true;

                    $updatedSessionData = [
                        'code' => $code,
                        'sent' => time(),
                        'phone' => $sessionData['phone'],
                    ];

//                    session()->put('smsCodeData', $updatedSessionData);
                    sessionSet('smsCodeData', $updatedSessionData);

                }
            }
        }

        return $response;
    }

    private function changePassword($data)
    {
        $response['passwordChanged'] = false;

        $passwordRules = [
            'password' => 'required|string|min:8',
        ];

        $passwordValidator = Validator::make($data, $passwordRules);

        if (!$passwordValidator->fails()) {

//            $phone = session()->get('verifiedUserPhone');
            $phone = sessionGet('verifiedUserPhone');

            if ($phone) {
                $newPassword = Hash::make($data['password']);

                \DB::table('users')->where('phone', $phone)->update(['password' => $newPassword]);

//                session()->forget('verifiedUserPhone');
                sessionSet('verifiedUserPhone', '');

                $response['passwordChanged'] = true;
            }
        }

        return $response;
    }

    private function sendSmsCode($phone, $code)
    {
        $params = [
            'urgent' => true,
            'key' => env('SMSOFFICE_API_KEY'),
            'destination' => $phone,
            'sender' => env('SMSOFFICE_SENDER'),
            'content' => 'SMS code: ' . $code,
        ];

        $query = http_build_query($params);
        $url = 'https://smsoffice.ge/api/v2/send?' . $query;

        return json_decode(file_get_contents($url));
    }

    /* login using social network */

    public function socLogin(Request $request)
    {
        $data = $request->all();

        $formTypeRules = [
            'formType' => 'required|string',
        ];

        $formTypeValidator = Validator::make($data, $formTypeRules);

        if (!$formTypeValidator->fails()) {
            switch ($data['formType']) {
                case 'checkEmail':
                    return $this->validateUserByEmail($data);
                    break;
                case 'validateCode':
                    return $this->validateSmsCode($data);
                    break;
            }
        }

        return ['error' => 'invalid input'];
    }

    private function validateUserByEmail($data)
    {
        $response['userExists'] = false;
        $response['codeSent'] = false;

        $emailRules = ['email' => 'bail|required|string|email'];

        $validator = Validator::make($data, $emailRules);

        if (!$validator->fails()) {
            $user = \DB::table('users')->select(['phone', 'email'])->where('email', $data['email'])->where('status', '!=', 'admin')->where('status', '!=', 'deleted')->first();

            if ($user) {
                $response['userExists'] = true;

                $code = (string) random_int(1000, 9999);

                $smsOfficeResponse = $this->sendSmsCode(_cv($user, 'phone'), $code);

                if (!empty($smsOfficeResponse->Success)) {
                    $response['codeSent'] = true;

                    $sessionData = [
                        'code' => $code,
                        'sent' => time(),
                        'email' => $user->email,
                    ];

//                    session()->put('socLoginSmsCodeData', $sessionData);
                    sessionSet('socLoginSmsCodeData', $sessionData);

                }
            }
        }

        return $response;
    }

    private function validateSmsCode($data)
    {
        $codeExpirationTime = 120;

        $response['authorized'] = false;
        $response['codeIsExpired'] = false;
        $response['codeIsWrong'] = false;

        $codeRules = [
            'code' => ['bail', 'required', 'string', 'regex:/^\d{4}$/'],
        ];

        $validator = Validator::make($data, $codeRules);

        if (!$validator->fails() && sessionGet('socLoginSmsCodeData')) {
            $sessionData = sessionGet('socLoginSmsCodeData');

            $response['codeIsExpired'] = time() > ($sessionData['sent'] + $codeExpirationTime);

            if (!$response['codeIsExpired']) {
                $response['codeIsWrong'] = $sessionData['code'] !== $data['code'];

                if (!$response['codeIsWrong']) {
                    $user = User::where('email', '=', $sessionData['email'])->where('status', '!=', 'admin')->where('status', '!=', 'deleted')->first();

                    if ($user) {
                        $token = JWTAuth::fromUser($user);

                        if ($token) {
                            sessionSet('socLoginSmsCodeData', '');

                            Auth::login($user);

                            $user->api_token = $token;

                            $user->save();

                            $response['access_token'] = $token;
                            $response['token_type'] = 'bearer';
                            $response['expires_in'] = auth('api')->factory()->getTTL() * 60;

                            $response['authorized'] = true;
                        }
                    }
                }
            }
        }

        return $response;
    }

    // standard login

    public function login(Request $request)
    {
        $data = $request->only(['username', 'password']);

        $rules = [
            'username' => 'bail|required|string',
            'password' => 'bail|required|string',
        ];

        $validator = Validator::make($data, $rules);

        if (!$validator->fails()) {
            $user = \DB::table('users')->where('username', '=', $data['username'])->where('status', '!=', 'admin')->first();

            if ($user) {
                if($user->email_verified_at === null) return response(['status' => 'verify_error'], 400);

                $token = JWTAuth::attempt($data);

                if ($token) {
                    $user = auth()->user();

                    $user->api_token = $token;
                    $user->last_activate = time();

                    $user->save();

                    DB::table('auth_logs')->insert([
                        'user_id' => $user->id,
                        'ip' => $_SERVER['REMOTE_ADDR'],
                        'created_at' => date('Y-m-d h:i:s'),
                    ]);

                    $response['access_token'] = $token;
                    $response['token_type'] = 'bearer';
                    $response['expires_in'] = auth('api')->factory()->getTTL() * 60;

                    return $response;
                }
            }
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

    // logout

    public function logout()
    {
        Auth::guard('api')->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    // user info

    public function me()
    {
        if(!Auth::user())return response()->json([]);
        $public_rating = CommentsModel::where('master_id', Auth::user()->id)->select(DB::raw('ROUND(SUM(rating) / COUNT(rating), 1) as public_rating, COUNT(rating) as public_rating_count'))->first();

        $user = Auth::user();
        $user->public_rating = $public_rating;
        $user = _psqlRow(_toArray($user));

        return response()->json($user);
    }

    // refresh token

    public function refresh()
    {
        return response()->json([
            'access_token' => auth()->refresh(),
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ]);
    }

    public function validateMethodAction($request = [])
    {
        /// if admin user is creating from non admin user return false;
        if (!_cv($request, 'status') == 'admin' && Auth::user()->status !== 'admin') {
            return false;
        }

        /// find authorized user permission
        $permission = new RoleModel();
        $res = $permission->getPermissionByRole(['role_id' => Auth::user()->role, 'permission_name' => 'admins']);
        $res = _toArray($res);

        /// check if user can change admin users; if not return false;
        if (!_cv($res, 'update')) {
            return false;
        }

        return true;
    }

    public function updPoints($userId)
    {
        $user = User::where('id', $userId)->first();

        $request = new LtbRequests();
        $points = $request->getPointsFromSevice($user->p_id);

        $wallet = WalletsModel::where('user_id', $userId)->where('type', 'points')->first();

        if (!$wallet) {
            WalletsModel::insert([
                'user_id' => $userId,
                'type' => 'points',
                'amount' => $points[0]['Points'] ?? 0,
                'status' => 'published',
            ]);
        } else {
            WalletsModel::where('user_id', $userId)->update([
                'amount' => $points[0]['Points'] ?? 0,
            ]);
        }
    }


    /// update user from 1C service
    public function updContragents($userId)
    {
        $user = User::where('id', $userId)->first();
//p($user);
        if(!isset($user->p_id)) return false;
//        $user->p_id = '35001123560'; /// for testing

        $request = new LtbRequests();
        $contragents = $request->getContragentsFromSevice($user->p_id);

        $data['masterVisibility'] = _cv($contragents, 'ხილვადობა_ხელოსნების_რეიტინგის_გვერდზე');
        $data['loyaltyCard'] = _cv($contragents, 'ფლობს_ლოიალურობის_ბარათს');
        $data['loyaltyDiscountPercent'] = _cv($contragents, 'ლოიალობის_ფასდაკლების_პროცენტი', 'nn')?$contragents['ლოიალობის_ფასდაკლების_პროცენტი']:0;
        $data['contragentDiscountPercent'] = _cv($contragents, 'კონტრაგენტის_ფასდაკლების_პროცენტი', 'nn')?$contragents['კონტრაგენტის_ფასდაკლების_პროცენტი']:0;

        $data['Contragent'] = _cv($contragents, 'Contragent');
        $data['ContragentID'] = _cv($contragents, 'ContragentID');
        $data['IsClient'] = _cv($contragents, 'IsClient');
        $data['IDNumber'] = _cv($contragents, 'IDNumber');

        $data['category'] = _cv($contragents, 'კატეგორია');
        $data['legal_form'] = _cv($contragents, 'სამართლებრივი_ფორმა');
        $data['no_resident'] = _cv($contragents, 'უცხო_ქვეყნის_მოქალაქე');
        $data['email'] = _cv($contragents, 'ელ_ფოსტა');
        $data['mobile_phone'] = _cv($contragents, 'მობილურის_ნომერი');

        $additional_info = _psqlCell($user->additional_info);
        $additional_info['contragents'] = $data ?? null;
        $user->additional_info = $additional_info;
//        p($additional_info);
        User::where('id', $userId)->update([
            'additional_info' => $additional_info,
        ]);

        $contragents = $request->registerContragent(_psql(_toArray($user)));


        return $contragents;
    }
    public function updMasters($userId)
    {
        $user = User::where('id', $userId)->where('status', 'master')->first();

        if (!$user)
            return response(['status' => 'error', 'message' => 'User Not Found']);

        $request = new LtbRequests();
        $data = $request->getMasterFromSevice($user->p_id);

        $additional_info = _psqlCell($user->additional_info);
        $additional_info['master']['premiumProductShare'] = $data['Percent_Premium'] ?? null;
        $additional_info['master']['standardProductShare'] = $data['Percent_Standart'] ?? null;
        $additional_info['master']['economyProductShare'] = $data['Percent_Econom'] ?? null;

        User::where('id', $userId)->update([
            'additional_info' => $additional_info,
            'rating' => $data['RatingLTB'] ?? 0,
            'sort' => $data['Rating'] ?? 0,
        ]);

        return $data;
    }
}
