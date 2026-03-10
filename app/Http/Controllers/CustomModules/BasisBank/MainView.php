<?php

namespace App\Http\Controllers\CustomModules\BasisBank;

use App\Models\Admin\OptionsModel;
use App\Models\User\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Shop\WalletsModel;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Admin\User\UserController;

/**
 * main controller for all the content types
 */
class MainView extends Controller
{

    // http://basisbank2.connect.ge/source/api/admin/bankservices/updateServicesData
    // http://basisbank2.connect.ge/source/api/admin/bankservices/updateServiceXrates
    protected $mainModel;
    protected $option;
    protected $error = false;

    public function __construct()
    {
//        parent::__construct();
        $this->option = new OptionsModel();
//        $this->mainModel = new TaxonomyModel();
    }


    public function updateServicesData(){
        $ret = [];
        $ret[] = $this->updateServiceXrates();
        $ret[] = $this->getAtms(['lang'=>'en']);
        $ret[] = $this->getAtms(['lang'=>'geo']);
        $ret[] = $this->getAtms(['lang'=>'cn']);
        $ret[] = $this->getServiceCenters(['lang'=>'en']);
        $ret[] = $this->getServiceCenters(['lang'=>'geo']);
        $ret[] = $this->getServiceCenters(['lang'=>'cn']);

        return response($ret);

    }

    public function updateServiceXrates(){

        $rates = $this->getXrates();
        $this->saveXratesToDb($rates);

        return "updated ". count($rates). " currency rates";


    }


    /** get formated bank x rates */
    public function getXrates(){
        $serviceUrl = $this->option->getSetting('xratesApiUrl');

        $res = $this->curlRequest(['url'=>$serviceUrl]);
        if(!$res)return false;

        $xml = simplexml_load_string($res);
//p($xml);
//        $array = json_decode(json_encode($xml),TRUE);
//exit;
        $xrates = [];
        $i = 0;
        foreach($xml as $a => $b) {
            if(++$i > 30)break;
            $i++;
//p($b);
            $xrates[$i]['date'] = $b[0]->attributes()->date->__toString();
            foreach ($b->type as $k=>$v){
                $type = $v[0]->attributes()->name->__toString();

                foreach ($v->currency as $kk=>$vv){
                    $currency = $vv[0]->attributes()->name->__toString();
                    $xrates[$i]['xrate'][$type][$currency] = $vv->__toString();

                }
            }

        }
//        p($xrates);
//        exit;
        return $xrates;

    }

    public function getAtms($params = []){
        $params['lang'] = _cv($params, 'lang')?$params['lang']:'GEO';

        $serviceUrl = $this->option->getSetting('atmApiUrl');

        $res = $this->curlRequest(['url'=>$serviceUrl, 'fields'=>[ 'lang'=>strtoupper($params['lang']) ]]);

        if(!$res)return false;
        $res = json_decode($res, 1);
        /// if there is some error return false;
        if(_cv($res, 'ErrorCode', 'nn'))return false;
        $params['lang'] = substr($params['lang'], 0,2);

//        p($res);
//        print 'bankAtms_'.$params['lang'];
        $this->option->updSetting(['key'=>'bankAtms_'.$params['lang'], 'content_group'=>'bank_services_backup', 'value'=>_cv($res, 'Data.ATMs')]);

//        $rr = $this->option->getListBy(['key'=>'bankAtms_en']);
//        p($rr);

        return "updated ". count(_cv($res, 'Data.ATMs')). " ATMs; lang: ".$params['lang'];

//        p($res);
    }

    public function getServiceCenters($params = []){
        $params['lang'] = _cv($params, 'lang')?$params['lang']:'GEO';

        $serviceUrl = $this->option->getSetting('servicesApiUrl');

        $res = $this->curlRequest(['url'=>$serviceUrl, 'fields'=>['lang'=>strtoupper($params['lang'])]]);
        if(!$res)return false;
        $res = json_decode($res, 1);
        /// if there is some error return false;
        if(_cv($res, 'ErrorCode', 'nn'))return false;

        $params['lang'] = substr($params['lang'], 0,2);
        $this->option->updSetting(['key'=>'bankBranches_'.$params['lang'], 'content_group'=>'bank_services_backup', 'value'=>_cv($res, 'Data.Branches')]);

        return "updated ". count(_cv($res, 'Data.Branches')). " Branches; lang: ".$params['lang'];

    }

    static function websiteXrates($params = ''){

        $date = (!_cv($params, 'date'))?date('Y-m-d'):$params['date'];

        $rates = DB::table('bank_xrates')->select(['date', 'xrates'])->where('date', '<=', $date)->orderBy('date', 'desc')->limit(2)->get();
//        $rates = DB::select(DB::raw("select date, xrates from bank_xrates where date <= '{$date}' order by date desc limit 2"))->get();
        return $rates;
    }

    public function saveXratesToDb($formatedXrates = []){

        $latestXrateDate = DB::table('bank_xrates')->max('date');

        /// update all latest unapdated xrates
        foreach ($formatedXrates as $k=>$v){
            print $v['date'];
            if($latestXrateDate >= $v['date'])break;
            $tmp = [ 'xrates'=>json_encode($v['xrate']), 'date'=>$v['date'] ];

            DB::table('bank_xrates')->insert(
                $tmp
            );

            if($k > 100)break;
        }

        /// update latest/today xrate
        $latestXrate = current($formatedXrates);
        if(!_cv($latestXrate, ['date']))return false;
        $tmp = [ 'xrates'=>json_encode($latestXrate['xrate']), 'date'=>$latestXrate['date'] ];

        DB::table('bank_xrates')->where('date', $latestXrate['date'])->delete();

        DB::table('bank_xrates')->insert(
            $tmp
        );

        return false;
    }


    public function curlRequest($params = []){
        if(!_cv($params, 'url'))return false;

        $curl = curl_init();
        $post_fields = _cv($params, 'fields', 'ar')?http_build_query(_cv($params, 'fields')):'';

//        curl_setopt($curl, CURLOPT_SSLVERSION, 1); //0
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_fields);
        curl_setopt($curl, CURLOPT_VERBOSE, '1');
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, 120);

//        curl_setopt($curl, CURLOPT_SSLCERT,         $certFile );
//        curl_setopt($curl, CURLOPT_SSLKEYPASSWD,   'hsd8jIj8BTGJONNZ'); //hsd8jIj8BTGJONNZ H76brebyuBGFkl98
//        curl_setopt($curl, CURLOPT_SSLKEY,        $certKeyFile);

        curl_setopt($curl, CURLOPT_URL, $params['url']);
        $result = curl_exec($curl);
        $info = curl_getinfo($curl);

        if(curl_errno($curl)){
            return false;
        }

        curl_close($curl);

        return $result;

    }


}
