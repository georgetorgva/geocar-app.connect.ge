<?php

namespace App\Models\Media;

use App\Traits\ConnectTransformatorTrait;
use DateTimeInterface;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Spatie\MediaLibrary\Conversion\Conversion;
use Spatie\MediaLibrary\Conversion\ConversionCollection;
use Spatie\MediaLibrary\Filesystem\Filesystem;
use Spatie\MediaLibrary\HasMedia\HasMedia;
use Spatie\MediaLibrary\Helpers\File;
use Spatie\MediaLibrary\Helpers\TemporaryDirectory;
use Spatie\MediaLibrary\ImageGenerators\FileTypes\Image;
use Spatie\MediaLibrary\Models\Concerns\IsSorted;
use Spatie\MediaLibrary\Models\Media as SpatieMedia;
use Spatie\MediaLibrary\Models\Traits\CustomMediaProperties;
use Spatie\MediaLibrary\ResponsiveImages\RegisteredResponsiveImages;
use Spatie\MediaLibrary\UrlGenerator\UrlGeneratorFactory;
use Illuminate\Support\Facades\DB;

class MediaModel extends Model
{

    public $table = 'media'; /// or shopmedia
    public $fillable = ['name',
        'file_name',
        'mime_type',
        'disk',
        'size',
        'descriptions',
        'upload_form',
        'conf',];

    public function __construct($attributes = array())
    {

        parent::__construct($attributes);
        if(isset($attributes['table']))$this->table = $attributes['table'];
    }


    protected $transformFields = [
        'id' => 'id',
        'name' => 'name',
        'media_type' => 'mediaType',
        'size' => 'size',
        'file_name' =>'filename'
    ];

    public function upd($params = []){
        DB::enableQueryLog();
        $uploadForm = _cv($params, 'upload_form')?$params['upload_form']:'other';
        $path = _cv($params, 'path')?$params['path']:false;
        $disk = _cv($params, 'disk')?$params['disk']:'public';
//p($params);
//        print $path;
//        $url = Storage::url($path);
        $url = Storage::disk($disk)->url($path);

        if(!$url)return false;
        $size = Storage::disk($disk)->size($path);
        $mimeType = Storage::disk($disk)->mimeType($path);
//        $extension = \File::extension($path);
//        $extension = $ext = pathinfo($path, PATHINFO_EXTENSION);

        $basic = MediaModel::create([
            'upload_form'=> $uploadForm,
            'name'=> $path,
            'file_name'=> $url,
            'mime_type'=> $mimeType,
            'disk'=> $disk,
            'size'=> $size,
            'descriptions'=> '',
            'conf'=> '',
        ]);

        $basic->save();

//        p(DB::getQueryLog());


        return $basic->id;
    }

    public function updDescription($descriptions='', $id=''){

        $basic = MediaModel::find($id);

        if(!$basic)return false;

        $basic->descriptions = _psqlupd($descriptions);
        $basic->save();

        return $basic->id;
    }

    public function deleteMediaFile($file_id=''){
        if(!is_numeric($file_id))return false;

        $res = MediaModel::find($file_id);

        if(!isset($res->id))return false;

        Storage::disk($res->disk)->delete($res->name);

        $res = MediaModel::where('id',$file_id)->delete();

        return $res;
    }

    public function getOne( $id = '' ){
        $c = config('filesystems.disks.public.url');
        DB::enableQueryLog();


        $res = MediaModel::select(DB::raw("id, CONCAT('{$c}', name) as url, size, descriptions, conf, name, mime_type"))->where('id', $id)->first();

        $query = DB::getQueryLog();
//        p($query);
        if(!$res)return false;

        $res = _psqlRow(_toArray($res));


//        if(!_cv($res, 'id'))return false;



        if(!is_array($res['descriptions']))$res['descriptions'] = [];
        $res['title'] = isset($res['descriptions'][requestLan()])?$res['descriptions'][requestLan()]:current($res['descriptions']);

        /// if file is image get other dimensions for devices
        if(strpos($res['mime_type'], 'image/') !== false) $res['devices'] = $this->getDeviceImages($res);

        /// if exists desktop version use it for default one
        if(_cv($res, 'devices.desktop'))$res['url'] = $res['devices']['desktop'];

        return $res;
    }
    public function getIdByName( $name = '' ){

        $res = MediaModel::select('id')->where('name', $name)->first();

        if($res)return $res->id;

        return false;
    }

