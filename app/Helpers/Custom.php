<?php

use Illuminate\Support\Facades\App;
use App\Services\Translations\WordsService;
use App\Services\Languages\LanguagesService;
use App\Models\Languages\Words;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

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

/**
 * Fetch a UI translation string by key for the current app locale.
 *
 * Looks up the key in the Words table. Returns the localised value when found.
 * When the record exists but has no translation for the current locale,
 * returns the key in ucwords form. When the key does not exist at all,
 * inserts a new Words record pre-filled with ucwords($key) for every locale,
 * then returns ucwords($key).
 *
 * @param  string|null $key  Translation key; HTML tags stripped, lowercased.
 * @return string            Translated string or ucwords($key) fallback.
 */
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

/**
 * Recursively collect all file and directory paths under a directory.
 *
 * Walks the directory tree and appends every file path and every
 * subdirectory path to $results. The root directory itself is not included.
 *
 * @param  string $dir      Absolute path to the directory to scan.
 * @param  array  $results  Accumulator passed by reference; populated in place.
 * @return array            Flat list of absolute paths found under $dir.
 */
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

/**
 * Safe dot-notation array getter with optional type checking.
 *
 * Traverses a nested array (or object) using a dot-separated path or an
 * array of keys, then validates the resolved value against $CheckType.
 * Returns the value on success or false when the path does not exist or the
 * type check fails.
 *
 * -------------------------------------------------------------------------
 * CHECK TYPES
 * -------------------------------------------------------------------------
 *   'empty'  (default) — returns value when !empty(), false otherwise.
 *   'num'              — returns value when is_numeric(), false otherwise.
 *   'nn'               — returns value when is_numeric() && != 0, else false.
 *   'ar'               — returns value when is_array(), false otherwise.
 *   int (e.g. '6')     — returns value when strlen == that int, else false.
 *   any other string   — returns the raw value regardless of content.
 *
 * @param  array|object $Array      Source data to traverse.
 * @param  string|array $key        Dot-separated path string or array of keys.
 * @param  string       $CheckType  Validation mode (see above). Default 'empty'.
 * @return mixed|false              Resolved value on success, false on failure.
 */
function _cv($Array = [], $key = false, $CheckType = 'empty')
{
    if ($key === false) {
        return false;
    }

    // Convert objects/Eloquent models to plain arrays; skip for native arrays (common case)
    if (is_object($Array)) {
        $Array = json_decode(json_encode($Array, JSON_UNESCAPED_UNICODE), true);
    }

    $key = is_array($key) ? $key : explode('.', (string)$key);
    $tmp = $Array;

    foreach ($key as $v) {
        if (!is_array($tmp) || !isset($tmp[$v])) {
            return false;
        }
        $tmp = $tmp[$v];
    }

    switch ($CheckType) {
        case 'empty':
            return !empty($tmp) ? $tmp : false;
        case 'num':
            return is_numeric($tmp) ? $tmp : false;
        case 'nn':
            return (is_numeric($tmp) && $tmp != 0) ? $tmp : false;
        case 'ar':
            return is_array($tmp) ? $tmp : false;
        default:
            if (is_numeric($CheckType)) {
                return strlen((string)$tmp) == (int)$CheckType ? $tmp : false;
            }
            return $tmp;
    }
}

/**
 * Decode JSON-encoded fields across an entire result set.
 *
 * Thin wrapper around _psqlRow() that applies the same decoding to every
 * row in a 2-D array (e.g. the output of DB::table()->get() after _toArray()).
 *
 * Performance note: $JsonFields is flipped once here and passed pre-flipped
 * to _psqlRow() on every iteration, avoiding an array_flip() call per row.
 *
 * @param  array $Data        Indexed array of associative rows.
 * @param  array $JsonFields  Optional list of field names that must be treated
 *                            as arrays even if they are not valid JSON
 *                            (comma-separated fallback; empty value → []).
 * @return array              Same structure as $Data with JSON fields decoded.
 *                            Returns [] when $Data is not an array.
 */
function _psql($Data = [], $JsonFields = [])
{
    if (!is_array($Data)) return [];

    if (!is_array($JsonFields)) $JsonFields = [];
    $flippedFields = array_flip($JsonFields);

    foreach ($Data as $k => $v) {
        $result     = _psqlRow($v, $flippedFields, true);
        $Data[$k]   = ($result !== false) ? $result : $v;
    }

    return $Data;
}

