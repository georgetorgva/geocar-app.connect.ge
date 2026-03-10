<?php

use Illuminate\Support\Facades\App;
use App\Services\Translations\WordsService;
use App\Services\Languages\LanguagesService;
use App\Models\Languages\Words;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

function getLocales($active = false)
{
    static $languages;
    if (empty($languages)) {
        $languages = App::make(LanguagesService::class)->languages($active);
    }
    if ($active) {
        return $languages->where('active', 1);
    } else {
        return $languages;
    }
}

if (!function_exists('array_sort_recursive')) {
    /**
     * Recursively sort an array by keys and values.
     *
     * @param array $array
     * @return array
     */
    function array_sort_recursive($array)
    {
        return Arr::sortRecursive($array);
    }
}

function tr($key = null)
{
    $key = strip_tags(strtolower($key));
    if (empty($key))return $key;

    $translations = Words::where('key', $key)->first();
    if($translations)$translations = _psqlRow(_toArray($translations));

    /// if exists locale translation
    if(_cv($translations, 'value.'.app()->getLocale()))return $translations['value'][app()->getLocale()];

    /// if locale translation does not exists return capitalized key
    if(_cv($translations, 'id', 'nn'))return ucwords($key);

    /// create new word translation
    $word = new \App\Models\Admin\WordsModel();

    $upd['key'] = $key;
    $wordValue = [];
    foreach (config('app.locales') as $k=>$v){
        $wordValue[$k] = ucwords($key);
    }
    $upd['value'] = $wordValue;

    $word->upd($upd);

    return ucwords($key);

}

function getDirContents($dir, &$results = array())
{
    $files = scandir($dir);
    foreach ($files as $key => $value) {
        $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
        if (!is_dir($path)) {
            $results[] = $path;
        } elseif ($value != "." && $value != "..") {
            getDirContents($path, $results);
            $results[] = $path;
        }
    }

    return $results;
}

function getModules()
{
    return false;
//    $modules = Generator::where('status', 'created')->get();
//    return $modules;
}

/**
 * iterates trought array. if there is json decodable data decodes into array
 * @input array
 * @return decoded array
 */
function decodeJson($data = [])
{
    if (!is_array($data)) {
        return [];
    }
    foreach ($data as $k => $v) {
        $tmp = json_decode($v, 1);
        if (is_array($tmp)) {
            $data[$k] = $tmp;
        }
    }
    return $data;
}

/**
 * iterates trought array. if there is sub array encodes into json
 * @input array
 * @return encoded array
 */
function encodeJson($data = [])
{
    if (!is_array($data)) {
        return [];
    }
    foreach ($data as $k => $v) {
        if (is_array($v)) {
            $data[$k] = json_encode($v, JSON_UNESCAPED_UNICODE|JSON_NUMERIC_CHECK);
        };
    }
    return $data;
}

/** checks if array element exists and check type */
function _cv($Array = [], $key = false, $CheckType = 'empty')
{

//    $Array = _toArray($Array);
    $Array = json_decode( json_encode($Array, JSON_UNESCAPED_UNICODE), 1);

    if ($key === false) {
        return false;
    }

    if (!is_array($key)) {
        $key = explode('.', $key);
    }
    $tmp = $Array;

    foreach ($key as $v) {
        if (!isset($tmp[$v])) {
            return false;
        }
        $tmp = $tmp[$v];
    }

    if ($CheckType == 'empty' && !empty($tmp)) {
        $tmp;
    } elseif ($CheckType == 'empty') {
        return false;
    }

    if ($CheckType == 'num' && is_numeric($tmp)) {
        return $tmp;
    } elseif ($CheckType == 'num') {
        return false;
    }

    if ($CheckType == 'nn' && is_numeric($tmp) && $tmp != 0) {
        $tmp;
    } elseif ($CheckType == 'nn') {
        return false;
    }

    if ($CheckType == 'ar' && is_array($tmp)) {
        $tmp;
    } elseif ($CheckType == 'ar') {
        return false;
    }

    if (is_numeric($CheckType) && strlen($tmp) == $CheckType) {
        $tmp;
    } elseif (is_numeric($CheckType)) {
        return false;
    }

    return $tmp;
}

