<?php

namespace App\Http\Controllers\CustomModules\Ltb;


use App\Http\Controllers\Admin\Shop\services\LtbRequests;
use App\Models\User\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Shop\WalletsModel;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use App\Models\Admin\OnlineFormsModel;
use Illuminate\Database\Eloquent\Model;
use App\Models\CustomModules\Ltb\CommentsModel;
use App\Models\CustomModules\Ltb\ProjectsModel;
use App\Http\Controllers\Admin\User\UserController;
use Illuminate\Support\Facades\DB;


/**
 * main controller for all the content types
 */
class MainAdmin extends Controller
{

    //
    protected $mainModel;
    protected $error = false;

    /** ltb module index */
    public function getIndx($params = [])
    {
        $ret['conf'] = config('adminltb');
        $ret['userGroupsList'] = config('adminltb.masters.regularFields.member_group.values');

        return response($ret);
    }

    /** get users */
    public function getMasters($params = [])
    {
        $user = new User();
        $list = $user->getList($params);
        //        p($list);

        return response($list);
    }
    /** get user */
    public function getMaster($params = [])
    {
        $user = new User();
        //        $params['status'] = 'public';
        $list = $user->getOne($params);
        //        p($list);

        return response($list);
    }

    public function updateMasters($params = [])
    {

        if (!_cv($params, 'status') || !_cv($params, 'status') == 'admin')
            $params['status'] = 'person';

        if (_cv($params, 'address')) {
            $default = false;
            foreach ($params['address'] as $index => $addr) {
                if ($addr['default']['default'] == 1 && $default == false) {
                    $default = true;
                    $params['address'][$index]['default']['default'] = 1;
                } else {
                    $params['address'][$index]['default']['default'] = 0;
                }
            }
        }
        if (_cv($params, 'password')) {
            $params['password'] = Hash::make($params['password']);
        }

        if (_cv($params, ['additional_info', $params['status'], 'agreements', 'subscribeAgreement']) == 1) {
            $form = new OnlineFormsModel();
            $checkExist = $form->checkExist(['formType' => 'subscribeForm', 'value' => [$params['email']], 'findType' => 'AND']);
            if ($checkExist == false) {
                $form->upd(['formType' => 'subscribeForm', 'email' => $params['email']]);
            }
        } else {
            $form = new OnlineFormsModel();
            $form->del('subscribeForm', $params['email']);
        }

        if (_cv($params, 'additional_info.' . $params['status'] . '.privateId')) {
            $params['p_id'] = $params['additional_info'][$params['status']]['privateId'];
        }
        if (_cv($params, 'additional_info.' . $params['status'] . '.serialNumber')) {
            $params['p_id'] = $params['additional_info'][$params['status']]['serialNumber'];
        }

        $user = new User();

//        $ltbRequests = new LtbRequests();
//        $registerContragentStatus = $ltbRequests->registerContragent($params);

        $ret = $user->upd($params);

//        p($registerContragentStatus);

        $userController = new UserController();
//

        if (_cv($params, 'status') == 'master') {
            $userController->updMasters($params['id']);
        }

        if (is_numeric($ret)) {
            $userController->updContragents($ret);
            $ret = $user->getOne(['id' => $ret]);
        }

//        $ret['registerContragentStatus'] = $registerContragentStatus;
        return response($ret, _cv($ret, 'id', 'nn') ? 200 : 201);
    }
    public function deleteMasters($params = [])
    {
        $user = User::where('id', $params['id'])->first();
        $params['username'] = time() . '_' . $user->username;
        $params['p_id'] = time() . '_' . $user->p_id;
        $params['email'] = time() . '_' . $user->email;
        $params['phone'] = time() . '_' . $user->phone;

        ProjectsModel::where('user_id', $params['id'])->update([
            'status' => 'hidden'
        ]);

        CommentsModel::where('master_id', $params['id'])->orWhere('author_id', $params['id'])->update([
            'status' => 'hidden'
        ]);


        $user = new User();
        $ret = $user->upd($params);

        $user = User::where('id', $ret)->first();
        return response($user, _cv($user, 'id', 'nn') ? 200 : 201);
    }


    /** get users */
    public function getProjects($params = [])
    {
        $model = new ProjectsModel();
        $params['status'] = ['published', 'hidden'];
        $params['translate'] = requestLan();

        $list = $model->getList($params);
        //        p($list);

        return response($list);
    }
    /** get user */
    public function getProject($params = [])
    {
        $model = new ProjectsModel();
        //        $params['status'] = 'public';
        $list = $model->getOne($params);
        return response($list);
    }

    public function updateProjects($params = [])
    {
        if (_cv($params, 'slug') && empty($params['slug'])) {
            $params['slug'] = Str::slug($params['xx']['title']);
        }
        $model = new ProjectsModel();
        $ret = $model->updItem($params);

        if (is_numeric($ret)){
            $ret = $model->getOne(['id' => $ret]);
        }

        return response($ret, _cv($ret, 'id', 'nn') ? 200 : 201);
    }

    //    comments API-s
    public function getComments($params = [])
    {
        $model = new CommentsModel();
        $params['status'] = ['published', 'hidden'];
        $list = $model->getList($params);
        return response($list);
    }

    public function getComment($params = [])
    {
        $model = new CommentsModel();
        $list = $model->getOne($params);
        return response($list);
    }

    public function updateComment($params = [])
    {
        $model = new CommentsModel();
        $ret = $model->updItem($params);

        if (is_numeric($ret))
            $ret = $model->getOne(['id' => $ret]);

        return response($ret, _cv($ret, 'id', 'nn') ? 200 : 201);
    }

    // Wallets Api
    public function getWallets($params = [])
    {
        $wallets = new WalletsModel();
        $list = $wallets->getList($params);

        return response($list);
    }
    public function getWallet($params = [])
    {
        $wallets = new WalletsModel();
        $list = $wallets->getOne($params);

        return response($list);
    }
    public function updateWallets($params = [])
    {
        if (!_cv($params, 'id', 'nn')) {
            $params['account_number'] = time();
        }

        $model = new WalletsModel();
        $ret = $model->updItem($params);

        if (is_numeric($ret))
            $ret = $model->getOne(['id' => $ret]);

        return response($ret, _cv($ret, 'id', 'nn') ? 200 : 201);
    }

    public function addUsersToGroup($params = [])
    {
        if(!_cv($params, ['importList'], 'ar') || !_cv($params, ['importList']))return response(['error'=>'not enought info'], 201);

        \DB::table('users')
            ->whereIn('p_id', $params['importList']) // Specify the condition for the update
            ->update([ 'member_group' => $params['group'] ]);

        return response(['msg'=>'users group updated'], 200);
    }

}
