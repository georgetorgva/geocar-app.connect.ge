<?php

use App\Http\Controllers\Admin\Subscription;
use App\Http\Controllers\Admin\BankServices;
use App\Http\Controllers\Admin\Shop\Payments\PayPal;
use App\Http\Controllers\Api\GoogleAnalyticsController;
use Illuminate\Http\Request;

use App\Http\Controllers\Api\Main;
use App\Http\Controllers\Admin\Page;

use App\Http\Controllers\Admin\Media;
use App\Http\Controllers\Admin\Words;
use App\Http\Controllers\Api\Feedback;
use App\Http\Controllers\Admin\Options;

use App\Http\Controllers\Admin\SiteMap;
use App\Http\Controllers\Admin\Widgets;
use App\Http\Controllers\Admin\Taxonomy;
use App\Http\Controllers\Admin\OnlineForms;
use App\Http\Controllers\Admin\Roles\Roles;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Admin\Shop\Attributes;
use App\Http\Controllers\Admin\Roles\Permitions;
use App\Http\Controllers\Admin\Shop\ShopAdminMain;
use App\Http\Controllers\Admin\User\UserController;
use App\Http\Controllers\Api\UserController as ApiUser;
use App\Http\Controllers\Admin\Shop\services\LtbRequests;
use App\Http\Controllers\Admin\Shop\services\LtbResponses;
use Illuminate\Support\Facades\DB;

use App\Http\Controllers\Api\SendGridController;
use App\Http\Controllers\Api\EnagrammController;
use App\Http\Controllers\Api\SubscriptionController;

use App\Http\Controllers\Services\Idx;
use App\Http\Controllers\Api\CustomController;

//use App\Http\Controllers\Silk\SilkMain;

//header('Access-Control-Allow-Headers: Content-Type, X-Auth-Token, Origin, Authorization');
//header('Access-Control-Allow-Origin: *');

/*
 *
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
//
//// session() -> put('cpu', 'i5');
//
//
//echo session() -> get('cpu');
//DB::select(("SET group_concat_max_len = 100000000"));

Route::any('admin/login',         [AuthController::class, 'login']);
Route::any('admin/logout',         [AuthController::class, 'logout']);
Route::any('admin/indx', [\App\Http\Controllers\Admin\Main::class, 'indx']);

/**
 * connect admin routes
 */
