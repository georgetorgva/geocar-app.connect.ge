<?php

namespace App\Http\Controllers\Admin;

use App\Models\Admin\SiteMapModel;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controller;
use App\Models\Admin\PageModel;
use App\Models\Media\MediaModel;
use Illuminate\Validation\Validator;
use Illuminate\Support\Facades\Storage;
use Spatie\ImageOptimizer;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use App\Models\Admin\StockModel;
use App\Models\Admin\ProductsModel;
use App\Models\Admin\OptionsModel;


/**
 * main controller for all the content types
 */
class Media extends Controller
{

    public $notResizeMinWidth = 1920;
    public $defaultMaxWidth = 1920;
    public $defaultMaxHeight = 1280;
    public $mediaTables = ['media'=>'media', 'product'=>'shopmedia'];

    /// min width for not changable png images; used for not change png transparent icons
    public $minWidthForPng = 300;
    //
    protected $mainModel;
    protected $error = false;

    public function __construct()
    {
        $this->mainModel = new MediaModel();
    }

    public function uploadImageToStorage($request, $table = 'media')
    {
        //        p($request->all());



        if (isset($this->mediaTables[$request->mediatable]))
            $table = $this->mediaTables[$request->mediatable];

        $rootPath = config('filesystems.disks.public.root'); //root, url

        $fileExtension = strtolower($request->file('file')->getClientOriginalExtension()); // added
        $convertToWebp = $request->toWebpFormat && $fileExtension !== 'webp'; // added

        $fileBaseName = pathinfo($request->file('file')->getClientOriginalName(), PATHINFO_FILENAME); // changed
        $fileBaseName = date('Ymdis') . '-' . sanitizeFilename($fileBaseName); // changed
        $filename = $fileBaseName . '.' . $fileExtension; // changed

        //        $path = Storage::disk('public')->putFile('media', $request->file );
        $path = Storage::disk('public')->putFileAs('media', $request->file, $filename);

        $imagePath = $rootPath . $path;

        $manager = new ImageManager(new Driver());
        $image = $manager->read($imagePath);

        /// if image size is less thaan 150 px does not optimizes/// usefull for icon images
        if ($image->width() > $this->minWidthForPng) { /// $_FILES['file']['type']!='image/png' &&

            if ($convertToWebp && \File::exists($imagePath)) { // added
                \File::delete($imagePath);

                $filename = $fileBaseName . '.webp';
                $path = 'media' . DIRECTORY_SEPARATOR . $filename;
                $imagePath = $rootPath . $path;
                $fileExtension = 'webp';
            }

            $options = new OptionsModel();
            $text = $options->getSetting('website_image_watermark_text', 'site_configurations');
            $text = str_replace(" <br> ", " \n ", $text);

            if ($image->width() > $this->notResizeMinWidth && $image->height() > $this->notResizeMinWidth) {

                $this->setMaxSizes($image);
                $image->scale(width: $this->defaultMaxWidth, height: $this->defaultMaxHeight);
                $image->save($imagePath, quality: 75);
            }

            if ($request -> useWatermark && is_file(public_path('img/watermark-logo1.png'))) {
                /// add watermark image
                $image->place(public_path('img/watermark-logo1.png'), 'bottom-right', 0, 30);
            }

            /// add watermark text
            /** * /
            $jpg->text($text, 500, 300, function($font) {
            if(is_file("./font/bpg_excelsior_caps_dejavu.ttf")){
            $font->file("./font/bpg_excelsior_caps_dejavu.ttf");
            }else{
            $font->file(3);
            }
            $font->size(84);
            $font->color([255, 255, 255, 0.5]);
            $font->align('right');
            $font->valign('bottom');
            $font->angle(0);
            });
            /***/

            $image->save($imagePath, quality: 75);
        }

        $mediaModel = new MediaModel(['table' => $table]);

        $id = $mediaModel->upd(['path' => $path, 'upload_form' => $request->upload_form]);

        return $mediaModel->getOne($id);
    }

    public function uploadToServer(Request $request)
    {

        $res = $this->uploadImageToStorage($request);
        $table = (isset($this->mediaTables[$request->mediatable])) ? $this->mediaTables[$request->mediatable] : current($this->mediaTables);

        if (is_numeric($request->page) && $table == 'media') {
            $pageModel = new PageModel();

            $rr = $pageModel->addPageMedia(['key' => $request->field, 'data_id' => $request->page, 'file_id' => $res['id']]);

            $ret = $rr['draft'];


        } elseif (is_numeric($request->page) && $table == 'shopmedia') {
            if ($request->model == 'ProductModel') {
                $pageModel = new ProductsModel();
                $pageModel->addShopMedia(['data_id' => $request->page, 'res' => $res['id'], 'field' => $request->field]);
                $res = $pageModel->productgetone($request->page);
                $ret = _cv($res, [$request->field]);

            } else {
                $pageModel = new StockModel();
                $pageModel->addShopMedia(['data_id' => $request->page, 'res' => $res['id'], 'field' => $request->field]);
                $res = $pageModel->stockgetone($request->page);
                $ret = _cv($res, [$request->field]);
            }

        } else {
            $ret = [$res];
        }

        if (!is_array($ret)) {
            $ret = [];
        }
//        $ret = array_values($ret);
//p($ret);
        return response($ret, 200, ['newidddd' => $res['id']]);
    }

