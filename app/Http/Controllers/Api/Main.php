<?php

namespace App\Http\Controllers\Api;

use App;
use App\Http\Controllers\Admin\Page;
use App\Models\Admin\FormBuilderModel;
use App\Models\Admin\RedirectionsModel;
use DateTime;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Validator;
use App\Models\User\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Admin\PageModel;
use App\Models\Admin\StockModel;
use App\Models\Admin\WordsModel;
use App\Models\Admin\OptionsModel;
use App\Models\Admin\SiteMapModel;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\TaxonomyModel;
use App\Http\Controllers\Admin\Shop;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Admin\Media;
use Illuminate\Support\Facades\Cache;
use App\Models\Admin\OnlineFormsModel;
use App\Http\Controllers\Admin\Widgets;
use App\Models\Shop\ProductsModel;

use App\Http\Controllers\Admin\OnlineForms;
use App\Http\Controllers\Admin\BankServices;
use App\Http\Transformers\AddressTransformer;
use Illuminate\Support\Facades\Cookie;
use function Symfony\Component\HttpFoundation\getCookies;
//use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;


/**
 * @OA\Info(title="Connect CMRS system API", version="0.1")
 * @OA\Server(url=L5_SWAGGER_CONST_HOST)
 */

class Main extends App\Http\Controllers\Api\ApiController
{
    //
    /**
     * Bootstrap endpoint — returns all global site environment data needed to initialise the frontend.
     *
     * Called once on app load (or per page server-render). Provides navigation, settings,
     * content-type registry, locale config, and media base URL. Everything the frontend
     * needs before it can render any page lives here.
     *
     * ── Request params ────────────────────────────────────────────────────────
     *
     *   lang   header   optional   Locale code (e.g. "en", "ge"). Resolved via requestLan().
     *                              Determines which locale is active and keys the cache entry.
     *
     *   qr     array    optional   GraphQL-like field selector. An array of dot-notation keys
     *                              to pluck from the full payload. When provided, only the
     *                              requested fields are returned — the rest are discarded.
     *                              Applied AFTER cache retrieval, so it never affects the
     *                              cached object.
     *
     *                              Example: ["thinMenu", "siteSettings", "locales"]
     *
     * ── Caching ───────────────────────────────────────────────────────────────
     *
     *   The full payload is cached per-locale in the file store under the key:
     *     "apiIndx_{locale}"
     *   TTL is controlled by env('CACHE_INDX', 2) (minutes).
     *   server_time is always injected after cache retrieval so it reflects actual
     *   server time regardless of cache state.
     *
     * ── Response fields ───────────────────────────────────────────────────────
     *
     *   locale             string    Active locale code (e.g. "en").
     *
     *   locales            array     All configured locales from config('app.locales').
     *
     *   siteMenus          array     Menu placement definitions from
     *                                config('adminpanel.site_menus'). Describes which
     *                                menu slots exist (e.g. "main", "footer").
     *
     *   sitePlaceHolders   array     UI placeholder strings from config('app.sitePlaceHolders').
     *
     *   static             string    Public storage base URL (from filesystems.disks.public.url).
     *                                Prepend to relative media paths returned by content APIs.
     *
     *   smartLayouts       array     Layout/component definitions from
     *                                config('adminpanel.smartLayouts').
     *
     *   contentTypes       array     Registered CMS content types with internal-only fields
     *                                stripped: title, route, slug_field, searchable, taxonomy,
     *                                fields, orderBy, relation. Safe to expose to the frontend.
     *
     *   menus              array     Full sitemap tree as produced by
     *                                SiteMapModel::generateMenuForSite(). Contains all menu
     *                                nodes with full URL resolution, titles, and configs.
     *
     *   thinMenu           array     Flattened menu list derived from menus. Each entry has:
     *                                  id        — sitemap row ID
     *                                  title     — resolved title for the active locale
     *                                  pid       — parent ID (0 = root)
     *                                  url       — relative URL string
     *                                  configs   — component/layout config array
     *                                  menu_type — slot identifier (e.g. "main", "footer")
     *                                Use this for rendering navigation; use menus only when
     *                                the full tree structure is needed.
     *
     *   siteSettings       array     Key/value map of all options in the
     *                                "site_configurations" options group. Contains runtime
     *                                site config (logo, contact info, social links, etc.).
     *
     *   cookies            string|null  Cookie consent description text for the active locale,
     *                                   sourced from options key "thirdPartyScripts".
     *                                   Null if not configured.
     *
     *   xrates             mixed     Exchange rates value from site_configurations.xrates.
     *                                Null if not configured.
     *
     *   server_time        string    Current server timestamp in format "D M d Y H:i:s O".
     *                                Always live — never served from cache.
     *
     * @OA\Post(
     *   path="/view/main/indx",
     *   tags={"Public website data"},
     *   summary="Bootstrap — returns all global site environment data for frontend initialisation",
     *   operationId="index",
     *   @OA\RequestBody(
     *     @OA\JsonContent(
     *       @OA\Property(property="qr", type="array", @OA\Items(type="string"),
     *         description="Optional field selector — dot-notation keys to pluck from the full payload",
     *         example={"thinMenu","siteSettings"})
     *     )
     *   ),
     *   @OA\Response(response="200", description="Full site bootstrap payload, or a subset when qr is provided")
     * )
     */
    public function index()
    {
        $request = Request();
        $locale  = requestLan();

        // One cache entry per locale — never keyed by qr
        $cacheKey = 'apiIndx_' . $locale;
        $payload  = Cache::store('file')->get($cacheKey);

        if (!$payload) {
            $payload = $this->buildIndexPayload($locale);
            Cache::put($cacheKey, $payload, env('CACHE_INDX', 2));
        }

        // Injected after cache retrieval so it always reflects actual server time
        $payload['server_time'] = date('D M d Y H:i:s O');

        // GraphQL-like field selection applied after cache — never pollutes cache keys
        $response = apiShortener($payload, $request->qr);

        return response()->json($response, 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Builds the full bootstrap payload for index().
     *
     * Private helper — not a route. Executes all DB and config reads needed
     * for the /view/main/indx response: options (site_configurations + general),
     * sitemap menus, content types, and layout configs. Extracted so the result
     * can be cached independently of server_time injection and qr field selection.
     *
     * @param  string $locale  Active locale code (e.g. "en").
     * @return array           Full payload array as documented in index().
     */
    private function buildIndexPayload(string $locale): array
    {
        $menuModel = new SiteMapModel();
        $content   = new PageModel();

        // Single query replaces three separate OptionsModel calls
        $optionRows = OptionsModel::select(['key', 'value', 'content_group'])
            ->where(function ($q) {
                $q->where('content_group', 'site_configurations')
                  ->orWhere('content_group', 'general');
            })
            ->get();

        $siteSettings = [];
        $cookies      = null;
        $xrates       = null;

        foreach ($optionRows as $row) {
            $val = _cv(_psqlRow(_toArray($row)), 'value');
            if ($row->content_group === 'site_configurations') {
                $siteSettings[$row->key] = $val;
                if ($row->key === 'xrates') $xrates = $val;
            } elseif ($row->key === 'thirdPartyScripts') {
                $cookies = _cv($val, ['description', $locale]);
            }
        }

        $menus = $menuModel->generateMenuForSite();

        return [
            'locale'           => $locale,
            'locales'          => config('app.locales'),
            'siteMenus'        => config('adminpanel.site_menus'),
            'sitePlaceHolders' => config('app.sitePlaceHolders'),
            'static'           => config('filesystems.disks.public.url'),
            'smartLayouts'     => config('adminpanel.smartLayouts'),
            'smartComponents'  => array_keys(config('adminpanel.smartComponents', [])),
            'contentTypes'     => $content->getContentTypes([
                'exclude' => ['title', 'route', 'slug_field', 'searchable', 'taxonomy', 'fields', 'orderBy', 'relation'],
            ]),
            'menus'            => $menus,
            'thinMenu'         => $menuModel->thinMenu($menus, [
                'id'        => 'id',
                'title'     => "titles.{$locale}.title",
                'pid'       => 'pid',
                'url'       => 'fullUrls.fullUrlRelated',
                'configs'   => 'configs',
                'menu_type' => 'menu_type',
            ]),
            'siteSettings'     => $siteSettings,
            'cookies'          => $cookies,
            'xrates'           => $xrates,
        ];
    }

    /**
     *    @OA\Post( path="/view/main/indxTranslatable", tags={"Public website data"}, summary="General data localised", operationId="indxTranslatable",
     *    @OA\Response( response="200", description="{}" )
     * )
     */
    public function indxTranslatable(){
        $options = new OptionsModel();
        $content = new PageModel();
        $widgets = new Widgets();
        $forms = new App\Models\Admin\FormBuilderModel();
//        $shopSiteMain = new Shop\ShopSiteMain();


        $ret['server_time'] = date('D M d Y H:i:s O');
        $ret['locale'] = requestLan(); //config('app.locale');

        $cacheKey = 'apiIndxTranslatable_'.$ret['locale'];
        $value = Cache::store('file')->get($cacheKey);
        if($value) return $value;

//        $ret['terms'] = $this->getAllTerms();
        $ret['widgets'] = $widgets->getWidgetsForSite(['locale'=>$ret['locale']]);
        $ret['generalPages'] = $content->getList(['slug'=>['privacy', 'terms-condition']]);

        $ret['smartForms'] = $forms->getListBy();
//        $ret['productsCategories'] = $shopSiteMain->getCategories();

        $cookies = $options->getSetting( 'thirdPartyScripts', 'general' );
        $ret['cookies'] = _cv($cookies, ['description', $ret['locale']]);

//        $ret['categoryAttributeTypes'] = $options->getListBy(['content_group'=>'shop_attribute_type']);

//        $ret['attributes'] = $attributes->getAllTerms();
//        $ret['attribute_configs'] = config('adminshop.attributes');
//        $ret['products_configs'] = config('adminshop.products');
//        $ret['productsCategories'] = $shopSiteMain->getCategories();

        Cache::put($cacheKey, response()->json($ret, 200, [], JSON_UNESCAPED_UNICODE), env('CACHE_INDX', 2));

        return response()->json($ret, 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Returns locale-resolved content for one or more named widgets.
     *
     * Widgets are reusable freeform content blocks managed in the admin panel
     * (stored in the `widgets` table). Each widget has a machine name, a
     * form_structure that defines its fields, and a content object that stores
     * values per locale and field index. This endpoint fetches the requested
     * widgets and collapses them into a flat, locale-resolved key/value map
     * ready for direct use by the frontend.
     *
     * ── Route ────────────────────────────────────────────────────────────────
     *
     *   POST /api/view/parts/getWidgets
     *   Dispatched via the dynamic parts/{method} router with cache.api middleware.
     *   $params is the full request body ($request->all()).
     *
     * ── Request params (POST body) ────────────────────────────────────────────
     *
     *   name   string|string[]   required   Machine name(s) of the widget(s) to fetch.
     *                                        Matched with whereIn, so a single string or
     *                                        an array of names are both accepted.
     *                                        Returns HTTP 201 with an error if omitted.
     *
     *   lang   header            optional   Locale code (e.g. "en", "ge"). Resolved via
     *                                        requestLan(). Determines which locale slice
     *                                        of the widget content is returned.
     *
     * ── Data flow ────────────────────────────────────────────────────────────
     *
     *   1. Validates that `name` is present; returns 201 error if not.
     *   2. Resolves the active locale via requestLan().
     *   3. Calls WidgetModel::getList() to fetch matching rows from `widgets`,
     *      JSON-decoding form_structure and content columns.
     *   4. WidgetModel::getProduction() reshapes each widget:
     *        - Iterates form_structure to get ordered field names.
     *        - Maps content[locale][fieldIndex] → fieldName for every locale.
     *        - Because locale is always provided here, further reduces to only
     *          the active locale: content[locale][fieldName] = value.
     *   5. Returns the resulting map keyed by widget machine name.
     *
     * ── Response shape ───────────────────────────────────────────────────────
     *
     *   A flat object keyed by widget machine name. Each value is an object of
     *   field-name → field-value pairs for the active locale:
     *
     *   {
     *     "<widgetName>": {
     *       "<fieldName>": <value>,
     *       "<fieldName2>": <value>,
     *       ...
     *     },
     *     "<widgetName2>": { ... },
     *     ...
     *   }
     *
     *   Widgets whose name matches but have no content for the active locale
     *   will appear as null under their key.
     *   Widgets not found in the DB are simply absent from the response.
     *
     * ── Error responses ──────────────────────────────────────────────────────
     *
     *   HTTP 201  { "error": "widget name not set" }  — `name` param missing.
     *
     * ── Widget DB structure (reference) ──────────────────────────────────────
     *
     *   widgets.name            string   Machine name identifier (unique per widget).
     *   widgets.form_structure  JSON     Array of field definitions: [{name, type, ...}, ...]
     *                                    Field order here maps to content indices.
     *   widgets.content         JSON     Locale-keyed content: { "en": { 0: val, 1: val }, "ge": {...} }
     */
    public function getWidgets($params = []){
        if(!_cv($params, 'name')) return response(['error'=>'widget name not set'], 201);

        $ret['locale'] = requestLan(); //config('app.locale');


        $widgets = new Widgets();

        $widgets = $widgets->getWidgetsForSite(['locale'=>$ret['locale'], 'name'=>_cv($params, 'name')]);
        return response($widgets);
    }

    /**
     * Returns locale-resolved taxonomy terms for one or more taxonomy types.
     *
     * Fetches all terms belonging to the requested taxonomy/taxonomies and returns
     * them grouped by taxonomy name. Translatable fields (e.g. title) are collapsed
     * to the active locale so the frontend receives flat objects without locale keys.
     * Intended for populating filter tabs, category selectors, and similar UI elements.
     *
     * ── Route ────────────────────────────────────────────────────────────────
     *
     *   POST /api/view/parts/getTerms
     *   Dispatched via the dynamic parts/{method} router with cache.api middleware.
     *   $params is the full request body ($request->all()).
     *
     * ── Request params (POST body) ────────────────────────────────────────────
     *
     *   taxonomy   string|string[]   required   Taxonomy machine name or array of names.
     *                                            A string is coerced to a single-element array.
     *                                            Each name must be registered in
     *                                            config('adminpanel.taxonomy') — unregistered
     *                                            names are silently skipped (return empty array).
     *                                            Returns HTTP 201 error if omitted entirely.
     *
     *   lang       header            optional    Locale code (e.g. "en", "ge"). Resolved
     *                                            internally by TaxonomyModel via requestLan().
     *                                            Determines which locale of translatable
     *                                            fields is returned.
     *
     * ── Registered taxonomy types (this project) ─────────────────────────────
     *
     *   service_category   Category tabs on the services list page.
     *   blog_category      Category tabs on the blog list page.
     *   faq_category       Category tabs on the FAQ page.
     *   branch_city        City filter on the branches page.
     *   offer_category     Category tabs on the corporate offers page.
     *
     * ── Data flow ────────────────────────────────────────────────────────────
     *
     *   1. Validates that `taxonomy` is present; returns 201 error if not.
     *   2. Coerces a single string to an array for uniform iteration.
     *   3. For each taxonomy name calls TaxonomyModel::getList() with:
     *        taxonomy      — the taxonomy machine name to filter by
     *        translate = 1 — resolves translatable meta fields for the active locale,
     *                        returning them under their base name (e.g. "title" not "title_en")
     *   4. Returns all results grouped under their taxonomy name key.
     *
     * ── Note on select behaviour ──────────────────────────────────────────────
     *
     *   The call passes `selectFields` but TaxonomyModel::getList() expects the key
     *   `select`. As a result selectFields is silently ignored and the effective SELECT
     *   is `taxonomy.*` plus LEFT-JOINed translated meta columns for the active locale.
     *   Each term therefore includes all base columns from the taxonomy table.
     *
     * ── Response shape ────────────────────────────────────────────────────────
     *
     *   A plain object (auto-serialised to JSON by Laravel) keyed by taxonomy name.
     *   Each value is an array of term objects ordered by `sort ASC`:
     *
     *   {
     *     "<taxonomyName>": [
     *       {
     *         "id":       int,     // term ID
     *         "pid":      int,     // parent term ID (0 = root)
     *         "slug":     string,
     *         "taxonomy": string,  // echoes the taxonomy name
     *         "sort":     int,
     *         "count":    int,     // usage count (maintained by CMS)
     *         "title":    string,  // locale-resolved translatable field
     *         ...                  // any other translatable meta fields defined in config
     *       },
     *       ...
     *     ],
     *     "<taxonomyName2>": [ ... ]
     *   }
     *
     *   Unregistered taxonomy names produce an empty array under their key.
     *   Unlike most other methods in this controller the return value is a plain
     *   array, not a Response object — Laravel serialises it automatically.
     *
     * ── Error responses ───────────────────────────────────────────────────────
     *
     *   HTTP 201  { "error": "taxonomy not set" }  — `taxonomy` param missing or empty.
     */
    public function getTerms($params = []){

        $taxonomies = _cv($params, 'taxonomy');
        if (!is_array($taxonomies)) {
            $taxonomies = [$params['taxonomy']];
        }
        if(!$taxonomies)return response(['error'=>'taxonomy not set'], 201);
        $terms = [];
        $taxModel = new TaxonomyModel();

        foreach ($taxonomies as $taxonomy) {
            // Fetch the list for the current taxonomy
            $taxonomyList = $taxModel->getList([
                'taxonomy' => $taxonomy,
                'translate' => 1,
                'selectFields' => "taxonomy.id, taxonomy.pid, taxonomy.slug"
            ]);

            // Assign the list to the corresponding taxonomy key
            $terms[$taxonomy] = $taxonomyList;
        }

        return $terms;

    }

    /// get first data for seo meta tags generation
    /**
     * SSR first-load endpoint — resolves a URL to its meta tags, SEO data, and page context.
     *
     * Called by the frontend on the very first page load to get all data needed for
     * server-side rendering (title, description, OG tags, cookie state, redirect target).
     * Handles URL redirections before any content resolution — if a redirection exists for
     * the requested path, returns a 302 redirect immediately.
     *
     * POST /api/view/main/firstLoader   (also called as an internal route by firstLoader.php)
     *
     * Params: none in body — URL and locale are derived from the request path.
     * Cookie: validCookies — included in the file-cache key so cookie consent state
     *         is reflected in cached responses per visitor consent level.
     *
     * Cache: "ssrImitationIndex_{base64(validCookie_uri)}" — TTL env('CACHE_INDX', 60) min.
     *
     * Response: MetaTagsGenerator::index() result — title, description, OG tags, hreflang,
     *           schema markup, and page bootstrap data for the resolved sitemap entry.
     */
    public function firstLoader(Request $request){

        $locales = config('app.locales');

        $validCookie = _cv($_COOKIE, 'validCookies')?$_COOKIE['validCookies']:'-';
        $url = $request->url();
        $uri = $request->path();

        $cacheNamePart = base64_encode("{$validCookie}_".substr($uri, 0, 88));
        $value = Cache::store('file')->get('ssrImitationIndex_'.$cacheNamePart);
        if($value) return $value;


        $langFromUrl = substr($request->path(), 0,2);
        $currentLang = isset($locales[$langFromUrl])?$langFromUrl:key($locales);
        $uriNoLan = str_replace("{$currentLang}/", '', $uri );

        $request->headers->set('lang', $currentLang);

        $RedirectionsModel = new App\Models\Admin\RedirectionsModel();

        $ret['redirectableUrl'] = $RedirectionsModel->getRedirectionUrl([ 'domain'=>$request->getHttpHost(), 'path'=>$uri ]);

//        return  response([]);
        if($ret['redirectableUrl']) return redirect($ret['redirectableUrl']);

        $parsedUrl = $this->urlParser();
        $siteMapModel = new SiteMapModel();
        $selectedMenu = $siteMapModel->getByPath( $parsedUrl['menuPath'] );

//        $cacheNamePart = isset($selectedMenu['id'])?base64_encode("{$validCookie}_{$selectedMenu['id']}_{$currentLang}"):"{$validCookie}_unknowpage_{$currentLang}";



        $MetaTags = new MetaTagsGenerator();

        $ret = $MetaTags->index(['validCookies'=>$validCookie, 'path'=>$uri, 'pageUrl'=>$url, 'lang'=>$currentLang, 'menuData'=>$selectedMenu, 'parsedUrl'=>$parsedUrl, 'menuId'=> _cv($selectedMenu, 'id', 'nn')]);

        Cache::put('ssrImitationIndex_'.$cacheNamePart, response($ret), env('CACHE_INDX', 60));

        return $ret;
    }

    /**
     * Returns the active third-party script/cookie data for the current visitor's consent state.
     *
     * POST /api/view/main/getValidCookiesData
     *
     * Reads the visitor's $_COOKIE superglobal and passes it to MetaTagsGenerator to
     * determine which cookie categories the visitor has consented to. Returns only the
     * script/tracking entries that are allowed under the current consent level.
     * Used by the frontend cookie consent module to activate permitted scripts after
     * the visitor updates their preferences.
     *
     * Params: none in body — consent state is read from $_COOKIE directly.
     * Response: array of active cookie/tracking script entries for the current visitor.
     */
    public function getValidCookiesData(Request $request){
//        p($request->all());
        $MetaTags = new MetaTagsGenerator();

        $ret = $MetaTags->getActiveCookiesData($_COOKIE);


        return response($ret);
    }

    /**
     * Returns the full thirdPartyScripts cookie consent configuration.
     *
     * POST /api/view/main/cookies
     *
     * Fetches the complete cookie/third-party scripts configuration from the options table
     * (key='thirdPartyScripts', group='general'). Used by the frontend to render the full
     * cookie consent modal with all available categories and their descriptions.
     * Unlike getValidCookiesData, this returns the full config regardless of visitor consent.
     *
     * Cache: "cookies_full" — Response object cached, TTL env('CACHE_INDX', 2) min.
     * Response: full thirdPartyScripts option value (JSON-decoded structure).
     */
    public function cookies()
    {
        $value = Cache::store('file')->get('cookies_full');
        if ($value) return $value;

        DB::enableQueryLog();

        $options = new OptionsModel();
        $cookies = $options->getSetting('thirdPartyScripts', 'general');

        Cache::put('cookies_full', response()->json($cookies, 200, [], JSON_UNESCAPED_UNICODE), env('CACHE_INDX', 2));

        return response()->json($cookies, 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Standard page-content endpoint — returns all component data for a given sitemap page.
     *
     * This is the single canonical API all frontend pages should call to retrieve their content.
     * It resolves a sitemap entry by ID, iterates its configured components, fetches each
     * component's data from the DB, and returns a keyed map ready for the frontend to render.
     *
     * ── Request params (POST body) ────────────────────────────────────────────
     *
     *   contentid        int       required   Sitemap row ID (matches sitemap.id).
     *                                         Identifies which page/menu entry to load.
     *
     *   singleview       int       optional   When present and non-zero, switches to
     *                                         "single item" mode for content (e.g. a detail
     *                                         page). Only components flagged singleLayout=1
     *                                         are returned. The value is used as the content
     *                                         item ID injected into primary components.
     *
     *   productview      int       optional   Same as singleview but for shop products.
     *                                         Triggers single-item mode and routes data
     *                                         fetching through ProductsModel.
     *
     *   content-category int       optional   Term ID used to filter components that have
     *                                         filterFromContentCategoryParam=1 and a taxonomy
     *                                         configured. Allows URL-driven category filtering
     *                                         without a separate API call.
     *
     *   lang             header    optional   Locale code (e.g. "en", "ge"). Resolved via
     *                                         requestLan(). Affects translated fields and
     *                                         the cache key.
     *
     * ── Modes ────────────────────────────────────────────────────────────────
     *
     *   List view   (default)  — no singleview/productview in request.
     *                            Only components with listLayout=1 are processed.
     *                            Cache TTL: config('app.cache_list_view', 60).
     *
     *   Single view            — singleview=<id> or productview=<id> in request.
     *                            Only components with singleLayout=1 are processed.
     *                            Primary components (primary=1) additionally receive
     *                            the single item ID so they return one specific record.
     *                            Cache TTL: config('app.cache_single_view', 60).
     *
     * ── Response shape ────────────────────────────────────────────────────────
     *
     *   {
     *     "secondary": {
     *       "<ComponentName>_<unicId>": {
     *         "data": {
     *           "list":      array,   // fetched content/product items
     *           "listCount": int,     // total matching records
     *           "page":      int,
     *           "pageCount": int
     *         },
     *         "conf": { ... }        // full component configuration from sitemap
     *       },
     *       ...
     *     }
     *   }
     *
     *   Each key in "secondary" is "<selectedComponent>_<unicId>" as defined in the
     *   sitemap's secondary_data array. Components with useContent=0 return an empty
     *   data array (conf is still included).
     *
     * ── Caching ──────────────────────────────────────────────────────────────
     *
     *   Responses are cached in the file store under the key:
     *     "getCurrentContent" + base64( json(request_body) + locale )
     *   A cache hit returns immediately before any DB queries are made.
     *
     * ── Component resolution ─────────────────────────────────────────────────
     *
     *   Components are defined in sitemap.secondary_data (JSON array). Each entry must:
     *     - have enabled=1
     *     - be registered in config/adminpanel.php under smartComponents
     *     - match the current view mode (listLayout / singleLayout)
     *
     *   The smartComponents config determines whether data is fetched via PageModel
     *   (type=content) or ProductsModel (type=product).
     *
     * @OA\Post(
     *   path="/view/main/getCurrentContent",
     *   tags={"Public website data"},
     *   summary="Standard page content loader — returns all component data for a sitemap page",
     *   operationId="getCurrentContent",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"contentid"},
     *       @OA\Property(property="contentid",   type="integer", example=14,  description="Sitemap page ID"),
     *       @OA\Property(property="singleview",  type="integer", example=42,  description="Content item ID for detail/single view"),
     *       @OA\Property(property="productview", type="integer", example=7,   description="Product ID for product detail view"),
     *       @OA\Property(property="content-category", type="integer", example=3, description="Term ID for URL-driven category filter")
     *     )
     *   ),
     *   @OA\Response(response="200", description="Keyed map of component payloads under 'secondary'")
     * )
     */
    public function getCurrentContent($menuId = false)
    {
        $request = Request();
        $singleRequest = $this->checkSingleRequestData($request->all());
        $locale = requestLan();

        $cacheKey = base64_encode(json_encode($request->all()) . $locale);
        $cacheType = $singleRequest['single'] ? 'cache_single_view' : 'cache_list_view';

        $value = Cache::store('file')->get('getCurrentContent' . $cacheKey);
        if ($value !== null) return response($value);

        /// if not set menu id ($contentid) return false
        if (!$menuId && $request->contentid) $menuId = $request->contentid;
        if (!is_numeric($menuId)) return [];

        $menuItem = new SiteMapModel();

        /// get requested menu
        $currentMenu = $menuItem->getOne(['id' => $menuId]);

        /// if can't find menu return false
        if (!isset($currentMenu['id'])) return [];
        $ret = [];

        /// select secondary content
        if (_cv($currentMenu, 'secondary_data')) {
            $ret['secondary'] = $this->getContentLoop($currentMenu['secondary_data'], $currentMenu['secondary_template'], $request->all(), $singleRequest);
        }

        Cache::store('file')->put('getCurrentContent' . $cacheKey, $ret, config('app.' . $cacheType, 60));

        return response($ret);
    }

    /**
     * Iterates a sitemap's component list and fetches data for each enabled component.
     *
     * Internal — called only by getCurrentContent(). Not a direct API route.
     * Filters components by view mode (listLayout vs singleLayout), validates each against
     * the smartComponents config registry, and delegates data fetching to getContentByMenuPlace().
     * Returns a keyed map of { "ComponentName_unicId": payload } ready for getCurrentContent()
     * to return as the "secondary" object.
     *
     * @param  array  $params         Component config array from sitemap.secondary_data.
     * @param  string $template       Secondary template name (currently unused in routing).
     * @param  array  $req            Raw request body, forwarded for content-category filtering.
     * @param  array  $singleRequest  Result of checkSingleRequestData() — single/type/id/key.
     * @return array  Keyed component payloads: { ComponentName_unicId: {data, conf} }
     */
    public function getContentLoop($params = [], $template = '', $req = [], $singleRequest=[])
    {

        $smartComponentsConfig = config("adminpanel.smartComponents");

        $singleView = $singleRequest['single'];
        //        $singleView = _cv($req, 'singleview', 'nn');

        $ret = [];
        foreach ($params as $k => $v) {
            /// continue if component not enabled
            if (_cv($v, 'enabled', 'nn') != 1)
                continue;
            /// if it is singleView get only singleview components;
            /// if it is singleView and primary continue;
            /// if it is listview data else get only listview components data
            if ($singleView && _cv($v, 'singleLayout', 'nn') != 1) {
                continue;
            } else if (!$singleView && _cv($v, 'listLayout', 'nn') != 1) {
                continue;
            }


            /// additional params for primary data
            if(_cv($v, 'primary', 'nn') == 1 && $singleRequest['single'] && $singleRequest['id']){
                $v['id'] = [$singleRequest['id']];
                $v['page'] = [$singleRequest['id']];
                $v['doPrimary'] = true;
                $v['useContent'] = 1;
            }

            $v['unicId'] = isset($v['unicId']) ? $v['unicId'] : null;
            $v['componentName'] = isset($v['selectedComponent']) ? $v['selectedComponent'] : null;

            /// if place does not exists continue
            if (!$v['componentName'] || !isset($smartComponentsConfig[$v['componentName']]))continue;

            $v['componentMainConfigs'] = $smartComponentsConfig[$v['componentName']];
            $v['porc'] = _cv($v['componentMainConfigs'], 'type');
            $v['content-category'] = _cv($req, 'content-category');

            $ret["{$v['componentName']}_{$v['unicId']}"] = (_cv($v, 'useContent', 'nn') == 1) ? $this->getContentByMenuPlace($v) : [];

        }

        return $ret;
    }

    /**
     * Fetches content or product data for a single component slot.
     *
     * Internal — called only by getContentLoop(). Not a direct API route.
     * Routes to PageModel::getContent() (porc='content') or ProductsModel (porc='product')
     * based on the component's type defined in smartComponents config.
     * Applies filterFromContentCategoryParam taxonomy filtering when configured.
     * For single-primary components (doPrimary=true), fetches and increments view count.
     *
     * @param  array $params  Merged component config from sitemap + singleRequest overrides.
     *                        Key fields: porc, useContent, contentType, taxonomy, page,
     *                        filterFromContentCategoryParam, doPrimary, primary, singleLayout.
     * @return array  { data: {list, listCount, page, pageCount}, conf: $params }
     */
    public function getContentByMenuPlace($params = [])
    {
//                p($params);
//        p(_cv( $params, 'porc' ));

        /// check component is for content or product; set content by default
        $params['porc'] = _cv($params, 'porc') ? $params['porc'] : 'content';

        $ret = [];

        $locale = requestLan();

        $params['header']   = _cv($params, 'header.' . $locale);
        $params['siteMode'] = siteMode();

        $componentIsSinglePrimary = (_cv($params, 'doPrimary') && _cv($params, 'primary') == 1 && _cv($params, 'singleLayout') == 1) ? true : false;

        /// filter secondary data (component data) by url content-category param (content-category means term id)
        /// if === filterFromContentCategoryParam checked AND url param content-category exists AND component has by taxonomy filter set some taxonomy
        if (_cv($params, 'filterFromContentCategoryParam') == 1 && _cv($params, 'content-category', 'nn') && _cv($params, 'taxonomy')) {
            $taxonomyModel = new TaxonomyModel();
            $taxonomy = $taxonomyModel->getTaxonomyByTermId(['id' => $params['content-category']]);
            if ($taxonomy === _cv($params, 'taxonomy')) {
                $params['terms'] = [$params['content-category']];
            }
        }

        if (_cv($params, 'useContent', 'nn') == 1 && $params['porc'] == 'product') {
            $params['selected_language'] = $locale;
            $params['translate']         = $locale;
            $params['status']            = 'published';
            $params['limit']             = _cv($params, 'limit', 'nn') ? $params['limit'] : 5;

            $product = new ProductsModel();
            if (_cv($params, 'doPrimary')) {
                $ret['data'] = $product->getProductsGrouped($params);
                $product->updateViewCount($params);
            } else {
                $ret['data'] = $product->getList($params);
            }

        } else if (_cv($params, 'useContent', 'nn') == 1 && $params['porc'] == 'content' && (isset($params['contentType']) || isset($params['taxonomy']) || isset($params['page']))) {

            $page = new PageModel();
            $params['translate'] = $locale;
            $ret['data'] = $page->getContent($params);

        }

        $ret['conf'] = $params;
        return $ret;

    }

    /**
     * Detects whether the request targets a single item (detail view) or a list view.
     *
     * Internal — called by getCurrentContent(). Checks for productview or singleview
     * in the request body and returns a normalised descriptor used to gate component
     * filtering and inject the item ID into primary components.
     *
     * @param  array $request  Raw request body ($request->all()).
     * @return array { single: bool, type: 'product'|'content'|'', id: int, key: string }
     */
    private function checkSingleRequestData($request = []){
        $ret = ['single'=>false, 'type'=>'', 'id'=>0, 'key'=>''];
        if(isset($request['productview']) && intval($request['productview'])){
            $ret = ['single'=>true, 'type'=>'product', 'id'=>intval($request['productview']), 'key'=>'productview'];
        }elseif(isset($request['singleview']) && intval($request['singleview'])){
            $ret = ['single'=>true, 'type'=>'content', 'id'=>intval($request['singleview']), 'key'=>'singleview'];
        }
        return $ret;
    }

    /**
     * @OA\Post( path="/view/main/getDataList", tags={"Public website data"}, summary="Get content list depend requested filter params", operationId="getDataList",
     *   @OA\Parameter( name="request params", description="request params", required=true, in="path",
     *     @OA\Schema(
     *      @OA\Property(property="id", type="int", example="14"),
     *      @OA\Property(property="componentUnicId", type="string", example="email@example.com"),
     *      @OA\Property(property="searchText", type="string", example="email@example.com"),
     *      @OA\Property(property="searchTerms", type="string", example="email@example.com"),
     *      @OA\Property(property="pageNumber", type="string", example="email@example.com"),
     *      @OA\Property(property="perPage", type="string", example="email@example.com"),
     *  ),),
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    /**
     * Dynamic paginated content list — the primary endpoint for frontend search, filter, and pagination.
     *
     * Operates in two modes depending on which params are supplied:
     *
     *   Component mode  — `id` + `componentUnicId` are both present.
     *                     Looks up the sitemap entry by `id`, finds the matching component
     *                     config by `unicId` inside secondary_data, and merges it with the
     *                     request. The component config (from admin panel) takes precedence
     *                     over request params for overlapping keys (array_merge order).
     *                     Use this when the frontend needs to honour the exact component
     *                     setup the admin has configured for a page slot.
     *
     *   Direct mode     — no `componentUnicId`; `contentType` is required instead.
     *                     Bypasses sitemap lookup entirely and queries content using only
     *                     the request params. Use this for generic content fetches that
     *                     are not tied to a specific sitemap component.
     *
     * Unlike getCurrentContent, this endpoint has NO file cache — every call hits
     * the DB. It is intended for interactive requests (search, filter, pagination)
     * where stale data is not acceptable.
     *
     * ── Route ────────────────────────────────────────────────────────────────
     *
     *   POST /api/view/main/getDataList   (explicit named route, no parts/* dispatch)
     *
     * ── Request params (POST body) ────────────────────────────────────────────
     *
     *   id                int              required (component mode)
     *                                      Sitemap menu ID. Used to look up the page entry
     *                                      and, when componentUnicId is set, to locate the
     *                                      component config in secondary_data.
     *
     *   contentType       string           required (direct mode)
     *                                      CMS content type machine name (e.g. "service",
     *                                      "blog"). Ignored when componentUnicId is set and
     *                                      the component config already defines contentType.
     *                                      Must not be "product" — use getProductDataList.
     *
     *   componentUnicId   string           optional
     *                                      Unique ID of a specific component slot within the
     *                                      sitemap's secondary_data array. When provided, its
     *                                      stored config (contentType, taxonomies, page_count,
     *                                      etc.) is merged with the request and takes precedence
     *                                      over request params for any overlapping keys.
     *
     *   searchText        string           optional
     *                                      Full-text search string forwarded to PageModel.
     *
     *   searchDate        array            optional
     *                                      Date filter. Two forms:
     *                                        [from]        — exact date prefix match (LIKE 'from%')
     *                                        [from, to]    — inclusive date range (>= from, <= to)
     *
     *   searchTerms       int|int[]|null   optional
     *                                      Taxonomy term filter. Three behaviours:
     *                                        int    — single term ID; taxonomy is auto-resolved
     *                                                 via getTaxonomyByTermId(), sets taxonomy + term.
     *                                        int[]  — multiple term IDs; each is resolved to its
     *                                                 taxonomy and grouped into taxonomies map
     *                                                 (AND logic within each taxonomy).
     *                                        empty  — clears term filter when `taxonomy` is also
     *                                                 sent (shows all items in that taxonomy).
     *
     *   taxonomies        object           optional
     *                                      Direct taxonomy filter map, bypassing searchTerms
     *                                      resolution. Format: { taxName: [termId, ...] }
     *                                      Uses AND logic between taxonomy groups.
     *                                      Overrides any taxonomies derived from searchTerms.
     *
     *   taxonomies_or     object           optional
     *                                      Same structure as taxonomies but applies OR logic
     *                                      between taxonomy groups.
     *
     *   taxonomy          string           optional
     *                                      Taxonomy name used together with empty searchTerms
     *                                      to reset the term filter (show all in taxonomy).
     *
     *   pageNumber        int              optional
     *                                      1-based page number for pagination.
     *                                      Alias of `page` — both are forwarded; pageNumber
     *                                      maps to PageModel's `page` param via getContent().
     *
     *   page              int              optional
     *                                      Direct page param, also forwarded. If both page
     *                                      and pageNumber are sent, pageNumber takes effect
     *                                      inside getContent(); `page` sets the component slot.
     *
     *   perPage           int              optional
     *                                      Items per page. Maps to page_count internally.
     *
     *   limit             int              optional
     *                                      Alias of perPage. If both are sent, limit wins
     *                                      (it is applied after perPage in the merge).
     *
     *   exclude           int              optional
     *                                      Page ID to exclude from results (e.g. current item
     *                                      on a detail page to avoid self-reference in related).
     *
     *   ids               int[]            optional
     *                                      Whitelist of specific page IDs to fetch.
     *                                      Applied as whereIn on pages.id.
     *
     *   pageOrder         string           optional
     *                                      Sort direction: "asc" or "desc" (case-insensitive).
     *                                      Validated against a whitelist — invalid values are
     *                                      silently ignored and the content-type default is used.
     *
     *   translate         string           optional
     *                                      Locale code override for field translation.
     *                                      Defaults to requestLan() (the lang header) if omitted.
     *
     *   lang              header           optional
     *                                      Locale code (e.g. "en", "ge"). Used as the default
     *                                      for `translate` when that param is not explicit.
     *
     * ── Error responses ───────────────────────────────────────────────────────
     *
     *   { "error": "use `getProductDataList` instead" }
     *     — content_type == "product" was passed.
     *
     *   { "error": "required menu `id` or `contentType`" }
     *     — neither a numeric id nor contentType was supplied.
     *
     *   { "error": "data not found" }
     *     — params resolved to an empty set after sitemap + component merge.
     *
     * ── Response shape ────────────────────────────────────────────────────────
     *
     *   {
     *     "list": [               // array of locale-resolved content items
     *       {
     *         "id":    int,
     *         "slug":  string,
     *         "title": string,    // locale-resolved
     *         ...                 // all fields defined for the content type
     *       },
     *       ...
     *     ],
     *     "listCount":      int,          // total matching records (before pagination)
     *     "page":           int,          // current page number
     *     "exectime":       float,        // server-side execution time in seconds
     *     "componentUnicId": string|null  // echoed from request
     *   }
     *
     * @OA\Post(
     *   path="/view/main/getDataList",
     *   tags={"Public website data"},
     *   summary="Paginated content list with search, filter, and taxonomy support",
     *   operationId="getDataList",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       @OA\Property(property="id",              type="integer", description="Sitemap menu ID (component mode)"),
     *       @OA\Property(property="contentType",     type="string",  description="Content type machine name (direct mode)"),
     *       @OA\Property(property="componentUnicId", type="string",  description="Component slot unicId within sitemap secondary_data"),
     *       @OA\Property(property="searchText",      type="string",  description="Full-text search"),
     *       @OA\Property(property="searchTerms",     description="Term ID (int), array of IDs, or empty to reset"),
     *       @OA\Property(property="taxonomies",      type="object",  description="Direct taxonomy filter map {taxName:[termId,...]}"),
     *       @OA\Property(property="pageNumber",      type="integer", description="Page number (1-based)"),
     *       @OA\Property(property="perPage",         type="integer", description="Items per page"),
     *       @OA\Property(property="exclude",         type="integer", description="Page ID to exclude"),
     *       @OA\Property(property="pageOrder",       type="string",  description="Sort direction: asc or desc")
     *     )
     *   ),
     *   @OA\Response(response="200", description="Paginated list with listCount, page, exectime, and componentUnicId")
     * )
     */
    public function getDataList()
    {

        $start = microtime(true);
        $request = Request();
        //        $request->merge(['contentType'=>'partners']); /// for testing

        if ($request->content_type == 'product') {
            return response(['error' => 'use `getProductDataList` instead']);
        }

        if (!is_numeric($request->id) && !$request->contentType) return response(['error' => 'required menu `id` or `contentType`']);
        $menuItem = new SiteMapModel();
        $page = new PageModel();
        $currentMenuPlaceParams = $request->all();
        $tmp = [];
        if ($request->componentUnicId) {
            /// get requested menu
            $currentMenu = $menuItem->getOne(['id' => $request->id]);
            foreach ($currentMenu['secondary_data'] as $k => $v) {
                if (_cv($v, 'unicId') == $request->componentUnicId) {
                    $tmp = $v;
                    break;
                }
            }
        }
        $currentMenuPlaceParams = array_merge($currentMenuPlaceParams, $tmp);
        if($request->exclude){
            $currentMenuPlaceParams['exclude'] = $request->exclude;
        }

        if($request->ids){
            $currentMenuPlaceParams['ids'] = $request->ids;
        }

        if (!$request->componentUnicId && $request->contentType) {
            $currentMenuPlaceParams['contentType'] = $request->contentType;
        }
        if (!$currentMenuPlaceParams)
            return response(['error' => 'data not found']);


        $ret = [];
        $currentMenuPlaceParams['page'] = $request->page;
        if ($request->searchText)
            $currentMenuPlaceParams['searchText'] = $request->searchText;
        if ($request->searchDate)
            $currentMenuPlaceParams['searchDate'] = $request->searchDate;
        if (is_numeric($request->pageNumber)) {
            $currentMenuPlaceParams['pageNumber'] = $request->pageNumber;
        }
        if (is_numeric($request->perPage)) $currentMenuPlaceParams['page_count'] = $request->perPage;
        if (is_numeric($request->limit)) $currentMenuPlaceParams['page_count'] = $request->limit;

        $taxonomyModel = new TaxonomyModel();


        if (is_numeric($request->searchTerms)) {
            $taxonomy = $taxonomyModel->getTaxonomyByTermId(['id' => $request->searchTerms]);
            $currentMenuPlaceParams['taxonomy'] = $taxonomy;
            $currentMenuPlaceParams['term'] = $request->searchTerms;
        } else if (!$request->searchTerms && $request->taxonomy) {
            /// if unselected all terms
            $currentMenuPlaceParams['taxonomy'] = $request->taxonomy;
            $currentMenuPlaceParams['term'] = '';
        } else if (is_array($request->searchTerms)) {

            foreach ($request->searchTerms as $v) {
                $taxonomy = $taxonomyModel->getTaxonomyByTermId(['id' => $v]);
                if (!$taxonomy)
                    continue;
                $currentMenuPlaceParams['taxonomies'][$taxonomy][] = $v;
            }
        }

        if ($request->taxonomies) {
            $currentMenuPlaceParams['taxonomies'] = $request->taxonomies;
        }

        if ($request->taxonomies_or) {
            $currentMenuPlaceParams['taxonomies_or'] = $request->taxonomies_or;
        }

        if (isset($request->contentType)) {
            $currentMenuPlaceParams['contentType'] = $request->contentType;
        }

        if(isset($request['translate'])){
            $currentMenuPlaceParams['translate'] = $request['translate'];
        } else{
            $currentMenuPlaceParams['translate'] = requestLan();
        }

        if($request->pageOrder){
            $allowedOrders = ['asc' => 0, 'desc' => 1];

            $pageOrder = (string) $request->pageOrder;
            $pageOrder = strtolower($pageOrder);

            if(isset($allowedOrders[$pageOrder])) {
                $currentMenuPlaceParams['page_order'] = $pageOrder;
            }
        }

        $ret = $page->getContent($currentMenuPlaceParams);

        $ret['exectime'] = microtime(true) - $start;
        $ret['componentUnicId'] = $request->componentUnicId;
        //        p($ret);
        return response($ret);
    }

    /**
     * Returns locale-resolved terms for every taxonomy registered in config.
     *
     * Iterates the full `config('adminpanel.taxonomy')` map and fetches terms for
     * each taxonomy in one loop, returning a single object keyed by taxonomy name.
     * Intended as a bulk alternative to getTerms() when the caller needs all
     * taxonomies at once rather than requesting them individually.
     *
     * ── Current usage ────────────────────────────────────────────────────────
     *
     *   Admin panel bootstrap — Admin\Main::index() includes this under the
     *   `terms` key so the CMS UI can populate every taxonomy dropdown without
     *   further requests.
     *
     *   Public API — the call inside Api\Main::indxTranslatable() is currently
     *   commented out. This method has no active public route and is not called
     *   by the frontend. Use getTerms() for public-facing taxonomy fetches.
     *
     * ── Request params ────────────────────────────────────────────────────────
     *
     *   None. Takes no input. Locale is resolved internally by TaxonomyModel
     *   via requestLan() (lang header).
     *
     * ── vs getTerms() ────────────────────────────────────────────────────────
     *
     *   getTerms($params)  — caller specifies which taxonomy/taxonomies to fetch;
     *                        passes selectFields (wrong key, silently ignored) so
     *                        effectively selects taxonomy.* + translated meta.
     *
     *   getAllTerms()       — no input; fetches every registered taxonomy; passes
     *                        the correct `select` key with an explicit lean column
     *                        list (id, pid, slug, sort, taxonomy) plus translated
     *                        meta fields for the active locale. Leaner rows, full
     *                        coverage.
     *
     * ── Data flow ────────────────────────────────────────────────────────────
     *
     *   1. Loads the full taxonomy registry from config('adminpanel.taxonomy').
     *   2. For each registered taxonomy key calls TaxonomyModel::getList() with:
     *        taxonomy  — the taxonomy machine name
     *        translate — 1, collapses translatable meta fields to the active locale
     *        select    — explicit column list: taxonomy.id, taxonomy.pid,
     *                    taxonomy.slug, taxonomy.sort, taxonomy.taxonomy
     *                    (plus JOINed locale-resolved meta columns, e.g. title)
     *   3. Results are ordered by sort ASC (TaxonomyModel default).
     *   4. Returns a plain array — no Response wrapper, auto-serialised by Laravel.
     *
     * ── Response shape ────────────────────────────────────────────────────────
     *
     *   A plain object keyed by every taxonomy machine name in config:
     *
     *   {
     *     "<taxonomyName>": [
     *       {
     *         "id":       int,     // term ID
     *         "pid":      int,     // parent term ID (0 = root)
     *         "slug":     string,
     *         "sort":     int,
     *         "taxonomy": string,  // echoes the taxonomy name
     *         "title":    string   // locale-resolved translatable field
     *         // ... any other translatable meta fields defined for this taxonomy
     *       },
     *       ...
     *     ],
     *     "<taxonomyName2>": [ ... ],
     *     ...
     *   }
     *
     *   Taxonomies with no terms in the DB return an empty array under their key.
     *   All registered taxonomies are always present as keys — none are omitted.
     *
     * ── No caching, no error conditions ──────────────────────────────────────
     *
     *   Results are always fetched live. There are no error return paths — invalid
     *   or misconfigured taxonomy entries in config simply produce empty arrays.
     */
    public function getAllTerms(){
        $terms = [];
        $taxonomy = config('adminpanel.taxonomy');
        $taxModel = new TaxonomyModel();

        foreach ($taxonomy as $k=>$v){
            $terms[$k] = $taxModel->getList(['taxonomy' => $k, 'translate'=>1, 'select'=>["taxonomy.id", "taxonomy.pid", "taxonomy.slug", "taxonomy.sort", "taxonomy.taxonomy"]]);
        }

        return $terms;

    }

    /**
     * @OA\Post( path="/view/main/search", tags={"Public website data"}, summary="get searched content", operationId="search",
     *   @OA\Parameter( name="request params", description="request params", required=true, in="path",
     *     @OA\Schema(
     *      @OA\Property(property="searchWord", type="string", example="some text"),
     *  ),),
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function search(Request $request)
    {
        $contentTypes = config('adminpanel.content_types');
        $searchableContentTypes = [];
        $searchableContentTypesTitles = [];

        foreach ($contentTypes as $key => $content)
        {
            if (!empty($content['searchable']))
            {
                $searchableContentTypes[] = $key;

                $searchableContentTypesTitles[$key] = $content['title'];
            }
        }

        if (empty($searchableContentTypes))
        {
            return [];
        }

        $searchRules = [
            'searchText' => 'bail|nullable|string|max:1000',
            'contentType' => 'bail|nullable|array|max:100',
            'contentType.*' => 'bail|required|string|max:300',
            'contentFields' => 'bail|nullable|array|max:50',
            'contentFields.*' => 'bail|required|string|max:300',
            'limit' => 'bail|nullable|integer|min:1|max:10000',
            'page' => 'bail|nullable|integer|min:1|max:10000'
        ];

        $input = $request->only(['searchText', 'contentType', 'contentFields', 'limit', 'page']);

        $searchValidator = \Validator::make($input, $searchRules);

        if ($searchValidator->fails() || !$request->searchText)
        {
            return [];
        }

        $contentFields = $input['contentFields'] ?? [];

        if (!empty($request->contentType))
        {
            $validInputContentTypes = [];

            foreach ($request->contentType as $contentType)
            {
                if (in_array($contentType, $searchableContentTypes))
                {
                    $validInputContentTypes[] = $contentType;
                }
            }

            if (!empty($validInputContentTypes))
            {
                $searchableContentTypes = $validInputContentTypes;
            }
        }

        $pageModel = new PageModel;
        $pageController = new Page;

        $fieldConfigs['fields'] = [
            'title' => [],
            //'meta_keys' => ['file', 'pdf_files', 'xhtml_files'],
        ];

        $fieldConfigs['regularFields'] = [
            'content_type' => ['dbFilter' => 'whereIn']
        ];

        $currentPage = $request->page ?: 1;

        $listFilter = [
            'content_type' => $searchableContentTypes,
            'status' => 'published',
            'searchText' => $request['searchText'],
            'translate' => 1,
            'fieldConfigs' => $fieldConfigs,
            'limit' => $request->limit ?: 10,
            'page' => $currentPage,
            'sortField' => 'content_type',
            'sortDirection' => 'asc'
        ];

        $list = $pageModel->getList($listFilter);
        $results = [];
        $normalizedSearchText = strtolower($request['searchText']);

        $filesFieldsByContentType = [
            'financial_results' => [
                'onlyFile' => true,
                'fields' => [
                    'file' => [
                        'type' => 'simple'
                    ]
                ]
            ],
            'annual_reports' => [
                'onlyFile' => true,
                'fields' => [
                    'pdf_files' => [
                        'type' => 'simple'
                    ],
                    'xhtml_files' => [
                        'type' => 'simple'
                    ]
                ]
            ],
            'investor_day_presentations' => [
                'onlyFile' => true,
                'fields' => [
                    'file' => [
                        'type' => 'simple'
                    ]
                ]
            ],
            'financial_statements' => [
                'onlyFile' => true,
                'fields' => [
                    'file' => [
                        'type' => 'simple'
                    ]
                ]
            ],
            'credit_ratings' => [
                'onlyFile' => false,
                'fields' => [
                    'file' => [
                        'type' => 'complex',
                        'parentField' => 'content'
                    ]
                ]
            ],
            'shareholder_meetings' => [
                'onlyFile' => false,
                'fields' => [
                    'file' => [
                        'type' => 'complex',
                        'parentField' => 'content'
                    ]
                ]
            ],
            'page' => [
                'onlyFile' => false,
                'fields' => [
                    'file' => [
                        'type' => 'complex',
                        'parentField' => 'content'
                    ]
                ]
            ],
            'news' => [
                'onlyFile' => false,
                'fields' => [
                    'file' => [
                        'type' => 'complex',
                        'parentField' => 'content'
                    ]
                ]
            ]
        ];

        foreach ($list['list'] as $item)
        {
            $filesConfigsByContentType = $filesFieldsByContentType[$item['content_type']];

            $fields = $filesConfigsByContentType['fields'];

            $matchedFiles = [];

            $contentContainsComplexField = false;
            $textFoundElsewhere = '';

            foreach ($fields as $field => $config)
            {
                if ($config['type'] === 'simple')
                {
                    if (!isset($item[$field]) || !is_array($item[$field]) || empty($item[$field])) continue;

                    foreach ($item[$field] as $fileData)
                    {
                        $normalizedFileUrl = strtolower($fileData['url']);
                        $normalizedFileTitle = strtolower($fileData['title'] ?? '');

                        if (!$normalizedFileTitle)
                        {
                            if (str_contains($normalizedFileUrl, $normalizedSearchText))
                            {
                                $matchedFiles[$field][] = [
                                    'matchedText' => $normalizedFileUrl,
                                    'url' => $fileData['url']
                                ];
                            }
                        }

                        else
                        {
                            if (str_contains($normalizedFileTitle, $normalizedSearchText))
                            {
                                $matchedFiles[] = [
                                    'matchedText' => $normalizedFileTitle,
                                    'url' => $fileData['url']
                                ];
                            }
                        }
                    }
                }

                else
                {
                    $contentContainsComplexField = true;

                    $complexFieldData = $item[$config['parentField']] ?? [];

                    if (!is_array($complexFieldData) || empty($complexFieldData)) continue;

                    foreach ($complexFieldData as $complexFieldDataItem)
                    {
                        if ($complexFieldDataItem['conf']['type'] !== 'file')
                        {
                            if ($complexFieldDataItem['conf']['type'] === 'editor' || $complexFieldDataItem['conf']['type'] === 'textarea')
                            {
                                if (str_contains(strtolower($complexFieldDataItem['data']), $normalizedSearchText))
                                {
                                    $textFoundElsewhere = $complexFieldDataItem['data'];
                                }
                            }

                            continue;
                        }

                        $complexFieldFilesData = $complexFieldDataItem['data'] ?? [];

                        if (!is_array($complexFieldFilesData) || empty($complexFieldFilesData)) continue;

                        foreach ($complexFieldFilesData as $fileData)
                        {
                            $normalizedFileUrl = strtolower($fileData['url']);
                            $normalizedFileTitle = strtolower($fileData['title'] ?? '');

                            if (!$normalizedFileTitle)
                            {
                                if (str_contains($normalizedFileUrl, $normalizedSearchText))
                                {
                                    $matchedFiles[$field][] = [
                                        'matchedText' => $normalizedFileUrl,
                                        'url' => $fileData['url']
                                    ];
                                }
                            }

                            else
                            {
                                if (str_contains($normalizedFileTitle, $normalizedSearchText))
                                {
                                    $matchedFiles[$field][] = [
                                        'matchedText' => $normalizedFileTitle,
                                        'url' => $fileData['url']
                                    ];
                                }
                            }
                        }
                    }
                }
            }

            if ($contentContainsComplexField)
            {
                if (!empty($matchedFiles))
                {
                    foreach ($matchedFiles as $fileFieldKey => $matchedFilesData)
                    {
                        foreach ($matchedFilesData as $matchedFileData)
                        {
                            $results[] = [
                                'id' => $item['id'],
                                'title' => $matchedFileData['matchedText'],
                                'url' => [$matchedFileData['url']],
                                'content_type' => $item['content_type'] ?? '',
                                'data' => ['date' => $item['date'], 'slug' => $item['slug']],
                                'content_type_title' => $searchableContentTypesTitles[$item['content_type']] ?? '',
                                'result_type' => 'file'
                            ];
                        }
                    }
                }

                if ($textFoundElsewhere)
                {
                    $results[] = [
                        'id' => $item['id'],
                        'title' => getSearchedTextPart($textFoundElsewhere, $request['searchText']),
                        'url' => $pageController->generateLinks(['content_id' => $item['id'], 'content_type' => $item['content_type'], 'slug' => $item['slug']]),
                        'content_type' => $item['content_type'] ?? '',
                        'data' => $item,
                        'content_type_title' => $searchableContentTypesTitles[$item['content_type']] ?? '',
                        'result_type' => 'content'
                    ];
                }

                else
                {
                    $foundText = '';

                    $keysToLookFor = ['title', 'teaser'];

                    foreach ($keysToLookFor as $keyToLookFor)
                    {
                        if ($foundText) break;

                        if (!empty($item[$keyToLookFor]) && str_contains(strtolower($item[$keyToLookFor]), $normalizedSearchText))
                        {
                            $foundText = $item[$keyToLookFor];
                        }
                    }

                    if ($foundText)
                    {
                        $results[] = [
                            'id' => $item['id'],
                            'title' => getSearchedTextPart($foundText, $request['searchText']),
                            'url' => $pageController->generateLinks(['content_id' => $item['id'], 'content_type' => $item['content_type'], 'slug' => $item['slug']]),
                            'content_type' => $item['content_type'] ?? '',
                            'data' => $item,
                            'content_type_title' => $searchableContentTypesTitles[$item['content_type']] ?? '',
                            'result_type' => 'content'
                        ];
                    }
                }
            }

            else
            {
                if (empty($matchedFiles))
                {
                    $results[] = [
                        'id' => $item['id'],
                        'title' => getSearchedTextPart($item['searchVal'], $request['searchText']),
                        'url' => $pageController->generateLinks(['content_id' => $item['id'], 'content_type' => $item['content_type'], 'slug' => $item['slug']]),
                        'content_type' => $item['content_type'] ?? '',
                        'data' => $item,
                        'content_type_title' => $searchableContentTypesTitles[$item['content_type']] ?? '',
                        'result_type' => 'content'
                    ];
                }

                else
                {
                    foreach ($matchedFiles as $fieFieldKey => $matchedFilesData)
                    {
                        foreach ($matchedFilesData as $matchedFileData)
                        {
                            $results[] = [
                                'id' => $item['id'],
                                'title' => $matchedFileData['matchedText'],
                                'url' => [$matchedFileData['url']],
                                'content_type' => $item['content_type'] ?? '',
                                'data' => [],
                                'content_type_title' => $searchableContentTypesTitles[$item['content_type']] ?? '',
                                'result_type' => 'file'
                            ];
                        }
                    }
                }
            }
        }

        if (!empty($contentFields))
        {
            foreach ($results as $resultIndex => $existingResult)
            {
                $data = [];

                foreach ($contentFields as $fieldName)
                {
                    $fieldValue = $existingResult['data'][$fieldName] ?? null;

                    if ($fieldValue !== null)
                    {
                        $data[$fieldName] = $fieldValue;
                    }
                }

                $results[$resultIndex]['data'] = $data;
            }
        }

        return [
            'list' => $results,
            'listCount' => $list['listCount'] ?? count($results),
            'page' => $list['page'] ?? $currentPage,
            'pageMatchCount' => count($results)
        ];
    }

    /// content Search
    /**
     * Searches pages_meta values for the given keyword and returns matching page records.
     *
     * Internal sub-method called by search(). Not a direct API route.
     * Executes a raw LIKE query on pages_meta.val limited to searchable content types,
     * then fetches full page records via PageModel::getList() for the matched IDs.
     * Returns up to 20 results ordered by date DESC.
     *
     * @param  string $searchWord  Keyword to search for.
     * @return array|Response  Array of full page records, or empty Response if no keyword.
     */
    public function inContentsearch($searchWord = '')
    {
//        DB::enableQueryLog();

//        p($request->all());
        if(!$searchWord)return response([]);

        $titleField = "title_".requestLan();
        $getSearchableContentTypes = "'".implode("','", $this->getSearchableContentTypes())."'";

        $ret['results'] = DB::select( DB::raw("SELECT pages.id, pages.content_type FROM pages_meta
                                        left  pages ON pages.id = pages_meta.data_id
                                        left join pages_meta as title ON title.data_id = pages.id and title.key='{$titleField}'
                                        where pages_meta.val like '%{$searchWord}%' AND pages.page_status = 1 AND pages.content_type IN ({$getSearchableContentTypes})
                                        group by pages.id, pages.content_type
                                        order by pages.date desc
                                        limit 20
                                        ") );

//        $query = DB::getQueryLog();
//        p($query);

        $ret['results'] = _toArray($ret['results']);
        $ids = array_column($ret['results'], 'id');

        $pages = new PageModel();
        $res = count($ids)?$pages->getList(['ids'=>$ids]):[];

        return $res;
    }

    /**
     * Searches sitemap titles for the given keyword and returns matching menu entries.
     *
     * Internal sub-method called by search(). Not a direct API route.
     * Executes a raw LIKE query on sitemap.titles (JSON column) limited to 20 results,
     * then extracts locale-resolved title and teaser for the active lang header.
     *
     * @param  string $searchWord  Keyword to search for.
     * @return array|Response  Array of { id, title, teaser }, or empty Response if no keyword.
     */
    public function inSitemapSearch($searchWord = '')
    {
//        DB::enableQueryLog();

        if(!$searchWord)return response([]);

        $results = DB::select( DB::raw("SELECT titles, id FROM sitemap
                                        where titles like '%{$searchWord}%'
                                        limit 20
                                        ") );

        $results = _psql(_toArray($results));

        $lan = requestLan();

        $res = [];
        foreach ($results as $k=>$v){
//            $results[$k]['titles'] = _cv($v['titles'], $lan);
            $res[] = [ 'id'=>$v['id'], 'title'=>_cv($v['titles'], [$lan, 'title']), 'teaser'=>_cv($v['titles'], [$lan, 'teaser'])?$v['titles'][$lan]['teaser']:'' ];
        }

        return $res;
    }

    /**
     * Searches page content and title fields, returning results with contextual snippets.
     *
     * Internal sub-method called by search(). Not a direct API route.
     * Queries pages_meta for the locale-specific title and content fields of searchable
     * content types. Uses getSearchedTextPart() to extract a snippet around the match.
     * Returns up to 20 results with: content_type, id, slug, key, title, val (snippet), url.
     *
     * @param  string $searchWord  Keyword to search for.
     * @return array|Response  Array of result objects with snippet, or empty Response if no keyword.
     */
    public function smallSearch($searchWord)
    {
// dd($searchWord);
        //        p($request->all());
        if(!$searchWord)return response([]);

        $titleField = "title_".requestLan();
        $contentField = "content_".requestLan();
        $getSearchableContentTypes = "'".implode("','", $this->getSearchableContentTypes())."'";

        $ret['results'] = DB::select( DB::raw("SELECT pages.slug,pages.id, pages.content_type, pages_meta.key, pages_meta.val, title.val as title FROM pages_meta
                                        left join pages ON pages.id = pages_meta.data_id
                                        left join pages_meta as title ON title.data_id = pages.id and title.key='{$titleField}'
                                        where (pages_meta.key='{$contentField}' or pages_meta.key='{$titleField}' ) and pages_meta.val like '%{$searchWord}%' AND pages.page_status = 1 AND pages.content_type IN ({$getSearchableContentTypes})
                                        group by pages.id
                                        limit 20
                                        ") ); //->keyBy('content_type');
//           p(_toArray($ret['results']));
        $ret['results'] = _psql(_toArray($ret['results']));
//p($ret['results']);
//        count($ret);

        $results = [];
        foreach ($ret['results'] as $k=>$v){

            $tmp = $v['val'];

            $tmp = getSearchedTextPart($tmp, $searchWord);


            if( $tmp == '' ) continue;

            $res = [];

            $res['content_type'] = $v['content_type'];
            $res['id'] = $v['id'];
            $res['url'] = '#';
            $res['key'] = $v['key'];
            $res['title'] = $v['title'];
            $res['val'] = $tmp;
            $res['slug'] = $v['slug'];

            $results[] = $res;
        }

        return $results;
    }

    /**
     * Returns the machine names of all content types marked as searchable in config.
     *
     * Internal helper used by inContentsearch() and smallSearch() to build the
     * IN clause that restricts search queries to content types with searchable=1
     * in config('adminpanel.content_types').
     *
     * @return string[]  Array of content type machine name strings.
     */
    public function getSearchableContentTypes(){
        $contentTypes = config('adminpanel.content_types');
        $ret = [];
        foreach ($contentTypes as $k=>$v){
            if(isset($v['searchable']) && $v['searchable']==1) $ret[] = $k;
        }
        return $ret;
    }


    /**
     * Returns the full UI string translation dictionary for all locales.
     *
     * Exposes the entire contents of the `words` table so the frontend can
     * perform client-side string translation without per-string API calls.
     * This is the public counterpart of the server-side tr() helper — both
     * read from the same `words` table.
     *
     * ── Route ────────────────────────────────────────────────────────────────
     *
     *   ANY /api/view/main/getTranslations
     *   Explicit named route (any method accepted; POST is the intended method).
     *
     * ── Request params ────────────────────────────────────────────────────────
     *
     *   None. No locale filtering — always returns all locales in one payload.
     *   The lang header is not read by this method; locale selection is left
     *   to the frontend to slice the response by its active locale.
     *
     * ── Caching ───────────────────────────────────────────────────────────────
     *
     *   The full response object is cached in the file store under the fixed key
     *   "wordtranslations" (locale-agnostic, all locales together).
     *   TTL: env('CACHE_STRINGS', 2) minutes.
     *
     *   Note: the cache stores response($translation) — a Response object — not
     *   the raw array. On a cache hit the Response is returned directly, bypassing
     *   all DB queries. On a miss the Response is built fresh and then cached.
     *
     * ── Data flow ────────────────────────────────────────────────────────────
     *
     *   1. File-cache lookup under "wordtranslations" — returns immediately on hit.
     *   2. WordsModel::getBy() — fetches all rows from `words` ordered by key ASC,
     *      selecting id, key, value. The `value` column is JSON-decoded to an array
     *      of locale → translation pairs: { "en": "...", "ge": "..." }.
     *   3. wordsByLan('') — called with an empty locale string, so the locale
     *      shortcut (return only one locale) is never triggered. Iterates every
     *      word and every configured locale, building:
     *        res[locale][key] = translation  (falls back to key itself if missing)
     *   4. Returns the full res map containing every locale. Caches the Response.
     *
     * ── words table structure (reference) ────────────────────────────────────
     *
     *   words.key    string   Translation key — lowercased, stripped, max 200 chars.
     *                         Used as the lookup token by the tr() helper and as the
     *                         fallback value when a translation is absent for a locale.
     *   words.value  JSON     Locale-keyed translations: { "en": "text", "ge": "text" }
     *                         New keys are auto-created by tr() with empty translations
     *                         when an unknown key is encountered server-side.
     *
     * ── Response shape ────────────────────────────────────────────────────────
     *
     *   A nested object keyed first by locale code, then by translation key:
     *
     *   {
     *     "en": {
     *       "translation key":   "English text",
     *       "another key":       "Another English text",
     *       ...
     *     },
     *     "ge": {
     *       "translation key":   "Georgian text",
     *       "another key":       "Georgian text",
     *       ...
     *     }
     *   }
     *
     *   If a word has no translation for a given locale, the key string itself
     *   is returned as the value (same fallback behaviour as the tr() helper).
     *   All configured locales (config('app.locales')) are always present as keys.
     *
     * @OA\Post(
     *   path="/view/main/getTranslations",
     *   tags={"Public website data"},
     *   summary="Full UI string translation dictionary for all locales",
     *   operationId="getTranslations",
     *   @OA\Response(
     *     response="200",
     *     description="Nested object: { locale: { key: translation } } for every configured locale"
     *   )
     * )
     */
    public function getTranslations(){
        $value = Cache::store('file')->get('wordtranslations');
        if($value) return $value;

        $words = new WordsModel();
        $translation = $words->wordsByLan();

        Cache::put('wordtranslations', response($translation), env('CACHE_STRINGS', 2));

        return response($translation);
    }

    /**
     * Registers a new translation key — read-through, write on miss.
     *
     * The frontend counterpart of the server-side tr() auto-creation behaviour.
     * When the frontend encounters a UI string not yet in the translation dictionary,
     * it calls this endpoint to register the key. If the key already exists the
     * existing row is returned without any write. If it is new, a row is created
     * with the key itself as the placeholder translation for every locale.
     *
     * ── Route ────────────────────────────────────────────────────────────────
     *
     *   ANY /api/view/main/adwrd
     *   Explicit named route (any method accepted; POST is the intended method).
     *
     * ── Request params (POST body) ────────────────────────────────────────────
     *
     *   wrd   string   required   The translation key to look up or register.
     *                             Normalized before storage via cleanKey():
     *                               strtolower( trim( strip_tags( $key ) ) )
     *                             truncated to 200 characters.
     *                             Returns a plain empty array [] — not a Response
     *                             object — if omitted or falsy.
     *
     * ── Data flow ────────────────────────────────────────────────────────────
     *
     *   1. Guards against missing wrd — returns [] immediately (no Response wrap).
     *   2. getOne(['key' => wrd]) — looks up the normalized key in the words table.
     *      cleanKey() is applied internally so the lookup always uses the stored form.
     *   3. Key found   → returns the existing row immediately. No write occurs.
     *   4. Key missing → calls upd(['key' => wrd]) which:
     *        a. Applies cleanKey() to normalize the key.
     *        b. Re-checks for an existing row (double-check inside upd, guards
     *           against race conditions between the getOne and upd calls).
     *        c. Creates a new WordsModel instance if still not found.
     *        d. Sets value JSON to { locale: key } for every configured locale —
     *           the key string itself is the placeholder translation.
     *        e. Sets changed = 1, saves, returns the new row ID (int).
     *   5. Returns response(newId) wrapping the integer ID of the created row.
     *
     * ── Key normalisation ─────────────────────────────────────────────────────
     *
     *   cleanKey() lowercases, trims whitespace, strips HTML tags, and truncates
     *   to 200 characters. The stored key may therefore differ from the raw input.
     *   Callers should use the same normalisation when looking up translations in
     *   the getTranslations() dictionary.
     *
     * ── Cache behaviour ───────────────────────────────────────────────────────
     *
     *   Creating a new key does NOT invalidate the "wordtranslations" file cache.
     *   The new key will not appear in getTranslations() responses until the cache
     *   expires (env('CACHE_STRINGS', 2) minutes).
     *
     * ── Response shape ────────────────────────────────────────────────────────
     *
     *   Three possible return values depending on the code path taken:
     *
     *   []                        Plain empty array (not a Response). wrd was missing.
     *
     *   response($word)           Existing word row — key already existed, no write:
     *                             {
     *                               "id":    int,
     *                               "key":   string,
     *                               "value": { "en": "...", "ge": "..." }
     *                             }
     *
     *   response($newId)          Integer ID of the newly created row. All locale
     *                             translations are pre-filled with the key string itself
     *                             as a placeholder, matching the tr() fallback behaviour.
     *
     * @OA\Post(
     *   path="/view/main/adwrd",
     *   tags={"Public website data"},
     *   summary="Register a translation key — returns existing row or creates with key-as-placeholder translations",
     *   operationId="adwrd",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"wrd"},
     *       @OA\Property(property="wrd", type="string", example="submit button label",
     *         description="Translation key to look up or register. Normalized via cleanKey() before storage.")
     *     )
     *   ),
     *   @OA\Response(response="200",
     *     description="Existing word row (object) if key existed, or new row ID (int) if created")
     * )
     */
    public function adwrd(Request $request){
//        p($request->wrd);
        if(!$request->wrd)return [];

        $words = new WordsModel();
        $word = $words->getOne(['key'=>$request->wrd]);
        if(isset($word['id']))return response($word);

        $translation = $words->upd(['key'=>$request->wrd]);
        return response($translation);
    }


    /**
     *    @OA\Post( path="/view/main/getServiceCenters", tags={"Public website data"}, summary="Get service centers list", operationId="getServiceCenters",
     *    @OA\Response( response="200", description="{}" )
     * )
     */
    public function getServiceCenters(Request $request){
        $data = new PageModel();
        $ret = $data->getList(['content_type'=>'serviceCenters']);

        return response($ret);
    }

    /**
     * Handles frontend form submissions — validates, persists, and dispatches email notifications.
     *
     * Orchestrates up to four concerns depending on the form type and its configuration:
     * reCAPTCHA verification, duplicate/custom validation, DB persistence, and email dispatch.
     * Behaviour is driven by the form's entry in config('adminpanel.onlineForms') and by
     * whether a matching FormBuilder record exists in the DB.
     *
     * ── Route ─────────────────────────────────────────────────────────────────
     *
     *   ANY /api/view/main/saveSubmitedForm
     *   Explicit named route (any method; POST is the intended method).
     *
     * ── Request params (POST body) ────────────────────────────────────────────
     *
     *   formType              string   required   Machine name of the form. Must match a key
     *                                             in config('adminpanel.onlineForms') for
     *                                             config-driven behaviour; unknown types fall
     *                                             back to the generic save path.
     *
     *   g-recaptcha-response  string   required   Google reCAPTCHA v2 token. Validated before
     *                                             any write or email dispatch. Stripped from
     *                                             the data saved to the DB.
     *
     *   pac                   any      internal   Honeypot / internal field. Always stripped
     *                                             from the data before processing.
     *
     *   ...                   any      optional   All other request fields are treated as form
     *                                             payload and passed through to persistence and
     *                                             email handlers verbatim.
     *
     * ── Registered form types (this project) ─────────────────────────────────
     *
     *   contact-form    disableSave: false — DB save skipped; relies on FormBuilder email.
     *   subscribeForm   disableSave: false, validate: {unique: 'email'} — duplicate email
     *                   check triggers early return before any save or email.
     *
     * ── Execution branches (in order) ────────────────────────────────────────
     *
     *   1. SANITISE
     *      pac and g-recaptcha-response are removed from $data before any further use.
     *      reCAPTCHA token is validated via OnlineForms::validateReCaptcha() — result
     *      stored for use as a gate in branches 2 and 4 below.
     *
     *   2. VALIDATION PATH (early return — skips all subsequent branches)
     *      Condition: formType is in onlineForms config AND has a `validate` key.
     *      Calls OnlineForms::validateForm() which currently supports:
     *        unique: [field]  — checks the `forms` table for an existing row with the
     *                           same formType and matching field value. Returns an error
     *                           array listing duplicated fields, or a success response.
     *      Returns immediately — no reCAPTCHA gate, no DB save, no email dispatch.
     *
     *   3. SAVE PATH
     *      Condition: formType NOT found in onlineForms config,
     *                 OR formType found and its `disableSave` value is truthy.
     *      Note: the variable name `disableSave` is inverted — truthy enables this save
     *      branch, falsy skips it. Both current form types have disableSave: false so
     *      this branch is skipped for them; unknown/unconfigured form types always save.
     *      When active: if reCAPTCHA passed → OnlineForms::saveForm($data) persists the
     *      payload to the `forms` table; otherwise $ret = reCAPTCHA failure details.
     *
     *   4. CUSTOM FUNCTION PATH (runs in addition to other branches, not exclusive)
     *      Condition: formType config entry has a `function` key.
     *      Runs MainAggregator::index($data) for custom form-specific processing
     *      (e.g. bespoke email templates, third-party integrations). Not gated by
     *      reCAPTCHA — runs regardless of token validity.
     *
     *   5. FORMBUILDER EMAIL PATH (runs in addition to other branches, not exclusive)
     *      Condition: a FormBuilder record exists for the formType AND either
     *        form_settings.toEmails is set on that record, OR a `contact_email_to`
     *        option exists in site_configurations.
     *      If reCAPTCHA passed → MainAggregator::formBuilderSendMail() sends a
     *      notification email using the FormBuilder template and recipient list.
     *      If reCAPTCHA failed → $sendInfo = false, no email sent.
     *
     * ── Response shape ────────────────────────────────────────────────────────
     *
     *   Validation path (early return from branch 2):
     *     Result of validateForm() — format varies:
     *     { "errors": ["fieldName", ...] }  on duplicate found
     *     or success response if no duplicates.
     *
     *   Normal path (branches 3–5):
     *     {
     *       "sendInfo": <result of aggregator/formBuilderSendMail, or false on reCAPTCHA fail, or []>,
     *       "saveInfo": <OnlineForms::saveForm response, or reCAPTCHA error details, or []>
     *     }
     *
     * @OA\Post(
     *   path="/view/main/saveSubmitedForm",
     *   tags={"Public website data"},
     *   summary="Submit a form — validate, persist to DB, and dispatch email notification",
     *   operationId="saveSubmitedForm",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"formType","g-recaptcha-response"},
     *       @OA\Property(property="formType",             type="string", example="contact-form",
     *         description="Registered form machine name"),
     *       @OA\Property(property="g-recaptcha-response", type="string",
     *         description="Google reCAPTCHA v2 token"),
     *       @OA\Property(property="...",                  type="string",
     *         description="Any additional form fields passed through to persistence and email handlers")
     *     )
     *   ),
     *   @OA\Response(response="200",
     *     description="{ sendInfo: ..., saveInfo: ... } or early validation result")
     * )
     */
    public function saveSubmitedForm(Request $request){

        $onlineForms = config('adminpanel.onlineForms');
        $options = new OptionsModel();
        $configEmail = $options->getByKey('contact_email_to');
        $data = $request -> all();
        $ret = [];
        $sendInfo = [];
        $validations = isset($onlineForms[$request->formType]['validate']) ? $onlineForms[$request->formType]['validate'] : null;

        $recaptchaValidation = OnlineForms::validateReCaptcha($data['g-recaptcha-response'] ?? '');

        unset($data['pac']);
        unset($data['g-recaptcha-response']);

        if(_cv($onlineForms, [$request->formType]) && $validations != null){
            $form = new OnlineForms();
            return $form->validateForm($request->all(), $validations);
        }
        if(!_cv($onlineForms, [$request->formType]) || _cv($onlineForms, [$request->formType,'disableSave'])){
            $ret = $recaptchaValidation['success'] ? OnlineForms::saveForm($data) : $recaptchaValidation;
        }
        if(_cv($onlineForms, [$request->formType, 'function'])){
            $aggregator = new App\Http\Controllers\Admin\FormAggregators\MainAggregator();
            $sendInfo = $aggregator->index($data);
        }

        $formBuilder = new FormBuilderModel();
        $form = $formBuilder->getOne(['form_name'=>$request->formType]);

        if(_cv($form, 'id', 'nn') && ( _cv($form, 'form_settings.toEmails') || $configEmail)){
            $aggregator = new App\Http\Controllers\Admin\FormAggregators\MainAggregator();
            $sendInfo = $recaptchaValidation['success'] ? $aggregator->formBuilderSendMail($data, $form) : false;
        }

        return response(['sendInfo'=>$sendInfo, 'saveInfo'=>$ret]);
    }

    /**
     * @OA\Post( path="/view/main/getBulkData", tags={"Public website data"}, summary="main method to get and response api for website, get specific data by 'node' key", operationId="getBulkData",
     *   @OA\Parameter( name="request params", description="request params", required=true, in="path",
     *     @OA\Schema(
     *      @OA\Property(property="node", type="string", example="serviceMap"),
     *  ),),
     *
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function getBulkData(Request $request){

        if($request->node == 'servicesMap'){

            $data = new PageModel();
            $ret = $data->getList(['content_type'=>'serviceMap']);


        }else if($request->node == 'getServiceCenters'){
            $data = new PageModel();
            $ret = $data->getList(['content_type'=>'serviceCenters']);
        }

        return response($ret);
    }

    /**
     * @OA\Post( path="/view/main/uploadfile", tags={"Public website data"}, summary="upload files from website", operationId="uploadfile",
     *   @OA\Parameter( name="request params", description="request params", required=true, in="path",
     *     @OA\Schema(
     *      @OA\Property(property="file", type="string", example="serviceMap"),
     *      @OA\Property(property="type", type="string", example="image"),
     *  ),),
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function uploadfile(Request $request){
        $media = new Media();

        $request->type = 'private';
        $request->validate = 'mimes:jpeg,jpg,png,pdf,docx|required|max:10000';
        $res = $media->justUploadFileToServer($request);

        return response($res);

    }

    /**
     * Generates the XML sitemap or a JSON route list for the site.
     *
     * Called by the sitemap route (GET /sitemap.xml or similar). Two output modes:
     *   return='routes' — JSON: { menu: [url,...], singleRoutes: [...] }
     *                     Used by the frontend/SSR to pre-generate static paths.
     *   default         — XML sitemap (application/xml, Sitemaps 0.9 + image extension).
     *                     Entries: all sitemap menu nodes per locale (weekly, priority 1.0)
     *                     + single-page content routes from singlePageSitemapRoutes()
     *                     (weekly, priority 0.5). Skips items with hide_from_sitemap_xml
     *                     config flag or external redirect_url.
     *
     * Cache: "getSitemap_{base64(params)}" — TTL config('app.cache_indx', 60) min.
     *
     * @param  array $params  Optional. Pass ['return'=>'routes'] for JSON route list.
     */
    public function getSitemap($params = []){

        $cacheNamePart = 'getSitemap_'.base64_encode(json_encode($params));

        $value = Cache::store('file')->get($cacheNamePart);
        if ($value !== null) {
            if (_cv($params, 'return') == 'routes') {
                return response()->json($value, 200, [], JSON_UNESCAPED_UNICODE);
            }
            return response($value, 200)->header('Content-Type', 'application/xml');
        }

        $locales = config('app.locales');
        $host = config('app.website_url');

        $singleRoutes = '';
        if( _cv($params, 'return')=='routes' ){
            $singleRoutes = $this->singlePageSitemapRoutes(['return'=>'routes']);
        }else{
            $singleRoutes = $this->singlePageSitemapRoutes(['return'=>'xml']);
        }

        $menuItem = new SiteMapModel();
        $menus = $menuItem->getMenuRouted();

        $tmpp = [];
        $xmlParts = [];

        foreach ($locales as $kk=>$vv){
            foreach ($menus as $v){
                $configs = _cv($v,['configs'], 'ar')?$v['configs']:[];

                /// disable menu from sitemap
                if(array_search( 'hide_from_sitemap_xml', $configs)!==false)continue;

                /// if there is redirection
                if (strpos($v['redirect_url'], '://')!==false)continue;

                $hst = strpos($v['route'], 'http')===false?$host:'';
                $urlKey = "{$hst}/{$kk}/{$v['route']}";
                if(isset($tmpp[$urlKey]))continue;

                $tmpp[$urlKey] = $urlKey;

                $datetime = new DateTime($v['updated_at']);
                $last_mode = $datetime->format(DateTime::ATOM);

                $xmlParts[] = "<url>
                            <loc>{$urlKey}</loc>
                            <lastmod>{$last_mode}</lastmod>
                            <changefreq>weekly</changefreq>
                            <priority>1.0</priority>
                          </url>";
            }
        }
        $tmp = implode('', $xmlParts);



        $cacheTtl = config('app.cache_indx', 60);

        if( _cv($params, 'return')=='routes' ){
            $data = ['menu' => array_values($tmpp), 'singleRoutes' => $singleRoutes];
            Cache::put($cacheNamePart, $data, $cacheTtl);
            return response()->json($data, 200, [], JSON_UNESCAPED_UNICODE);

        }else{ /// return xml version
            $xml = '<?xml version="1.0" encoding="UTF-8"?>
                <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
                  '.$tmp.'
                  '.$singleRoutes.'
                </urlset>';

            Cache::put($cacheNamePart, $xml, $cacheTtl);
            return response($xml, 200)->header('Content-Type', 'application/xml');
        }



    }

    /**
     * Generates sitemap entries for content detail (single-item) pages.
     *
     * Internal helper called by getSitemap(). Reads defaultSingleRoute options to find
     * which sitemap node is the parent route for each content type, then fetches all
     * published pages of that type and builds locale-prefixed URLs in the form:
     *   {host}/{locale}/{menuPath}/{pageId}-{slug}
     * Includes image:image blocks for each page's media fields.
     *
     * @param  array $params  return key controls output format:
     *                          'xml'             — XML string of <url> blocks (default for getSitemap)
     *                          'routes'          — flat array of { path, media[] }
     *                          'routesLocalized' — same grouped by locale code
     * @return string|array   XML string or route array depending on params['return'].
     */
    public function singlePageSitemapRoutes($params = []){

        $locales = config('app.locales');
        $host = config('app.website_url');

        $options = new OptionsModel();
        $sitemapModel = new SiteMapModel();
        $pagesModel = new PageModel();

        $singleRoutes = $options->getListByRaw(['key'=>'defaultSingleRoute']);
        $siteMapIds = [];
        $pageIds = [];
        $pageIdsLocalized = [];
        $xmlParts = [];

        foreach ($singleRoutes as $k=>$v){
            if(!_cv($v,['value'], 'nn'))continue;

            $contentType = str_replace(['content_type_settings_'], '', $v['content_group']);

            $menuItem = $sitemapModel->getOne(['id'=>$v['value']]);
            if(!_cv($menuItem, ['id'], 'nn'))continue;

            $pages = $pagesModel->getPages(['content_type'=>$contentType, 'limit'=>1000, 'status'=>'published']);

            foreach ($pages['list'] as $kk=>$vv){
                $imagesForSitemap = $this->getImagesForSitemap($vv, $locales);

                // compute date once per page, not once per locale
                $last_mode = (new DateTime($vv['updated_at']))->format(DateTime::ATOM);

                foreach ($locales as $kkk=>$vvv){
                    $url = "{$host}/{$kkk}/{$menuItem['fullpath']}/{$vv['id']}-{$vv['slug']}";
                    $pageIds[] = ['path'=>$url, 'media'=>$imagesForSitemap['media']];
                    $pageIdsLocalized[$kkk][] = ['path'=>$url, 'media'=>$imagesForSitemap['media']];

                    $xmlParts[] = "<url>
                            <loc>{$url}</loc>
                            {$imagesForSitemap['xml']}
                            <lastmod>{$last_mode}</lastmod>
                            <changefreq>weekly</changefreq>
                            <priority>0.5</priority>
                          </url>";
                }
            }

            $siteMapIds[$contentType] = $v['value'];
        }

        if( _cv($params, 'return')=='routes' ){
            return $pageIds;
        }elseif( _cv($params, 'return')=='routesLocalized' ){
            return $pageIdsLocalized;
        }elseif( _cv($params, 'return')=='xml' ){
            return implode('', $xmlParts);
        }

        return [];
    }

    /**
     * Extracts unique image URLs from a page record and formats them as sitemap image blocks.
     *
     * Internal helper called by singlePageSitemapRoutes(). Iterates all locale-keyed
     * media fields on the page (plus locale-neutral 'xx' fields), deduplicates by URL,
     * and returns both the XML image:image fragment and a flat URL array.
     *
     * @param  array $data    Locale-keyed page record (e.g. from PageModel::getPages()).
     * @param  array $locales Active locales from config('app.locales').
     * @return array { xml: string, media: string[] }
     */
    private function getImagesForSitemap($data=[], $locales=[]){
        $tmp = ['xml'=>[], 'media'=>[]];
        // 'xx' covers locale-neutral (non-prefixed) media fields stored on the page
        $locales['xx'] = 'xx';
        foreach ($locales as $kk=>$vv) {
            if(!isset($data[$kk]))continue;
            foreach ($data[$kk] as $kkk=>$vvv){
                if(!isset($vvv[0]['url']))continue;
                foreach ($vvv as $kkkk=>$vvvv){
                    $tmp['media'][$vvvv['url']] = $vvvv['url'];
                    $tmp['xml'][$vvvv['url']] =
"<image:image>
    <image:loc>{$vvvv['url']}</image:loc>
</image:image>\n";
                }
            }
        }
        return ['xml'=>implode('', array_values($tmp['xml'])), 'media'=>array_values($tmp['media'])];
    }


    /**
     * Returns vacancy categories with the count of published vacancies in each.
     *
     * POST /api/view/parts/getVacancyTaxonomies  (via parts/{method} dispatcher)
     *
     * Fetches vacancy_category taxonomy terms with a join count of related published
     * `vacancy` content items. Used by the vacancy listing page to render category
     * filter tabs with item counts.
     *
     * Params: none.
     * Response: array of taxonomy term objects each including a count field.
     */
    public function getVacancyTaxonomies(){

        $taxModel = new TaxonomyModel();

        $list = $taxModel->getContentCounts('vacancy_category', 'vacancy');

        return $list;

    }

    /**
     * Ensures an appSessionId cookie is set for the current visitor.
     *
     * Internal helper. If the $_COOKIE['appSessionId'] is not already present,
     * sets a 1-year cookie with a generated ID (md5 of current datetime).
     * Returns the generated session ID string regardless of whether the cookie was set.
     *
     * @return string  The generated session ID (ssid + md5(YmdHis)).
     */
    private function setAppSessionId(){
        $sessId = 'ssid'.md5(date('YmdHis'));
        if(!isset($_COOKIE['appSessionId'])){
            setcookie( 'appSessionId', $sessId, strtotime( '+1 year' ) , '/' );
        }
        return $sessId;
    }

    /**
     * Returns the FormBuilder form configuration for a given form name.
     *
     * POST /api/view/parts/getFormBuilderForm  (via parts/{method} dispatcher)
     *
     * Fetches the form definition (fields, settings, email recipients) from the
     * FormBuilder model by form_name. Used by the frontend to dynamically render
     * forms configured in the admin panel without hardcoding field structure.
     *
     * Params:  form_name  string  required  Machine name of the form to retrieve.
     * Returns: full FormBuilder record as JSON, or { error: 'Form name not set' } if missing.
     */
    public function getFormBuilderForm($params=[])
    {
        if(!_cv($params, 'form_name'))return response(['error'=>'Form name not set']);
        $model = new FormBuilderModel();

        $formData = $model->getOne(['form_name'=>$params['form_name']]);

        return response()->json($formData, 200, [], JSON_UNESCAPED_UNICODE);

    }

    /**
     * Generates SEO meta tags for a given page URL.
     *
     * POST /api/view/parts/getMetaTags  (via parts/{method} dispatcher)
     *
     * Resolves the provided URL to a sitemap entry, then generates the full set of
     * meta tags: title, description, keywords, OG/Twitter cards, hreflang, canonical,
     * and JSON-LD schema. Falls back to the 'personal' sitemap entry if the path is
     * not found. Used by SSR renderers and meta tag injection layers.
     *
     * Params:
     *   url   string  required  Full page URL to generate tags for.
     *   lang  string  optional  Locale override; defaults to first configured locale.
     *
     * Cache: "seoObject_{base64(params)}" — TTL config('app.cache_indx', 60) min.
     * Response: MetaTagsGenerator::getMetaTags() result as JSON.
     */
    public function getMetaTags($params=[])
    {

        $request = Request();

        $locales = config('app.locales');

        $validCookie = _cv($_COOKIE, 'validCookies') ? $_COOKIE['validCookies'] : '-';
        $url = $params['url'];
        $cacheNamePart = base64_encode(json_encode($params));

        $value = Cache::store('file')->get('seoObject_' . $cacheNamePart);
//        if ($value !== null) return response()->json($value, 200, [], JSON_UNESCAPED_UNICODE);

        $parseUrl = parse_url($url);

        $uri = isset($parseUrl['path']) ? $parseUrl['path'] : '';
        $langFromUrl = substr($uri, 0, 2);

        $currentLang = _cv($params, ['lang']) ? $params['lang'] : key($locales);

        $uriNoLan = str_replace("{$currentLang}/", '', $uri);

        $request->headers->set('lang', $currentLang);

        $parsedUrl = $this->urlParser($uri, $currentLang);

        $siteMapModel = new SiteMapModel();
        $selectedMenu = $siteMapModel->getByPath($parsedUrl['menuPath']);

        if (!isset($selectedMenu['id'])) {
            $selectedMenu = $siteMapModel->getByPath('personal');
            $uri = "{$currentLang}/personal";
        }

        $MetaTags = new MetaTagsGenerator();

        $ret = $MetaTags->getMetaTags(['validCookies' => $validCookie, 'path' => $uri, 'pageUrl' => $url, 'lang' => $currentLang, 'menuData' => $selectedMenu, 'parsedUrl' => $parsedUrl, 'menuId' => _cv($selectedMenu, 'id', 'nn')]);

        Cache::store('file')->put('seoObject_' . $cacheNamePart, $ret, config('app.cache_indx', 60));

        return response()->json($ret, 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Parses a URL path into its structural components for sitemap and single-item resolution.
     *
     * Internal helper used by firstLoader() and getMetaTags(). Strips the locale prefix,
     * detects single-item URL patterns ('singleview', 'product'), extracts the numeric item ID,
     * and isolates the menu path used for sitemap lookup.
     *
     * @param  string|false $path  URL path to parse; uses current request path if false.
     * @param  string|null  $lang  Locale override; auto-detected from path if null.
     * @return array {
     *   uri, currentLang, menuPath, uriNoLan, singleId,
     *   singleType?, singleSplit?, urlParams: {singleview, viewitem}, locale
     * }
     */
    private function urlParser($path = false, $lang = null)
    {
        $path = trim($path, '/');
        $request = Request();
        $singleParts = ['singleview', 'product'];

        $locales = config('app.locales');

        $ret['uri'] = $path ? $path : $request->path();
        $ret['uri'] = urldecode($ret['uri']);
        $langFromUrl = trim(substr($ret['uri'], 0, 3), '/');
        $ret['currentLang'] = isset($locales[$langFromUrl]) ? $langFromUrl : ($lang ?: key($locales));
        $ret['menuPath'] = $ret['uriNoLan'] = ($ret['currentLang'] == $langFromUrl) ? str_replace(
            "{$ret['currentLang']}/",
            '',
            $ret['uri']
        ) : $ret['uri'];
        $ret['singleId'] = '';
        foreach ($singleParts as $v) {
            if (!strpos($ret['uriNoLan'], $v))
                continue;

            $ret['singleType'] = $v;
            $ret['singleSplit'] = explode($v, $ret['uriNoLan']);
            $ret['menuPath'] = trim($ret['singleSplit'][0], '/');

            foreach (explode('/', $ret['singleSplit'][1]) as $vv) {
                $ret['singleId'] = intval($vv);
                if (is_numeric(intval($vv)) && intval($vv) > 0)
                    break;
            }

            break;

        }

        if ($ret['singleId'] == '') {
            $tmp = explode('/', $ret['uriNoLan']);
            $tmpp = [];
            foreach ($tmp as $v) {
                if (is_numeric(intval($v)) && intval($v) > 0) {
                    $ret['singleId'] = intval($v);
                    break;
                }
                $tmpp[] = $v;
            }

            $ret['menuPath'] = implode('/', $tmpp);
        }


        /// variables for old versions
        $ret['urlParams']['singleview'] = $ret['singleId'];
        $ret['urlParams']['viewitem'] = $ret['singleId'];

        $ret['locale'] = $ret['currentLang'];
        // p($ret);
        return $ret;

    }

    /**
     * Returns all URL redirections configured in the CMS.
     *
     * POST /api/view/parts/getRedirections  (via parts/{method} dispatcher)
     *
     * Two output modes controlled by params['return']:
     *   'raw'     — full object keyed by from_url: { to_url, virtual }
     *               Used when the consumer needs the virtual flag (e.g. Next.js middleware).
     *   default   — simple from_url → to_url map (array_column).
     *
     * Params:  return  string  optional  Pass 'raw' for full object; omit for simple map.
     * No caching — always live to ensure redirections take effect immediately after admin edits.
     */
    public function getRedirections($params = [])
    {
        $res = RedirectionsModel::get()->toArray();

        if(_cv($params, 'return')=='raw'){
            $output = [];
            foreach ($res as $v) {
                $output[$v['from_url']] = ['to_url'=>$v['to_url'],'virtual'=>$v['virtual']];
            }

            return response($output);
        }else{

            $res = array_column($res, 'to_url', 'from_url');
        }
        return response($res);
    }


    //// testing method
    /** @internal Developer scratch method — not for production use. */
    public function anytest(){


print 11111;



        return response([]);
    }

    /**
     * Returns the site accessibility configuration.
     *
     * POST /api/view/parts/getAccessibilityOptions  (via parts/{method} dispatcher)
     *
     * Fetches the 'accessibilityOptions' value from the options table and JSON-decodes it.
     * Used by the frontend accessibility toolbar to load available options (font size,
     * contrast modes, etc.) without hardcoding them.
     *
     * Params: none.
     * Response: decoded accessibility options array, or { status: false, message: '...' } if unset.
     */
    public function getAccessibilityOptions(){
        $optionData = OptionsModel::select('value')
            ->where('key', '=', 'accessibilityOptions')
            ->first();

        if ($optionData && $optionData->value) {
            return json_decode($optionData->value, true);
        }

        return response()->json(['status' => false, 'message' => 'No accessibility options found']);
    }

    /**
     * Returns the term IDs that are actually used by published content of a given type.
     *
     * POST /api/view/main/getAttachedTaxonomies
     *
     * Queries the taxonomy-relations join table to find which terms within the requested
     * taxonomies have at least one published page of the given content_type attached.
     * Useful for hiding empty filter tabs on listing pages — only terms with content are returned.
     *
     * Params (validated):
     *   taxonomies    string[]  required  Array of taxonomy machine names (max 10, each max 200 chars).
     *   content_type  string    required  Content type machine name (max 200 chars).
     *
     * Cache: "attached_taxonomies_{content_type}_{taxonomy}" per taxonomy — TTL 3600 seconds.
     * Response: object keyed by taxonomy name, each value an array of term IDs (int[]).
     *           Taxonomies with no attached terms are absent from the response.
     * Errors: HTTP 400 { error: '...' } on validation failure.
     */
    public function getAttachedTaxonomies(Request $request)
    {
        $input = $request->only(['taxonomies', 'content_type']);

        $rules = [
            'taxonomies' => 'bail|required|array|max:10',
            'taxonomies.*' => 'bail|required|string|max:200',
            'content_type' => 'bail|required|string|max:200'
        ];

        $validator = \Validator::make($input, $rules);

        if ($validator->fails())
        {
            return response(['error' => $validator->errors()->first()], 400);
        }

        $response = [];

        foreach ($input['taxonomies'] as $taxonomy)
        {
            $cacheKey = 'attached_taxonomies_' . $input['content_type'] . '_' . $taxonomy;

            if (Cache::has($cacheKey))
            {
                $response[$taxonomy] = Cache::store('file')->get($cacheKey);
            }

            else
            {
                $result = \DB::table('pages')->select('taxonomy.id')
                                             ->join('modules_taxonomy_relations', 'modules_taxonomy_relations.data_id', '=', 'pages.id')
                                             ->join('taxonomy', 'taxonomy.id', '=', 'modules_taxonomy_relations.taxonomy_id')
                                             ->where('pages.status', 'published')
                                             ->where('taxonomy', $taxonomy)
                                             ->where('pages.content_type', $input['content_type'])
                                             ->groupBy('taxonomy.id')
                                             ->get()
                                             ->pluck('id')
                                             ->toArray();

                if (!empty($result))
                {
                    Cache::store('file')->put($cacheKey, $result, 3600);

                    $response[$taxonomy] = $result;
                }
            }
        }

        return $response;
    }

    // xrates

    /**
     * Fetches current exchange rates from the National Bank of Georgia API.
     *
     * Internal — called by scheduled jobs or admin triggers, not directly exposed as a public route.
     * Fetches the NBG currency JSON feed, maps the configured currencies (config('app.currency'))
     * to their current rates by NBG code, then calls updateXratesApi() to persist the result.
     * Returns false on cURL error or if the API response is malformed.
     *
     * @param  array $currencies  Optional currency config override. Defaults to config('app.currency').
     *                            Each entry must have an 'nbg_code' key.
     * @return array|false  Currency config array with 'rate' field populated, or false on failure.
     */
    public function get_x_rates($currencies = [])
    {
        $currencies = _cv($currencies, [0, 'nbg_code'])?$currencies:config('app.currency');

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://nbg.gov.ge/gw/api/ct/monetarypolicy/currencies/ka/json',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Cookie: PHPSESSID=3matiarut0dqu39sqvegri61u4'
            ),
        ));

        $response = curl_exec($curl);


        if (curl_errno($curl)) {
            echo 'curl error:' . curl_error($curl);
            dd(curl_error($curl));
            return false;
        }

        $res = json_decode($response, 1);
        curl_close($curl);

        if(!_cv($res, '0.currencies.0.code'))return false;

        $allCurrencies = array_column($res['0']['currencies'], 'rate','code');

        foreach ($currencies as $k=>$v){
            $currencies[$k]['rate'] = ( isset($v['nbg_code']) && isset($allCurrencies[$v['nbg_code']]) )?$allCurrencies[$v['nbg_code']]:'';
        }

        $this->updateXratesApi($currencies);

        return $currencies;
    }

    /**
     * Persists exchange rate data to the options table — once per calendar day.
     *
     * Internal — called only by get_x_rates(). Checks the `conf` column of the
     * existing 'xrates' option row against today's date. If the date differs (or the
     * row doesn't exist), writes the new rates as JSON and stamps conf with today's date.
     * Skips the write if the row was already updated today.
     *
     * @param  array  $xrateData  Currency array with populated 'rate' fields.
     * @return string  'updated' | 'no need to update' | 'saved' (new row created).
     */
    public function updateXratesApi($xrateData = []){

        $date_now = date("Y-m-d");
        $tmp = OptionsModel::Where('key', 'xrates')->first();

        if (isset($tmp)) {
            if ($date_now != $tmp->conf) {
                $currencyRates = _psqlupd($xrateData);

                $tmp->key = 'xrates';
                $tmp->value = $currencyRates;
                $tmp->conf = date("Y-m-d");
                $tmp->content_group = 'site_configurations';
                if ($tmp->value) {
                    $tmp->save();
                }

                return 'updated';
            }

            return 'no need to update';
        } else {
            $currencyRates = _psqlupd($xrateData);

            $tmp = new OptionsModel();
            $tmp->key = 'xrates';
            $tmp->value = $currencyRates;
            $tmp->conf = date("Y-m-d");
            $tmp->content_group = 'site_configurations';
            if ($tmp->value) {
                $tmp->save();
            }
            return 'saved';
        }
    }
}