    public function getList( $params = [] ){

        $c = config('filesystems.disks.public.url');

        $res = MediaModel::select(DB::raw("id, CONCAT('{$c}', name) as url, size, descriptions, conf, name, mime_type"));
        if(_cv($params, 'ids', 'ar')){
            $params['ids'] = leaveOnlyNumbers($params['ids']);
            $ids_ordered = implode( ',', $params['ids']);

            $res->whereIn('id', $params['ids'])->orderByRaw("FIELD(id, $ids_ordered)");
        }

        $ret = $res->limit(100)->get(); //->keyBy('id');
        $ret = _psql(_toArray($ret));
//        p(array_column($ret, 'id'));
        foreach ($ret as $k=>$v){

            if(!is_array($ret[$k]['descriptions']))$ret[$k]['descriptions'] = [];
            $ret[$k]['title'] = isset($ret[$k]['descriptions'][requestLan()]) ? $ret[$k]['descriptions'][requestLan()] : current($ret[$k]['descriptions']);

            /// if file is image get other dimensions for devices
            if(strpos($ret[$k]['mime_type'], 'image/') !== false) $ret[$k]['devices'] = $this->getDeviceImages($ret[$k]);

            /// if exists desktop version use it for default one
            if(_cv($ret[$k], 'devices.desktop')){
                $ret[$k]['url'] = $ret[$k]['devices']['desktop'];
            }

        }

        if(_cv($params, 'idAsKey')){
            $rett = [];
            foreach ($ret as $k=>$v){
                $rett[$v['id']] = $v;
            }
            $ret = $rett;
        }
//p($ret);
//        $c = config('filesystems.disks.public.url');
        return $ret;
    }

    /**
     * get responsive optimized images
     * if not found some size returns default one
     */
    public function getDeviceImages($data){
        $path = config('filesystems.disks.public'); //root, url
        $url = $path['url'];
//        p($path);
        $ret = [];

        $ret['desktop'] = is_file($path['root'].'/desktop/'.$data['name'])?$url.'desktop/'.$data['name']:$url.$data['name'];
        $ret['tablet'] = is_file($path['root'].'/tablet/'.$data['name'])?$url.'tablet/'.$data['name']:$url.$data['name'];
        $ret['mobile'] = is_file($path['root'].'/mobile/'.$data['name'])?$url.'mobile/'.$data['name']:$url.$data['name'];

        return $ret;

    }

    public function getLibraryList( $params = [] ){
//p($params);

        $fileTypes = ['image'=>'image', 'file'=>'file', 'audio'=>'audio', 'video'=>'video'];

        $mimeType = _cv($fileTypes, $params['mimeType']);
        if(!$mimeType)return false;

        $perpage = _cv($params, 'perPage', 'nn')?$params['perPage']:18;
        $orderField = _cv($params, 'orderField')?$params['orderField']:'id';
        $orderDirection = _cv($params, 'orderDirection')?$params['orderDirection']:'desc';

        $c = config('filesystems.disks.public.url');

        $res = MediaModel::select(DB::raw("id, CONCAT('{$c}', name) as url, size, descriptions, conf, name, mime_type"));
        if(_cv($params, 'mimeType')){
            $res->where('mime_type', 'like', "{$mimeType}/%");
        }

        if(_cv($params, 'uploadForm')){
            $res->where('upload_form', $params['uploadForm']);
        }

        if(_cv($params, 'searchWord')){
            $res->where('descriptions', 'like', "%{$params['searchWord']}%");
            $res->orWhere('name', 'like', "%{$params['searchWord']}%");
        }

        $listCount = $res->count(DB::raw('id'));

        $res->limit($perpage);
        if(_cv($params, 'currentPage', 'nn')){
            $offset = ($params['currentPage'] * $perpage) - $perpage > $listCount?0:($params['currentPage'] * $perpage)-$perpage;
            $res->offset($offset);
        }



        $res->orderBy($orderField, $orderDirection);
        $ret = $res->get();
        $ret = _psql(_toArray($ret));

        foreach ($ret as $k=>$v){

            if(!is_array($ret[$k]['descriptions']))$ret[$k]['descriptions'] = [];
            $ret[$k]['title'] = isset($ret[$k]['descriptions'][requestLan()]) ? $ret[$k]['descriptions'][requestLan()] : current($ret[$k]['descriptions']);

            /// if file is image get other dimensions for devices
            if(strpos($ret[$k]['mime_type'], 'image/') !== false) $ret[$k]['devices'] = $this->getDeviceImages($ret[$k]);

            /// if exists desktop version use it for default one
            if(_cv($ret[$k], 'devices.desktop')){
                $ret[$k]['url'] = $ret[$k]['devices']['desktop'];
            }
        }

        $returnData['listCount'] = $listCount;
        $returnData['list'] = $ret;

        return $returnData;
    }


}