    /** upload none graphic files */
    public function uploadFileToServer(Request $request)
    {

        $file = $this->justUploadFileToServer($request);

        $ret = [];
        if (is_numeric($request->page) && $request->field) {
            $pageModel = new PageModel();

            if (isset($file['id'])) {
                $pageModel->addPageMedia(['key' => $request->field, 'data_id' => $request->page, 'file_id' => $file['id']]);
            }

            $page = $pageModel->getOneDraft(['id' => $request->page, 'raw' => 1]);

            $ret = _cv($page, $request->field, 'ar') ? $page[$request->field] : [];

        } else if (isset($file['id'])) {
            $ret = [$file];
        }

        return response($ret);
    }

    public function uploadToServerMenu(Request $request)
    {
        $res = $this->uploadImageToStorage($request);
        if (!_cv($res, 'id', 'nn'))
            return false;

        //       p($res);

        $pageModel = new SiteMapModel();
        $medias = $pageModel->addMenuMedia(['field' => $request->field, 'menu_id' => $request->menuId, 'file' => $res['id']]);

        return response($medias);
    }

    public function justUploadToServer(Request $request){

        if($request->directory)$request->type = $request->directory;

        if($request->type == 'video' || $request->type == 'file' || $request->type == 'onlineForm'){
            $res = $this->justUploadFileToServer($request);
        }else{
            $res = $this->uploadImageToStorage($request);
        }

        return response($res);
    }

    public function justUploadFileToServer($request){
        $dir = empty($request->type)?'file':$request->type;


        if($request->validate){

            $rules = array(
                'file' => $request->validate
            );
            $validator = \Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return ['success'=>false,'message'=>$validator->errors()->first()];
            }


        }

        $filename = pathinfo($request->file('file')->getClientOriginalName(), PATHINFO_FILENAME);
        $filename = date('Ymdis').'-'.sanitizeFilename($filename).'.'.$request->file('file')->getClientOriginalExtension();

        $path = Storage::disk('public')->putFileAs( $dir, $request->file, $filename );


        $mediaModel = new MediaModel();

        $id = $mediaModel->upd(['path'=>$path, 'upload_form'=>$request->upload_form]);
        $res = $mediaModel->getOne($id);

