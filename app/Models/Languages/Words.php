<?php

namespace App\Models\Languages;

use Illuminate\Database\Eloquent\Model;

class Words extends Model
{
    public $table = 'words';

    public $timestamps = false;
    protected $fillable = [
        'key', 'value'
    ];
    public $translatable = ['value'];
}