/**
 * Decode JSON-encoded fields in a single associative row.
 *
 * Iterates over every scalar field in $Data and attempts to decode it.
 * Only strings that begin with '{' or '[' are passed to json_decode — all
 * other scalars (integers, dates, plain strings) are skipped entirely,
 * avoiding unnecessary JSON parser invocations.
 *
 * -------------------------------------------------------------------------
 * DECODING RULES PER FIELD
 * -------------------------------------------------------------------------
 *   1. Already an array          → left unchanged.
 *   2. String starting with '{' or '[', valid JSON array/object
 *                                → replaced with the decoded PHP array.
 *   3. String starting with '{' or '[', invalid JSON + field in $JsonFields
 *                                → comma-split fallback: explode(',', value).
 *   4. Any other non-empty value + field in $JsonFields
 *                                → comma-split fallback: explode(',', value).
 *   5. Empty string + field in $JsonFields
 *                                → replaced with [] (typed-empty array).
 *   6. All other scalars         → left unchanged.
 *
 * -------------------------------------------------------------------------
 * $preFlipped PARAMETER
 * -------------------------------------------------------------------------
 * When called from _psql(), the caller has already flipped $JsonFields once
 * for the entire batch. Pass $preFlipped = true to skip the redundant
 * array_flip() inside this function. When calling _psqlRow() directly,
 * leave $preFlipped at its default (false) — flipping happens here.
 *
 * @param  array $Data        Single associative row from a DB result set.
 * @param  array $JsonFields  Field names (values) or flipped map (keys) that
 *                            must be treated as arrays. See $preFlipped.
 * @param  bool  $preFlipped  True when $JsonFields has already been flipped
 *                            by the caller (e.g. via _psql()). Default false.
 * @return array|false        Decoded row, or false when $Data is not an array.
 */
function _psqlRow($Data = [], $JsonFields = [], $preFlipped = false)
{
    if (!is_array($Data)) return false;

    if (!is_array($JsonFields)) $JsonFields = [];
    if (!$preFlipped) $JsonFields = array_flip($JsonFields);

    foreach ($Data as $kk => $vv) {
        if (is_array($vv)) continue;

        // Short-circuit: json_decode can only produce an array for '{' / '[' prefixed strings.
        // Skip the JSON parser for all other scalars (integers, dates, short strings, etc.)
        // and fall straight through to the $JsonFields fallback check.
        if (is_string($vv) && $vv !== '' && ($vv[0] === '{' || $vv[0] === '[')) {
            $tmp = json_decode($vv, 1);
            if (is_array($tmp)) {
                $Data[$kk] = $tmp;
                continue;
            }
        }

        // Field declared as an array type but value was not valid JSON:
        // fall back to comma-split, or store an empty array when the value is blank.
        if (isset($JsonFields[$kk])) {
            $Data[$kk] = ($vv !== '') ? explode(',', (string)$vv) : [];
        }
    }

    return $Data;
}

/**
 * Decode a single JSON-encoded database cell value.
 *
 * Convenience wrapper around json_decode() for use when only one field
 * value needs to be decoded rather than a full row or result set.
 * Returns null when the string is not valid JSON.
 *
 * @param  string $Data  Raw string value as stored in the database.
 * @return mixed         Decoded PHP value (array, scalar), or null on failure.
 */
function _psqlCell($Data = '')
{
    return json_decode($Data, true);
}

/**
 * Encode a value for storage as a JSON column in the database.
 *
 * Ensures every value written to an EAV 'val' column or a JSON-typed
 * regular column is a valid non-empty string:
 *
 *   - Arrays are JSON-encoded with unicode preserved (JSON_UNESCAPED_UNICODE).
 *   - null / '' / false are stored as '{}' rather than NULL or an empty
 *     string, keeping the column value consistent and parseable on read.
 *   - Scalar strings (already encoded) are returned as-is.
 *
 * NOTE: empty() is intentionally NOT used here because empty("0") is true —
 * the string "0" is a valid scalar value and must not be replaced with "{}".
 *
 * @param  array|string|null $Data  Value to encode.
 * @return string                   JSON string, or '{}' for blank/null input.
 */