        return $res;
    }

    public function convertToWebp(Request $request) // added
    {
        $rootPath = config('filesystems.disks.public.root');

        if (!_cv($request -> imageData, 'id', 'nn')) return false;

        $table = (isset($this -> mediaTables[$request -> mediatable])) ? $this -> mediaTables[$request -> mediatable] : current($this -> mediaTables);

        $mediaModel = new MediaModel(['table' => $table]);
        $res = $mediaModel -> getOne($request -> imageData['id']);

        $resolutions = ['desktop', 'tablet', 'mobile'];

        $originalFileName = $rootPath . $res["name"];

        $originalFileBaseName = pathinfo($res["name"], PATHINFO_FILENAME);
        $originalFileExtension = strtolower(pathinfo($res["name"], PATHINFO_EXTENSION));
        $originalFileDir = pathinfo($res["name"], PATHINFO_DIRNAME);

        $filesDir = config('filesystems.disks.public.url');

        $newOriginalImageName = "{$originalFileDir}/{$originalFileBaseName}.webp";
        $newOriginalFileAddress = $filesDir . $newOriginalImageName;
        $newOriginalImagePath = $rootPath . $newOriginalImageName;

        if ($originalFileExtension === 'webp')
        {
            $res['error'] = 'conversion is not required';

            return response($res);
        }

        if (!\File::exists($originalFileName))
        {
            $res['error'] = 'file does not exist';

            return response($res);
        }

        foreach ($resolutions as $resolution)
        {
            $originalImageByDeviceName = "{$resolution}/{$originalFileDir}/{$originalFileBaseName}.{$originalFileExtension}"; // added
            $originalImageByDevicePath = $rootPath . $originalImageByDeviceName;

            $manager = new ImageManager(new Driver());
            $image = $manager->read(\File::exists($originalImageByDevicePath) ? $originalImageByDevicePath : $originalFileName);

            $imageByDeviceName = "{$resolution}/{$originalFileDir}/{$originalFileBaseName}.webp";

            $imageByDevicePath = $rootPath . $imageByDeviceName;
            $imageByDeviceAddress = $filesDir . $imageByDeviceName;

            $image->toWebp(75)->save($imageByDevicePath);

            $res['devices'][$resolution] = $imageByDeviceAddress;
        }

        $manager = new ImageManager(new Driver());
        $image = $manager->read($originalFileName);
        $image->toWebp(75)->save($newOriginalImagePath);
        $newFileSize = \File::size($newOriginalImagePath);

        $res['url'] = $newOriginalFileAddress;
        $res['mime_type'] = 'image/webp';
        $res['name'] = $newOriginalImageName;
        $res['size'] = $newFileSize;

        \DB::table($table) -> where('id', $request -> imageData['id']) -> update(['name' => $newOriginalImageName, 'file_name' => $newOriginalFileAddress, 'mime_type' => 'image/webp', 'size' => $newFileSize]);

        return response($res);
    }

    public function saveChangedImage(Request $request){
        $rootPath = config('filesystems.disks.public.root'); //root, url

        if(!_cv($request->imageData, 'id', 'nn'))return false;

        $table = (isset($this->mediaTables[$request->mediatable]))?$this->mediaTables[$request->mediatable]:current($this->mediaTables);

        $mediaModel = new MediaModel(['table'=>$table]);
        $res = $mediaModel->getOne($request->imageData['id']);

        $descriptions = [];
        if(_cv($request->imageData, 'descriptions', 'ar')){
            $descriptions = $request->imageData['descriptions'];
        }

        $mediaModel->updDescription($descriptions, $request->imageData['id']);

        if($request->cropImg && is_array($request->cropImg) && $res['name']){

            $ext = pathinfo($res['name'], PATHINFO_EXTENSION);

            foreach ($request->cropImg as $k=>$v){
                if(empty($v))continue;

                $file = explode(';base64,', $v);
                if(!isset($file[1]))return response('error while saving croped file', 201);

                $manager = new ImageManager(new Driver());
                $jpg = (string)$manager->read(base64_decode($file[1]))->encodeByExtension($ext, quality: 75);
//                print $k.'/'.$res['name'];
                $path = Storage::disk('public')->put($k.'/'.$res['name'], $jpg);
            }
        }

        $res = $mediaModel->getOne($request->imageData['id']);


        $original = "{$rootPath}{$res['name']}";


        /// resize image width proportional
        $resizeWidth = is_array($request->resizeWidth)?$request->resizeWidth:[];

        if(_cv($resizeWidth, 'desktop', 'nn')){
            $filePath = "{$rootPath}{$res['name']}";
            if(is_file($filePath)){
                $manager = new ImageManager(new Driver());
                $manager->read($filePath)->scale(width: $resizeWidth['desktop'])->save($filePath, quality: 65);
            }
        }

        /// resize to all devices
        foreach ($resizeWidth as $k=>$v){
            /// if widt is not defined or is less 50px do nothing
            if(!is_numeric($v) || $v < 50)continue;

            $filePath = "{$rootPath}{$k}/{$res['name']}";

            /// if there is not device file copy from original
            if( !is_file($filePath) && is_file($original) ) {

                try {
                    // Assuming $original and $filePath are properly defined
                    copy($original, $filePath);
                    // Copy successful

                } catch (\Exception $e) {
                    // Handle other exceptions
                    // Log or display a generic error message
//                    p($e);
                    print "can`t create file '{$filePath}'; Check directory name is correct and write permissions";
                    exit();
                }

            }
            /// if device file not exists do nothing
            if( !is_file($filePath) ) {
                continue;
            }

            /// modify device image
            $manager = new ImageManager(new Driver());
            $manager->read($filePath)->scale(width: $v, height: $this->defaultMaxHeight)->save($filePath, quality: 65);

        }

        $res = $mediaModel->getOne($request->imageData['id']);

        return response($res);

    }

    public function saveChangedFile(Request $request){

        if(!_cv($request->imageData, 'id', 'nn'))return false;
//       if(!$request->imageData->id)return false;
        $mediaModel = new MediaModel();
        $res = $mediaModel->getOne($request->imageData['id']);
//        p($res);

        $descriptions = [];
        if(_cv($request->imageData, 'descriptions', 'ar')){
            $descriptions = $request->imageData['descriptions'];
        }

        $mediaModel->updDescription($descriptions, $request->imageData['id']);

        $res = $mediaModel->getOne($request->imageData['id']);

//       p($res);

        return response($res);

    }

    public function deleteMedia(Request $request){

        if($request->mediatable == 'product'){
            $source = new Stock();
            return $source->deletePageMedia();
        }else if($request->mediatable == 'media'){
            $source = new Page();
            return $source->deletePageMedia($request->all());
        }

        return response(['message'=>'coud not delete media'], 201);

    }


    /** if uploaded image is biger than default size reduce it; else leave uploaded sizes */
    public function setMaxSizes($image){
        $this->defaultMaxWidth = $image->width() > $this->defaultMaxWidth ? $this->defaultMaxWidth : $image->width();
        $this->defaultMaxHeight = $image->height() > $this->defaultMaxHeight ? $this->defaultMaxHeight : $image->height();
        return false;
    }

    public function getLibrary(Request $request){
        $ret = $this->mainModel->getLibraryList($request->all());
        return response($ret);
    }

    public function getImgSource(Request $request){
//        p($request->imagePath);
        $fileName = substr($request->imagePath, strpos($request->imagePath, 'static/')+7);
        $filePath = public_path('static/').$fileName;
        $fileSource = file_get_contents($filePath);
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $fileSource = "data:image/{$extension};base64,".base64_encode($fileSource);
        return $fileSource;
    }

    public function getImagesList(Request $request)
    {
        $ret = $this->mainModel->getList(['ids'=>$request->id]);

        return response($ret);
    }


}
