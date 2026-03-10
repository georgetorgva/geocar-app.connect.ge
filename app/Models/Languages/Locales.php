<?php

namespace App\Models\Languages;

use Illuminate\Database\Eloquent\Model;

class Locales extends Model
{
    public $table = 'locales';
    public $timestamps = true;

    protected $fillable = [
        'active', 'default', 'name','locale'
    ];
}
