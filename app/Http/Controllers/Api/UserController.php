<?php

namespace App\Http\Controllers\Api;

use App\Models\Admin\OrderModel;
use App\Models\User\ShopAdresses;
use App\Models\User\User;
use Dotenv\Validator;
use App;
use App\Http\Transformers\UserTransformer;
use App\Services\User\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class UserController extends ApiController
{

    public function create(Request $request, UserService $userService)
    {

        $valid = [
            'email' => 'required|email|unique:users',
            'fullname' => 'required|string',
            'username' => 'required|unique:users',
            'phone' => 'required|numeric',
            'password' => 'required|min:8',
        ];

        $validator = Validator::make($request->all(), $valid)->validate();
        try {
            return $userService->create($request->all());
        } catch (Excepion $e) {
            return response($e->getMessage());
        }
    }
    public function Step1(Request $request){
      $error = [];
      if(!$request->agreeChecked){
          $error['agreeChecked'] = 'AggreeChecked is not Checked';
      }
      if(!$request->phone){
          $error['phone'] = 'phone not Entered';
      }
        if(!$request->email){
            $error['email'] = 'Email not Entered';
        }else{
            $user = User::Where('email', $request->email)->first();
            if($user){
                $error['email']  = 'User With this email exists';
            }
        }
        if(sizeof($error)>0){
            return response('dafiqsirda shecdoma', 201);
        }else{
            return response('success', 200);
        }

    }
    public function Step2(Request $request){
        if(!$request->anotherPhone){
            $error['anotherPhone']='anothherphone not specified';
            return response('dafiqsirda shecdoma', 201);
        }else{
            return response('success', 200);

        }

    }
    public function Step3(Request $request){
       if($request->sms_code=='5454'){
         return response('modis alijan', 200);

       }
        return response('opaaa, eroria', 201);
    }
    public function Step4(Request $request){
        $error = [];
        if(!$request->agreeChecked){
            $error['agreeChecked'] = 'AggreeChecked is not Checked';
        }
        if(!$request->phone){
            $error['phone'] = 'phone not Entered';
        }
        if(!$request->name){
            $error['name'] = 'name not Entered';
        }
        if(!$request->password){
            $error['password'] = 'password not Entered';
        }
        if(!$request->sms_code){
            $error['sms_code'] = 'sms_code not Entered';
        }
        if(!$request->email){
            $error['email'] = 'Email not Entered';
        }else{
            $user = User::Where('email', $request->email)->first();
            if($user){
                $error['email']  = 'User With this email exists';
            }
        }
        if(sizeof($error)>0){
            return response('dafiqsirda shecdoma', 201);
        }else{
            $response = $this->Registration($request);
            if($response){
                return $response;
            }else{
                return response('error while registration', 201);
            }
        }
    }
    public function Registration(Request $request){
        $valid = [
            'name' =>'required|string',
            'email' =>'required|string',
            'phone' =>'required|numeric|min:9',
            'password' =>'required|min:6',
            'agreeChecked' =>'required',
        ];
        request()->validate($valid);
        $user = User::create([
            'username' => $request->phone,
            'password' => trim(Hash::make($request->password)),
            'fullname'=>$request->name,
            'phone'=>$request->phone,
            'email' => $request->email,
        ]);
        $credentials = $request->only('phone','password');
        if (Auth::attempt($credentials, true)) {
            $user = Auth::user();
            $token =$user->getRememberToken();
            $realtoken =  $this->respondWithToken($token);
        }
        return $realtoken?:'dafiqsirda shecdoma';
    }
    protected function PostLogin(Request $request){
        $validateArray = [
            'phone' => 'required',
            'password' => 'required',
        ];
        request()->validate($validateArray);

        $credentials = $request->only('phone', 'password');
        if (Auth::attempt($credentials, true)) {
            $user = Auth::user();
            $token =$user->getRememberToken();
            return $this->respondWithToken($token);
        }
        return response('Login ver moxerxda', 201);

        session()->flash('error', tr('you have entered invalid credentials'));
        return redirect()->back()->withInput($request->input());

    }
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60
        ]);
    }
    public function GetUserData(Request $request){
        if($request->token==='null') return false;
       $token =trim($request->token);
       $user = User::Where('remember_token', $token)->first();
       $user->general_data = json_decode($user->general_data,1);
       $user->contanct_person = json_decode($user->contanct_person ,1);
       $address = ShopAdresses::where('user_id', $user->id)->get();
       $ordermodel = new OrderModel();
       $order = $ordermodel->GetOrdersByUser($user->id);
       return response()->json(['userdata'=>$user, 'address'=>$address, 'order'=>$order]);
    }
    public function UpdProfileSetting(Request $request){
        $user = User::where('remember_token', $request->token)->first();
        $user->fullname = $request->data['fullName'];
        $user->email = $request->data['email'];
        $user->username = $request->data['phone'];
        $user->phone = $request->data['phone'];
        $user->save();
        if($request->data['newPassword'] && $request->data['oldPassword']){
            if(!Hash::check($request->data['oldPassword'], $user->password)){
               return response('old_password no match', 201);
            }
            $user->password = Hash::make(trim($request->data['newPassword']));
            $user->save();
        }
        return $user;
    }
    public function UpdContactPerson(Request $request){
        $user = User::where('remember_token', $request->token)->first();
        $user->contanct_person = $request->data[0];
        $user->general_data = $request->data[1];
        $user->save();
        return $user;
    }
    public function EvluationForm(Request $request){
        return false;
    }
    public function AddAddress($params=[]){
        $request= Request();
        $params = $request;
        $token = $params['token'];
        $user = User::where('remember_token', $token)->first();
        if(isset($params['data']['main_adress']) && $params['data']['main_adress']){
            $addresses = ShopAdresses::Where('user_id', $user->id)->get();
            foreach ($addresses as $address){
                $add = ShopAdresses::find($address->id);
                $add->main_adress = 0;
                $add->save();
            }
        }
        $address = new ShopAdresses();
        $address->user_id = $user->id;
        $address->main_adress = isset($params['data']['main_adress'])?1:0;
        $address->city = $params['data']['city']?:'';
        $address->entrance = $params['data']['entrance']?:'';
        $address->floor = $params['data']['floor']?:'';
        $address->house = $params['data']['house']?:'';
        $address->name = $params['data']['name']?:'';
        $address->phone = $params['data']['phone']?:'';
        $address->street = $params['data']['street']?:'';
        $address->save();
        return response()->json(['addresses'=>ShopAdresses::where('user_id', $user->id)->get()]);
    }
    public function DeleteAddress(Request $request){
        $user = User::Where('remember_token', $request->token)->first();
        $address = ShopAdresses::find($request->data['id']);
        if($address){
            $address->delete();
        }
        $ret = ShopAdresses::Where('user_id', $user->id)->get();
        return response()->json(['success'=>$ret]);

    }
    public function EvaluateOrderForm(Request $request){
        dd($request);

    }
    public function EditAddress(Request $request){
        $user = User::Where('remember_token', $request->token)->first();
        if($request->data['main_adress']){
            $addresses = ShopAdresses::Where('user_id', $user->id)->get();
            foreach ($addresses as $address){
                $add = ShopAdresses::find($address->id);
                $add->main_adress = 0;
                $add->save();
            }
        }
        $address = ShopAdresses::find($request->data['id']);
        $address->main_adress = $request->data['main_adress']?1:0;
        $address->city = $request->data['city'];
        $address->entrance = $request->data['entrance'];
        $address->floor = $request->data['floor'];
        $address->house = $request->data['house'];
        $address->name = $request->data['name'];
        $address->phone = $request->data['phone'];
        $address->street = $request->data['street'];
        $address->save();
        $ret = ShopAdresses::Where('user_id', $user->id)->get();
        return response()->json(['success'=>$ret]);

    }




   /* public function update(Request $request)
    {
        $valid = [
            'id' => 'required',
            'email' => 'email',
            'fullname' => 'string',
            'phone' => 'numeric',
        ];
        if ($request->password) {
            $valid['password'] = 'min:8';
        }

        $messages = [
            'email.required' => "არასწორი ID"
        ];

        $request->request->remove('username');
        $validator = Validator::make($request->all(), $valid, $messages)->validate();
        $user = App::make(UserService::class)->update($request->id, $request->all());
        return $this->response($user, new UserTransformer);
    }


    public function getAllUser(Request $request)
    {
        $limit = 5;
        $search = null;
        if ($request->paginate) {
            $limit = $request->paginate;
        }
        if ($request->search) {
            $search = $request->search;
        }
        $users = App::make(UserService::class)->search($search, $request->get('page', 1), $limit);

        return $this->response($users, new UserTransformer);
    }


    public function delete(Request $request)
    {

        $user = App::make(UserService::class)->delete($request->id);
        if ($user) {
            return response()->json(['success'=>true]);
        }
    }*/
}