function _psqlupd($Data = '')
{
    if (is_array($Data)) $Data = json_encode($Data, JSON_UNESCAPED_UNICODE);
    if ($Data === null || $Data === '' || $Data === false) $Data = '{}';
    return $Data;
}

/**
 * Convert any value to a plain PHP array.
 *
 * Uses json_encode + json_decode to recursively cast objects, Eloquent
 * models, and collections to associative arrays. Scalars and already-plain
 * arrays pass through the round-trip unchanged.
 *
 * @param  mixed $Data  Any value — object, Eloquent result, array, or scalar.
 * @return array        Plain associative array representation of $Data.
 */
function _toArray($Data = '')
{
    return json_decode( json_encode($Data, JSON_UNESCAPED_UNICODE), 1); ///JSON_NUMERIC_CHECK

}

/**
 * Convert all keys of an associative array from camelCase to snake_case.
 *
 * Values are left unchanged. Only top-level keys are converted — nested
 * arrays are not recursed into.
 *
 * @param  array $data  Input array with camelCase keys.
 * @return array        New array with snake_case keys and original values.
 */
function toSnakeCase($data = [])
{
    $ret = [];
    foreach ($data as $k => $v) {
        $k = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $k));
        $ret[$k] = $v;
    }
    return $ret;
}

/**
 * Recursively strip HTML tags and dangerous characters from input data.
 *
 * Traverses arrays recursively. For scalar values, strips HTML tags then
 * trims the characters: * / + - = @ tab newline carriage-return and space.
 * Safe for use on arbitrary request input before processing or storage.
 *
 * @param  array|string $data  Input to sanitize.
 * @return array|string        Sanitized value with the same structure as input.
 */
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


/**
 * Debug pretty-print helper.
 *
 * Wraps print_r() output in <pre> tags for readable HTML output.
 * For development/debugging use only — should not be called in production.
 *
 * @param  mixed $data  Any value to inspect.
 * @return void
 */
function p($data = [])
{
    print "<pre>";
    print_r($data);
    print "</pre>";
}

/**
 * Split a flat request data array into main-table fields and meta fields.
 *
 * Moves keys that appear in $tableFields into the 'data' bucket; all
 * remaining keys go into 'meta'. When $fields is provided, the meta bucket
 * is further filtered through validateMetaData() to include only declared
 * field keys (with locale variants for translatable fields).
 *
 * @param  array $data        Flat input array (e.g. $request->all()).
 * @param  array $tableFields List of column names that belong to the main table.
 * @param  array $fields      Optional fieldConfigs 'fields' map; when provided,
 *                            meta keys are validated against it.
 * @return array{data: array, meta: array}
 */
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

/**
 * Filter a meta data map to only the keys declared in a fieldConfigs 'fields' map.
 *
 * For translatable fields (translate == 1), includes locale-suffixed variants
 * (e.g. 'title_ge', 'title_en'). For non-translatable fields, includes the
 * key as-is. Keys not present in $fields are discarded.
 *
 * @param  array $metaData  Flat meta key => value map to filter.
 * @param  array $fields    fieldConfigs 'fields' section; keys are field names,
 *                          values are field config arrays with a 'translate' flag.
 * @return array            Filtered meta map containing only declared field keys.
 */
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

/**
 * Merge main-table data and meta data into a single flat array.
 *
 * Merges $data and $metaData, then ensures that main-table field values
 * from $data take precedence over any same-key entries that came from meta.
 * Fields listed in $tableFields that do not exist in $data are removed from
 * the merged result.
 *
 * @param  array $data        Main-table field map (output of separateTableMetaFieldsData 'data').
 * @param  array $metaData    Meta field map (output of separateTableMetaFieldsData 'meta').
 * @param  array $tableFields List of column names belonging to the main table.
 * @return array              Merged flat array with main-table fields having priority.
 */
function mergeToMetaData($data = [], $metaData = [], $tableFields = [])
{

    $tmp = array_merge($data, $metaData);

    foreach ($tableFields as $k=>$v){
        if(isset($tmp[$v]))unset($tmp[$v]);
        if(isset($data[$v]))$tmp[$v] = $data[$v];
    }

    return $tmp;
}

