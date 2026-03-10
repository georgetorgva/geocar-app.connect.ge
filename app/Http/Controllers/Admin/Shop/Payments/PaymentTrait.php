<?php

namespace App\Http\Controllers\Admin\Shop\Payments;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Validator;



trait PaymentTrait
{

    static function getConfigFields(){
        return self::$configs;
    }

    static function getRateFields(){
        return self::$rates;
    }

}