Route::group([ 'prefix' => 'admin',  'middleware' => ['auth:api', 'reqcleaner','requestLog'] ], function () {

    Route::group(['prefix' => 'main' ], function () {
        Route::any('indx', [\App\Http\Controllers\Admin\Main::class, 'index']);
        Route::any('mainSearch', [\App\Http\Controllers\Admin\Main::class, 'mainSearch']);
        Route::any('dashboard', [\App\Http\Controllers\Admin\Main::class, 'dashboard']);
        Route::any('getformtypess', [\App\Http\Controllers\Admin\Main::class, 'getFormTypess']);
        Route::any('relationProducts', [Products::class, 'relationProducts']);
        // Route::any('products', [Products::class, 'products']);
        Route::any('products', [Products::class, 'productsadmin']);

        Route::any('stock', [\App\Http\Controllers\Admin\Main::class, 'stock']);
        Route::any('productmodels',[ProductModels::class, 'getPages']);

        Route::any('updFormBuilderForm', [\App\Http\Controllers\Admin\Main::class, 'updFormBuilderForm']);
        Route::any('getFormBuilderForm', [\App\Http\Controllers\Admin\Main::class, 'getFormBuilderForm']);
        Route::any('getFormBuilderForms', [\App\Http\Controllers\Admin\Main::class, 'getFormBuilderForms']);

        Route::any('getRedirections', [\App\Http\Controllers\Admin\Main::class, 'getRedirections']);
        Route::any('updRedirections', [\App\Http\Controllers\Admin\Main::class, 'updRedirections']);
        Route::any('deleteRedirections', [\App\Http\Controllers\Admin\Main::class, 'deleteRedirections']);

        Route::post('/clear-cache', function() { Artisan::call('cache:clear'); return "Cache is cleared"; });

    });

    Route::group(['prefix' => 'options' ], function () {
        Route::any('updContentTypeSettings', [Options::class, 'updContentTypeSettings']);
        Route::any('setConfigurations', [Options::class, 'updSiteConfigurations']);

        Route::any('updOption', [Options::class, 'updOption']);
        Route::any('updOptionRaw', [Options::class, 'updOptionRaw']);
        Route::any('getOption', [Options::class, 'getOption']);
        Route::any('getOptions', [Options::class, 'getOptions']);
        Route::any('deleteOption', [Options::class, 'deleteOption']);
    });

    Route::group(['prefix' => 'subscription' ], function () {
        Route::any('settings', [Subscription::class, 'getConfigs']);
        Route::any('getSubscribers', [Subscription::class, 'getSubscribers']);
        Route::any('getSubscriber', [Subscription::class, 'getSubscriber']);
        Route::any('exportSubscribers', [Subscription::class, 'exportSubscribers']);
        Route::any('deleteSubscriber', [Subscription::class, 'deleteSubscriber']);
        Route::any('updateSubscriber', [Subscription::class, 'updateSubscriber']);
        Route::any('sendEmailToSubscribers', [Subscription::class, 'sendEmailToSubscribers']);
        Route::get('getContentData', [Subscription::class, 'getContentData']);
        Route::get('getConfigurationData', [Subscription::class, 'getConfigurationData']);
        Route::get('updateConfigurationData', [Subscription::class, 'updateConfigurationData']);
    });

    Route::group(['prefix' => 'sitemap' ], function () {
        Route::post('updMenu', [SiteMap::class, 'updMenu']);
        Route::post('sortMenu', [SiteMap::class, 'sortMenu']);
        Route::any('setHomePage', [SiteMap::class, 'setHomePage']);
        Route::any('deleteMenu', [SiteMap::class, 'deleteMenu']);
        Route::any('deleteMenuImage', [SiteMap::class, 'deleteMenuImage']);
    });

    Route::group(['prefix'=>'taxonomy' ], function () {
        Route::any('getTerms', [Taxonomy::class, 'getTerms']);
        Route::post('updTerm', [Taxonomy::class, 'updTerm']);
        Route::post('updSort', [Taxonomy::class, 'updSort']);
        Route::post('deleteTerm', [Taxonomy::class, 'deleteTerm']);
    });

    Route::group(['prefix'=>'shop' ], function () {

        Route::any('main/{method}', function ($method, Request $request){
            $controller = new ShopAdminMain();
            return $controller->callAction($method, [$request->all()]);
        });

        Route::group(['prefix'=>'attributes' ], function () {
            Route::any('getTerms', [Attributes::class, 'getTerms']);
            Route::any('getAttributes', [Attributes::class, 'getAttributes']);
            Route::any('getAttribute', [Attributes::class, 'getAttribute']);
            Route::post('updTerm', [Attributes::class, 'updTerm']);
            Route::post('updSort', [Attributes::class, 'updSort']);
            Route::post('deleteAttribute', [Attributes::class, 'deleteAttribute']);
            Route::any('getAttributeTypes', [Attributes::class, 'getAttributeTypes']);
        });

    });

    Route::group(['prefix' => 'page'], function () {
        Route::post('updPage', [Page::class, 'updPage']);//->middleware(['reqcleaner']);
        Route::post('getPage', [Page::class, 'getPage']);
        Route::any('getPages', [Page::class, 'getPages']);
        Route::any('getPagesTranslated', [Page::class, 'getPagesTranslated']);
        Route::any('getPageTitles', [Page::class, 'getPageTitles']);
        Route::any('deletePageMedia', [Page::class, 'deletePageMedia']);
        Route::any('deletePage', [Page::class, 'deletePage']);
        Route::any('updListSort', [Page::class, 'updListSort']);
        Route::any('setSingleSort', [Page::class, 'setSingleSort']);
        Route::any('setPageStatus', [Page::class, 'setPageStatus']);
        Route::any('getContentLog', [Page::class, 'getContentLog']);

    });

    Route::group(['prefix'=>'users' ], function () {
        Route::post('getUsers', [UserController::class, 'index']);
        Route::post('getAdmins', [UserController::class, 'getAdmins']);
        Route::post('upduser', [UserController::class, 'update']);
    });

    Route::group(['prefix'=>'Roles'], function(){
        Route::any('/getRoles', [Roles::class, 'GetRoles']);
        Route::any('/Update', [Roles::class, 'UpdateData']);
        Route::any('/DeleteData', [Roles::class, 'DeleteData']);
    });

    Route::group(['prefix'=>'Permitions'], function(){
        Route::any('/getPermitions', [Permitions::class, 'GetPermitions']);
        Route::any('/Update', [Permitions::class, 'UpdateData']);
        Route::any('/DeleteData', [Permitions::class, 'DeleteData']);
        Route::any('/UpdateChoose', [Permitions::class, 'UpdateChoose']);

    });

    Route::group(['prefix'=>'media' ], function () {
        Route::any('uploadToServer', [Media::class, 'uploadToServer']);
        Route::any('uploadToServerMenu', [Media::class, 'uploadToServerMenu']);
        Route::any('justUploadToServer', [Media::class, 'justUploadToServer']);
        Route::any('saveChangedImage', [Media::class, 'saveChangedImage']);
        Route::any('uploadFileToServer', [Media::class, 'uploadFileToServer']);
        Route::any('saveChangedFile', [Media::class, 'saveChangedFile']);
        Route::any('uploadShopMedia', [Media::class, 'uploadShopMedia']);
        Route::any('deleteMedia', [Media::class, 'deleteMedia']);
        Route::any('getLibrary', [Media::class, 'getLibrary']);
        Route::any('getImgSource', [Media::class, 'getImgSource']);
        Route::any('getImagesList', [Media::class, 'getImagesList']);

    });

/*** /
    Route::group(['prefix'=>'words' ], function () {
        Route::any('getWords', [Words::class, 'getWords']);
        Route::any('updWord', [Words::class, 'updWord']);
        Route::any('deleteWord', [Words::class, 'deleteWord']);
    });
/***/

    Route::group(['prefix'=>'onlineforms' ], function () {
        Route::any('getformtypes', [OnlineForms::class, 'getFormTypes']);
        Route::any('getform', [OnlineForms::class, 'getForm']);
        Route::any('deleteform', [OnlineForms::class, 'deleteForm']);
        Route::any('restoreform', [OnlineForms::class, 'restoreForm']);
        Route::any('getList', [OnlineForms::class, 'getList']);
    });


    Route::post('/register',    [AuthController::class, 'register']);

    Route::prefix('google-analytics')->group(function () {
        Route::post('/home-report', [GoogleAnalyticsController::class, 'index']);
        Route::post('/realtime-report', [GoogleAnalyticsController::class, 'realtime']);
        Route::post('/traffic-acquisition', [GoogleAnalyticsController::class, 'trafficAcquisition']);
        Route::post('/events', [GoogleAnalyticsController::class, 'events']);
        Route::post('/pages', [GoogleAnalyticsController::class, 'pages']);
    });

    Route::any('smartShop/{method}', function ($method, Request $request){
        $controller = new ShopAdminMain();
        return $controller->callAction($method, [$request->all()]);
    });

    Route::any('ltb/{method}', function ($method, Request $request){
        $controller = new \App\Http\Controllers\CustomModules\Ltb\MainAdmin();
        return $controller->callAction($method, [$request->all()]);
    });


    Route::any('/{controller}/{method}', function ($controller, $method) {
        // Construct the full namespace of the controller
        $controllerNamespace = 'App\Http\Controllers\Admin\\' . ucfirst($controller);

        // Check if the controller exists
        if (!class_exists($controllerNamespace)) {
            abort(404); // Or handle the error as per your requirement
        }

        // Check if the method exists in the controller
        if (!method_exists($controllerNamespace, $method)) {
            abort(404); // Or handle the error as per your requirement
        }

        // Create an instance of the controller
        $controllerInstance = new $controllerNamespace;

        // Call the method on the controller instance
        return $controllerInstance->$method(request());
    })->where('method', '.*');


});