/**
 * Decode a GROUP_CONCAT-encoded meta string into a key-value map.
 *
 * Parses the custom delimiter format produced by the SQL GROUP_CONCAT pattern:
 *   key----GROUP_CONCATED_KEY_VAL_SEPARATOR----jsonValue----GROUP_CONCATED_FIELD_END----
 *
 * Each segment yields one entry. The JSON value is decoded if valid; otherwise
 * stored as the raw string. When $separateByLanguages is true, entries are
 * grouped under their locale code ('ge', 'en', etc.); unknown locales fall
 * back to 'xx'.
 *
 * @param  string $data                  Raw GROUP_CONCAT string from a DB query.
 * @param  bool   $separateByLanguages   When true, result is keyed by locale then
 *                                       field. When false, a flat key => value map.
 * @return array                         Decoded meta map.
 */
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


/**
 * Collapse locale-suffixed field variants into plain field keys for a given locale.
 *
 * For each translatable field in $fields, promotes the value for the target
 * locale (e.g. 'title_ge') to the base key ('title') and removes all
 * locale-suffixed variants. Adds a 'localisedto' key with the resolved locale.
 * Non-translatable fields are left untouched.
 *
 * @param  array        $data       Flat data array with locale-suffixed keys.
 * @param  string|false $translate  Target locale code. Falls back to the first
 *                                  configured locale when the given code is invalid.
 * @param  array        $fields     fieldConfigs 'fields' map; only fields with
 *                                  'translate' == 1 are processed.
 * @return array                    Data array with locale variants collapsed and
 *                                  'localisedto' added.
 */
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


/**
 * Check that all required keys are present in a params array.
 *
 * Returns a descriptive error string for the first missing key, or false
 * when all keys are present. Designed for early-return guards:
 *   if (paramsCheckFailed($params, ['id'])) return false;
 *
 * @param  array $params        Input array to check.
 * @param  array $checkNeedles  List of required key names.
 * @return string|false         Error string for the first missing key, or false.
 */
function paramsCheckFailed($params = [], $checkNeedles = [])
{
    foreach ($checkNeedles as $v) {
        if ( !isset($params[$v]) ) {
            return "`{$v}` param error";
        }
    }

    return false;
}

/**
 * Generate locale-suffixed field name variants for a given field key.
 *
 * Returns one entry per configured locale in the form 'fieldKey_locale'
 * (e.g. ['title_ge', 'title_en']). Used to build SELECT column lists and
 * meta key lookups for translatable fields.
 *
 * @param  string $fieldKey  Base field name (e.g. 'title').
 * @return array             List of locale-suffixed field names.
 */
function metaFieldsLocales($fieldKey = ''){
    $ret = [];
    foreach (config('app.locales') as $k=>$v){
        $ret[] = "{$fieldKey}_{$k}";
    }
    return $ret;
}

/**
 * Resolve the canonical field name for a given content type, stripping the locale suffix when needed.
 *
 * Checks whether the field exists directly in the content type's fieldConfigs.
 * If not, strips the last 3 characters (assumed locale suffix, e.g. '_ge')
 * and checks whether the resulting base field is translatable. Returns the
 * base field name when it is, otherwise returns the original field name.
 *
 * @param  string $field        Field name, possibly with a locale suffix (e.g. 'title_ge').
 * @param  string $contentType  Content type key from adminpanel config.
 * @param  string $locale       Locale code (currently unused; reserved for future use).
 * @return string               Canonical field name with locale suffix removed when applicable.
 */
function fieldLocalizedName($field='', $contentType='', $locale=''){
//    if(!$locale || !config("app.locales.{$locale}"))return $field;

    $fieldConfig = config("adminpanel.content_types.{$contentType}.fields.{$field}");
    if($fieldConfig)return $field;

    $fieldNoLan = substr($field, 0, -3);
    $fieldNoLanConfig = config("adminpanel.content_types.{$contentType}.fields.{$fieldNoLan}.translate");

    if($fieldNoLanConfig)return $fieldNoLan;

    return $field;

}

/**
 * Resolve the current request locale from headers or fallback to the default.
 *
 * Checks the 'lang' and 'Lang' request headers in order. If the resolved
 * locale exists in the configured locales map, it is returned. Otherwise
 * returns the first configured locale as the default.
 *
 * @param  string|false $lang  Optional locale override; skips header lookup
 *                             when provided.
 * @return string              Valid locale code from config('app.locales').
 */