/** prepare sql data. check if field is json encripted and decript */
function _psql($Data = [], $JsonFields = [])
{
//    $JsonFields = array_flip($JsonFields);
    foreach ($Data as $k => $v) {
        $Data[$k] = _psqlRow($v, $JsonFields);
    }

    return $Data;
}

function _psqlRow($Data = [], $JsonFields = [])
{
    if(!is_array($JsonFields))$JsonFields = [];
    $JsonFields = array_flip($JsonFields);
    if(!is_array($Data)){
//        p($Data);
        return false;
    }

    foreach ($Data as $kk => $vv) {
        if (is_array($vv)) {
            continue;
        }
        $tmp = json_decode($vv, 1);
        if(!is_array($tmp) && strlen($vv)>0 && isset($JsonFields[$kk])) $tmp = explode(',', $vv);


        if (is_array($tmp)) {
            $Data[$kk] = $tmp;
        } elseif (isset($JsonFields[$kk])) {
            /// if field value is empty but it should be an array returns empty array
            $Data[$kk] = [];
        }
    }


    return $Data;
}

function _psqlCell($Data = '')
{
    return json_decode($Data, 1);

}

function _psqlupd($Data = '')
{
    if(is_array($Data))$Data = json_encode($Data, JSON_UNESCAPED_UNICODE);
    if(empty($Data))$Data = '{}';
    return $Data;
}

function _toArray($Data = '')
{
    return json_decode( json_encode($Data, JSON_UNESCAPED_UNICODE), 1); ///JSON_NUMERIC_CHECK

}

function toSnakeCase($data = [])
{
    $ret = [];
    foreach ($data as $k => $v) {
        $k = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $k));
        $ret[$k] = $v;
    }
    return $ret;
}

function _sanitizeData($data = [])
{
    if(is_array($data)){
        foreach ($data as $k=>$v){
            $data[$k] = _sanitizeData($v);
        }
    }else{
        return trim(strip_tags($data), "*/+-=@\t\n\r ");
    }

    return $data;

}


function p($data = [])
{
    print "<pre>";
    print_r($data);
    print "</pre>";
}

/** separate from data array fields for table and meta table data */
function separateTableMetaFieldsData($data = [], $tableFields = [], $fields = [])
{

    foreach ($data as $k=>$v){
        if(!isset($v))$data[$k] = '';
    }

    $res = ['data' => [], 'meta' => []];
    foreach ($tableFields as $k => $v) {
        $v = trim($v);
        if ( isset($data[$v]) ) {
            $res['data'][$v] = $data[$v];
            unset($data[$v]);
        }
    }

    $res['meta'] = $data;
    if(!empty($fields))$res['meta'] = validateMetaData($res['meta'], $fields);

    return $res;
}

/// filter from all meta data only validated data depend on fields config
function validateMetaData($metaData = [], $fields = []){
    $ret = [];
    $locales = config('app.locales');

    foreach ($fields as $k=>$v){

        if(_cv($v, 'translate') == 1){

            foreach ($locales as $kk=>$vv){
                if(isset($metaData["{$k}_{$kk}"])) $ret["{$k}_{$kk}"] = $metaData["{$k}_{$kk}"];
            }


        }elseif(isset($metaData[$k])){
            $ret[$k] = $metaData[$k];
        }


    }

    return $ret;
}

function mergeToMetaData($data = [], $metaData = [], $tableFields = [])
{

    $tmp = array_merge($data, $metaData);

    foreach ($tableFields as $k=>$v){
        if(isset($tmp[$v]))unset($tmp[$v]);
        if(isset($data[$v]))$tmp[$v] = $data[$v];
    }

    return $tmp;
}