/**
 * public web routes
 */
Route::group(['prefix'=>'view', 'middleware' => ['api']], function () {
//    Route::any('ltb/{method}', [\App\Http\Controllers\CustomModules\Ltb\MainAdmin::class, $method]);

    Route::post('/getTextAudio', [EnagrammController::class, 'getAudio']);
    Route::get('/clearAudioCache', [EnagrammController::class, 'clearCache']);

    Route::any('ltb/{method}', function ($method, Request $request){
        $controller = new \App\Http\Controllers\CustomModules\Ltb\MainView();
        return $controller->callAction($method, [$request->all()]);
    });

    Route::any('bb/{method}', function ($method, Request $request){
        $controller = new \App\Http\Controllers\CustomModules\BasisBank\MainView();
        return $controller->callAction($method, [$request->all()]);
    });

    Route::any('ltb/private/{method}', function ($method, Request $request){
        $controller = new \App\Http\Controllers\CustomModules\Ltb\MainViewPrivate();
        return $controller->callAction($method, [$request->all()]);
    })->middleware('auth:api');

    Route::any('smartShop/{method}', function ($method, Request $request){
        $controller = new \App\Http\Controllers\Admin\Shop\ShopSiteMain();
        return $controller->callAction($method, [$request->all()]);
    });

    Route::any('smartShop/private/{method}', function ($method, Request $request){
        $controller = new \App\Http\Controllers\Admin\Shop\ShopSiteMainPrivate();
        return $controller->callAction($method, [$request->all()]);
    })->middleware('auth:api');

    Route::any('cart/{method}', function ($method, Request $request) {
        $controller = new \App\Http\Controllers\Admin\Shop\Cart();
        return $controller->callAction($method, [$request->all()]);
    });

    Route::group(['prefix' => 'bankservices'], function () {
        Route::controller(BankServices::class)->group(function () {
            Route::any('updateServicesData', 'updateServicesData');
            Route::any('updateServiceXrates', 'updateServiceXrates');
        });
    });

    Route::group(['prefix'=>'main'], function () {
        /// main other routes here
        Route::any('/indx', [Main::class, 'index']);
        Route::any('/getDataList', [Main::class, 'getDataList']);
        Route::any('/indxTranslatable', [Main::class, 'indxTranslatable']);
        Route::any('/getAttachedTaxonomies', [Main::class, 'getAttachedTaxonomies']);
        Route::any('/getCurrentContent/', [Main::class, 'getCurrentContent']); ///should be post method /// any for testing
        Route::any('/search/', [Main::class, 'search']); ///should be post method /// any for testing
        Route::any('/getTranslations', [Main::class, 'getTranslations']); ///should be post method /// any for testing
        Route::any('/adwrd', [Main::class, 'adwrd']); ///should be post method /// any for testing
        Route::any('/getServiceCenters', [Main::class, 'getServiceCenters']); ///get X rates by date.
        Route::any('/saveSubmitedForm', [Main::class, 'saveSubmitedForm']); ///save submited form datas.
        Route::any('/getBulkData', [Main::class, 'getBulkData']); /// get bulk data by params.
        Route::any('/getValidCookiesData', [Main::class, 'getValidCookiesData']); /// get bulk data by params.
        Route::any('/getCalendar', [Main::class, 'getCalendar']);
        Route::any('justUploadToServer', [Media::class, 'justUploadToServer']);
//        Route::any('/anytest', ['middleware' => ['reqcleaner'],  Main::class, 'anytest']); /// testing method.
        Route::any('/anytest', [Main::class, 'anytest'])->middleware(['reqcleaner']); /// testing method.

//        Route::any('/uploadimage', [Main::class, 'uploadimage']); /// upload graphic file/// image.
        Route::any('/uploadfile', [Main::class, 'uploadfile']); /// upload any file///

        Route::any('/getproducts', [Products::class, 'getproducts']);

        Route::any('parts/{method}', function ($method, Request $request) {
            $controller = new Main();
            try {
                return $controller->callAction($method, [$request->all()]);
            } catch (Exception $e) {
                return response(['error' => $e->getMessage()], 500);
            }
        })->middleware('cache.api');
    });

    Route::group(['prefix'=>'user'], function(){
        Route::any('login', [UserController::class, 'login']);
        Route::any('socLogin', [UserController::class, 'socLogin']);
        Route::any('logout', [UserController::class, 'logout']);
        Route::any('resetForgottenPassword', [UserController::class, 'resetForgottenPassword']);
        Route::any('memberRegistration', [UserController::class, 'register']);
    });

    Route::group(['prefix'=>'services'], function(){
        /// service routes here
        Route::any('getProductsFromShop', [LtbRequests::class, 'getProducts']);

        Route::get('idx/stock', [Idx::class, 'getStockData']);

        Route::any('ltb/{method}', function ($method, Request $request){
            $controller = new LtbResponses;
            return $controller->callAction($method, [$request->all()]);
        })->middleware('BasicAuth');
    });
});

// subscription routes

Route::group(['prefix' => 'subscription'], function(){

    Route::post('subscribe', [SubscriptionController::class, 'subscribe']);
    Route::post('unsubscribe', [SubscriptionController::class, 'unsubscribe']);
    Route::get('confirm', [SubscriptionController::class, 'confirm']);
    Route::post('sendManagementLink', [SubscriptionController::class, 'sendManagementLink']);
    Route::post('updateData', [SubscriptionController::class, 'updateData']);
    Route::get('getData', [SubscriptionController::class, 'getData']);
});

Route::any('/sendgridTest', [SendGridController::class, 'test']);

/**
 * public member web routes
 */
Route::group(['prefix'=>'member', 'middleware' => ['auth:api']], function () {
    Route::post('/me', [UserController::class, 'me']);
    Route::post('/refresh', [UserController::class, 'refresh']);
    Route::post('/updateProfileData', [UserController::class, 'updateProfileData']);
    Route::post('/updatePhone', [UserController::class, 'updateUserPhone']);
});

/**
 * project-specific route
 */

Route::group(['prefix'=>'custom'], function () {
    Route::any('/getLatestQuarterContentByTimelineYear', [CustomController::class, 'getLatestQuarterContentByTimelineYear']);
});
