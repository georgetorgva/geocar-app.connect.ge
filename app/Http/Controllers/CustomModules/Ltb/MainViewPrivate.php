<?php

namespace App\Http\Controllers\CustomModules\Ltb;

use App\Rules\ReCaptcha;
use App\Models\User\User;
use Illuminate\Support\Str;
use App\Models\Shop\WalletsModel;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\CustomModules\Ltb\CommentsModel;
use App\Models\CustomModules\Ltb\ProjectsModel;
use App\Http\Controllers\Admin\User\UserController;

/**
 * main controller for all the content types
 */
class MainViewPrivate extends Controller
{

    //
    protected $mainModel;
    protected $error = false;

    public function updateMasters($params = [])
    {
        $UserController = new UserController();
//        if (!Auth::user() || _cv($params, 'id') !== Auth::user()->id) return response(['error' => 'user not found'], 201);


        if (_cv($params, 'password') || _cv($params, 'profilePicture') || _cv($params, 'formType')) {
            $data = $UserController->updateProfileData($params);
            return $data;
        }



        if (_cv($params, 'public_rating')) {
            unset($params['public_rating']);
        }

        $user = new User();
        $ret = $user->upd($params);

        if (is_numeric($ret))
            $ret = $user->getOne(['id' => $ret]);

        return response($ret, _cv($ret, 'id', 'nn') ? 200 : 201);
    }


    public function updateProjects($params = [])
    {
//        p($_COOKIE);
        // Recaptcha
        $requestData['g-recaptcha-response'] = $_COOKIE['grecaptcha'] ?? _cv($params,['g-recaptcha-response']);
        $validator = Validator::make($requestData, [
            'g-recaptcha-response' => ['required', new ReCaptcha]
            ]
        );
        if($validator->fails()){
            return response(['errors'=>$validator->errors()], 201);
        }

        $params['status'] = $params['status'] ?? 'published';
        $params['user_id'] = Auth::user()->id;
        $params['slug'] = Str::slug(_cv($params, 'xx.title'));
        $params['date'] = date('Y-m-d h:i:s');

        $project = new ProjectsModel();
        if (_cv($params, 'id')) {
            $check = $project->checkUser($params['id']);
            if ($check) return $check;
        }

        $ret = $project->updItem($params);

        if (is_numeric($ret)){
            $ret = $project->getOne(['id' => $ret]);
        }

        return response($ret, _cv($ret, 'id', 'nn') ? 200 : 201);
    }


    public function updateComments($params = [])
    {
        if(mb_strlen($params['commentary']) > 1000) return response(['status' => 'length_error']);
        $params['status'] = $params['status'] ?? 'published';
        $params['author_id'] = Auth::user()->id;
        $params['date'] = date('Y-m-d h:i:s');

        $comments = new CommentsModel();
        if (_cv($params, 'id')) {
            $check = $comments->checkUser($params['id']);
            if ($check) return $check;
        }

        $ret = $comments->updItem($params);

        if (is_numeric($ret))
            $ret = $comments->getOne(['id' => $ret]);

        return response($ret, _cv($ret, 'id', 'nn') ? 200 : 201);
    }

    public function getWallets($params = [])
    {
        $params['whereRaw'] = ['user_id='.Auth::user()->id];

        $wallets = new WalletsModel();
        $list = $wallets->getList($params);

        return response($list);
    }
}
