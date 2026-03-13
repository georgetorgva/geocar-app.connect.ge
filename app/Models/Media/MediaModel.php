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

    /**
     * Register an already-uploaded file as a media record in the database.
     *
     * Looks up the file on the given Storage disk, reads its size and MIME
     * type, then inserts one row into the media table. The file must already
     * exist on disk before this method is called — it does not handle the
     * upload itself.
     *
     * -------------------------------------------------------------------------
     * SUPPORTED $params KEYS
     * -------------------------------------------------------------------------
     * @param  array $params {
     *   'path'        string  — storage-relative path to the file (required).
     *                          Returns false immediately when missing.
     *   'disk'        string  — Laravel filesystem disk name. Default: 'public'.
     *   'upload_form' string  — source/context label stored with the record
     *                          (e.g. 'editor', 'gallery'). Default: 'other'.
     * }
     *
     * -------------------------------------------------------------------------
     * RETURN VALUE
     * -------------------------------------------------------------------------
     * @return int|false  The new record's id on success.
     *                    false when 'path' is missing or the file does not
     *                    exist on the specified disk.
     */
    public function upd($params = []){
        $uploadForm = _cv($params, 'upload_form') ?: 'other';
        $path       = _cv($params, 'path');
        $disk       = _cv($params, 'disk') ?: 'public';

        if (!$path) return false;

        $storage = Storage::disk($disk);

        if (!$storage->exists($path)) return false;

        $url      = $storage->url($path);
        $size     = $storage->size($path);
        $mimeType = $storage->mimeType($path);

        $basic = $this->create([
            'upload_form' => $uploadForm,
            'name'        => $path,
            'file_name'   => $url,
            'mime_type'   => $mimeType,
            'disk'        => $disk,
            'size'        => $size,
            'descriptions'=> '',
            'conf'        => '',
        ]);

        return $basic->id;
    }

    /**
     * Update the descriptions field of an existing media record.
     *
     * Writes a single column update without loading the full Eloquent model.
     * The value is passed through _psqlupd() before storage to ensure it is
     * JSON-encoded when an array is given, or stored as '{}' when blank.
     *
     * -------------------------------------------------------------------------
     * VALIDATION
     * -------------------------------------------------------------------------
     * Returns false immediately when $id is missing, non-numeric, or <= 0,
     * and when no record with the given id exists in the table.
     *
     * -------------------------------------------------------------------------
     * PARAMS
     * -------------------------------------------------------------------------
     * @param  array|string $descriptions  Description data to store. Arrays are
     *                                     JSON-encoded; blank values stored as '{}'.
     * @param  int|null     $id            Primary key of the record to update.
     *
     * -------------------------------------------------------------------------
     * RETURN VALUE
     * -------------------------------------------------------------------------
     * @return int|false  The record id on success, false on validation failure
     *                    or when the record does not exist.
     */
    public function updDescription($descriptions = '', $id = null){

        if (!is_numeric($id) || $id <= 0) return false;

        if (!DB::table($this->table)->where('id', $id)->exists()) return false;

        DB::table($this->table)->where('id', $id)->update([
            'descriptions' => _psqlupd($descriptions),
        ]);

        return $id;
    }

    /**
     * Delete a media record and its associated file from storage.
     *
     * Loads the record to obtain the disk and file path, deletes the physical
     * file via the Storage facade, then removes the database row using the
     * loaded model instance — avoiding a redundant second query.
     *
     * -------------------------------------------------------------------------
     * OPERATION ORDER
     * -------------------------------------------------------------------------
     * 1. Validate $file_id (numeric, > 0).
     * 2. Load the record — returns false if not found.
     * 3. Delete the file from its storage disk.
     * 4. Delete the database row.
     *
     * Note: filesystem and database operations cannot share a transaction.
     * If the DB delete fails after the file is removed, the file will be gone
     * but the record will remain. In practice the DB delete on a known-existing
     * row will not fail, so this edge case is acceptable.
     *
     * -------------------------------------------------------------------------
     * PARAMS
     * -------------------------------------------------------------------------
     * @param  int|null $file_id  Primary key of the media record to delete.
     *
     * -------------------------------------------------------------------------
     * RETURN VALUE
     * -------------------------------------------------------------------------
     * @return int|false  The deleted record's id when both the file and the
     *                    DB row were removed successfully.
     *                    false when $file_id is invalid, the record does not
     *                    exist, or the Storage deletion failed.
     */
    public function deleteMediaFile($file_id = null){
        if (!is_numeric($file_id) || $file_id <= 0) return false;

        $res = $this->find($file_id);

        if (!$res) return false;

        $fileDeleted = Storage::disk($res->disk)->delete($res->name);

        $res->delete();

        return $fileDeleted ? $file_id : false;
    }

    /**
     * Fetch a single media record by primary key, with decoded meta and device URLs.
     *
     * Loads the record, decodes the JSON descriptions field, resolves the
     * localised title for the current request locale, and — for image MIME
     * types — appends a 'devices' map with optimised URLs per viewport size.
     * When a 'desktop' variant exists it is promoted to the default 'url'.
     *
     * -------------------------------------------------------------------------
     * PARAMS
     * -------------------------------------------------------------------------
     * @param  int|null $id  Primary key of the media record.
     *
     * -------------------------------------------------------------------------
     * RETURN VALUE
     * -------------------------------------------------------------------------
     * @return array|false  Associative array with at minimum:
     *                        id, url, size, descriptions (array), conf,
     *                        name, mime_type, title.
     *                      Images additionally have a 'devices' key with
     *                        desktop/tablet/mobile URL strings.
     *                      false when $id is invalid or the record is not found.
     */
    public function getOne( $id = null ){
        if (!is_numeric($id) || $id <= 0) return false;

        $c = config('filesystems.disks.public.url');

        $res = $this->selectRaw("id, CONCAT(?, name) as url, size, descriptions, conf, name, mime_type", [$c])->where('id', $id)->first();

        if(!$res)return false;

        $res = _psqlRow(_toArray($res));

        if (!is_array($res['descriptions'])) $res['descriptions'] = [];
        $lan = requestLan();
        $res['title'] = $res['descriptions'][$lan] ?? current($res['descriptions']);

        if (str_starts_with($res['mime_type'], 'image/')) $res['devices'] = $this->getDeviceImages($res);

        /// if exists desktop version use it for default one
        if(_cv($res, 'devices.desktop'))$res['url'] = $res['devices']['desktop'];

        return $res;
    }

    public function getIdByName( $name = '' ){

        $res = MediaModel::select('id')->where('name', $name)->first();

        if($res)return $res->id;

        return false;
    }

    /**
     * Fetch a list of media records with decoded meta and device URLs.
     *
     * When 'ids' is supplied the result set is restricted to those records and
     * ordered to match the input order (MySQL FIELD()). Without 'ids' the
     * first 100 rows in table order are returned.
     *
     * Each row has its JSON descriptions field decoded, a localised 'title'
     * resolved for the current request locale, and — for image MIME types —
     * a 'devices' map with optimised URLs per viewport size. When a 'desktop'
     * variant exists it is promoted to the default 'url'.
     *
     * -------------------------------------------------------------------------
     * SUPPORTED $params KEYS
     * -------------------------------------------------------------------------
     * @param  array $params {
     *   'ids'      int[]  — optional list of record ids to fetch; result
     *                       is ordered to match the input order.
     *   'idAsKey'  bool   — when truthy, re-index the result array by id.
     * }
     *
     * -------------------------------------------------------------------------
     * RETURN VALUE
     * -------------------------------------------------------------------------
     * @return array  List (or id-keyed map when 'idAsKey') of record arrays,
     *                each containing: id, url, size, descriptions (array),
     *                conf, name, mime_type, title, and optionally devices.
     */
    public function getList( $params = [] ){

        $c = config('filesystems.disks.public.url');

        $res = $this->selectRaw("id, CONCAT(?, name) as url, size, descriptions, conf, name, mime_type", [$c]);
        if(_cv($params, 'ids', 'ar')){
            $params['ids'] = leaveOnlyNumbers($params['ids']);
            $ids_ordered = implode( ',', $params['ids']);

            $res->whereIn('id', $params['ids'])->orderByRaw("FIELD(id, $ids_ordered)");
        }

        $ret = $res->limit(100)->get();
        $ret = _psql(_toArray($ret));
        $lan = requestLan();
        foreach ($ret as $k => $v) {
            if (!is_array($ret[$k]['descriptions'])) $ret[$k]['descriptions'] = [];
            $ret[$k]['title'] = $ret[$k]['descriptions'][$lan] ?? current($ret[$k]['descriptions']);

            if (str_starts_with($ret[$k]['mime_type'], 'image/')) $ret[$k]['devices'] = $this->getDeviceImages($ret[$k]);

            if (_cv($ret[$k], 'devices.desktop')) $ret[$k]['url'] = $ret[$k]['devices']['desktop'];
        }

        if (_cv($params, 'idAsKey')) {
            $ret = array_column($ret, null, 'id');
        }

        return $ret;
    }

    /**
     * Build a per-device URL map for a media file.
     *
     * For each viewport size (desktop, tablet, mobile) checks whether a
     * pre-generated variant exists on disk under the corresponding subdirectory
     * of the public storage root. If the variant file is present its URL is
     * returned; otherwise the original file URL is used as the fallback.
     *
     * -------------------------------------------------------------------------
     * PARAMS
     * -------------------------------------------------------------------------
     * @param  array $data  Media record array; must contain a 'name' key with
     *                      the storage-relative file path.
     *
     * -------------------------------------------------------------------------
     * RETURN VALUE
     * -------------------------------------------------------------------------
     * @return array{desktop: string, tablet: string, mobile: string}
     *               URL string for each device; falls back to the original URL
     *               when no device-specific variant exists.
     */
    public function getDeviceImages($data){
        $path = config('filesystems.disks.public');
        $url = $path['url'];
        $ret = [];

        $root = $path['root'];
        $name = $data['name'];

        foreach (['desktop', 'tablet', 'mobile'] as $device) {
            $ret[$device] = is_file("{$root}/{$device}/{$name}")
                ? "{$url}{$device}/{$name}"
                : "{$url}{$name}";
        }

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
