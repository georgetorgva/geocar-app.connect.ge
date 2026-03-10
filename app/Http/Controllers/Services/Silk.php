<?php

namespace App\Http\Controllers\Services;

use Exception;
use App;
use Illuminate\Http\Request;
use App\Models\Admin\OptionsModel;
use App\Http\Controllers\Admin\Options;
use App\Models\Silk\ChanelsModel;
use App\Models\Silk\PackageTypesModel;
use App\Models\Silk\PackagesModel;
use App\Models\Silk\AdditionalServiceModel;
use App\Models\Silk\RoamingsModel;
use App\Http\Controllers\Silk\SilkMain;
use App\Models\Admin\TaxonomyModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Silk extends App\Http\Controllers\Api\ApiController
{

    private function _get($com){
        $url=env('SILK_URL', 'localhost').$com;
        $client = new \GuzzleHttp\Client(['verify' => false]);
        $credentials = base64_encode(env('SILK_USER', 'user').':'.env('SILK_PASS', 'pass'));
        $response = $client->get($url,[
            'headers' => [
                'Authorization' => 'Basic ' . $credentials,
            ]
        ]);
        return json_decode ($response->getBody()->getContents());
    }
    private function _post($com,$body){
        $url=env('SILK_URL', 'localhost').$com;
        $client = new \GuzzleHttp\Client(['verify' => false]);
        $credentials = base64_encode(env('SILK_USER', 'user').':'.env('SILK_PASS', 'pass'));
        $response = $client->post($url,[
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . $credentials,
            ],
            'body'=>json_encode($body)
        ]);
        //print_r($response->getBody()->getContents());
        return json_decode ($response->getBody()->getContents());
    }
    private function _getB($com,$body){
        $url=env('SILK_URL', 'localhost').$com;
        $client = new \GuzzleHttp\Client(['verify' => false]);
        $credentials = base64_encode(env('SILK_USER', 'user').':'.env('SILK_PASS', 'pass'));
        $response = $client->get($url,[
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . $credentials,
            ],
            'body'=>json_encode($body)
        ]);
        //print_r($response->getBody()->getContents());
        return json_decode ($response->getBody()->getContents());
    }

    /**
     * @OA\Post( path="/view/services/phone-numbers", tags={"Public website Services"}, summary="get phone numbers list from silknet", operationId="phoneNumbers",
     *   @OA\Parameter( name="request params", description="request params", required=true, in="path",
     *     @OA\Schema(
     *      @OA\Property(property="searchWord", type="string", example="some text"),
     *  ),),
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function phoneNumbers(){
        try{
            $data=$this->_get('/rest/connect/phone-numbers');
            $data2=$this->_get('/rest/connect/phone-number-types');
            $phoneNumbers=$data->phoneNumbers;
            $phoneNumberTypes=$data2->phoneNumberTypes;

            return response([
                    'success'=>$data->success,'message'=>$data->message,
                    'phoneNumbers'=>$phoneNumbers,
                    'phoneNumberTypes'=>$phoneNumberTypes
                ]);
        }catch (\Exception $ex){
            return response(['success'=>false,'message'=>'Internal Server Error']);
        }
    }

    /**
     * @OA\Post( path="/view/services/fill-balance-check", tags={"Public website Services"}, summary="ბალანსის შევსების დაშვებითობა(შემოწმება)", operationId="fillBalanceCheck",
     *   @OA\Parameter( name="request params", description="request params", required=true, in="path",
     *     @OA\Schema(
     *      @OA\Property(property="phoneNumber", type="numeric", example="322555555"),
     *      @OA\Property(property="numberType", type="string", example="HOME_PHONE"),
     *  ),),
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function fillBalanceCheck(Request $request){
        try{
            $validator = \Validator::make($request->all(), [
                'phoneNumber' => ['required','numeric', 'digits_between:3,10'],
                'numberType'=>['required', 'in:PHONE,ACCOUNT,HOME_PHONE']
            ]);

            if ($validator->fails()) {return response(['success'=>false,'message'=>$validator->errors()->first()]);}
            $data = $this->_get('/rest/connect/fill-balance?phoneNumber='.$request->phoneNumber.'&numberType='.$request->numberType);
            //dd($data);
            return response([ 'success'=>$data->success,'message'=>$data->message ]);

            return response($data);
        }catch (\Exception $ex){
            return response(['success'=>false,'message'=>$ex->getMessage()]);
       }
    }

    /**
     * @OA\Post( path="/view/services/fixes-balance", tags={"Public website Services"}, summary="ბალანსის შემოწმება Fix ნომრებისათვის", operationId="fixesBalance",
     *   @OA\Parameter( name="request params", description="request params", required=true, in="path",
     *     @OA\Schema(
     *      @OA\Property(property="phoneNumber", type="numeric", example="322555555"),
     *      @OA\Property(property="numberType", type="string", example="HOME_PHONE"),
     *  ),),
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function fixesBalance(Request $request){
        try{
            $validator = \Validator::make($request->all(), [
                'phoneNumber' => ['required','numeric', 'digits_between:3,10'],
                'numberType'=>['required', 'in:PHONE,ACCOUNT,HOME_PHONE']
            ]);

            if ($validator->fails()) {return response(['success'=>false,'message'=>$validator->errors()->first()]);}

            $data=$this->_get('/rest/connect/fixes-balance?phoneNumber='.$request->phoneNumber.'&numberType='.$request->numberType);

            return response([
                'success'=>$data->success,'message'=>$data->message,
                'debt'=>$data->debt,
                'advance'=>$data->advance,
                'initials'=>$data->initials
            ]);
        }catch (\Exception $ex){
            return response(['success'=>false,'message'=>'Internal Server Error']);
       }
    }
    public function fillBalanceTest(Request $request){
        try{
            $validator = \Validator::make($request->all(), [
                'phoneNumber' => ['required','numeric', 'digits_between:3,10']
            ]);

            if ($validator->fails()) {return response(['success'=>false,'message'=>$validator->errors()->first()]);}


            $data=$this->_get('/rest/connect/fill-balance?phoneNumber='.$request->phoneNumber);

            return response([
                'success'=>$data->success,'message'=>$data->message,
            ]);
        }catch (\Exception $ex){
            return response(['success'=>false,'message'=>$ex->getMessage()]);
        }
    }

    /**
     * @OA\Post( path="/view/services/fill-balance", tags={"Public website Services"}, summary="ბალანსის შევსება mobile & Fix ნომრებისათვის", operationId="fillBalance",
     *   @OA\Parameter( name="request params", description="request params", required=true, in="path",
     *     @OA\Schema(
     *      @OA\Property(property="amount", type="numeric", example="1"),
     *      @OA\Property(property="phoneNumber", type="numeric", example="322112233"),
     *      @OA\Property(property="uniqueId", type="numeric", example="123456732"),
     *  ),),
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function fillBalance(Request $request){
        try{
            $validator = \Validator::make($request->all(), [
                "amount" => ['required','numeric','min:1'],
                'phoneNumber' => ['required','numeric', 'digits_between:3,10']
            ]);

            if ($validator->fails()) {
                return response(['success'=>false,'message'=>$validator->errors()->first()]);
            }

            $data=$this->_get('/rest/connect/fill-balance?phoneNumber='.$request->phoneNumber.'&numberType='.$request->numberType);

            if (!$data->success) throw new Exception($data->message);

            $callbackUrl=urlencode(route('fillBalanceCallback'));
            //dd(route('fillBalanceCallback'));
            //$request->amount=1;
            $description=Str::slug(urlencode('balansis shevseba'), '-');
            $url="/rest/ufc-payment/pre-authorization?amount={$request->amount}&currency=GEL&ipAddress={$request->ip()}&language=GE&description={$description}&messageType=SMS&callbackUrl={$callbackUrl}";
            //dd($url);
            $data=$this->_post($url,[]);
            if (!$data->success) throw new Exception($data->message);
            $bookingUniqueId=$data->data->transactionId;
            $submitHtml=$data->data->submitHtml;

            $id = \DB::table('silk_api_fill_balance_log')->insertGetId(
                [ 'ip' => $request->ip(),
                  'phoneNumber'=>$request->phoneNumber,
                  'numberType'=>$request->numberType,
                  'amount'=>$request->amount,
                  'bookingUniqueId'=>$bookingUniqueId
                 ]
            );

//            $body=[
//              'amount'=>$request->amount,'phoneNumber'=>$request->phoneNumber,'uniqueId'=>$id
//            ];
//            $data=$this->_post('/rest/connect/fill-balance',$body);


            return response([
                'success'=>true,'message'=>'Ok','submitHtml'=>$submitHtml
            ]);
        }catch (\Exception $ex){
            return response(['success'=>false,'message'=>$ex->getMessage()]);
        }
    }

    /**
     * @OA\Post( path="/view/services/fill-balance-callback", tags={"Public website Services"}, summary="fill Balance Callback from ufc", operationId="fillBalanceCallback",
     *   @OA\Parameter( name="request params", description="request params", required=true, in="path",
     *     @OA\Schema(
     *      @OA\Property(property="transactionId", type="numeric", example="1"),
     *      @OA\Property(property="phoneNumber", type="numeric", example="322112233"),
     *      @OA\Property(property="uniqueId", type="numeric", example="123456732"),
     *  ),),
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function fillBalanceCallback(Request $request){
        //return redirect('../online-services?success=true');
        $bookingUniqueId =$request->transactionId;
        if(!$bookingUniqueId){
            return redirect('../');
        }

        //ბანკის ტრანზაქციას ვამოწმებთ წარმატებაზე
        $data=$this->_get("/rest/ufc-payment/check-transaction-status?transactionId={$bookingUniqueId}&language=EN");

        $db=\DB::table('silk_api_fill_balance_log')->where('bookingUniqueId','=',$bookingUniqueId);
        $db->update(['statusData' => json_encode($data)]);

        if(!$data->success){
            $db->update(['success'=>false]);
            return redirect('../online-services?success=false');
        };

        $row=$db->first();
        if (!$row){
            $db->update(['success'=>false]);
            return redirect('../online-services?success=false');
        }



        //ბალანსის შევსება
        $body=['amount'=>$row->amount,'phoneNumber'=>$row->phoneNumber,'numberType'=>$row->numberType,'uniqueId'=>$bookingUniqueId];
        $data=$this->_post('/rest/connect/fill-balance',$body);
        $db->update(['fillBalanceStatusData' => json_encode($data)]);

        if(!$data->success){
            $db->update(['success'=>false]);
            //როლბექი
            $this->_post("/rest/ufc-payment/rollback-transaction?transactionId={$bookingUniqueId}&amount={$row->amount}&language=EN",[]);
            return redirect('../online-services?success=false');
        }

        //პრეავტორიზაციის კომიტი
        $url="/rest/ufc-payment/pre-authorization-commit?transactionId={$bookingUniqueId}&amount={$row->amount}&currency=GEL&language=GE&description=&messageType=SMS&ipAddress={$request->ip()}";
        $data=$this->_post($url,[]);
        $db->update(['commitStatusData' => json_encode($data)]);

        if(!$data->success){
            $db->update(['success'=>false]);
            return redirect('../online-services?success=false');
        }

        $db->update(['success'=>true]);
        return redirect('../online-services?success=true');
    }

    /**
     * @OA\Post( path="/view/services/topup-offer-check", tags={"Public website Services"}, summary="Topup პაკეტის შეძენის დაშვებითობა(შემოწმება)", operationId="topupOfferCheck",
     *   @OA\Parameter( name="request params", description="request params", required=true, in="path",
     *     @OA\Schema(
     *      @OA\Property(property="package_id", type="numeric", example="1"),
     *      @OA\Property(property="phoneNumber", type="numeric", example="322112233"),
     *  ),),
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function topupOfferCheck(Request $request){
        try{
            $validator = \Validator::make($request->all(), [
                "package_id" => ['required','numeric'],
                'phoneNumber' => ['required','numeric', 'digits_between:3,10']
            ]);

            if ($validator->fails()) {
                return response(['success'=>false,'message'=>$validator->errors()->first()]);
            }

            $pm=new PackagesModel();
            $package = $pm->getOne(['id'=>$request->package_id]);

            $packagePrice = _cv($package, ['price'])*100;

            $service_id=trim($package['service_id']); //bundleId
            //dd($service_id);
//            print '/rest/connect/topup-offer?phoneNumber='.$request->phoneNumber.'&bundleId='.$service_id.'&amount='.$packagePrice;
            $data=$this->_get('/rest/connect/topup-offer?phoneNumber='.$request->phoneNumber.'&bundleId='.$service_id.'&amount='.$packagePrice);

            return response([
                'success'=>$data->success,'message'=>$data->message,'errors'=>$data->errors
            ]);
        }catch (\Exception $ex){
            return response(['success'=>false,'message'=>'Internal Server Error']);
        }
    }

    /**
     * @OA\Post( path="/view/services/topup-offer", tags={"Public website Services"}, summary="Topup პაკეტის შეძენა", operationId="topupOffer",
     *   @OA\Parameter( name="request params", description="request params", required=true, in="path",
     *     @OA\Schema(
     *      @OA\Property(property="package_id", type="numeric", example="1"),
     *      @OA\Property(property="phoneNumber", type="numeric", example="322112233"),
     *  ),),
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function topupOffer(Request $request){
        try{
            $validator = \Validator::make($request->all(), [
                //"amount" => ['required','numeric','min:1','max:200'],
                "package_id" => ['required','numeric'],
                'phoneNumber' => ['required','numeric', 'digits_between:3,10'],
                //'serviceCode'=>['required']
            ]);

            if ($validator->fails()) {
                return response([
                    'success'=>false,'message'=>$validator->errors()->first()
                ]);
            }
///bbb
            $pm = new PackagesModel();
            $package = $pm->getOne(['id'=>$request->package_id]);
            $service_id = trim($package['service_id']); //bundleId
            $amount = _cv($package, ['price'])*100;

            //$amount=1;

            //dd($service_id);

            $data=$this->_get('/rest/connect/topup-offer?phoneNumber='.$request->phoneNumber.'&bundleId='.$service_id);

            if (!$data->success){
                return response(['success'=>$data->success,'message'=>$data->message,'errors'=>$data->errors]);
            }

            $callbackUrl=urlencode(route('topupOfferCallback'));
            $description=Str::slug(urlencode('paketis shedzena'), '-');
            $url="/rest/ufc-payment/pre-authorization?amount={$amount}&currency=GEL&ipAddress={$request->ip()}&language=GE&description={$description}&messageType=SMS&callbackUrl={$callbackUrl}";
            //dd($url);
            $data=$this->_post($url,[]);
            if (!$data->success) throw new Exception($data->message);
            $bookingUniqueId=$data->data->transactionId;
            $submitHtml=$data->data->submitHtml;

            $id = \DB::table('silk_api_topup-offer_log')->insertGetId(
                [ 'ip' => $request->ip(),
                  'phoneNumber'=>$request->phoneNumber,
                  'amount'=>$amount,
                  'bundleId'=>$service_id,
                  'bookingUniqueId'=>$bookingUniqueId
                ]
            );

            return response([
                'success'=>true,'message'=>'Ok','submitHtml'=>$submitHtml
            ]);

        }catch (\Exception $ex){
            return response([
                'success'=>false,'message'=>$ex->getMessage()
            ]);
        }
    }

    /**
     * @OA\Post( path="/view/services/topup-offer-callback", tags={"Public website Services"}, summary="topup Offer Callback", operationId="topupOfferCallback",
     *   @OA\Parameter( name="request params", description="request params", required=true, in="path",
     *     @OA\Schema(
     *      @OA\Property(property="transactionId", type="numeric", example="1333211231"),
     *  ),),
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function topupOfferCallback(Request $request){
        $bookingUniqueId =$request->transactionId;
        if(!$bookingUniqueId){
            return redirect('../');
        }

        $data=$this->_get("/rest/ufc-payment/check-transaction-status?transactionId={$bookingUniqueId}&language=EN");

        $db=\DB::table('silk_api_topup-offer_log')->where('bookingUniqueId','=',$bookingUniqueId);
        $db->update(['statusData' => json_encode($data)]);

        if(!$data->success){
            $db->update(['success'=>false]);
            return redirect('../online-services?success=false');
        };

        $row=$db->first();
        if (!$row){
            $db->update(['success'=>false]);
            return redirect('../online-services?success=false');
        }
        //პაკეტის შეძენა

        $body=[
            'amount'=>$row->amount,'phoneNumber'=>$row->phoneNumber,'bundleId'=>$row->bundleId,'uniqueId'=>$bookingUniqueId
        ];
        $data=$this->_post('/rest/connect/topup-offer',$body);
        $db->update(['fillBalanceStatusData' => json_encode($data)]);
        if(!$data->success){
            $db->update(['success'=>false]);
            //როლბექი
            $this->_post("/rest/ufc-payment/rollback-transaction?transactionId={$bookingUniqueId}&amount={$row->amount}&language=EN",[]);
            return redirect('/?success=false');
        }
                //პრეავტორიზაციის კომიტი
                $url="/rest/ufc-payment/pre-authorization-commit?transactionId={$bookingUniqueId}&amount={$row->amount}&currency=GEL&language=GE&description=&messageType=SMS&ipAddress={$request->ip()}";
                $data=$this->_post($url,[]);
                $db->update(['commitStatusData' => json_encode($data)]);

                if(!$data->success){
                    $db->update(['success'=>false]);
                    return redirect('/?success=false');
                }

                $db->update(['success'=>true]);
                return redirect('/?success=true');
    }

    /**
     * @OA\Post( path="/view/services/phone-number-types", tags={"Public website Services"}, summary="ნომრების ტიპების სიის წამოღება", operationId="phoneNumberTypes",
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function phoneNumberTypes(){
        try{
            $data=$this->_get('/rest/connect/phone-number-types');

            return response([
                'success'=>true,
                'message'=>'Ok',
                'phoneNumberTypes'=>$data->phoneNumberTypes
            ]);
        }catch (\Exception $ex){
            return response(['success'=>false,'message'=>'Internal Server Error']);
        }
    }

    /**
     * @OA\Post( path="/view/services/phone-numbers-total-price", tags={"Public website Services"}, summary="ტელეფონის ნომრების ჯამური ფასის დადგენა", operationId="phoneNumbersTotalPrice",
     *   @OA\Parameter( name="request params", description="request params", required=true, in="path",
     *     @OA\Schema(
     *      @OA\Property(property="phoneNumbers", type="numeric", example="13331231"),
     *  ),),
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function phoneNumbersTotalPrice(Request $request){
        try{
            $validator = \Validator::make($request->all(), [
                'phoneNumbers' => ['required','array']
            ]);

            if ($validator->fails()) {
                return response(['success'=>false,'message'=>$validator->errors()->first()]);
            }

            $body=[
                'phoneNumbers'=>[
                    "555552493",
                    "555552548"
                ]
            ];
            //dd($body);
            $data=$this->_getB('/rest/connect/phone-numbers-total-price',$body);

            return response([
                'success'=>$data->success,'message'=>$data->message,'totalPrice'=>$data->totalPrice
            ]);
        }catch (\Exception $ex){
            return response(['success'=>false,'message'=>$ex->getMessage()]);
        }
    }

    /**
     * @OA\Post( path="/view/services/cities", tags={"Public website Services"}, summary="ქალაქების სიის წამოღება", operationId="cities",
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function cities(){
        try{
            $data=$this->_get('/rest/connect/cities');
            return response([
                'success'=>$data->success,'message'=>$data->message,
                'cities'=>$data->cities
            ]);
        }catch (\Exception $ex){
            return response(['success'=>false,'message'=>'Internal Server Error']);
        }
    }

    /**
     * @OA\Post( path="/view/services/actions", tags={"Public website Services"}, summary="Action ების სიის წამოღება", operationId="actions",
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function actions(){
        try{
            $data=$this->_get('/rest/connect/actions');
            return response([
                'success'=>$data->success,'message'=>$data->message,
                'actions'=>$data->actions
            ]);
        }catch (\Exception $ex){
            return response(['success'=>false,'message'=>'Internal Server Error']);
        }
    }

    /**
     * @OA\Post( path="/view/services/service-types", tags={"Public website Services"}, summary="პროცესინგის სერვისების სიის წამოღება", operationId="serviceTypes",
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function serviceTypes(){
        try{
            $data=$this->_get('/rest/connect/service-types');
            return response([
                'success'=>$data->success,'message'=>$data->message,
                'serviceTypes'=>$data->serviceTypes
            ]);
        }catch (\Exception $ex){
            return response(['success'=>false,'message'=>'Internal Server Error']);
        }
    }

    /**
     * @OA\Post( path="/view/services/channels", tags={"Public website Services"}, summary="ჩენელების სიის წამოღება", operationId="channels",
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function channels(){
        try{
            $data=$this->_get('/rest/connect/channels');
            return response([
                'success'=>$data->success,
                'message'=>$data->message,
                'channels'=>$data->channels
            ]);
        }catch (\Exception $ex){
            return response(['success'=>false,'message'=>'Internal Server Error']);
        }
    }

    /**
     * @OA\Post( path="/view/services/installation-cities", tags={"Public website Services"}, summary="ქალაქების სიის წამოღება ინსტალაციებისთვის", operationId="installationCities",
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function installationCities(){
        try{
            $data=$this->_get('/rest/connect/installation-cities');
            return response([
                'success'=>$data->success,'message'=>$data->message,
                'cities'=>$data->cities
            ]);
        }catch (\Exception $ex){
            return response(['success'=>false,'message'=>'Internal Server Error']);
        }
    }

    /**
     * @OA\Post( path="/view/services/customer-types", tags={"Public website Services"}, summary="ქასთომერის ტიპების წამოღება", operationId="customerTypes",
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function customerTypes(){
        try{
            $data=$this->_get('/rest/connect/customer-types');
            return response([
                'success'=>$data->success,'message'=>$data->message,
                'customerTypes'=>$data->customerTypes
            ]);
        }catch (\Exception $ex){
            return response(['success'=>false,'message'=>'Internal Server Error']);
        }
    }

    /**
     * @OA\Post( path="/view/services/genders", tags={"Public website Services"}, summary="სქესის სიის წამოღება", operationId="genders",
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function genders(){
        try{
            $data=$this->_get('/rest/connect/genders');
            return response([
                'success'=>$data->success,'message'=>$data->message,
                'genders'=>$data->genders
            ]);
        }catch (\Exception $ex){
            return response(['success'=>false,'message'=>'Internal Server Error']);
        }
    }

    /**
     * @OA\Post( path="/view/services/technologies", tags={"Public website Services"}, summary="ტექნოლოგიების სიის წამოღება", operationId="technologies",
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function technologies(){
        try{
            $data=$this->_get('/rest/connect/technologies');
            return response([
                'success'=>$data->success,'message'=>$data->message,
                'technologies'=>$data->technologies
            ]);
        }catch (\Exception $ex){
            return response(['success'=>false,'message'=>'Internal Server Error']);
        }
    }

    /**
     * @OA\Post( path="/view/services/check-transaction-status", tags={"Public website Services"}, summary="სტატუსის შემოწმება / CHECK_TRANSACTION_STATUS", operationId="checkTransactionStatus",
     *   @OA\Parameter( name="request params", description="request params", required=true, in="path",
     *     @OA\Schema(
     *      @OA\Property(property="transactionId", type="numeric", example="13331231"),
     *      @OA\Property(property="language", type="string", example="GE"),
     *  ),),
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function checkTransactionStatus(Request $request){
        try{
            $validator = \Validator::make($request->all(), [
                "transactionId" => ['required'],
                'language' => ['required','in:GE,EN']
            ]);

            if ($validator->fails()) {
                return response(['success'=>false,'message'=>$validator->errors()->first()]);
            }
            $data=$this->_get('/rest/ufc-payment/check-transaction-status?transactionId='.$request->transactionId.'=&language='.$request->language);

            return response([
                'success'=>$data->success,'message'=>$data->message,
            ]);
        }catch (\Exception $ex){
            return response(['success'=>false,'message'=>'Internal Server Error']);
        }
    }

    /**
     * @OA\Post( path="/view/services/booking-free-numbers", tags={"Public website Services"}, summary="ტელეფონის ნომრების ჯავშანის დაპროცესება", operationId="bookingFreeNumbers",
     *   @OA\Parameter( name="request params", description="request params", required=true, in="path",
     *     @OA\Schema(
     *      @OA\Property(property="phoneNumbers", type="object", example="[13331231,32112332]"),
     *      @OA\Property(property="deliveryAmount", type="numeric", example="5"),
     *      @OA\Property(property="idNumber", type="numeric", example="1233211233"),
     *      @OA\Property(property="firstName", type="string", example="john"),
     *      @OA\Property(property="lastName", type="string", example="Smith"),
     *      @OA\Property(property="birthDate", type="date", example="1980-10-10"),
     *      @OA\Property(property="localCitizen", type="boolean", example="true"),
     *      @OA\Property(property="customerTypeId", type="numeric", example="1"),
     *      @OA\Property(property="genderId", type="numeric", example="1"),
     *      @OA\Property(property="cityId", type="numeric", example="14"),
     *      @OA\Property(property="street", type="string", example="Rustaveli 45"),
     *      @OA\Property(property="floor", type="numeric", example="5"),
     *      @OA\Property(property="appartment", type="numeric", example="15"),
     *      @OA\Property(property="phoneNumber", type="numeric", example="1233211233"),
     *      @OA\Property(property="mobileNumber", type="numeric", example="1233211233"),
     *      @OA\Property(property="email", type="email", example="john@silknet.ge"),
     *      @OA\Property(property="language", type="string", example="GE"),
     *      @OA\Property(property="comment", type="string", example="some comment"),
     *      @OA\Property(property="file1", type="file", example="{}"),
     *      @OA\Property(property="file2", type="file", example="{}"),
     *  ),),
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function bookingFreeNumbers(Request $request){
        //dd($request->all());

        try{
            $validator = \Validator::make($request->all(), [
                "phoneNumbers" => ['required','array'],
                "deliveryAmount"=>['required','numeric','min:0'],
                "idNumber"=>['required','numeric','digits'],
                "firstName"=>['required','max:50'],
                "lastName"=>['max:50'],
                'birthDate'=>['required','date_format:Y-m-d'],
                "localCitizen"=>['required','boolean'],
                "customerTypeId"=>['required','numeric'],
                "genderId"=>['required','numeric'],
                "cityId"=>['required','numeric'],
                "street"=>['required','max:100'],
                "floor"=>['max:50'],
                "appartment"=>['max:50'],
                'phoneNumber'=>['required','max:50'],
                'mobileNumber'=>['required','max:50'],
                'email'=>['required','email'],
                'language' => ['required','max:2'], //'in:GE,EN'
                'comment' => ['string','nullable'],
                'file1'=> 'image|mimes:jpeg,png,jpg,gif',
                'file2'=> 'image|mimes:jpeg,png,jpg,gif',

                "trIdNumber"=>['max:50'],
                "trFirstName"=>['max:50'],
                "trFastName"=>['max:50'],
            ]);

            if ($validator->fails()) {
                return response(['success'=>false,'message'=>$validator->errors()->first()]);
            }

            $date=new \DateTime();



            $body=[
                'phoneNumbers'=>$request->phoneNumbers
            ];
            $data=$this->_getB('/rest/connect/phone-numbers-total-price',$body);
            if (!$data->success) throw new Exception($data->message);

            $totalPrice=$data->totalPrice;

            $data=$this->_post("/rest/ufc-payment/pre-authorization?amount={$totalPrice}&currency=GEL&ipAddress={$request->ip()}&language=GE&description={$request->comment}&messageType=SMS&callbackUrl=http://google.com/",[]);

            if (!$data->success) throw new Exception($data->message);

            $bookingUniqueId=$data->data->transactionId;
            $submitHtml=$data->data->submitHtml;
            //dd($bookingUniqueId);

            $phoneNumbers=["555111222"];

            $body=[
              "uniqueId" =>"",
              "createDate"=>$date->format('Y-m-d H:i:s'),
              "serviceType"=>1,
              "serviceSubType"=>10,
              "portingFrom"=>1,
              "portingTo"=>7,
              "promotype"=>0,
              "phoneNumbers"=>$phoneNumbers,
              "bookingUniqueId"=>$bookingUniqueId,
              "bankTransactionResult"=>true,
              "payedAmount"=>$totalPrice,
              "language"=>"ka",
              "clientIpAddress"=>$request->ip(),
              "comment"=>"",
              "technologyId"=>51,
              "directorName"=>"",
              "directorIdNumber"=>"",
              "googleMapUrl"=>"http://",
              "cadastralCode"=>"",
                "customer"=>[
                "idNumber"=> "",
                "firstName"=>$request->firstName,
                "lastName"=>$request->lastName,
                "birthDate"=>$request->birthDate,
                "localCitizen"=>$request->localCitizen,
                "customerTypeId"=>40,
                "genderId"=>42,
                "contact"=>[
                    "phoneNumber"=>$request->phoneNumber,
                    "mobileNumber"=>$request->mobileNumber,
                    "email"=>$request->email
                    ],
                    "address"=>[
                    "cityId"=>$request->cityId,
                    "street"=>$request->street,
                    "floor"=>$request->floor,
                    "appartment"=>$request->appartment
                    ],
                    "juridicalAddress"=>[
                        "street"=>""
                    ]
                    ],
                "products"=>[
                //[
                    //"productName"=>"prod1",
                    //"packageName"=>"pack1",
                    //"price"=>300
                //],
                //[
                //"productName"=>"prod2",
                //"packageName"=>"pack2",
                //"price"=>700
                //]
                ],
                "totalAmount"=>0,
                "trustedPerson"=>[
                    "idNumber"=>$request->trIdNumber,
                    "firstName"=>$request->trFirstName,
                    "lastName"=>$request->trLastName,
                    "documentNumber"=>"",
                    "documentIndex"=>"",
                ],
                "files"=>[
                "file1"=>"",
                "file2"=>"",
                "file3"=>"",
                "file4"=>"",
                "file5"=>""
                ],
                "other"=>[
                "last3CallNumber1"=>"",
                "last3CallNumber2"=>"",
                "last3CallNumber3"=>"",
                "lastPayedAmount"=>0
                ]
            ];

            $id = \DB::table('silk_api_processBooking')->insertGetId(
                [ 'date' => $date->format('Y-m-d H:i:s')]
            );

            $body["uniqueId"]=$id;

            \DB::table('silk_api_processBooking')
                    ->where('id', $id)
                    ->update(['data' => json_encode($body),'bookingUniqueId'=>$bookingUniqueId]);


            $data=$this->_post('/rest/connect/book-phone-numbers',['bookingUniqueId'=>$bookingUniqueId,'phoneNumbers'=>$phoneNumbers]);
            if (!$data->success) throw new Exception($data->message);


            //$data=$this->_post('/rest/connect/process-booking',$body);
            //return response(["ok"]);
            return response([
                'success'=>true,'message'=>'Ok','submitHtml'=>$submitHtml
            ]);

        }catch(\Exception $ex){
            return response(['success'=>false,'message'=>$ex->getMessage()]);
        }
    }

    /**
     * @OA\Post( path="/view/services/channels-sync", tags={"Public website Services"}, summary="არხების სინქრონიზაცია, საიტის ბაზას და სილკნეტის ბაზას შორის", operationId="channelsSync",
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function channelsSync (){

        ChanelsModel::Sync();
        return 'ok';
    }

    /**
     * @OA\Post( path="/view/services/cities-sync", tags={"Public website Services"}, summary="ქალაქების სინქრონიზაცია, საიტის ბაზას და სილკნეტის ბაზას შორის", operationId="citiesSync",
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function citiesSync(){

        try{
            $data = $this->_get('/rest/connect/cities?serviceType=1'); /// SIM-ის შეცვლა
            $buySim = $this->_get('/rest/connect/cities?serviceType=2'); /// ნომრის შეძენა
            $porting = $this->_get('/rest/connect/cities?serviceType=3'); /// პორტირება
            $installationCities = $this->_get('/rest/connect/installation-cities');

            if ($data->success && $installationCities->success){
                $silkmain=new SilkMain();
                $silkmain->regionsSync($data->cities, $installationCities->cities, $buySim->cities, $porting->cities);
            }
            return response(['success'=>true,'message'=>'OK']);
         }catch(\Exception $ex){
            return response(['success'=>false,'message'=>$ex->getMessage()]);
        }
    }

    /**
     * @OA\Post( path="/view/services/phone-number-verification", tags={"Public website Services"}, summary="phone number verification", operationId="phoneNumberVerification",
     *   @OA\Parameter( name="request params", description="request params", required=true, in="path",
     *     @OA\Schema(
     *      @OA\Property(property="phoneNumber", type="numeric", example="13331231"),
     *  ),),
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function phoneNumberVerification(Request $request){

        $validator = \Validator::make($request->all(), [
            'phoneNumber' => ['required']
        ]);

        if ($validator->fails()) {
                return response(['success'=>false,'message'=>$validator->errors()->first()]);
        }

        \DB::table('smsCodes')->where('date','<','NOW()-INTERVAL 60 MINUTE')->delete();

        $code=rand(0,9).rand(0,9).rand(0,9).rand(0,9);
        $status = $this->sendSms($request->phoneNumber,"SMS Code: ".$code);


        $smsId = false;
        if ($status->success){

            $smsId = (string) Str::uuid();

            $id = \DB::table('smsCodes')->insertGetId([ 'id'=>$smsId,'code' => $code]);
        }

        return response(['success'=>$status->success,'message'=>$status->message,'smsid'=>$smsId]);
    }

    public function sendSms($phoneNumber,$text){
        try{
            $data=$this->_post('/rest/connect/send-sms',['phoneNumber'=>$phoneNumber,'text'=>$text]);
            return $data;
        }catch(\Exception $ex){
            return response(['success'=>false,'message'=>$ex->getMessage()]);
        }
    }

    /**
     * @OA\Post( path="/view/services/package-purchase-business", tags={"Public website Services"}, summary="პაკეტის შეძენა, ბიზნესის ვერსია", operationId="packagePurchaseBusiness",
     *   @OA\Parameter( name="request params", description="request params", required=true, in="path",
     *     @OA\Schema(
     *      @OA\Property(property="smsId", type="string", example="1331"),
     *      @OA\Property(property="smsCode", type="string", example="1331"),
     *      @OA\Property(property="technologyId", type="numeric", example="1"),
     *      @OA\Property(property="serviceType", type="numeric", example="1"),
     *      @OA\Property(property="serviceSubType", type="numeric", example="1"),
     *      @OA\Property(property="mobileNumber", type="numeric", example="1321232123"),
     *      @OA\Property(property="phoneNumber", type="numeric", example="1321232123"),
     *      @OA\Property(property="email", type="email", example="john@silknet.com"),
     *      @OA\Property(property="company", type="string", example="john&silknet"),
     *      @OA\Property(property="contactPerson", type="string", example="john"),
     *      @OA\Property(property="cityId", type="numeric", example="1"),
     *      @OA\Property(property="street", type="string", example="Rustaveli 23"),
     *      @OA\Property(property="juridicalAddress", type="string", example="Rustaveli 23"),
     *      @OA\Property(property="products", type="object", example="[...]"),
     *      @OA\Property(property="installAmount", type="numeric", example="5"),
     *      @OA\Property(property="totalPrice", type="numeric", example="45"),
     *  ),),
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function packagePurchaseBusiness(Request $request){
        try{
            $validator = \Validator::make($request->all(), [
                'smsId' => ['required'],
                'smsCode' => ['required'],
                'technologyId'=>['required'],
                'serviceType'=>['required','numeric'],
                'serviceSubType'=>['required','numeric'],
                'mobileNumber'=>['required'],
                'phoneNumber' =>['nullable'],
                'email'=>['nullable'],
                'company'=>['required'],
//                'compay_idNumber'=>['required'],
                'contactPerson'=>['required'],
//                'directorName'=>['required'],
//                'directorIdNumber'=>['required'],
//                'cadastralCode'=>['required'],
                'cityId'=>['required','numeric'],
                'street'=>['required'],
//                'floor'=>['required'],
//                'appartment'=>['required'],
                'juridicalAddress'=>['nullable'],
                'products'=>['required'],
                'installAmount'=>['nullable',"numeric"],
                'totalPrice'=>['required',"numeric"]
            ]);

            if ($validator->fails()) {
                return response(['success'=>false,'message'=>$validator->errors()->first()]);
            }

            $smsCode=\DB::table('smsCodes')->where('date','>','NOW()-INTERVAL 60 MINUTE')->where('id','=',$request->smsId) ->first();

            if (!$smsCode || $request->smsCode!=$smsCode->code){
                return response(['success'=>false,'message'=>'incorrect sms code']);
            }

            ////////// generate addresses and cities
            ////////// generate addresses and cities
            $taxonomyModel = new TaxonomyModel();
            $tax = $taxonomyModel->getOne(['id'=>$request->cityId]);
            $legalCityId = $selectedCityId = _cv($tax,['silk_installation_city_id']);
            $legalAddress = $address = "Street: {$request->street}, Entrance: {$request->entrance}, Floor: {$request->floor}, Appartment: {$request->appartment}";

            /// if there is same legal address
            if(is_array($request->legalAddress) && array_search('same', $request->legalAddress) === false){

                if(is_numeric($request->cityIdJuridical)){
                    $tax2 = $taxonomyModel->getOne(['id'=>$request->cityIdJuridical]);
                    $legalCityId = _cv($tax2, ['silk_installation_city_id']);
                }
                $legalAddress = "Street: {$request->streeti}, Entrance: {$request->entrancei}, Floor: {$request->floori}, Appartment: {$request->appartmenti}";

            }
            ///////////////// END generate addressees and cities

            $date=new \DateTime();
            $body=[
                "payment_status" =>0,
                "uniqueId" =>"",
                "createDate"=>$date->format('Y-m-d H:i:s'),
                "serviceType"=>$request->serviceType,
                "serviceSubType"=>$request->serviceSubType,
                //"portingFrom"=>1,
                //"portingTo"=>7,
                //"promotype"=>0,
                "phoneNumbers"=>[$request->mobileNumber],
                "bookingUniqueId"=>'',
                "bankTransactionResult"=>false,
                //"payedAmount"=>$totalPrice,
                "language"=>"ka",
                "clientIpAddress"=>$request->ip(),
                "comment"=>"",
                "technologyId"=>$request->technologyId,
                "directorName"=>$request->directorName,
                "directorIdNumber"=>$request->directorIdNumber,
                //"googleMapUrl"=>"http://",
                "cadastralCode"=>$request->cadastralCode,
                "customer"=>[
                  "idNumber"=> $request->compay_idNumber,
                  "firstName"=>$request->company,
                  "lastName"=>'',
                  "birthDate"=>null,
                  "localCitizen"=>true,
                  "customerTypeId"=>41, //Juridical person
                  "genderId"=>42, //Male
                  "contact"=>[
                      "personName"=>$request->contactPerson ,
                      "phoneNumber"=>$request->phoneNumber,
                      "mobileNumber"=>$request->mobileNumber,
                      "email"=>$request->email
                      ],
                        "address"=>[
                            "cityId"=>$selectedCityId,
                            "street"=>$address,
                        ],
                        "juridicalAddress"=>[
                            "cityId"=>$legalCityId,
                            "street"=>$legalAddress
                        ]
                      ],
                      "products" => $request->products,

                  "installAmount"=>$request->installAmount,
                  "totalAmount"=>$request->totalPrice,

              ];
//            p($body); return response([]);


            $id = \DB::table('silk_api_processBooking')->insertGetId( [ 'date' => $date->format('Y-m-d H:i:s')] );

            $body["uniqueId"]=$id;
            $body["bookingUniqueId"]=str_pad($id, 28, "0", STR_PAD_LEFT);

            \DB::table('silk_api_processBooking')
                    ->where('id', $id)
                    ->update(['data' => json_encode($body),'bookingUniqueId'=>$body["bookingUniqueId"]]);

            //echo(json_encode($body,JSON_PRETTY_PRINT));
            //exit;

            $data=$this->_post('/rest/connect/process-booking',$body);

            \DB::table('silk_api_processBooking')
                    ->where('id', $id)
                    ->update(['statusData' => json_encode($data), 'service_send_status'=>1]);

            //dd($data);
            return response([ 'success'=>$data->success,'message'=>$data->message, ]);


        }catch(\Exception $ex){
            return response(['success'=>false,'message'=>$ex->getMessage()]);
        }

    }

    /**
     * @OA\Post( path="/view/services/package-purchase", tags={"Public website Services"}, summary="პაკეტის შეძენა", operationId="packagePurchase",
     *   @OA\Parameter( name="request params", description="request params", required=true, in="path",
     *     @OA\Schema(
     *      @OA\Property(property="smsId", type="string", example="1331"),
     *      @OA\Property(property="smsCode", type="string", example="1331"),
     *      @OA\Property(property="technologyId", type="numeric", example="1"),
     *      @OA\Property(property="serviceType", type="numeric", example="1"),
     *      @OA\Property(property="serviceSubType", type="numeric", example="1"),
     *      @OA\Property(property="mobileNumber", type="numeric", example="1321232123"),
     *      @OA\Property(property="email", type="email", example="john@silknet.com"),
     *      @OA\Property(property="customer_firstName", type="string", example="john"),
     *      @OA\Property(property="customer_idNumber", type="numeric", example="123321123231"),
     *      @OA\Property(property="customer_birthDate", type="date", example="1980-10-10"),
     *      @OA\Property(property="customer_localCitizen", type="boolean", example="true"),
     *      @OA\Property(property="customer_customerTypeId", type="numeric", example="1"),
     *      @OA\Property(property="customer_genderId", type="numeric", example="1"),
     *      @OA\Property(property="cityId", type="numeric", example="1"),
     *      @OA\Property(property="street", type="string", example="Rustaveli 23"),
     *      @OA\Property(property="juridicalAddress", type="string", example="Rustaveli 23"),
     *      @OA\Property(property="products", type="object", example="[...]"),
     *      @OA\Property(property="installAmount", type="numeric", example="5"),
     *      @OA\Property(property="totalPrice", type="numeric", example="45"),
     *  ),),
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function packagePurchase(Request $request){
        //dd($request);
        try{
            $validator = \Validator::make($request->all(), [
                'smsId' => ['required'],
                'smsCode' => ['required'],
                'technologyId'=>['required'],
                'serviceType'=>['required','numeric'],
                'serviceSubType'=>['required','numeric'],
                'mobileNumber'=>['required'],
                'phoneNumber' =>['nullable'],
                'email'=>['nullable'],
                //'personName'=>['required'],
                'customer_firstName'=>['required'],
                //'customer_lastName'=>['required'],
                'customer_idNumber'=>['required'],
                'customer_birthDate'=>['required','date_format:Y-m-d'],
                'customer_localCitizen'=>['required','boolean'],
                'customer_customerTypeId'=>['required','numeric'],
                'customer_genderId'=>['required','numeric'],
                'cityId'=>['required','numeric'],
                'street'=>['required'],
//                'floor'=>['required'],
//                'appartment'=>['required'],
                'juridicalAddress'=>['nullable'],
                'products'=>['required'],
                //'productName'=>['required'],
                //'packageName'=>['required'],
                //'pointCount'=>['required',"numeric"],
                'installAmount '=>['nullable',"numeric"],
                'totalPrice'=>['required',"numeric"]
            ]);



            if ($validator->fails()) {
                return response(['success'=>false,'message'=>$validator->errors()->first()]);
            }
//            DB::enableQueryLog();
//            $smsCode=\DB::table('smsCodes')->where('date','>',(new \DateTime)->modify('-60 minutes'))->where('id','=',$request->smsId) ->first();
            $smsCode=\DB::table('smsCodes')->where('date','>','NOW()-INTERVAL 60 MINUTE')->where('id','=',$request->smsId) ->first();
//            p(DB::getQueryLog());


            if (!$smsCode || $request->smsCode!=$smsCode->code){
                return response(['success'=>false,'message'=>'incorrect sms code']);
            }


            ////////// generate addresses and cities
            ////////// generate addresses and cities
            $taxonomyModel = new TaxonomyModel();
            $tax = $taxonomyModel->getOne(['id'=>$request->cityId]);
            $legalCityId = $selectedCityId = _cv($tax,['silk_installation_city_id']);
            $legalAddress = $address = "Street: {$request->street}, Entrance: {$request->entrance}, Floor: {$request->floor}, Appartment: {$request->appartment}";

            /// if there is same legal address
            if(is_array($request->legalAddress) && array_search('same', $request->legalAddress) === false){

                if(is_numeric($request->cityIdJuridical)){
                    $tax2 = $taxonomyModel->getOne(['id'=>$request->cityIdJuridical]);
                    $legalCityId = _cv($tax2, ['silk_installation_city_id']);
                }
                $legalAddress = "Street: {$request->streeti}, Entrance: {$request->entrancei}, Floor: {$request->floori}, Appartment: {$request->appartmenti}";

            }
            ///////////////// END generate addressees and cities

            $date=new \DateTime();
            $body=[
                "payment_status" =>0,
                "uniqueId" =>"",
                "createDate"=>$date->format('Y-m-d H:i:s'),
                "serviceType"=>$request->serviceType,
                "serviceSubType"=>$request->serviceSubType,
                //"portingFrom"=>1,
                //"portingTo"=>7,
                //"promotype"=>0,
                "phoneNumbers"=>[$request->mobileNumber],
                "bookingUniqueId"=>'',
                "bankTransactionResult"=>false,
                //"payedAmount"=>$totalPrice,
                "language"=>"ka",
                "clientIpAddress"=>$request->ip(),
                "comment"=>"",
                "technologyId"=>$request->technologyId,
                //"directorName"=>"",
                //"directorIdNumber"=>"",
                //"googleMapUrl"=>"http://",
                //"cadastralCode"=>"",
                  "customer"=>[
                  "idNumber"=>$request->customer_idNumber,
                  "firstName"=>$request->customer_firstName,
//                  "lastName"=>"",
                  "birthDate"=>$request->customer_birthDate,
                  "localCitizen"=>(boolean)$request->customer_localCitizen,
                  "customerTypeId"=>$request->customer_customerTypeId,
                  "genderId"=>$request->customer_genderId,
                  "contact"=>[
                      "personName"=>"",
                      "phoneNumber"=>$request->phoneNumber,
                      "mobileNumber"=>$request->mobileNumber,
                      "email"=>$request->email
                      ],
                      "address"=>[
                        "cityId"=>$selectedCityId,
                        "street"=>$address,
                      ],
                      "juridicalAddress"=>[
                          "cityId"=>$legalCityId,
                          "street"=>$legalAddress
                      ]
                    ],
                    "products"=>$request->products,

                  "installAmount"=>$request->installAmount,
                  "totalAmount"=>$request->totalPrice,
              ];

//            p($body); return response([]);

            $id = \DB::table('silk_api_processBooking')->insertGetId( [ 'date' => $date->format('Y-m-d H:i:s')] );

            $body["uniqueId"]=$id;
            $body["bookingUniqueId"] = str_pad($id, 28, "0", STR_PAD_LEFT);

            \DB::table('silk_api_processBooking')
                    ->where('id', $id)
                    ->update(['data' => json_encode($body),'bookingUniqueId'=>$body["bookingUniqueId"]]);

            //echo(json_encode($body,JSON_PRETTY_PRINT));
            //exit;

            $data=$this->_post('/rest/connect/process-booking',$body);

            \DB::table('silk_api_processBooking')
                    ->where('id', $id)
                    ->update(['statusData' => json_encode($data), 'service_send_status'=>1]);



            //dd($data);
            return response([
                'success'=>$data->success,'message'=>$data->message,
            ]);


        }catch(\Exception $ex){
            return response(['success'=>false,'message'=>$ex->getMessage()]);
        }

    }


    public function colectAndsendUnsentBookings(){

        $trayAmounts = 10;
        $bookings = \DB::table('silk_api_processBooking')->whereRaw("service_send_status_try < {$trayAmounts} AND service_send_status != 1 AND bookingUniqueId !='' ")->limit(50)->get();

        foreach ($bookings as $k=>$v){
            $this->sendUnsendBookings(['bookingUniqueId'=>$v->bookingUniqueId]);
        }

        return response([]);

    }

    /// send all unsend datas to silk booking service
    public function sendUnsendBookings($params = []){
        $bookingUniqueId = _cv($params, ['bookingUniqueId']);
        if(!$bookingUniqueId)return false;

        /// select transaction from db
        $db=\DB::table('silk_api_processBooking')->where('bookingUniqueId','=',$bookingUniqueId);
        $row = $db->first();

//p($row);
        if(!$row)return false;

        $body = _psqlCell($row->data);
//        $body['bookingUniqueId'] = str_pad(rand(10000, 99999), 28, '0'); /// for testing
//p($body);
        /// if transaction already send do nothing
        if($row->service_send_status==1)return false;

        if(_cv($body,'totalAmount')>0){
            $checkTransactionStatus = $this->_get("/rest/ufc-payment/check-transaction-status?transactionId={$bookingUniqueId}&language=EN");

            /// set last status for transaction
            if(!$checkTransactionStatus->success) {
                $db->update(['success' => false, 'statusData' => _psqlupd(_toArray($checkTransactionStatus))]);
            }else{
                $db->update(['success' => true, 'statusData' => _psqlupd(_toArray($checkTransactionStatus))]);
            }
        }

        $service_send_status_try = $row->service_send_status_try+1;
        $db->update(['service_send_status_try'=>$service_send_status_try]);

        $silkResponse = $this->_post('/rest/connect/process-booking', $body);

        $db->update(['silk_response'=>_psqlupd(_toArray($silkResponse))]);

        if(_cv($silkResponse, 'success')=='1'){
            $db->update(['service_send_status'=>1]);
        }


        return false;


    }


    /// payment callback. commit payment and send to silknet
    public function portingCallback(Request $request){
        $bookingUniqueId = $request->transactionId;
        if(!$bookingUniqueId){
            return redirect('../');
        }

        $data = $this->_get("/rest/ufc-payment/check-transaction-status?transactionId={$bookingUniqueId}&language=EN");
//p($data);
        $db=\DB::table('silk_api_processBooking')->where('bookingUniqueId','=',$bookingUniqueId);

        $db->update(['statusData' => json_encode($data)]);

        /// check transaction status
        if(!$data->success){
            $db->update(['success'=>false]);
//            return 444444;
            return redirect('../online-services?success=false');
        };

        /// select transaction from db and check if exists
        $row = $db->first();
        if (!$row){
            $db->update(['success'=>false]);
//            return 3333333;
            return redirect('../online-services?success=false');
        }

        $body = json_decode($row->data);

        $db->update(['statusData' => json_encode($data)]);

        if(!$data->success){
            $db->update(['success'=>false]);
            //როლბექი
            $this->_post("/rest/ufc-payment/rollback-transaction?transactionId={$bookingUniqueId}&amount={$body->totalAmount}&language=EN",[]);
//            return 2222222;
            return redirect('../?success=false');
        }

        /**** /
        //პრეავტორიზაციის კომიტი
        $url = "/rest/ufc-payment/pre-authorization-commit?transactionId={$bookingUniqueId}&amount={$body->totalAmount}&currency=GEL&language=GE&description=&messageType=SMS&ipAddress={$request->ip()}";
        $data = $this->_post($url,[]);
        //p($data);
        $db->update(['commitStatusData' => json_encode($data)]);

        if(!$data->success){
            $db->update(['success'=>false]);
//            return 1111;
            return redirect('../?success=false');
        }
        /****/
        /// if transaction is success, set payment_status = 1 and send data to silknet
        $body->payment_status = 1;
        $body->bankTransactionResult = true;

        $db->update(['success'=>true, 'data'=>_psqlupd(_toArray($body))]);
        $data = $this->_post('/rest/connect/process-booking', $body);

        if(_cv($data, 'success')=='1'){
            $db->update(['service_send_status'=>1]); //'silk_response'=>json_encode($data)
        }