function requestLan($lang = false){
    if(!$lang && app('request')->header('lang'))$lang = app('request')->header('lang');
    if(!$lang && app('request')->header('Lang'))$lang = app('request')->header('Lang');
    $requestLan = $lang;
    $langs = config('app.locales');
    if(isset($langs[$requestLan]))return $requestLan;

    return array_key_first($langs);

}

/**
 * Determine whether the current request is in preview or production mode.
 *
 * Reads the 'siteMode' request header and compares it against a daily HMAC
 * token derived from the APP_KEY. Returns 'preview' when they match,
 * 'production' otherwise.
 *
 * @return string  'preview' or 'production'.
 */
function siteMode(){
    $siteMode = app('request')->header('siteMode');
    $token = md5(date('Ymd').env('APP_KEY'));

    if( $siteMode == $token )return 'preview';

    return 'production';

}

/**
 * Recursively search a nested array for a substring and return the matching value.
 *
 * Traverses all scalar leaf values (HTML stripped) looking for $searchWord.
 * Returns the stripped text of the first matching leaf, or false when no
 * match is found anywhere in the structure.
 *
 * @param  array|mixed  $data        Data structure to search.
 * @param  string       $searchWord  Substring to look for.
 * @return string|false              Stripped text of the first matching leaf,
 *                                   or false when not found.
 */
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

/**
 * Project a data array down to only the dot-path keys specified in $query.
 *
 * For each dot-path in $query, reads the value from $data via _cv() and
 * writes it into a new array preserving the nested structure via setArrayDeep().
 * Returns $data unchanged when $query is empty.
 *
 * @param  array $data   Source data array to project.
 * @param  array $query  List of dot-notation paths to include (e.g. ['id', 'meta.title']).
 * @return array         New array containing only the requested paths.
 */
function apiShortener($data=[], $query=[]){

    if(!is_array($query) || empty($query))return $data;

    $tmp = [];
    foreach ($query as $v){
        setArrayDeep($tmp, $v, _cv($data, $v));
    }

    return $tmp;
}

/**
 * Set a value at a dot-notation path inside a nested array, creating intermediate keys.
 *
 * Modifies $array in place by reference. Intermediate keys that do not exist
 * are created as empty arrays. Overwrites any existing value at the path.
 *
 * @param  array  &$array  Target array to modify by reference.
 * @param  string  $keys   Dot-separated path (e.g. 'meta.title').
 * @param  mixed   $value  Value to assign at the resolved path.
 * @return void
 */
function setArrayDeep(&$array, $keys, $value) {
    $keys = explode(".", $keys);
    $current = &$array;
    foreach($keys as $key) {
        $current = &$current[$key];
    }
    $current = $value;
}

/**
 * Extract a text snippet around the first occurrence of a search word.
 *
 * Strips HTML, finds the first case-insensitive occurrence of $searchWord,
 * and returns a substring of $beforeWords characters before and $afterWords
 * characters after it. The matched word is wrapped in a <span> tag.
 * When $text is an array, searches recursively and returns the first match.
 *
 * @param  array|string $text         Text or nested array of texts to search in.
 * @param  string       $searchWord   Word or phrase to find.
 * @param  int          $beforeWords  Number of characters to include before the match.
 * @param  int          $afterWords   Number of characters to include after the match.
 * @return string|false               Snippet with the match wrapped in <span>,
 *                                    or false when not found.
 */
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

/**
 * Remove all non-numeric values from an array.
 *
 * Iterates the array and unsets any entry where the value is not numeric.
 * Keys are preserved. Returns the filtered array.
 *
 * @param  array $array  Input array to filter.
 * @return array         Array containing only entries where is_numeric($value) is true.
 */
function leaveOnlyNumbers($array = []){
    foreach ($array as $k=>$v){
        if(!is_numeric($v))unset($array[$k]);
    }
    return $array;
}
/**
 * Sanitize a string for safe use as a filesystem filename.
 *
 * Transliterates Georgian (and other Unicode) characters to ASCII via
 * transliterate(), lowercases the result, strips a broad set of special
 * characters, replaces spaces and %20 with hyphens, collapses consecutive
 * separators, truncates to 80 characters, and trims leading/trailing
 * dots, hyphens, and underscores.
 *
 * @param  string $filename  Raw filename string (without extension).
 * @return string            Sanitized filename safe for storage.
 */
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

/**
 * Calculate elapsed execution time from a start timestamp.
 *
 * @param  float $startTimeStamp  Timestamp captured via microtime(true).
 * @return string                 Human-readable string: "execution time: X sec".
 */
