<?php

namespace App\Models\Shop;

use App\Models\Admin\OptionsModel;
use App\Models\Admin\SmartTableModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use \Validator;
use Illuminate\Support\Facades\DB;
use App\Models\Media\MediaModel;
use App\Models\Admin\MetaModel;

class AttributeModel extends SmartTableModel
{
    //
    protected $table = 'shop_attribute';
    protected $metaTable = 'shop_attribute_meta';
    protected $relationTable = '';
    protected $fieldConfigs = 'adminshop.attributes';


    public $timestamps = true;
    public $error = false;
    protected $meta;
    protected $locale;
    protected $locales;

    //

    protected $allAttributes = [
        'id',
        'pid',
        'slug',
        'attribute',
        'count',
        'sort',
        'conf',
        'service_id',
        'hierarchy_hash',
        'created_at',
        'updated_at',
    ];

    protected $fillable = [
        'pid', 'slug',  'hierarchy_hash', 'service_id', 'conf', 'sort', 'count', 'attribute',
    ];
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    function __construct()
    {
        parent::__construct();
        $this->meta = new MetaModel($this->metaTable);
        $this->locale = requestLan(); //config('app.locale');
        $this->locales = config('app.locales');

    }

    public function upd($data = [])
    {
        $attribute = _cv($data, 'attribute');
        $OptionsModel = new OptionsModel();
        $attrType = $OptionsModel->getOneBy(['id'=>$attribute]);

        $configs = config('adminshop.attributes');
        if(_cv($attrType, ['fields'])){
            foreach ($attrType['fields'] as $k=>$v){
                if(!_cv($v, 'name'))continue;
                $configs['fields'][$v['name']] = $v;
            }
        }
        $this->fieldConfigs = $configs;

//        p($data);
//        p($this->fieldConfigs);

        return $this->updItem($data);
    }


    public function deleteTerm($data = [])
    {
        if(!_cv($data, 'id', 'nn'))return false;
        $upd['pid'] = 0;
        AttributeModel::where('pid', $data['id'])->update($upd);
        AttributeModel::where('id', $data['id'])->delete();
        return $data['id'];
    }

    public function extractTranslated($data = [], $locale = ''){

        if(strlen($locale)!=2)return $data;

        if(!isset($data['metass']) || !is_array($data['metass']))return $data;

        foreach ($data['metass'] as $k=>$v){
            $translatable = substr($k, -3, 1);
            $translateLan = substr($k, -3);

            if($translatable === '_'){

                if($translateLan == "_{$locale}") {
                    $data[substr($k, 0,-3)] = $v;
                }

            }else{
                $data[$k] = $v;
            }


        }

        return $data;
    }

}


