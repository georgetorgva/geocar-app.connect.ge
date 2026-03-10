<?php

namespace App\Http\Controllers\Api;

use App\Rules\ReCaptcha;
use App\Models\User\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Admin\OptionsModel;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use App\Http\Transformers\UserTransformer;

class AuthController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
       $this->middleware('auth:api', ['except' => ['login']]);
    }


    public function handle(){
//        exit();
//        print 111111;
        return true;
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login()
    {
        $credentials = request(['username', 'password']);

        $setting = new OptionsModel;
        $cache = Cache::store('file')->get('AdminPanelConfig');
        
        $captchaStatus = _cv($cache, 'captchaStatus') ?? _cv($setting->getSetting('captcha_status'), 'value');
        if(!is_array($captchaStatus)) $captchaStatus = [];
        if(in_array('adminpanel_captcha', $captchaStatus)){
            $validator = Validator::make(request()->all(), [
                'grecaptcha' => ['required', new ReCaptcha]
                ]
            );
            if ($validator->fails()) {
                return response()->json(['error' => 'Captcha Is Required!'], 401);
            }
        }

        $token = JWTAuth::attempt($credentials);
        if (!$token) {
            return response()->json(['error' => 'Incorrect Username Or Password!'], 401);
        }

        $user = Auth::user();
        $user->api_token = $token;
        $user->save();

        return $this->respondWithToken($token);
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {

        $user = auth()->user();
        return response()->json(auth()->user());
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        Auth::guard('api')->logout();
        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(auth()->refresh());
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60
        ]);
    }
}