function codeTimeTracker($startTimeStamp = ''){
    $time_end = microtime(true);
    $execution_time = ($time_end - $startTimeStamp);

    return "execution time: {$execution_time} sec";

}

/**
 * Return the list of field keys marked for display on the admin list view.
 *
 * Reads the field configuration for the given content type from adminpanel
 * config and returns the keys of fields where 'showOnAdminList' == 1.
 *
 * @param  string $contentType  Content type key (e.g. 'page', 'blog').
 * @param  string $locale       Locale code (currently unused; reserved).
 * @return array                List of field key strings, or [] when the
 *                              content type has no field config.
 */
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

/**
 * Transliterate Georgian Unicode characters to their ASCII equivalents.
 *
 * Maps each Georgian character to a Latin equivalent using a lookup table,
 * then passes the result through Str::slug() to produce a URL-safe string.
 * Non-Georgian characters pass through unchanged before slugification.
 *
 * @param  string $input    Input string, possibly containing Georgian characters.
 * @param  string $replace  Separator character for Str::slug(). Default '-'.
 * @return string           Transliterated, slugified ASCII string.
 */
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

/**
 * Generate a random alphanumeric UID string.
 *
 * Shuffles a repeated character pool of digits and lowercase letters and
 * returns a substring of the requested length. Not cryptographically secure —
 * suitable for UI identifiers and soft keys, not security tokens.
 *
 * @param  int $length  Desired length of the generated UID. Default 12.
 * @return string       Random alphanumeric string of the given length.
 */
function uidGenerator($length = 12){
    return substr(str_shuffle(str_repeat('1234567890qwertyuiopasdfghjklmnbvcxz', $length)), 0, $length);
}

/**
 * Filter a list of records to only include the specified dot-path keys.
 *
 * Converts $array to plain PHP arrays, then maps each item through
 * filter_keys_recursive() to retain only the paths listed in $filters.
 *
 * @param  array $array    List of records (objects or arrays).
 * @param  array $filters  List of dot-notation paths to retain (e.g. ['id', 'meta.title']).
 * @return array           List of records containing only the specified paths.
 */
function filterRecords($array, $filters) {
    $array = json_decode(json_encode($array), true);
    return array_map(function($item) use ($filters) {
        return filter_keys_recursive($item, $filters);
    }, $array);
}

/**
 * Recursively extract dot-path keys from a single record array.
 *
 * For each dot-notation path in $filters, traverses $arr following each
 * key segment and writes the resolved value into the output array at the
 * same nested path. Paths that do not exist in $arr are silently skipped.
 *
 * @param  array $arr      Source record to filter.
 * @param  array $filters  List of dot-notation paths to extract.
 * @return array           New array containing only the extracted paths.
 */
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

/**
 * Format a SKU by left-padding it with zeros to a fixed length.
 *
 * @param  string|int $sku     Raw SKU value.
 * @param  int        $length  Target string length. Default 9.
 * @return string              Zero-padded SKU string.
 */
function sku($sku='', $length=9){
    return str_pad($sku, $length,0, STR_PAD_LEFT);
}

/**
 * Resolve the current application session identifier.
 *
 * Checks, in order: the 'appSessionId' request header, the 'appSessionId'
 * cookie, then falls back to the Laravel session id. This allows API clients
 * that cannot use cookies to pass their own session token via a header.
 *
 * @return string  Session identifier string.
 */
function appSessionId(){

    if(request()->header('appSessionId')){
        return request()->header('appSessionId');

    }else if(isset($_COOKIE['appSessionId'])){
        return trim($_COOKIE['appSessionId']);
    }

    return session()->getId();
}


/**
 * Write a value to the file cache under a session-scoped key.
 *
 * When $data is falsy or an empty string, the cache entry is deleted.
 * Otherwise the value is stored with the given TTL in seconds.
 *
 * @param  string $key       Cache key to write.
 * @param  mixed  $data      Value to store; falsy/empty triggers deletion.
 * @param  int    $lifetime  TTL in seconds. Default 200.
 * @return mixed             The stored $data value, or false when deleted.
 */
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

/**
 * Read a value from the file cache by a session-scoped key.
 *
 * @param  string $key  Cache key to read.
 * @return mixed|false  Cached value, or false when the key is missing or empty.
 */
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
