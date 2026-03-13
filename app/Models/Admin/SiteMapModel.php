<?php
namespace App\Models\Admin;

use App\Models\Media\MediaModel;
use Illuminate\Support\Facades\Cache;
use \Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SiteMapModel extends Model
{

    public $table = 'sitemap';
    public $timestamps = false;
    public static $relationTable = 'modules_sitemap_relations';

    const MENU_CACHE_TTL = 1000000; // ~11.5 days in seconds

//    protected $transformFields = [
//        'content_group' => 'contentGroup',
//    ];
    protected $fillable = [
        'menu_type',
        'pid',
        'updated_at',
        'inserted_at',
        'sort',
        'primary_template',
        'secondary_template',
        'seo',
        'titles',
        'url_slug',
        'media',
        'configs',
        'primary_page',
        'set_home',
        'secondary_data',
        'primary_content_type',
        'redirect_url',
        'url_target',
    ];

    /** clean value from regular table fields */
    protected $cleanFromFields = [
        'id',
        'menu_type',
        'pid',
        'updated_at',
        'inserted_at',
        'sort',
        'primary_template',
        'secondary_template',
        'seo',
        'titles',
        'url_slug',
        'media',
        'configs',
        'primary_page',
        'set_home',
        'secondary_data',
        'primary_content_type',
        'redirect_url',
        'url_target',
    ];

    private $rules = array(
        'menu_type' => 'required',
    );

    protected $hidden = [

    ];

    protected $attributes = [

    ];

    /**
     * Fetch sitemap menu nodes with their related module associations.
     *
     * Executes a LEFT JOIN against modules_sitemap_relations and aggregates
     * relation rows into a JSON array column 'relatedModules' via GROUP_CONCAT.
     * COALESCE ensures nodes with no relations return '[]' rather than null.
     *
     * Each row's 'media' field is resolved from raw media IDs to full media
     * records in a single batch query (one getList() call for all rows) rather
     * than one query per media item.
     *
     * Results are ordered by sort ASC, then id DESC.
     *
     * -------------------------------------------------------------------------
     * SUPPORTED $params KEYS
     * -------------------------------------------------------------------------
     * @param  array $params {
     *   'menu_type' string    — filter by menu type (e.g. 'main_menu').
     *   'id'        int       — filter to a single node by primary key.
     *   'ids'       int[]     — filter to a set of nodes by primary key.
     *   'set_home'  bool      — when truthy, return only the home-page node.
     * }
     *
     * -------------------------------------------------------------------------
     * RETURN VALUE
     * -------------------------------------------------------------------------
     * @return array  List of decoded menu node arrays, each containing all
     *                sitemap columns plus 'relatedModules' (array) and
     *                'media' (array of resolved media records).
     */
    public function getListBy($params = [])
    {
        $qr = DB::table($this->table)->select(DB::raw("{$this->table}.*,
        COALESCE(concat('[', GROUP_CONCAT(CONCAT('{\"module\":\"',modules_sitemap_relations.`table`, '\",\"id\":', modules_sitemap_relations.`table_id`, '}')), ']'), '[]') as relatedModules
        "));

        if (_cv($params, 'menu_type')) {
            $qr->where($this->table.'.menu_type', $params['menu_type']);
        }
        if (_cv($params, 'id', 'nn')) {
            $qr->where($this->table.'.id', $params['id']);
        }
        if (_cv($params, ['ids'], 'ar')) {
            $qr->whereIn($this->table.'.id', $params['ids']);
        }
        if (_cv($params, ['set_home'])) {
            $qr->where($this->table.'.set_home', 1);
        }

        $qr->leftJoin("modules_sitemap_relations", "modules_sitemap_relations.sitemap_id", "{$this->table}.id");

        $qr->groupBy("{$this->table}.id");
        $qr->orderBy("{$this->table}.sort", "ASC" );
        $qr->orderBy("{$this->table}.id", "DESC" );


        $list = $qr->get();
        $list = _psql(_toArray($list));

        $allMediaIds = [];
        foreach ($list as $v) {
            if (is_array($v['media'])) {
                foreach ($v['media'] as $mediaId) {
                    if (is_numeric($mediaId)) $allMediaIds[] = (int)$mediaId;
                }
            }
        }

        $mediaMap = $allMediaIds
            ? (new MediaModel())->getList(['ids' => array_unique($allMediaIds), 'idAsKey' => true])
            : [];

        foreach ($list as $k => $v) {
            $list[$k]['media'] = $this->getMenuMedias($v['media'], $mediaMap);
        }

        return $list;
    }

    /**
     * Fetch a single sitemap node by primary key with resolved media records.
     *
     * Loads the row via find(), decodes all JSON columns via _psqlRow(), then
     * resolves the 'media' field from raw IDs to full media records using a
     * single batch getList() call rather than one query per media item.
     *
     * -------------------------------------------------------------------------
     * PARAMS
     * -------------------------------------------------------------------------
     * @param  array $params {
     *   'id' int  — primary key of the sitemap node (required).
     * }
     *
     * -------------------------------------------------------------------------
     * RETURN VALUE
     * -------------------------------------------------------------------------
     * @return array|false  Decoded node array with resolved 'media' records.
     *                      [] when the record does not exist.
     *                      false when 'id' param is missing.
     */
    public function getOne($params = [])
    {
        if (paramsCheckFailed($params, ['id'])) return false;

        $ret = $this->find($params['id']);

        if (!$ret) return [];

        $ret = _psqlRow(_toArray($ret));

        $mediaIds = array_values(array_filter((array)_cv($ret, ['media'], 'ar'), 'is_numeric'));
        $mediaMap = $mediaIds
            ? (new MediaModel())->getList(['ids' => $mediaIds, 'idAsKey' => true])
            : [];

        $ret['media'] = $this->getMenuMedias($ret['media'], $mediaMap);

        return $ret;
    }

    public function getAllMenus(){
        $res = $this->getListBy();

        return $res;
    }

    /**
     * Insert or update a sitemap menu node.
     *
     * When 'id' is present, builds an Eloquent instance marked as existing
     * without issuing a SELECT — all fields are overwritten, so there is no
     * value in loading the current row first. When 'id' is absent, inserts
     * a new node with sort = 1.
     *
     * After saving, regenerates the full menu cache via generateMenuForSite().
     *
     * -------------------------------------------------------------------------
     * SUPPORTED $params KEYS
     * -------------------------------------------------------------------------
     * @param  array $params {
     *   'id'                 int     — primary key for update; omit to insert.
     *   'menu_type'          string  — required; menu group identifier.
     *   'pid'                int     — parent node id.
     *   'primary_template'   string  — primary template identifier.
     *   'secondary_template' string  — secondary template identifier.
     *   'seo'                array   — SEO meta fields; JSON-encoded on save.
     *   'titles'             array   — localised title map; JSON-encoded on save.
     *   'url_slug'           string  — URL segment for this node.
     *   'configs'            array   — config flags; JSON-encoded on save.
     *   'set_home'           int     — 1 to mark as home page node.
     *   'secondary_data'     array   — secondary content data; JSON-encoded.
     *   'primary_data'       array   — primary content data; JSON-encoded.
     *   'redirect_url'       string  — external or internal redirect target.
     *   'url_target'         string  — link target attribute (e.g. '_self', '_blank').
     *   'media'              array   — media field map; JSON-encoded on save.
     * }
     *
     * -------------------------------------------------------------------------
     * RETURN VALUE
     * -------------------------------------------------------------------------
     * @return int|false  The saved record's id on success.
     *                    false when 'menu_type' is missing.
     */
    public function upd($params = [])
    {
        if (!_cv($params, ['menu_type'])) {
            return false;
        }

        if (_cv($params, ['id'], 'nn')) {
            $upd = $this->newInstance([], true);
            $upd->setRawAttributes([$this->getKeyName() => $params['id']], true);
        } else {
            $upd = new SiteMapModel();
            $upd->sort = 1;
        }

        $upd->menu_type = $params['menu_type']?$params['menu_type']:'main_menu';
        $upd->pid = $params['pid']?:'';
        $upd->primary_template = $params['primary_template']?:'';
        $upd->secondary_template = $params['secondary_template']?:'';
        $upd->seo = _psqlupd(_cv($params, 'seo'));
        $upd->titles = _psqlupd(_cv($params, 'titles'));
        $upd->url_slug =  $params['url_slug']?:'';
        $upd->configs = _psqlupd(_cv($params, 'configs'));
        $upd->set_home = $params['set_home']?:'';
        $upd->secondary_data = _psqlupd(_cv($params, 'secondary_data'));
        $upd->primary_data = _psqlupd(_cv($params, 'primary_data'));
        $upd->redirect_url = $params['redirect_url']?:'';

        if(_cv($params, ['url_target'])){
            $upd->url_target = $params['url_target']?:'_self';
        }

        if(_cv($params, 'media', 'ar')){
            $upd->media = _psqlupd($params['media']);
        }

        $upd->save();

        $this->generateMenuForSite(true);

        return $upd->id;
    }

    /**
     * Batch-update sort order and parent nesting for a set of menu nodes.
     *
     * Collects all sort and pid values from the input list and executes at most
     * two UPDATE statements (one CASE WHEN for sort, one for pid) regardless of
     * how many nodes are being reordered — instead of one SELECT + UPDATE per node.
     *
     * Regenerates the full menu cache after the update.
     *
     * -------------------------------------------------------------------------
     * SUPPORTED $params KEYS
     * -------------------------------------------------------------------------
     * @param  array $params {
     *   'sordData' array  — list of node maps, each with:
     *                        'id'   int — primary key (required per item).
     *                        'sort' int — new sort position.
     *                        'pid'  int — new parent node id.
     * }
     *
     * -------------------------------------------------------------------------
     * RETURN VALUE
     * -------------------------------------------------------------------------
     * @return bool  true on success, false when 'sordData' is missing/empty
     *               or no valid node ids were found.
     */
    public function sortMenu($params = []){
        if (!_cv($params, 'sordData', 'ar')) return false;

        $sortCases = '';
        $pidCases  = '';
        $sortBindings = [];
        $pidBindings  = [];
        $ids = [];

        foreach ($params['sordData'] as $v) {
            if (!_cv($v, 'id', 'nn')) continue;
            $id = (int)$v['id'];
            $ids[] = $id;

            if (is_numeric($v['sort'] ?? null)) {
                $sortCases      .= "WHEN {$id} THEN ? ";
                $sortBindings[]  = (int)$v['sort'];
            }
            if (is_numeric($v['pid'] ?? null)) {
                $pidCases      .= "WHEN {$id} THEN ? ";
                $pidBindings[]  = (int)$v['pid'];
            }
        }

        if (empty($ids)) return false;

        $inList = implode(',', $ids);

        if ($sortCases) {
            DB::statement("UPDATE `{$this->table}` SET `sort` = CASE `id` {$sortCases} END WHERE `id` IN ({$inList})", $sortBindings);
        }
        if ($pidCases) {
            DB::statement("UPDATE `{$this->table}` SET `pid` = CASE `id` {$pidCases} END WHERE `id` IN ({$inList})", $pidBindings);
        }

        $this->generateMenuForSite(true);

        return true;
    }

    /**
     * Toggle the home-page flag for a menu node and clear it on all others.
     *
     * Reads the current set_home value with a single scalar query, computes
     * the toggled value, then applies a single CASE WHEN UPDATE across the
     * whole table — setting the target node and zeroing all others in one pass.
     *
     * Regenerates the full menu cache after the update.
     *
     * -------------------------------------------------------------------------
     * PARAMS
     * -------------------------------------------------------------------------
     * @param  array $params {
     *   'id' int  — primary key of the node to toggle (required).
     * }
     *
     * -------------------------------------------------------------------------
     * RETURN VALUE
     * -------------------------------------------------------------------------
     * @return array|false  Full menu list from getListBy() on success.
     *                      false when 'id' param is missing.
     */
    public function setMenuHomePage($params = []){
        if(!_cv($params, 'id', 'nn'))return false;

        $current  = DB::table($this->table)->where('id', $params['id'])->value('set_home');
        $newValue = $current ? 0 : 1;

        DB::statement(
            "UPDATE `{$this->table}` SET `set_home` = CASE WHEN `id` = ? THEN ? ELSE 0 END",
            [$params['id'], $newValue]
        );

        $this->generateMenuForSite(true);

        return $this->getListBy();
    }

    /**
     * Update the sort position and/or parent id of a single menu node.
     *
     * Issues a single targeted UPDATE — no SELECT is performed.
     * Only the fields present and numeric in $params are written.
     *
     * -------------------------------------------------------------------------
     * SUPPORTED $params KEYS
     * -------------------------------------------------------------------------
     * @param  array $params {
     *   'id'   int  — primary key of the node to update (required).
     *   'sort' int  — new sort position (optional).
     *   'pid'  int  — new parent node id (optional).
     * }
     *
     * -------------------------------------------------------------------------
     * RETURN VALUE
     * -------------------------------------------------------------------------
     * @return bool|false  true when the row was updated.
     *                     false when 'id' is missing or no valid fields provided.
     */
    public function updateSortAndNesting( $params = [] ){
        if (paramsCheckFailed($params, ['id'])) return false;

        $fields = [];
        if (isset($params['pid']) && is_numeric($params['pid']))   $fields['pid']  = (int)$params['pid'];
        if (isset($params['sort']) && is_numeric($params['sort'])) $fields['sort'] = (int)$params['sort'];

        if (empty($fields)) return false;

        return (bool) DB::table($this->table)->where('id', $params['id'])->update($fields);
    }

    /**
     * Delete a menu node and promote its direct children to root level.
     *
     * Re-parents all direct children (pid = $menuId) to root (pid = 0) in a
     * single UPDATE, then deletes the node row, then regenerates the menu cache.
     * Previously this issued one SELECT + one UPDATE per child (N+1).
     *
     * -------------------------------------------------------------------------
     * PARAMS
     * -------------------------------------------------------------------------
     * @param  int|string $menuId  Primary key of the node to delete.
     *
     * -------------------------------------------------------------------------
     * RETURN VALUE
     * -------------------------------------------------------------------------
     * @return int|false  The deleted node id on success.
     *                    false when $menuId is not numeric.
     */
    public function deleteMenuNode($menuId = ''){
        if(!is_numeric($menuId))return false;

        DB::table($this->table)->where('pid', $menuId)->update(['pid' => 0]);

        DB::table($this->table)->where('id', $menuId)->delete();

        $this->generateMenuForSite(true);

        return (int)$menuId;
    }

    /**
     * Update arbitrary columns on a single menu node.
     *
     * Each value is passed through _psqlupd() before writing — arrays are
     * JSON-encoded, scalars are stored as-is. Issues a single targeted UPDATE
     * without loading the model first.
     *
     * -------------------------------------------------------------------------
     * PARAMS
     * -------------------------------------------------------------------------
     * @param  int|string $menuId  Primary key of the node to update.
     * @param  array      $data    Column => value map of fields to write.
     *
     * -------------------------------------------------------------------------
     * RETURN VALUE
     * -------------------------------------------------------------------------
     * @return int|false  The node id on success.
     *                    false when $menuId is not numeric or $data is empty.
     */
    public function changeMenuParam($menuId = '', $data = []){
        if(!is_numeric($menuId) || empty($data))return false;

        $fields = [];
        foreach ($data as $k => $v){
            $fields[$k] = _psqlupd($v);
        }

        DB::table($this->table)->where('id', $menuId)->update($fields);

        return (int)$menuId;
    }

    /**
     * Add or replace a media entry on a menu node's media map.
     *
     * Reads the current 'media' column with a single scalar query (no full
     * row load), merges the new file into the map under the given field key,
     * persists via changeMenuParam(), then returns the updated media map
     * directly — no second DB read needed.
     *
     * -------------------------------------------------------------------------
     * PARAMS
     * -------------------------------------------------------------------------
     * @param  array $data {
     *   'menu_id' int    — primary key of the menu node (required).
     *   'field'   string — media map key to write (e.g. 'cover') (required).
     *   'file'    mixed  — file value to store under the field key (required).
     * }
     *
     * -------------------------------------------------------------------------
     * RETURN VALUE
     * -------------------------------------------------------------------------
     * @return array|false  Updated media map on success.
     *                      false when any required param is missing.
     */
    public function addMenuMedia($data = []){
        if( !_cv($data, 'menu_id', 'nn') || !_cv($data, 'field') || !_cv($data, 'file') )return false;

        $raw          = DB::table($this->table)->where('id', $data['menu_id'])->value('media');
        $decoded      = _psqlRow(['media' => $raw]);
        $data['media'] = _cv($decoded, 'media', 'ar') ? $decoded['media'] : [];
        $data['media'][$data['field']] = $data['file'];

        $this->changeMenuParam($data['menu_id'], ['media'=>$data['media']]);

        return $data['media'];

    }

    /**
     * Clear a media entry from a menu node's media map.
     *
     * Reads the current 'media' column with a single scalar query, sets the
     * given field key to an empty string, then persists via changeMenuParam().
     *
     * -------------------------------------------------------------------------
     * PARAMS
     * -------------------------------------------------------------------------
     * @param  array $data {
     *   'menu_id' int    — primary key of the menu node (required).
     *   'field'   string — media map key to clear (required).
     * }
     *
     * -------------------------------------------------------------------------
     * RETURN VALUE
     * -------------------------------------------------------------------------
     * @return int|false  The node id on success.
     *                    false when any required param is missing.
     */
    public function deleteMenuMedia($data = []){
        if( !_cv($data, 'menu_id', 'nn') || !_cv($data, 'field') )return false;

        $raw           = DB::table($this->table)->where('id', $data['menu_id'])->value('media');
        $decoded       = _psqlRow(['media' => $raw]);
        $data['media'] = _cv($decoded, 'media', 'ar') ? $decoded['media'] : [];
        $data['media'][$data['field']] = '';

        return $this->changeMenuParam($data['menu_id'], ['media'=>$data['media']]);
    }

    /**
     * Resolve raw media IDs in a media field map to full media record arrays.
     *
     * Iterates the map and replaces each numeric value (a media id) with the
     * corresponding media record. Non-numeric values (already-resolved records
     * or empty strings) are left untouched.
     *
     * When a pre-fetched $mediaMap is provided (id-keyed), lookups are O(1)
     * array access with no DB queries. When no map is provided, a MediaModel
     * instance is created lazily on first numeric entry and getOne() is called
     * per item — only use this path for single-node calls.
     *
     * -------------------------------------------------------------------------
     * PARAMS
     * -------------------------------------------------------------------------
     * @param  array $mediaField  Media map of field key => media id or record.
     * @param  array $mediaMap    Optional id-keyed map of pre-fetched media
     *                            records (from MediaModel::getList idAsKey).
     *
     * -------------------------------------------------------------------------
     * RETURN VALUE
     * -------------------------------------------------------------------------
     * @return array  The same map with numeric ids replaced by media records.
     *                Returns [] when $mediaField is not an array.
     */
    public function getMenuMedias($mediaField = [], $mediaMap = []){
        if(!is_array($mediaField))return [];
        $MediaModel = null;
        foreach ($mediaField as $k => $v){
            if(!is_numeric($v)) continue;
            $mediaField[$k] = $mediaMap
                ? ($mediaMap[$v] ?? false)
                : ($MediaModel ??= new MediaModel())->getOne($v);
        }

        return $mediaField;
    }

    /**
     * Annotate each menu node with a fully resolved 'route' string.
     *
     * For redirect nodes the route is the redirect_url value directly.
     * For normal nodes the route is built by walking up the pid chain and
     * prepending each ancestor's url_slug, up to a maximum depth of 50.
     *
     * Accepts an already-fetched flat menu list to avoid a redundant DB query
     * when the caller already has the data. Falls back to getListBy() when
     * no list is provided.
     *
     * -------------------------------------------------------------------------
     * PARAMS
     * -------------------------------------------------------------------------
     * @param  array $flatMenu  Optional flat list from getListBy(). When empty
     *                          or omitted, getListBy() is called internally.
     *
     * -------------------------------------------------------------------------
     * RETURN VALUE
     * -------------------------------------------------------------------------
     * @return array  Id-keyed map of menu nodes, each with an added 'route' key.
     */
    public function getMenuRouted($flatMenu = []){
        $flatMenu = $flatMenu ?: $this->getListBy();
        $tmp = array_column($flatMenu, null, 'id');
        foreach ($tmp as $k=>$v){

            if($v['redirect_url']){
                $tmp[$k]['route'] = $v['redirect_url'];
                continue;
            }

            $route = $v['url_slug'];
            $pid   = $v['pid'];
            for ($i = 1; $i <= 50; $i++) {
                if (!isset($tmp[$pid])) break;
                $route = $tmp[$pid]['url_slug'] . '/' . $route;
                $pid   = $tmp[$pid]['pid'];
            }

            $tmp[$k]['route'] = $route;

        }

        return $tmp;
    }

    /**
     * Resolve a sitemap node by its full URL path.
     *
     * First looks for a non-redirect node whose 'fullpath' matches the given
     * path (NULL and empty redirect_url are both excluded). Falls back to the
     * node flagged as home (set_home = 1) when no match is found.
     *
     * -------------------------------------------------------------------------
     * PARAMS
     * -------------------------------------------------------------------------
     * @param  string $path  Full URL path to look up; leading/trailing slashes
     *                       are stripped before matching.
     *
     * -------------------------------------------------------------------------
     * RETURN VALUE
     * -------------------------------------------------------------------------
     * @return array  Decoded node array on success; [] when no match and no
     *                home node exists.
     */
    public function getByPath($path = ''){
        $path    = trim($path, '/');
        $columns = ['id','menu_type','pid','seo','titles','url_slug','media','configs','secondary_data','redirect_url'];

        $res = DB::table($this->table)->select($columns)->where('fullpath', $path)->where(function($q){ $q->whereNull('redirect_url')->orWhere('redirect_url', '=', ''); })->first();

        if (!$res) $res = DB::table($this->table)->select($columns)->where('set_home', 1)->first();

        if (!$res) return [];

        return _psqlRow(_toArray($res));
    }

    /**
     * Build and cache the full annotated menu tree for the frontend.
     *
     * Returns the cached version when available and $generate is false.
     * When regenerating: fetches all nodes via getListBy(), computes full URLs
     * for each node via generateFullUrl(), then persists all fullpath values
     * back to the DB in a single batch CASE WHEN UPDATE (previously one UPDATE
     * per node). The result is stored in the file cache under 'menuForSite'.
     *
     * -------------------------------------------------------------------------
     * PARAMS
     * -------------------------------------------------------------------------
     * @param  bool $generate  When true, bypasses the cache and forces a full
     *                         rebuild. Default: false.
     *
     * -------------------------------------------------------------------------
     * RETURN VALUE
     * -------------------------------------------------------------------------
     * @return array  Sequential list of menu nodes, each with an added
     *                'fullUrls' map containing locale URLs and path metadata.
     */
    public function generateMenuForSite($generate = false)
    {

        $value = Cache::store('file')->get('menuForSite');
        if ($value && !$generate) {
            return $value;
        }

        $res      = $this->getListBy();
        $idAsKeys = array_column($res, null, 'id');

        $pathCases    = '';
        $pathBindings = [];

        foreach ($idAsKeys as $k => $v) {
            $idAsKeys[$k]['fullUrls'] = $this->generateFullUrl($k, $idAsKeys);
            $pathCases      .= "WHEN {$k} THEN ? ";
            $pathBindings[]  = $idAsKeys[$k]['fullUrls']['fullUrlRelated'];
        }

        if ($pathCases) {
            DB::statement(
                "UPDATE `{$this->table}` SET `fullpath` = CASE `id` {$pathCases} END WHERE `id` IN (" . implode(',', array_keys($idAsKeys)) . ")",
                $pathBindings
            );
        }

        $idAsKeys = array_values($idAsKeys);

        Cache::put('menuForSite', $idAsKeys, self::MENU_CACHE_TTL);

        return $idAsKeys;

    }

    /**
     * Compute the full URL metadata for a single menu node.
     *
     * Walks the ancestor chain via pid pointers to build two URL variants:
     *   - 'fullUrlRelated'  — full path including all ancestors' slugs.
     *   - 'urlRelatedShort' — same but skipping nodes flagged 'exclude-from-url'
     *                         in their configs array.
     *
     * For redirect nodes the redirect_url is used directly; absolute URLs
     * (containing '://') are stored as-is, relative ones are prefixed with
     * the locale key.
     *
     * Per-locale URL strings are added as top-level keys on the returned array.
     * The DB fullpath write has been moved to the caller (generateMenuForSite)
     * and is now batched — this method is pure computation only.
     *
     * -------------------------------------------------------------------------
     * PARAMS
     * -------------------------------------------------------------------------
     * @param  int|string $menuId    Primary key of the node to process.
     * @param  array      $menuList  Id-keyed flat menu map (from getListBy).
     *
     * -------------------------------------------------------------------------
     * RETURN VALUE
     * -------------------------------------------------------------------------
     * @return array{
     *   urlRelatedShort: string,
     *   fullUrlRelated: string,
     *   l: int,
     *   p: int[],
     *   ...<string, string>
     * }
     */
    public function generateFullUrl($menuId = '', $menuList=[]){
        $ret = [ 'urlRelatedShort'=>'/', 'fullUrlRelated'=>'/', 'l'=>0, 'p'=>[] ];

        if(!isset($menuList[$menuId]))return $ret;
        $menu = $menuList[$menuId];

        $locales = config('app.locales');
        if (!empty($menu['redirect_url'])) {

            $ret = [ 'fullUrlRelated'=>$menu['redirect_url'], 'l'=>1, 'p'=>[0] ];
            foreach ($locales as $k=>$v){
                if (!str_contains($menu['redirect_url'], '://')) {
                    $ret[$k] = "/{$k}/{$menu['redirect_url']}";
                }else{
                    $ret[$k] = $menu['redirect_url'];
                }

            }

            return $ret;
        }
        $includeInShort = fn($node) => !is_array($node['configs'] ?? null) || !in_array('exclude-from-url', $node['configs']);

        $ret['fullUrlRelated'] = $menu['url_slug'];

        if ($includeInShort($menu)) {
            $ret['urlRelatedShort'] = $menu['url_slug'];
        }

        $ret['l'] = 1;
        $pId = $menu['pid'];

        for ($i=0; $i<=20; $i++){
            if($pId==0 || !isset($menuList[$pId]))break;
            $ret['fullUrlRelated'] = "{$menuList[$pId]['url_slug']}/{$ret['fullUrlRelated']}";

            if ($includeInShort($menuList[$pId])) {
                $ret['urlRelatedShort'] = "{$menuList[$pId]['url_slug']}/{$ret['urlRelatedShort']}";
            }


            ++$ret['l'];
            $ret['p'][] = $pId;
            $pId = $menuList[$pId]['pid'];

        }

        foreach ($locales as $k=>$v){
            $ret[$k] = "/{$k}/{$ret['urlRelatedShort']}";
        }

        return $ret;
    }

    /**
     * Project a full menu list down to a slim field subset.
     *
     * Maps each node to only the fields specified in $fields, where each entry
     * is 'outputKey' => 'sourcePath'. Dot-notation paths (e.g. 'fullUrls.ge')
     * are resolved via _cv(); flat keys use direct array access.
     *
     * When $fields is omitted, defaults to id, pid, a locale-aware title
     * (resolved via requestLan()), and the fullUrlRelated path.
     *
     * -------------------------------------------------------------------------
     * PARAMS
     * -------------------------------------------------------------------------
     * @param  array $menuList  Flat menu list as returned by generateMenuForSite()
     *                          or getListBy().
     * @param  array $fields    Map of output key => source dot-path. Optional;
     *                          defaults to a locale-aware standard set.
     *
     * -------------------------------------------------------------------------
     * RETURN VALUE
     * -------------------------------------------------------------------------
     * @return array  Sequential list of projected node arrays.
     *                [] when $menuList is not an array.
     */
    public function thinMenu($menuList=[], $fields=[]){
        if (!is_array($menuList)) return [];

        if (empty($fields)) {
            $lan    = requestLan();
            $fields = ['id' => 'id', 'title' => "titles.{$lan}.title", 'pid' => 'pid', 'fullUrl' => 'fullUrls.fullUrlRelated'];
        }

        $ret = [];

        foreach ($menuList as $v){
            $tmp = [];
            foreach ($fields as $kk => $vv){
                $tmp[$kk] = str_contains($vv, '.') ? _cv($v, $vv) : ($v[$vv] ?? null);
            }
            $ret[] = $tmp;
        }
        return $ret;
    }

    /**
     * Walk up the menu ancestor chain to find the nearest node with a secondary template.
     *
     * Previously recursive with one getOne() DB query per hop. Now iterative,
     * using the cached menu from generateMenuForSite() — zero DB queries on
     * warm cache, one cache read on cold. Also eliminates the unbounded
     * recursion risk from pid cycles in the data.
     *
     * -------------------------------------------------------------------------
     * PARAMS
     * -------------------------------------------------------------------------
     * @param  array $params {
     *   'id' int  — primary key of the starting menu node (required).
     * }
     *
     * -------------------------------------------------------------------------
     * RETURN VALUE
     * -------------------------------------------------------------------------
     * @return array|false  The first ancestor node (inclusive) that has a
     *                      non-empty secondary_template.
     *                      false when 'id' is missing, the node is not found,
     *                      or no ancestor has a secondary template.
     */
    public function findSecondaryContentTemplateConfiguration($params=[]){
        if (paramsCheckFailed($params, ['id'])) return false;

        $menuList = $this->generateMenuForSite();
        $menuMap  = array_column($menuList, null, 'id');

        $id = $params['id'];
        while (isset($menuMap[$id])) {
            $node = $menuMap[$id];
            if (_cv($node, 'secondary_template')) return $node;
            if (!_cv($node, 'pid', 'nn')) return false;
            $id = $node['pid'];
        }

        return false;
    }

    /////// sitemap module relation

    /**
     * Replace the sitemap relation for a content record.
     *
     * Atomically removes any existing relation for the given sitemap_id + table
     * combination and inserts the new one inside a single DB transaction —
     * previously the delete and insert were separate unprotected operations.
     * Regenerates the menu cache after the write.
     *
     * -------------------------------------------------------------------------
     * SUPPORTED $params KEYS
     * -------------------------------------------------------------------------
     * @param  array $params {
     *   'sitemap_id' int    — sitemap node id to relate to (required).
     *   'table_id'   int    — primary key of the related content record (required).
     *   'table'      string — table name of the related content record (required).
     * }
     *
     * -------------------------------------------------------------------------
     * RETURN VALUE
     * -------------------------------------------------------------------------
     * @return bool|false  true on successful insert.
     *                     false when any required param is missing.
     */
    public static function doRelation($params = [])
    {
        if(!_cv($params, 'sitemap_id', 'nn') || !_cv($params, 'table_id', 'nn') || !_cv($params, 'table') )return false;

        $ret = DB::transaction(function () use ($params) {
            self::removeRelations(['sitemap_id'=>$params['sitemap_id'], 'table_id'=>$params['table_id'], 'table'=>$params['table'] ]);

            return DB::table(self::$relationTable)->insert([
                'sitemap_id' => $params['sitemap_id'],
                'table_id'   => $params['table_id'],
                'table'      => $params['table'],
            ]);
        });

        (new static())->generateMenuForSite(true);

        return $ret;
    }

    /**
     * Delete sitemap relations matching a sitemap_id + table combination.
     *
     * Used as the delete step inside doRelation()'s transaction before a fresh
     * relation is inserted. Cache regeneration is intentionally left to the
     * caller — previously this method regenerated the cache itself, causing a
     * double rebuild per doRelation() call.
     *
     * -------------------------------------------------------------------------
     * SUPPORTED $params KEYS
     * -------------------------------------------------------------------------
     * @param  array $params {
     *   'sitemap_id' int    — sitemap node id whose relations to remove (required).
     *   'table'      string — content table name to filter by (required).
     * }
     *
     * -------------------------------------------------------------------------
     * RETURN VALUE
     * -------------------------------------------------------------------------
     * @return bool|false  true on successful delete.
     *                     false when any required param is missing.
     */
    public static function removeRelations($params = []){
        if(!_cv($params, 'sitemap_id', 'nn') || !_cv($params, 'table') )return false;

        DB::table(self::$relationTable)
            ->where('table', $params['table'])
            ->where('sitemap_id', $params['sitemap_id'])
            ->delete();

        return true;
    }


}