function decodeJoinedMetaData($data = [], $separateByLanguages = false)
{
    /**
    ----GROUP_CONCATED_KEY_VAL_SEPARATOR----
    ----GROUP_CONCATED_FIELD_END----,
     */
    $data = explode('----GROUP_CONCATED_FIELD_END----', $data);

    if(!is_array($data))return [];

    $tmp = [];
    foreach ($data as $k=>$v){
        $v = explode('----GROUP_CONCATED_KEY_VAL_SEPARATOR----', $v);
        if(!isset($v[1]))continue;
        $tmpp = json_decode($v[1], 1);
        $lan = isset($v[2]) && strlen($v[2])==2?$v[2]:'xx';

        if($separateByLanguages){
            $tmp[$lan][$v[0]] = (is_array($tmpp))? $tmpp:$v[1];
        }else{
            $tmp[$v[0]] = (is_array($tmpp))? $tmpp:$v[1];
        }


    }
//p($tmp);
    return $tmp;
}


function extractTranslated($data = [], $translate = false, $fields = []){
//p($data);
//p($fields);


    //        $locale = getLocales(1);
//    $locale = requestLan(); //config('app.locale');
    $locales = config('app.locales');
    if($translate && !isset($locales[$translate]))$translate = array_key_first(($locales));

    foreach ($fields as $k=>$v){
        if(!$v['translate'])continue;

        /// define empty translation
        $tmp = '';

        foreach ($locales as $kk=>$vv){
            /// if translation exists assign to data array without locale suffix
            if($translate == $kk && isset($data["{$k}_{$kk}"]))$data[$k] = $data["{$k}_{$kk}"];

            /// if found translations with locale suffix unset them
            if(isset($data["{$k}_{$kk}"])){
                /// cache translation
                $tmp = $data["{$k}_{$kk}"];
                unset($data["{$k}_{$kk}"]);
            }
        }
        /// if locale translation not set set cached translation
        if(!isset($data[$k]))$data[$k] = '';

    }

    $data['localisedto'] = $translate;

    return $data;
}


function paramsCheckFailed($params = [], $checkNeedles = [])
{
    foreach ($checkNeedles as $v) {
        if ( !isset($params[$v]) ) {
            return "`{$v}` param error";
        }
    }

    return false;
}

function metaFieldsLocales($fieldKey = ''){
    $ret = [];
    foreach (config('app.locales') as $k=>$v){
        $ret[] = "{$fieldKey}_{$k}";
    }
    return $ret;
}

function fieldLocalizedName($field='', $contentType='', $locale=''){
//    if(!$locale || !config("app.locales.{$locale}"))return $field;

    $fieldConfig = config("adminpanel.content_types.{$contentType}.fields.{$field}");
    if($fieldConfig)return $field;

    $fieldNoLan = substr($field, 0, -3);
    $fieldNoLanConfig = config("adminpanel.content_types.{$contentType}.fields.{$fieldNoLan}.translate");

    if($fieldNoLanConfig)return $fieldNoLan;

    return $field;

}

function requestLan($lang = false){
    if(!$lang && app('request')->header('lang'))$lang = app('request')->header('lang');
    if(!$lang && app('request')->header('Lang'))$lang = app('request')->header('Lang');
    $requestLan = $lang;
    $langs = config('app.locales');
    if(isset($langs[$requestLan]))return $requestLan;

    return array_key_first($langs);

}

function siteMode(){
    $siteMode = app('request')->header('siteMode');
    $token = md5(date('Ymd').env('APP_KEY'));

    if( $siteMode == $token )return 'preview';

    return 'production';

}

function serchRecursive($data=[], $searchWord = ''){

    if(!is_array($data))return false;
    foreach ($data as $k=>$v){
        if(is_array($v)){
            $tmp = serchRecursive($v, $searchWord);
            if($tmp)return $tmp;
            continue;
        }

        if(strpos(strip_tags($v), $searchWord) !== false)return strip_tags($v);
//        if(strpos($k, $searchWord) !== false)return $k;
    }

    return false;

}