//        p(_toArray($data));
//        return '';

        return redirect('../?success=true');
    }

    /**
     * @OA\Post( path="/view/services/_porting", tags={"Public website Services"}, summary="სიმის პორტირების მოთხოვნა", operationId="_porting",
     *   @OA\Parameter( name="request params", description="request params", required=true, in="path",
     *     @OA\Schema(
     *      @OA\Property(property="portingFrom", type="string", example="beeline"),
     *      @OA\Property(property="promotype", type="numeric", example="2"),
     *      @OA\Property(property="idNumber", type="numeric", example="12332115661"),
     *      @OA\Property(property="firstName", type="string", example="john"),
     *      @OA\Property(property="birthDate", type="date", example="1980-10-10"),
     *      @OA\Property(property="cityId", type="numeric", example="1"),
     *      @OA\Property(property="street", type="string", example="Rustaveli 23"),
     *      @OA\Property(property="email", type="email", example="john@silknet.com"),
     *      @OA\Property(property="passportFileId", type="string", example="2"),
     *      @OA\Property(property="serviceSubType", type="numeric", example="2"),
     *      @OA\Property(property="trIdNumber", type="numeric", example="2"),
     *      @OA\Property(property="trFirstName", type="string", example="john"),
     *      @OA\Property(property="trDocumentNumber", type="numeric", example="32112332112"),
     *      @OA\Property(property="trDocumentIndex", type="numeric", example="9877899878"),
     *      @OA\Property(property="comment", type="string", example="any comment"),
     *  ),),
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function _porting(Request $request){
        try{
                $validator = \Validator::make($request->all(), [
//                    'phoneNumber' =>['required'],
                    'portingFrom'=>['required'],
                    'promotype'=>['required','numeric'],
                    'idNumber'=>['nullable'],
                    'firstName'=>['nullable'],
                    'birthDate'=>['nullable','date_format:Y-m-d'],
                    'cityId'=>['required','numeric'],
                    'address'=>['required'],
                    'email'=>['email'],
//                    'altPhoneNumber'=>['required'],
                    'passportFileId'=>['nullable'],
                    'serviceSubType'=>['required'],
                    'trIdNumber'=>['nullable'],
                    'trFirstName'=>['nullable'],
                    'trDocumentNumber'=>['nullable'],
                    'trDocumentIndex'=>['nullable'],
                    'comment'=>['nullable']
                ]);

                if ($validator->fails()) {
                    return response(['success'=>false,'message'=>$validator->errors()->first()]);
                }

                $tax = new TaxonomyModel();
                $tax = $tax->getOne(['id'=>$request->cityId]);

            //dd($tax);
            $deliveryprice_porting_price = _cv($tax,['deliveryprice_porting'], 'nn')?$tax['deliveryprice_porting']:0;
            $deliveryprice_porting_price = $deliveryprice_porting_price*100;
                //$deliveryprice_porting_price=10;
                //dd($deliveryprice_porting_price);
            $paymentStatus = $deliveryprice_porting_price == 0?0:-1;
            $bankTransactionResult = $deliveryprice_porting_price == 0?true:false;

                $date=new \DateTime();
                $body=[
                    "bankTransactionResult" =>$bankTransactionResult,
                    "payment_status" =>$paymentStatus,
                    "uniqueId" =>"",
                    "createDate"=>$date->format('Y-m-d H:i:s'),
                    "serviceType"=>3, //პორტირება
                    "serviceSubType"=>$request->serviceSubType,
                    "portingFrom"=>$request->portingFrom,
                    "portingTo"=>8,
                    "promoType"=>$request->promotype,
                    "phoneNumbers"=>[],
                    "bookingUniqueId"=>'',
                    "payedAmount"=>$deliveryprice_porting_price,
                    "language"=>"ka",
                    "clientIpAddress"=>$request->ip(),
                    "comment"=>$request->comment,
                    "technologyId"=>51, //მობილური
                    //"directorName"=>"",
                    //"directorIdNumber"=>"",
                    //"googleMapUrl"=>"http://",
                    //"cadastralCode"=>"",
                      "customer"=>[
                      "idNumber"=>$request->idNumber,
                      "firstName"=>$request->firstName,
                      "lastName"=>"",
                      "birthDate"=>$request->birthDate,
                      "localCitizen"=>true,
                      "customerTypeId"=>40, //ფიზიკური პირი
                      "genderId"=>42, //მამრობითი
                      "contact"=>[
                          "personName"=>"",
                          "phoneNumber"=>$request->altPhoneNumber,
                          "mobileNumber"=>$request->phoneNumber,
                          "email"=>$request->email
                          ],
                          "address"=>[
                          "cityId"=>_cv($tax,['silk_id']),
                          "street"=>$request->address,
                          "floor"=>"",
                          "appartment"=>""
                          ],
                          "juridicalAddress"=>[
                              "street"=>$request->juridicalAddress
                          ]
                          ],
                        "products"=>[],

                      "totalAmount"=>$deliveryprice_porting_price,
                      "trustedPerson"=>[
                          "idNumber"=>$request->trIdNumber,
                          "firstName"=>$request->trFirstName,
                      //    "lastName"=>$request->trLastName,
                          "documentNumber"=>$request->trDocumentNumber,
                          "documentIndex"=>$request->trDocumentIndex,
                      ],
//                    "files"=>[
//                        "file1"=>_cv($request->passportFileId, '0'),
//                        "file2"=>_cv($request->passportFileId, '1'),
//                    ],
                    "files"=>[
                        "file1"=>$this->getImageUrl(_cv($request->passportFileId, '0'), 'url'),
                        "file2"=>$this->getImageUrl(_cv($request->passportFileId, '1'), 'url'),
                    ],
                      //"other"=>[
                      //"last3CallNumber1"=>"",
                      //"last3CallNumber2"=>"",
                      //"last3CallNumber3"=>"",
                      //"lastPayedAmount"=>0
                      //]
                  ];

//                p($body); //return response([]);

                  $id = \DB::table('silk_api_processBooking')->insertGetId( [ 'date' => $date->format('Y-m-d H:i:s')] );

                 $body["uniqueId"]=$id;
                if ($deliveryprice_porting_price>0){

                    $callbackUrl=urlencode(route('portingCallback'));
                    $description=Str::slug(urlencode('balansis shevseba'), '-');
                    $url="/rest/ufc-payment/pre-authorization?amount={$deliveryprice_porting_price}&currency=GEL&ipAddress={$request->ip()}&language=GE&description={$description}&messageType=SMS&callbackUrl={$callbackUrl}";
                    //dd($url);
                    $data=$this->_post($url,[]);
                    if (!$data->success) throw new Exception($data->message);
                    $bookingUniqueId=$data->data->transactionId;
                    $submitHtml=$data->data->submitHtml;
                    $body["bookingUniqueId"]=$bookingUniqueId;

                    \DB::table('silk_api_processBooking')->where('id', $id)
                    ->update(['statusData' => json_encode($data),'data' => json_encode($body),'bookingUniqueId'=>$body["bookingUniqueId"]]);

                    return response(['success'=>$data->success,'message'=>$data->message,'submitHtml'=>$submitHtml]);

                }else{

                    $body["bookingUniqueId"] = str_pad($id, 28, "0", STR_PAD_LEFT);
                    \DB::table('silk_api_processBooking')->where('id', $id)
                    ->update(['data' => json_encode($body),'bookingUniqueId'=>$body["bookingUniqueId"]]);

                    $data = $this->_post('/rest/connect/process-booking',$body);
//p($data);
                    \DB::table('silk_api_processBooking')->where('id', $id)
                            ->update(['statusData' => json_encode($data), 'service_send_status'=>1]);

//                    return response([]);
                    return response(['success'=>$data->success,'message'=>$data->message]);
                }
        }catch(\Exception $ex){
            return response(['success'=>false,'message'=>$ex->getMessage()]);
        }
    }

    /**
     * @OA\Post( path="/view/services/_changeSim", tags={"Public website Services"}, summary="სიმის ცვლილების მოთხოვნა", operationId="_changeSim",
     *   @OA\Parameter( name="request params", description="request params", required=true, in="path",
     *     @OA\Schema(
     *      @OA\Property(property="phoneNumber", type="numeric", example="593648778"),
     *      @OA\Property(property="idNumber", type="numeric", example="12332115661"),
     *      @OA\Property(property="firstName", type="string", example="john"),
     *      @OA\Property(property="birthDate", type="date", example="1980-10-10"),
     *      @OA\Property(property="cityId", type="numeric", example="1"),
     *      @OA\Property(property="address", type="string", example="Rustaveli 23"),
     *      @OA\Property(property="email", type="email", example="john@silknet.com"),
     *      @OA\Property(property="passportFileId", type="string", example="2"),
     *      @OA\Property(property="serviceSubType", type="numeric", example="2"),
     *      @OA\Property(property="trIdNumber", type="numeric", example="2"),
     *      @OA\Property(property="trFirstName", type="string", example="john"),
     *      @OA\Property(property="trDocumentNumber", type="numeric", example="32112332112"),
     *      @OA\Property(property="trDocumentIndex", type="numeric", example="9877899878"),
     *      @OA\Property(property="comment", type="string", example="any comment"),
     *  ),),
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function _changeSim(Request $request){
        try{
            $validator = \Validator::make($request->all(), [
                'phoneNumber' =>['required'],
//                'portingFrom'=>['required'],
//                'promotype'=>['required','numeric'],
                'idNumber'=>['nullable'],
                'firstName'=>['nullable'],
                'birthDate'=>['nullable','date_format:Y-m-d'],
                'cityId'=>['required','numeric'],
                'address'=>['required'],
                'email'=>['email'],
//                'altPhoneNumber'=>['required'],
                'passportFileId'=>['nullable'],
                'serviceSubType'=>['required'],
                'trIdNumber'=>['nullable'],
                'trFirstName'=>['nullable'],
                'trDocumentNumber'=>['nullable'],
                'trDocumentIndex'=>['nullable'],
                'comment'=>['nullable']
            ]);

            if ($validator->fails()) {
                return response(['success'=>false,'message'=>$validator->errors()->first(), 'allMesages'=>$validator->errors()]);
            }

            $tax = new TaxonomyModel();
            $tax = $tax->getOne(['id'=>$request->cityId, 'sssgetByMeta'=>['silk_id'=>$request->cityId]]);

            $simChangePrice = is_numeric($request->simChangePrice)?$request->simChangePrice:0;
            $deliveryprice = _cv($tax, ['deliveryprice_cardchange'], 'nn')?$tax['deliveryprice_cardchange']:0;

            $deliveryprice += $simChangePrice;
            $deliveryprice = $deliveryprice*100;

            $paymentStatus = $deliveryprice == 0?0:-1;
            $bankTransactionResult = $deliveryprice == 0?true:false;


            $date=new \DateTime();
            $body=[
                "payment_status" =>$paymentStatus,
                "bankTransactionResult" =>$bankTransactionResult,
                "uniqueId" =>"",
                "createDate"=>$date->format('Y-m-d H:i:s'),
                "serviceType"=>1, //სიმის შეცვლა
                "serviceSubType"=>$request->serviceSubType,
//                "portingFrom"=>$request->portingFrom,
//                "portingTo"=>7,
                "promotype"=>$request->promotype,
                "phoneNumbers"=>[],
                "bookingUniqueId"=>'',
                "payedAmount"=>$deliveryprice,
                "language"=>"ka",
                "clientIpAddress"=>$request->ip(),
                "comment"=>$request->comment,
                "technologyId"=>51, //მობილური
                //"directorName"=>"",
                //"directorIdNumber"=>"",
                //"googleMapUrl"=>"http://",
                //"cadastralCode"=>"",
                "customer"=>[
                    "idNumber"=>$request->idNumber,
                    "firstName"=>$request->firstName,
                    "lastName"=>"",
                    "birthDate"=>$request->birthDate,
                    "localCitizen"=>true,
                    "customerTypeId"=>40, //ფიზიკური პირი
                    "genderId"=>42, //მამრობითი
                    "contact"=>[
                        "personName"=>"",
                        "phoneNumber"=>$request->altPhoneNumber,
                        "mobileNumber"=>$request->phoneNumber,
                        "email"=>$request->email
                    ],
                    "address"=>[
                        "cityId"=>_cv($tax,['silk_id']),
                        "street"=>$request->address,
                        "floor"=>"",
                        "appartment"=>""
                    ],
                    "juridicalAddress"=>[
                        "street"=>$request->juridicalAddress
                    ]
                ],
                "products"=>[],

                "totalAmount"=>$deliveryprice,
                "trustedPerson"=>[
                    "idNumber"=>$request->trIdNumber,
                    "firstName"=>$request->trFirstName,
                    //    "lastName"=>$request->trLastName,
                    "documentNumber"=>$request->trDocumentNumber,
                    "documentIndex"=>$request->trDocumentIndex,
                ],
//                "files"=>[
//                    "file1"=>_cv($request->passportFileId, '0'),
//                    "file2"=>_cv($request->passportFileId, '1'),
//                ],
                "files"=>[
                    "file1"=>$this->getImageUrl(_cv($request->passportFileId, '0'), 'url'),
                    "file2"=>$this->getImageUrl(_cv($request->passportFileId, '1'), 'url'),
                ],
                //"other"=>[
                //"last3CallNumber1"=>"",
                //"last3CallNumber2"=>"",
                //"last3CallNumber3"=>"",
                //"lastPayedAmount"=>0
                //]
            ];
//            p($body);
//            return response ([]);

            $id = \DB::table('silk_api_processBooking')->insertGetId( [ 'date' => $date->format('Y-m-d H:i:s')]);

            $body["uniqueId"]=$id;

            if ($deliveryprice>0){

                $callbackUrl = urlencode(route('portingCallback'));
                $description = Str::slug(urlencode('baratis shecvla'), '-');
                $url="/rest/ufc-payment/pre-authorization?amount={$deliveryprice}&currency=GEL&ipAddress={$request->ip()}&language=GE&description={$description}&messageType=SMS&callbackUrl={$callbackUrl}";
                //dd($url);
                $data = $this->_post($url,[]);
                if (!$data->success) throw new Exception($data->message);
                $bookingUniqueId=$data->data->transactionId;
                $submitHtml=$data->data->submitHtml;
                $body["bookingUniqueId"]=$bookingUniqueId;

                \DB::table('silk_api_processBooking')->where('id', $id)
                    ->update(['statusData' => json_encode($data),'data' => json_encode($body),'bookingUniqueId'=>$body["bookingUniqueId"]]);

                return response(['success'=>$data->success,'message'=>$data->message,'submitHtml'=>$submitHtml]);

            }else{
                $body["bookingUniqueId"]=str_pad($id, 28, "0", STR_PAD_LEFT);
                \DB::table('silk_api_processBooking')->where('id', $id)
                    ->update(['data' => json_encode($body),'bookingUniqueId'=>$body["bookingUniqueId"]]);

                $data = $this->_post('/rest/connect/process-booking',$body);

                \DB::table('silk_api_processBooking')->where('id', $id)
                    ->update(['statusData' => json_encode($data), 'service_send_status'=>1]);

                return response(['success'=>$data->success,'message'=>$data->message]);
            }
        }catch(\Exception $ex){
            return response(['success'=>false,'message'=>$ex->getMessage()]);
        }
    }

    /**
     * @OA\Post( path="/view/services/_buyNewSim", tags={"Public website Services"}, summary="სიმის შეძენის მოთხოვნა", operationId="_buyNewSim",
     *   @OA\Parameter( name="request params", description="request params", required=true, in="path",
     *     @OA\Schema(
     *      @OA\Property(property="choosenNumbers", type="object", example="[593648778]"),
     *      @OA\Property(property="idNumber", type="numeric", example="12332115661"),
     *      @OA\Property(property="firstName", type="string", example="john"),
     *      @OA\Property(property="birthDate", type="date", example="1980-10-10"),
     *      @OA\Property(property="cityId", type="numeric", example="1"),
     *      @OA\Property(property="address", type="string", example="Rustaveli 23"),
     *      @OA\Property(property="email", type="email", example="john@silknet.com"),
     *      @OA\Property(property="passportFileId", type="string", example="2"),
     *      @OA\Property(property="serviceSubType", type="numeric", example="2"),
     *      @OA\Property(property="trIdNumber", type="numeric", example="2"),
     *      @OA\Property(property="trFirstName", type="string", example="john"),
     *      @OA\Property(property="trDocumentNumber", type="numeric", example="32112332112"),
     *      @OA\Property(property="trDocumentIndex", type="numeric", example="9877899878"),
     *      @OA\Property(property="comment", type="string", example="any comment"),
     *  ),),
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function _buyNewSim(Request $request){
        try{
            $validator = \Validator::make($request->all(), [
//                'phoneNumbers' =>['required'],
//                'portingFrom'=>['required'],
//                'promotype'=>['required','numeric'],
                'choosenNumbers'=>['required'],
                'idNumber'=>['nullable'],
                'firstName'=>['nullable'],
                'birthDate'=>['nullable','date_format:Y-m-d'],
                'cityId'=>['required','numeric'],
                'address'=>['required'],
                'email'=>['email'],
//                'altPhoneNumber'=>['required'],
                'passportFileId'=>['nullable'],
                'serviceSubType'=>['required'],
                'trIdNumber'=>['nullable'],
                'trFirstName'=>['nullable'],
                'trDocumentNumber'=>['nullable'],
                'trDocumentIndex'=>['nullable'],
                'comment'=>['nullable']
            ]);

            if ($validator->fails()) {
                return response(['success'=>false,'message'=>$validator->errors()->first(), 'allMesages'=>$validator->errors()]);
            }

            /// ნომრების ფასი მოდის თეთრებში. ამიტომ თეთრებში გადაყვანა სჭირდება მხოლოდ მიწოდების ფასს
            $phoneNumbers = [];
            $phoneNumbersPriceAmount = 0;
            foreach ($request->choosenNumbers as $v){
                if(!_cv($v, ['phoneNumber']))continue;
                $phoneNumbers[] = $v['phoneNumber'];
                $phoneNumbersPriceAmount += $v['price'];
            }


            $tax = new TaxonomyModel();
            $tax = $tax->getOne(['id'=>$request->cityId]);

            $deliveryprice = _cv($tax, ['deliveryprice_buynumber'], 'nn')?($tax['deliveryprice_buynumber']*100):0;
            $deliveryprice += $phoneNumbersPriceAmount;

            $date=new \DateTime();

            $paymentStatus = $deliveryprice == 0?0:-1;
            $bankTransactionResult = $deliveryprice == 0?true:false;
            $body=[
                "payment_status" =>$paymentStatus,
                "bankTransactionResult" =>$bankTransactionResult,
                "uniqueId" =>"",
                "createDate"=>$date->format('Y-m-d H:i:s'),
                "serviceType"=>2, //სიმის ყიდვა
                "serviceSubType"=>$request->serviceSubType,
//                "portingFrom"=>$request->portingFrom,
//                "portingTo"=>7,
                "promotype"=>$request->promotype,
                "phoneNumbers"=>$phoneNumbers,
                "bookingUniqueId"=>'',
                "payedAmount"=>$deliveryprice,
                "language"=>"ka",
                "clientIpAddress"=>$request->ip(),
                "comment"=>$request->comment,
                "technologyId"=>51, //მობილური
                //"directorName"=>"",
                //"directorIdNumber"=>"",
                //"googleMapUrl"=>"http://",
                //"cadastralCode"=>"",
                "customer"=>[
                    "idNumber"=>$request->idNumber,
                    "firstName"=>$request->firstName,
                    "lastName"=>"",
                    "birthDate"=>$request->birthDate,
                    "localCitizen"=>$request->localCitizen,
                    "customerTypeId"=>40, //ფიზიკური პირი
                    "genderId"=>42, //მამრობითი
                    "contact"=>[
                        "personName"=>"",
                        "phoneNumber"=>$request->altPhoneNumber,
                        "mobileNumber"=>$request->phoneNumber,
                        "email"=>$request->email
                    ],
                    "address"=>[
                        "cityId"=>_cv($tax,['silk_id']), //$request->cityId,
                        "street"=>$request->address,
                        "floor"=>"",
                        "appartment"=>""
                    ],
                    "juridicalAddress"=>[
                        "street"=>$request->juridicalAddress
                    ]
                ],
                "products"=>[],
                "totalAmount"=>$deliveryprice,
                "trustedPerson"=>[
                    "idNumber"=>$request->trIdNumber,
                    "firstName"=>$request->trFirstName,
                    //    "lastName"=>$request->trLastName,
                    "documentNumber"=>$request->trDocumentNumber,
                    "documentIndex"=>$request->trDocumentIndex,
                ],
//                "files"=>[
//                    "file1"=>_cv($request->passportFileId, '0'),
//                    "file2"=>_cv($request->passportFileId, '1'),
//                ],
                "files"=>[
                    "file1"=>$this->getImageUrl(_cv($request->passportFileId, '0'), 'url'),
                    "file2"=>$this->getImageUrl(_cv($request->passportFileId, '1'), 'url'),
                ],
            ];


            $id = \DB::table('silk_api_processBooking')->insertGetId( [ 'date' => $date->format('Y-m-d H:i:s')]);

            $body["uniqueId"] = $id;

//            $data = $this->_post('/rest/connect/book-phone-numbers', ['bookingUniqueId'=>$body["uniqueId"], 'phoneNumbers'=>$phoneNumbers]);


            if ($deliveryprice>0){
//                $deliveryprice = 1;
                $callbackUrl = urlencode(route('portingCallback'));
                $description = Str::slug(urlencode('baratis shedzena'), '-');
                $url="/rest/ufc-payment/pre-authorization?amount={$deliveryprice}&currency=GEL&ipAddress={$request->ip()}&language=GE&description={$description}&messageType=SMS&callbackUrl={$callbackUrl}";
                //dd($url);
                $data = $this->_post($url,[]);

                if (!$data->success) throw new Exception($data->message);
                $bookingUniqueId = $data->data->transactionId;
                $bookPhoneNumbers = $this->_post('/rest/connect/book-phone-numbers', ['bookingUniqueId'=>$bookingUniqueId, 'phoneNumbers'=>$phoneNumbers]);

                $submitHtml=$data->data->submitHtml;
                $body["bookingUniqueId"]=$bookingUniqueId;

                \DB::table('silk_api_processBooking')
                    ->where('id', $id)
                    ->update(['statusData' => json_encode($data),'data' => json_encode($body),'bookingUniqueId'=>$body["bookingUniqueId"]]);

//                return response([]);
                return response(['success'=>$data->success,'message'=>$data->message,'submitHtml'=>$submitHtml]);

            }else{
                $body["bookingUniqueId"]=str_pad($id, 28, "0", STR_PAD_LEFT);
                $bookPhoneNumbers = $this->_post('/rest/connect/book-phone-numbers', ['bookingUniqueId'=>$body["bookingUniqueId"], 'phoneNumbers'=>$phoneNumbers]);

                \DB::table('silk_api_processBooking')
                    ->where('id', $id)
                    ->update(['data' => json_encode($body),'bookingUniqueId'=>$body["bookingUniqueId"]]);

                $data = $this->_post('/rest/connect/process-booking',$body);

                \DB::table('silk_api_processBooking')
                    ->where('id', $id)
                    ->update(['statusData' => json_encode($data)]);

                return response(['success'=>$data->success,'message'=>$data->message]);
            }
        }catch(\Exception $ex){
            return response(['success'=>false,'message'=>$ex->getMessage(), 'catched'=>1]);
        }
    }

    private function getImageUrl($imageId = 0, $return = 'raw'){
        $mediaModel = new App\Models\Media\MediaModel();
        $ret = $mediaModel->getOne($imageId);

        if($return == 'raw')return $ret;
        if(_cv($ret, $return))return $ret[$return];
        return _cv($ret, 'id')?_cv($ret, 'id'):'';
    }

}
