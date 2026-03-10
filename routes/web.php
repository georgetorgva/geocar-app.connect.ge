<?php

use App\Models\Media\MediaModel;
use App\Http\Controllers\Api\Main;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/



//Route::any('testme', [ServicesResponse::class, 'testme']);
Route::get('/foo', function () {
    Artisan::call('storage:link');
});

Route::get('/clear-cache', function() {
    Artisan::call('cache:clear');
    return "Cache is cleared";
});

Route::any('/', [Main::class, 'firstLoader']);
Route::any('/getcalendar', [Main::class, 'getCalendar']);
//Route::get('/', function () { return view('welcome'); });

Route::any('/sitemap.xml', [Main::class, 'getSitemap']);
Route::any('{catchall}', [Main::class, 'firstLoader'])->where("catchall", ".*");

