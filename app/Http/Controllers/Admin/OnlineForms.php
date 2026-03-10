<?php

namespace App\Http\Controllers\Admin;

//use http\Exception;
use App\Rules\ReCaptcha;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use App;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Models\Admin\OptionsModel;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\OnlineFormsModel;
use Illuminate\Support\Facades\Validator;
use function PHPSTORM_META\type;

class OnlineForms extends App\Http\Controllers\Api\ApiController
{
    protected $mainModel;

    public function __construct()
    {
        $this->mainModel = new OnlineFormsModel();
    }

    static function saveForm($request = [])
    {
        unset($request['left-text']);
        unset($request['dynamicForm']);

        $model = new OnlineFormsModel();
        $model->upd($request);

        return response(['status' => 'success']);
    }

    public static function validateReCaptcha($recaptchaToken)
    {
        $response['success'] = true;

        $input = [
            'g-recaptcha-response' => $recaptchaToken
        ];

        $rules = [
            'g-recaptcha-response' => ['bail', 'required', new ReCaptcha]
        ];

        $validator = Validator::make($input, $rules);

        if ($validator->fails())
        {
            $response['success'] = false;
            $response['original'] = ['status' => 'error'];
            $response['message'] = $validator->errors();
        }

        return $response;
    }

    public function getList() : Response
    {
        $request = request();

        $res = $this->mainModel->getListBy( ['formType'=>$request->formType, 'pageNumber'=>$request->pageNumber, 'perPage'=>$request->perPage, 'search'=>$request->searchWord ] );

        $statusFilter = is_array($request->status) ? $request->status : [$request->status];

        $filteredList = array_filter($res['list'], function ($item) use ($statusFilter) {
           return in_array($item['status'], $statusFilter);
        });

        return response(array_values($filteredList));
    }

    public function getformtypes(){
        $ret = DB::table('forms')->whereIn('status', ['published', ''])->select('name')->groupBy('name')->get()->toArray();
        $ret = array_column($ret, 'name');
        return response($ret, 200);


    }


    public function deleteform(Request $request)
    {
        $res = DB::table('forms')->where('id', $request->id)->where('status', 'deleted')->delete();
        if ($res === 0) {
            DB::table('forms')->where('id', $request->id)->update([
                'status' => 'deleted',
            ]);
        }
        return response(['status' => 'deleted']);
    }
    public function restoreform(Request $request)
    {
        DB::table('forms')->where('id', $request->id)->update([
            'status' => 'published',
        ]);
        return response(['status' => 'published']);
    }

    public function validateForm($request = [], $validations = [] ){
        foreach($validations as $key => $validation){
            if($key == 'unique'){
                $ret = $this->duplicateForm($request, $validation);
            }
            return $ret;
        }
    }

    public function duplicateForm($request, $validations) {
        $validations = is_array($validations) ? $validations : [$validations];
        $errors = [];
        
        foreach ($validations as $validation) {
            if (array_key_exists($validation, $request)) {
                $exists = DB::table('forms')
                    ->where('name', $request['formType'])
                    ->where("data->$validation", $request[$validation])
                    ->exists();
                if ($exists) {
                    $errors[] = $validation;
                }
            }
        }
        
        if (!empty($errors)) {
            return ['status' => 'error', 'message' => 'already subscribed', 'errors' => $errors];
        }
        
        $ret = $this->saveForm($request);
        return ['status' => 'success', 'saveInfo' => $ret];
    }
}
