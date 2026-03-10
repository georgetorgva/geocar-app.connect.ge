<?php

namespace App\Http\Controllers\Admin\Shop;

use App\Http\Controllers\Admin\Shop\services\LtbRequests;
use Illuminate\Support\Carbon;
use App\Models\Shop\OrderModel;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use App\Models\Shop\CartModel;


/**
 * main controller for shop
 */
class ShopSiteMainPrivate extends Controller
{


    //
    protected $mainModel;
    protected $error = false;

    public function getOrders($params = [])
    {
        $orderModel = new OrderModel();

        /// date filter
        if(_cv($params, 'filters.dateFilters')){
            if($params['filters']['dateFilters'] === 'month1'){
                $startDate = date('Y-m-d', strtotime(" -1 month"));
                $endDate = date('Y-m-d');
            } elseif ($params['filters']['dateFilters'] === 'month3'){
                $startDate = date('Y-m-d', strtotime(" -3 month"));
                $endDate = date('Y-m-d');
            } elseif ($params['filters']['dateFilters'] === 'month6'){
                $startDate = date('Y-m-d', strtotime(" -6 month"));
                $endDate = date('Y-m-d');
            } else {
                $startDate = date($params['filters']['dateFilters'].'-01-01');
                $endDate = date('Y-m-d', strtotime($startDate." +1 year"));
            }

            $reqParams['created_at'] = [$startDate, $endDate];
        }

        /// status filter
        $orderStatuses = config('adminshop.order.order_status');

        if(_cv($params, 'filters.status') ){ ///&& array_search($params['filters']['status'], $orderStatuses) !== false
            $reqParams['order_status'] = $params['filters']['status'];
        }

        /// search text filter
        if(_cv($params, 'filters.searchText')){
            $reqParams['searchText'] = $params['filters']['searchText'];
        }

        /// loged in user filter
        $reqParams['user_id'] = Auth::user()->id;
//        p($reqParams);
        $order = $orderModel->getList($reqParams);

        return response($order);
    }

    public function getPointTransactions($params = [])
    {
        $LtbRequests = new LtbRequests();
        $idNumber = Auth::user()->p_id;
        // $idNumber = '01011039765';

        $cacheName = "pointTransactions_{$idNumber}";
        $value = Cache::store('file')->get($cacheName);
        if($value) return $value;

        $ret = $LtbRequests->getPointTransactions(['idNumber'=>$idNumber]);

        Cache::put($cacheName, response($ret), 3600);

        return response($ret);
    }

    public function GetShippingCost($params = [])
    {

        $userId = (Auth::user() && Auth::user()->id)?Auth::user()->id:false;

        if(!$userId)return response(['error'=>'user not authorized!']);

        $LtbRequests = new LtbRequests();
        $cart = new CartModel();

        $res = $cart->getOne(['user_id'=>$userId]);
        $ret = $LtbRequests->getShippingCost(['cart'=>$res]);


        return response($ret);
    }



}