function apiShortener($data=[], $query=[]){

    if(!is_array($query) || empty($query))return $data;

    $tmp = [];
    foreach ($query as $v){
        setArrayDeep($tmp, $v, _cv($data, $v));
    }

    return $tmp;
}

function setArrayDeep(&$array, $keys, $value) {
    $keys = explode(".", $keys);
    $current = &$array;
    foreach($keys as $key) {
        $current = &$current[$key];
    }
    $current = $value;
}

function getSearchedTextPart( $text='', $searchWord = '', $beforeWords=50, $afterWords=50){

    /// if text is array search in it recursively
    if(is_array($text)){
        foreach ($text as $k=>$v){
            $tmp = getSearchedTextPart($v, $searchWord, $beforeWords, $afterWords);
            if($tmp)return $tmp;
        }
        return false;
    }

    $result = strtolower(strip_tags($text));
    $searchWord = strtolower($searchWord);
    $wordIndex = mb_strpos($result, $searchWord);

    if($wordIndex === false) return false;

    $fromIndex = ($wordIndex - $beforeWords)>0?($wordIndex - $beforeWords):0;
    $toIndex = $afterWords+strlen($searchWord);

//    $result = "{$fromIndex}--{$toIndex}--".substr($text, $fromIndex, $toIndex);
    $result = mb_substr($result, $fromIndex, $toIndex);

    $result = str_ireplace($searchWord, "<span>{$searchWord}</span>", $result);

    return $result;

}

function leaveOnlyNumbers($array = []){
    foreach ($array as $k=>$v){
        if(!is_numeric($v))unset($array[$k]);
    }
    return $array;
}
function sanitizeFilename($filename = ''){
    $special_chars = array( '?', '[', ']', '/', '\\', '=', '<', '>', ':','@', ';', ',', "'", '"', '&', '$', '#', '*', '(', ')', '|', '~', '`', '!', '{', '}', '%', '+', '’', '«', '»', '”', '“', chr( 0 ) );

    $filename = transliterate( $filename );
    $filename = strtolower( $filename );
    $filename = str_replace( $special_chars, '', $filename );
    $filename = str_replace( array( '%20', '+' ), '-', $filename );
    $filename = preg_replace( '/[\r\n\t -]+/', '-', $filename );
    $filename = substr( $filename, 0,80 );
    $filename = trim( $filename, '.-_' );
    return $filename;
}

function codeTimeTracker($startTimeStamp = ''){
    $time_end = microtime(true);
    $execution_time = ($time_end - $startTimeStamp);

    return "execution time: {$execution_time} sec";

}

function listViewFields($contentType = '', $locale = ''){
    if(!$contentType)return [];
    $contentTypeConfs = config("adminpanel.content_types.{$contentType}.fields");
    if(!is_array($contentTypeConfs))return [];

    $locales = config('app.locales');

    foreach ($contentTypeConfs as $k=>$v){
        if(_cv($v, ['showOnAdminList'])!=1)continue;
        $titleFields[] = $k;


//        if(_cv($v, ['translate']) && $locale){
////            $titleFields[] = [$locale] = [$k];
//            $titleFields[] = "{$k}_{$locale}";
//        }else if(_cv($v, ['translate'])){
//            foreach ($locales as $kk=>$vv){
////                $titleFields[] = [$kk] = [$k];
//                $titleFields[] = "{$k}_{$kk}";
//            }
//        }else{
//            $titleFields[] = $k;
//        }

    }
    return $titleFields;

}

