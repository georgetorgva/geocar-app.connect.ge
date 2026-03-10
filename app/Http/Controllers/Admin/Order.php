<?php

namespace App\Http\Controllers\Admin;

use App\Models\Admin\OrderModel;
use App\Models\User\User;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controller;
use App\Models\Admin\StockModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;
use App\Models\Media\MediaModel;
/**
 * main controller for all the content types
 */
class Order extends Controller
{


    //
    protected $mainModel;
    protected $error = false;

    public function __construct()
    {
        $this->mainModel = new OrderModel();
    }

    public function InOneClick(){
        $request = Request();
        DB::table('quick_orders')
            ->insert([
                'name'=>$request->info['name'],
                'number'=>$request->info['phone'],
                'regular_customer'=>$request->info['regularCostumer']?1:0,
                'ordering'=>$request->info['home']?1:$request->info['office']?2:3,
                'cart'=>json_encode($request->cart['data']),
                'unique_id'=>json_encode($request->cart['data']['uniqueID'])
            ]);
        $cart = new Cart();
        $cart->ClearHistory(['UniqueID'=>$request->cart['data']['uniqueID']]);
        return response(tr('shekveta warmateebit ganxorcielda'), 200);
    }
    public function CheckOrRegisterUser($params =[]){
        $request = Request();
        $params=$request;
        $user = User::where('phone', $params['phone'])->first();
        if($user){
            return response('user exists', 200);
        }
        return response('validated', 200);
    }
    public function AnotherNumber(){
        $request = Request();
        return $request;
    }
    public function CheckCode(){
        $request = Request();
        if($request->code=='5454'){
            return response($request,200);
        }
        return response($request,201);
    }
    public function SendOrder($params=[]){
        $request = Request();
        $params = $request;
        $order = $this->mainModel->UserSendOrder($params);

        return $order;
    }
    public function SendBarOrder(Request $request){
        $params = $request;
        $order = $this->mainModel->UserSendBarOrder($params);

        return $order;
    }

    public function getPages(){
        return $this->mainModel->GetOrders();
    }
    public function GetBarOrder(){
        return $this->mainModel->GetBarOrder();
    }

    public function deleteOrderitem(Request $request){
        $data = $this->mainModel->deleteOrderitem($request);
        return $this->mainModel->GetOrder($data->id)[0];
    }

}
