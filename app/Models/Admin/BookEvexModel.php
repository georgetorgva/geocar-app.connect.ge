<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use \Validator;
use Illuminate\Support\Facades\DB;

class BookEvexModel extends Model
{
    //
    protected $table = 'attribute';
    protected $metaTable = 'attribute_meta';
    public $timestamps = true;
    protected $error = false;
    protected $meta;

    //
    protected $allAttributes = [
        'id',
        'pid',
        'attribute',
        'count',
        'sort',
        'attr_relations',
        'created_at',
        'updated_at',
    ];
    protected $fillable = [
        'pid',
    ];
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];



}
