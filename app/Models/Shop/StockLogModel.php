<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StockLogModel extends Model
{
    protected $table = 'shop_stock_logs';
    public $timestamps = true;

    //
    protected $allAttributes = [
        'id',
        'product_id',
        'created_at',
        'updated_at',
        'quantity',
        'date',
    ];
    protected $fillable = [
        'product_id',
        'quantity',
        'date',
        'status',
    ];
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];
    public function Insert($p_id, $quantity,$date){

        $db =  DB::table($this->table)->insertGetId(
            [
                'product_id' => $p_id,
                'quantity' => $quantity,
                'date' => $date,
                'status'=>1
            ]
        );
//        dd($db);
       return $this->GetOne($db);
    }
    public function GetOne($id){
        $db =  DB::table($this->table)->where('id', $id)->first();
        return $db;
    }
    public function getPages(){
        $db =  DB::table($this->table)->get();
        return $db;
    }


}
