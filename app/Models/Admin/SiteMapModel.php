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

    public function getListBy($params = [])
    {
        DB::enableQueryLog();
        $qr = DB::table($this->table)->select(DB::raw("{$this->table}.*,
        concat('[', GROUP_CONCAT(CONCAT('{\"module\":\"',modules_sitemap_relations.`table`, '\",\"id\":', modules_sitemap_relations.`table_id`, '}')), ']') as relatedModules
        "));
//        $qr = DB::table($this->table)->select(DB::raw("{$this->table}.*"));

        if (isset($params['menu_type'])) {
            $qr->where($this->table.'.menu_type', $params['menu_type']);
        }
        if (isset($params['id'])) {
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
        $query = DB::getQueryLog();

//            p($query);

        $list = _psql(_toArray($list));
        foreach ($list as $k=>$v){
            $list[$k]['media'] = $this->getMenuMeidas($v['media']);
        }

        return $list;
    }

    public function getKeyValListBy($params = [])
    {
        $list = $this->getListBy($params);
        $ret = [];
        foreach ($list as $k=>$v){
            $ret[$v['menu_type']] = $v['value'];
        }

        return $ret;
    }

    public function getOne($params = [])
    {
        if (paramsCheckFailed($params, ['id'])) return false;

        $ret = SiteMapModel::find($params['id']);

        $ret = _psqlRow(_toArray($ret));

        if(!_cv($ret,['id'], 'nn'))return [];

        $ret['media'] = $this->getMenuMeidas(_cv($ret,['media'], 'nn'));

        return $ret;
    }

    public function getAllMenus(){
        $res = $this->getListBy();

        return $res;
    }

    /**
     * custom update function
     */
    public function upd($params = [])
    {
//        p($params);

        if (!_cv($params, ['menu_type'])) {
            return false;
        }
        $upd = $params;
//            dd($upd);
        if (_cv($params,['id'], 'nn')) {
            $upd = SiteMapModel::find($params['id']);
        } else {

            $upd = new SiteMapModel();
            $upd->sort = 1;

        }
//        p($upd);
        $upd->menu_type = $params['menu_type']?$params['menu_type']:'main_menu';

        $upd->pid = $params['pid']?:'';
//        $upd->sort = $params['sort']?:'';
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
            $tmp = [];
            foreach ($params['media'] as $k=>$v){
                $tmp[$k] = isset($v[0])?_cv($v, '0.id', 'nn'):_cv($v, 'id', 'nn');
            }

//            $upd->media = _psqlupd($tmp);
            $upd->media = _psqlupd($params['media']);
        }

        $upd->save();

        $this->generateMenuForSite(true);

        return $upd->id;
//
//        return false;
    }

    public function sortMenu($params = []){
//        p($params['sordData']);
        foreach ($params['sordData'] as $k=>$v){
            $this->updateSortAndNesting($v);
        }
        return true;
    }

    public function setMenuHomePage($params = []){
// "useAsHomePage":1
        if(!_cv($params, 'id', 'nn'))return false;

        $upd = SiteMapModel::find($params['id']);
        $upd->set_home = $upd->set_home?0:1;
        $upd->save();
        DB::table($this->table)
            ->where('id','!=', $params['id'])
            ->update(['set_home' => 0]);
        return $this->getListBy();
    }

    public function updateSortAndNesting( $params = [] ){
        if (paramsCheckFailed($params, ['id'])) return false; //, 'pid', 'sort'

        $upd = SiteMapModel::find( $params['id'] );
        if( isset($params['pid']) && is_numeric($params['pid']) ){
            $upd['pid'] = $params['pid'];
        }
        if(is_numeric($params['sort'])){
            $upd['sort'] = $params['sort'];
        }

        return $upd->save();
    }

    /** set all child menu nodes as root menus
     * need after parent node deleting
     */
    public function deleteMenuNode($menuId = ''){
        if(!is_numeric($menuId))return false;

        $upd = SiteMapModel::where( 'pid', $menuId)->get()->toArray();

        foreach ($upd as $v){
            $this->changeMenuParam($v['id'], ['pid'=>0]);
        }
        SiteMapModel::where('id', $menuId)->delete();
    }

    public function changeMenuParam($menuId = '', $data = []){
        if(!is_numeric($menuId))return false;

        $upd = SiteMapModel::find($menuId);

        foreach ($data as $k=>$v){
            $upd->{$k} = _psqlupd($v);
        }
        $upd->save();
        return $upd->id;
    }

    /**
     * menu images main field is 'media'
     * under media there can be many other image fields
     * main image field is 'cover'
     */
    public function addMenuMedia($data = []){
        if( !_cv($data, 'menu_id', 'nn') || !_cv($data, 'field') || !_cv($data, 'file') )return false;

        $menuNode = $this->getOne(['id'=>$data['menu_id']]);
//p($menuNode);
        $data['media'] = _cv($menuNode, 'media','ar')?$menuNode['media']:[];
        $data['media'][$data['field']] = $data['file'];

        $changedId = $this->changeMenuParam($data['menu_id'], ['media'=>$data['media']]);
        $menuNode = $this->getOne(['id'=>$data['menu_id']]);
//        p($menuNode);

        return _cv($menuNode, 'media');

    }

    public function deleteMenuMedia($data = []){
        if( !_cv($data, 'menu_id', 'nn') || !_cv($data, 'field') )return false;

        $menuNode = $this->getOne(['id'=>$data['menu_id']]);

        $data['media'] = _cv($menuNode, 'media', 'ar')?$menuNode['media']:[];
        $data['media'][$data['field']] = '';

        return $this->changeMenuParam($data['menu_id'], ['media'=>$data['media']]);
    }

    public function getMenuMeidas($mediaField = []){
        if(!is_array($mediaField))return [];
        $MediaModel = new MediaModel();
        foreach ($mediaField as $k=>$v){
            if(!is_numeric($v)) continue;
            $mediaField[$k] = $MediaModel->getOne($v);
        }

        return $mediaField;

    }

    public function getMenuRouted($flatMenu = []){
        $flatMenu = $this->getListBy();
        $tmp = [];
        foreach ($flatMenu as $v){
            $tmp[$v['id']] = $v;
        }

        $ret = [];
        foreach ($tmp as $k=>$v){

            if($v['redirect_url']){
                $tmp[$k]['route'] = $v['redirect_url'];
                continue;
            }

            $route = $v['url_slug'];
            $pid = $v['pid'];
            for ($i=1; $i<=50; $i++){
                /// if menu doesnot exists
                if(!isset($tmp[$pid]))break;
                $route = $tmp[$pid]['url_slug'].'/'.$route;

                /// if menu has no parent
                if(!$tmp[$pid])break;
                $pid = $tmp[$pid]['pid'];

            }

            $tmp[$k]['route'] = $route;

        }

        return $tmp;
    }

    private function buildTree($data = []){

    }

    private function decodeValues($data)
    {

        $data = json_decode(json_encode($data),1);

//        if (!isset($data['data_type']) || !isset($data['value'])) {
//            return false;
//        }
//
//        if ($data['data_type'] != 'json') {
//            return $data;
//        }
//
//        $tmp = json_decode($data['value'], 1);
//        $tmp['id'] = $data['id'];
//        $tmp['menu_type'] = $data['menu_type'];
//
//        return $tmp;
        return $data;
    }

    /// get menu by full path
    public function getByPath($path = ''){
        $path = trim($path, '/');
        $res = DB::table($this->table)->select(['id','menu_type','pid','seo','titles','url_slug','media','configs','secondary_data','redirect_url'])->where('fullpath', $path)->where('redirect_url', '=', '')->first();

        if(!$res) $res = DB::table($this->table)->select(['id','menu_type','pid','seo','titles','url_slug','media','configs','secondary_data','redirect_url'])->where('set_home', 1)->first();

        $res = _psqlRow(_toArray($res));
        return $res;
    }

    public function generateMenuForSite($generate = false)
    {

        $value = Cache::store('file')->get('menuForSite');
        if ($value && !$generate) {
            return $value;
        }

        $res = $this->getListBy();
        $idAsKeys = [];
        foreach ($res as $k => $v) {
            $idAsKeys[$v['id']] = $v;
        }

        foreach ($idAsKeys as $k => $v) {
            $idAsKeys[$k]['fullUrls'] = $this->generateFullUrl($k, $idAsKeys);
        }

        $idAsKeys = array_values($idAsKeys);

        Cache::put('menuForSite', $idAsKeys, 1000000);

        return $idAsKeys;

    }

    public function generateFullUrl($menuId = '', $menuList=[]){
        $ret = [ 'urlRelatedShort'=>'/', 'fullUrlRelated'=>'/', 'l'=>0, 'p'=>[] ];

        if(!isset($menuList[$menuId]))return $ret;
        $menu = $menuList[$menuId];

        $host = request()->getSchemeAndHttpHost();
        $locales = config('app.locales');
        if(isset($menu['redirect_url']) && !empty($menu['redirect_url'])){

            $ret = [ 'fullUrlRelated'=>$menu['redirect_url'], 'l'=>1, 'p'=>[0] ];
            foreach ($locales as $k=>$v){
                if(strpos($menu['redirect_url'], '://')===false){
                    $ret[$k] = "/{$k}/{$menu['redirect_url']}";
                }else{
                    $ret[$k] = $menu['redirect_url'];
                }

            }

            return $ret;
        }
        $ret['fullUrlRelated'] = $menu['url_slug'];

        if(!isset($menu['configs']) || !is_array($menu['configs']) || array_search('exclude-from-url', $menu['configs'])===false){
            $ret['urlRelatedShort'] = $menu['url_slug'];
        }


        $ret['l'] = 1;
        $pId = $menu['pid'];

        for ($i=0; $i<=20; $i++){
            if($pId==0 || !isset($menuList[$pId]))break;
            $ret['fullUrlRelated'] = "{$menuList[$pId]['url_slug']}/{$ret['fullUrlRelated']}";

            if(!isset($menuList[$pId]['configs']) || !is_array($menuList[$pId]['configs']) || array_search('exclude-from-url', $menuList[$pId]['configs'])===false){
                $ret['urlRelatedShort'] = "{$menuList[$pId]['url_slug']}/{$ret['urlRelatedShort']}";
            }


            ++$ret['l'];
            $ret['p'][] = $pId;
            $pId = $menuList[$pId]['pid'];

        }

        foreach ($locales as $k=>$v){
//            $ret[$k] = "/{$k}/{$ret['fullUrlRelated']}";
            $ret[$k] = "/{$k}/{$ret['urlRelatedShort']}";
        }

        DB::table($this->table)->where('id', $menuId)->update(['fullpath'=>$ret['fullUrlRelated']]);
//        p($ret);
        return $ret;
    }

    public function thinMenu($menuList=[], $fields=['id'=>'id', 'title'=>'titles.ge.title', 'pid'=>'pid', 'fullUrl'=>'fullUrls.fullUrlRelated']){
        $ret = [];

        foreach ($menuList as $k=>$v){
            $tmp = [];
            foreach ($fields as $kk=>$vv){
                if(isset($v[$vv])){
                    $tmp[$kk] = $v[$vv];
                }else{
                    $tmp[$kk] = _cv($v, $vv);
                }
            }
            $ret[] = $tmp;
        }
        return $ret;
    }

    /** recursive method
     * searchs nearest parent menu with template
     * uses first found menu template
     */
    public function findSecondaryContentTemplateConfiguration($params=[]){
        if (paramsCheckFailed($params, ['id'])) return false;
        $currentMenu = $this->getOne(['id'=>$params['id']]);
        if(!_cv($currentMenu, 'id', 'nn'))return false;
        if(_cv($currentMenu, 'secondary_template'))return $currentMenu;
        if(!_cv($currentMenu, 'pid', 'nn'))return false;

        return $this->findSecondaryContentTemplateConfiguration(['id'=>$currentMenu['pid']]);

    }

    /////// sitemap module relation

    public static function doRelation($params = [])
    {
        if(!_cv($params, 'sitemap_id', 'nn') || !_cv($params, 'table_id', 'nn') || !_cv($params, 'table') )return false;

        self::removeRelations(['sitemap_id'=>$params['sitemap_id'], 'table_id'=>$params['table_id'], 'table'=>$params['table'] ]);

        DB::enableQueryLog();
        $ret = DB::table(self::$relationTable)->insert(
            [
                'sitemap_id'=>$params['sitemap_id'],
                'table_id'=>$params['table_id'],
                'table'=>$params['table'],
            ]
        );

        $query = DB::getQueryLog();

        $sitemap = new SiteMapModel();
        $sitemap->generateMenuForSite(true);

        return $ret;
    }

    public static function removeRelations($params = []){
//        p($params);
        if(!_cv($params, 'sitemap_id', 'nn') || !_cv($params, 'table') )return false;
        DB::enableQueryLog();
        $remove = DB::table(self::$relationTable)
            ->where('table', $params['table']);

        // if(_cv($params, 'table_id', 'nn')){
        //     $remove->where('table_id', $params['table_id']);

        // } elseif
        if (_cv($params, 'sitemap_id', 'nn')){
            $remove->where('sitemap_id', $params['sitemap_id']);
        }

        $remove->delete();

        $query = DB::getQueryLog();

        $sitemap = new SiteMapModel();
        $sitemap->generateMenuForSite(true);

        return false;

    }


}
