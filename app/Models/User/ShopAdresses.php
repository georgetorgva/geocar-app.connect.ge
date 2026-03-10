<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use \Validator;

use Illuminate\Support\Facades\DB;

/**
 * main meta model for all standard meta tables
 */
class ShopAdresses extends Model
{
    //

    public $table = 'shop_addresses';
    public $timestamps = true;
    protected $error = false;

}
