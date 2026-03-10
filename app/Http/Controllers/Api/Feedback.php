<?php

namespace App\Http\Controllers\Api;
use App;
use http\Client\Curl\User;
use http\Env\Request;
use Illuminate\Support\Facades\DB;

class Feedback extends ApiController
{
    public function Feedback(){
        $request= Request();
        $user=null;
        if($request->token && $request->token!=='null'){
            $user = User::where('remember_token', $request->token)->first();
            $user= $user->id;
        }
        $arr= [];
        if($request->data['appealTypeComplaint']){
            $arr[]= 'complaint';
        }
        if($request->data['appealTypeOffer']){
            $arr[]= 'offer';
        }
        if($request->data['appealTypegratitude']){
            $arr[]= 'grattitude';
        }
        DB::table('feedbacks')->insert([
            'city'=>$request->data['City'],
            'appeal_type'=>sizeof($arr)>0?json_encode($arr):null,
            'customer_type'=>$request->data['costumerType'],
            'email'=>$request->data['email'],
            'name'=>$request->data['name'],
            'phone'=>$request->data['phone'],
            'textarea'=>$request->data['textarea'],
            'user_id'=>$user,
            ]);
        return response('success', 200);
    }
}