function transliterate($input, $replace = '-')
{
    $map = [
        'ა' => 'a',
        'ბ' => 'b',
        'გ' => 'g',
        'დ' => 'd',
        'ე' => 'e',
        'ვ' => 'v',
        'ზ' => 'z',
        'თ' => 't',
        'ი' => 'i',
        'კ' => 'k',
        'ლ' => 'l',
        'მ' => 'm',
        'ნ' => 'n',
        'ო' => 'o',
        'პ' => 'p',
        'ჟ' => 'zh',
        'რ' => 'r',
        'ს' => 's',
        'ტ' => 't',
        'უ' => 'u',
        'ფ' => 'f',
        'ქ' => 'q',
        'ღ' => 'gh',
        'ყ' => 'y',
        'შ' => 'sh',
        'ჩ' => 'ch',
        'ც' => 'ts',
        'ძ' => 'dz',
        'წ' => 'w',
        'ჭ' => 'ch',
        'ხ' => 'x',
        'ჯ' => 'j',
        'ჰ' => 'h'
    ];

    $inputSize = mb_strlen($input,'UTF-8');
    $output = [];

    for ($i = 0; $i < $inputSize; $i++)
    {
        $character = mb_substr($input, $i, 1);

        $output[] = $map[$character] ?? $character;
    }

    $output = implode('', $output);
    $output = Str::slug($output, $replace);

    return $output;
//    return str_replace(['_', ' '],$replace, $output);
}

function uidGenerator($length = 12){
    return substr(str_shuffle(str_repeat('1234567890qwertyuiopasdfghjklmnbvcxz', $length)), 0, $length);
}

function filterRecords($array, $filters) {
    $array = json_decode(json_encode($array), true);
    return array_map(function($item) use ($filters) {
        return filter_keys_recursive($item, $filters);
    }, $array);
}

function filter_keys_recursive($arr, $filters) {
    $filteredArr = [];
    foreach ($arr as $key => $val) {
        if(!empty($filters)) {
            foreach ($filters as $filter) {
                $filter_keys = explode(".", $filter);
                $current = &$arr;
                $match = true;
                $temp = &$filteredArr;
                foreach ($filter_keys as $filter_key) {
                    if(is_array($current) && array_key_exists($filter_key, $current)) {
                        if(!isset($temp[$filter_key])) {
                            $temp[$filter_key] = [];
                        }
                        $temp = &$temp[$filter_key];
                        $current = &$current[$filter_key];
                    } else {
                        $match = false;
                        break;
                    }
                }
                if($match) {
                    $temp = $current;
                }
            }
        }
    }
    return $filteredArr;
}

function sku($sku='', $length=9){
    return str_pad($sku, $length,0, STR_PAD_LEFT);
}

function appSessionId(){

    if(request()->header('appSessionId')){
        return request()->header('appSessionId');

    }else if(isset($_COOKIE['appSessionId'])){
        return trim($_COOKIE['appSessionId']);
    }

    return session()->getId();
}


function sessionSet($key = '', $data = [], $lifetime = 200){

    if(!$data || $data == ''){
        Cache::store('file')->delete($key);
        $data = false;
    }else{
//        p($data);
        Cache::store('file')->put($key, $data, $lifetime);
    }

    return $data;
}

function sessionGet($key = ''){
//    print $key;
    $sessionData = Cache::store('file')->get($key);

    if(!$sessionData || $sessionData=='') return false;

    return $sessionData;
}

function discountCalculator($price = 0, $discountAmount = 0, $discountType = 'percent', $offerId = '', $offerData = []){
    $ret = ['price'=>$price, 'calcPrice'=>$price, 'amount'=>0, 'discountAmount' => $discountAmount, 'discountType' => $discountType];

    /// check if discount is amount or percent;
    ///  then make discount on price value
    if($discountType === 'amount'){
        $ret['calcPrice'] = $price - $discountAmount;
    }else{
        $ret['calcPrice'] = $price - (($price * $discountAmount)/100);
    }

    if($ret['calcPrice'] < 0)$ret['calcPrice'] = 0;

    $ret['calcPrice'] = round($ret['calcPrice'], 2);
    $ret['amount'] = round($price - $ret['calcPrice'], 2);
    $ret['oId'] = $offerId;
    $ret['start'] = _cv($offerData, 8);
    $ret['end'] = _cv($offerData, 9);
    $ret['loyalty'] = _cv($offerData, 10);

    return $ret;

}
