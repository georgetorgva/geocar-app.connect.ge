<?php

namespace App\Models\User;

use App\Models\Admin\Roles\RoleModel;
use App\Models\CustomModules\Ltb\CommentsModel;
use App\Models\Shop\LocationsModel;
use App\Traits\ConnectTransformatorTrait;
use http\Client\Request;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use \Validator;


class User extends Authenticatable implements JWTSubject, MustVerifyEmail
{
    use Notifiable,
        ConnectTransformatorTrait;

    protected $table = 'users';

    protected $transformFields = [
        'id' => 'id',
        'username' => 'username',
        'email' => 'email',
        'fullname' => 'fullname',
        'phone' => 'phone',
        'avatar' => 'avatar',
        'password' => 'password'
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'username', 'email', 'password','fullname','phone','additional_info','address','p_id','status', 'rating', 'public_rating', 'last_activate', 'sort', 'verification', 'member_group'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function registerMediaConversions(Media $media = null):void
    {
        $this->addMediaConversion('thumb')
            ->width(100)
            ->height(100)
            ->nonQueued();
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function getOne($params=[])
    {
        $params['limit'] = 1;
        $res = $this -> getList($params);
        if (isset($res['list'][0])) return $res['list'][0];
        return [];
    }

    public function getList ($params=[])
    {
        $returnData['listCount'] = 0;
        $returnData['localisedTo'] = requestLan();
        $returnData['list'] = [];
        $params['limit'] = _cv($params, ['limit'], 'nn') ?$params['limit']:10;
        $params['page'] = _cv($params, 'page', 'nn')?$params['page']:1;
        $returnData['page'] = $params['page'];


        DB::enableQueryLog();

        $selectFields = ['id','username','role','email','fullname','phone','email_verified_at','remember_token','created_at','updated_at','status','address','p_id','api_token','additional_info', 'rating', 'last_activate', 'member_group'];
        $selectFields = "{$this -> table}.".implode(", {$this -> table}.", $selectFields);

        $qStr = "{$selectFields}, roles.name as roleName, ROUND(SUM(ltb_comments.rating) / COUNT(ltb_comments.rating), 1) as publicRating, COUNT(ltb_comments.rating) as numberOfComments";

        if(_cv($params, 'selectFields')){
            $qStr = $params['selectFields'];
        }

        $qr = DB::table($this -> table) -> select(DB::raw($qStr));

        if(!_cv($params, 'search') && _cv($params, 'searchText') )$params['search'] = $params['searchText'];
        if ( _cv($params, 'search') ){
            $qr->whereRaw("LOCATE( '{$params['search']}', fullname )");
        }

        if (_cv($params, ['id']) && !_cv($params, ['id'], 'ar')) $params['id'] = [$params['id']];
        if (_cv($params, 'id', 'ar')) $qr -> whereIn($this -> table.'.id', $params['id']);

        if (_cv($params, ['role']) && !_cv($params, ['role'], 'ar')) $params['role'] = [$params['role']];
        if (_cv($params, 'role', 'ar')) $qr -> whereIn($this -> table.'.role', $params['role']);

        if (_cv($params, ['email']) && !_cv($params, ['email'], 'ar')) $params['email'] = [$params['email']];
        if (_cv($params, 'email', 'ar')) $qr -> whereIn($this -> table.'.email', $params['email']);

        if (_cv($params, ['fullname']) && !_cv($params, ['fullname'], 'ar')) $params['fullname'] = [$params['fullname']];
        if (_cv($params, 'fullname', 'ar')) $qr -> whereIn($this -> table.'.fullname', $params['fullname']);

        if (_cv($params, ['phone']) && !_cv($params, ['phone'], 'ar')) $params['phone'] = [$params['phone']];
        if (_cv($params, 'phone', 'ar')) $qr -> whereIn($this -> table.'.phone', $params['phone']);

        if (_cv($params, ['status']) && !_cv($params, ['status'], 'ar')) $params['status'] = [$params['status']];
        if (_cv($params, 'status', 'ar')) $qr -> whereIn($this -> table.'.status', $params['status']);

        if (_cv($params, 'address') && !is_array($params['address'])) $qr -> where($this -> table.'.address', 'like', "%{$params['address']}%");
        if (_cv($params, 'additional_info') && !is_array($params['additional_info'])) $qr -> where($this -> table.'.additional_info', 'like', "%{$params['additional_info']}%");

        if (_cv($params, 'conf') && !is_array($params['conf'])) $qr -> where($this -> table.'.conf', 'like', "%{$params['conf']}%");
        $qr -> whereNotIn("{$this -> table}.status", ['admin', 'deleted']);

        $qr -> leftJoin('ltb_comments', function($join){
            $join -> on('ltb_comments.master_id', '=', $this -> table . '.id')->where('ltb_comments.status', 'published');
        });

        $qr -> leftJoin('roles', function($join){
            $join -> on('roles.id', '=', $this -> table . '.role');
        });


        $sortfield = _cv($params, ['sortField'])?$params['sortField']:'id';
        $sortDirection = _cv($params, ['sortDirection'])?$params['sortDirection']:'DESC';

        $qr -> orderBy("{$this -> table}.{$sortfield}", $sortDirection);

        $listCount = $qr->count(DB::raw("DISTINCT({$this->table}.id)"));
        $qr -> groupBy("{$this->table}.id");

        if (_cv($params, 'limit')) $qr -> take($params['limit']);

        if (_cv($params, 'page')) $qr -> skip(($params['page'] - 1) * $params['limit']) -> take($params['limit']);

        if (_cv($params, 'searchBy', 'ar')){
            foreach ($params['searchBy'] as $k=>$v){
                $qr -> havingRaw("LOCATE('{$v}', {$k})");
            }
        }

        if (_cv($params, 'searchByOr', 'ar')){
            foreach ($params['searchByOr'] as $k=>$v){
                $qr -> orHavingRaw("LOCATE('{$v}', {$k})");
            }
        }

        $list = $qr -> get();

//        p($list);
//        p(DB::getQueryLog());

        if (!$list) return $returnData;

        $ret = _psql(_toArray($list));


        foreach ($ret as $k=>$v){

            if(_cv($params, 'fields', 'ar')){
                $tmp = [];
                foreach ($params['fields'] as $kk=>$vv){
                    $tmp[$vv] = @$ret[$k][$vv];
                }

                $ret[$k] = $tmp;
            }
        }

        $returnData['listCount'] = $listCount;
        $returnData['list'] = $ret;
        $returnData['page'] = _cv($params, 'page', 'nn')?$params['page']:1;

        return $returnData;
    }


    public function upd($data = [])
    {
//        DB::enableQueryLog();
        $request = Request();

        /// validate page table regular data
        $validator = Validator::make($data,
            [
                'id' => 'integer|nullable',
                'username' => 'required',
            ]
        );

        if ($validator->fails()){
            return $validator->messages()->all();
        }

        if(_cv($data, 'status')=='admin')$data['status'] = 'person';


        /// update or ....
        if(_cv($data, 'id', 'nn')){
            $upd = User::find( $data['id'] );
        }

        // .... or create
        if(!isset($upd->id)){
            $upd = new User();
        }

        foreach ($this->fillable as $k=>$v){
            if(!isset($data[$v]))continue;
            $upd[$v] = is_array($data[$v])?_psqlupd($data[$v]):$data[$v];
        }

        $upd->save();

        return $upd->id;
    }

    public function updUserAddress($params = []){
//p($params['address']['city']);

        if(_cv($params, ['address','city'], 'nn')){
            $locations = new LocationsModel();
            $location = $locations->getOne(['id'=>$params['address']['city']]);

            $params['address']['cityId'] = $location['id'];
            $params['address']['city'] = $location['name_ge'];
            $params['address']['cityTitles']['name_ge'] = $location['name_ge'];
            $params['address']['cityTitles']['name_en'] = $location['name_en'];

        }

        if(!_cv($params, ['address','cityId'], 'nn') || !_cv($params, ['address','city']))return ['error'=>'city ID not exist'];

        if(!_cv($params, 'id', 'nn') || !_cv($params, 'address', 'ar'))return ['error'=>'not correct structure'];
        $userData = User::select(['id','address'])->where('id', $params['id'])->first();

        $params['address']['uid'] = _cv($params, 'address.uid')?$params['address']['uid']:uidGenerator();
        $defaultAddressUid = _cv($params, ['address','default','default'])==1?$params['address']['uid']:false;

//        print $userData->id;
        $addresses = _psqlCell($userData->address);
        if(!is_array($addresses))$addresses = [];


        $addressChanged = false;
        foreach ($addresses as $k=>$v){
            /// if uid not exist for some address generate new one
            if(!_cv($v, ['uid']))$addresses[$k]['uid'] = $v['uid'] = uidGenerator();

            /// if current editaed address set to default, all other addreses disable default
            if($defaultAddressUid && $v['uid'] != $defaultAddressUid){
                $v['default']['default'] = 0;
            }

            /// if address already exists and needs edit change with new info
            if($params['address']['uid'] == $v['uid']){
                $addresses[$k] = $params['address'];
                $addressChanged = true;
            }

        }

        /// if address is new add to addresses stack
        if(!$addressChanged)$addresses[] = $params['address'];

        $userData->address = _psqlupd($addresses);
        User::where('id', $params['id'])->update(['address'=>$addresses]);
//        print $userData->save();

        return $addresses;

    }

    public function delUserAddress($params = []){
//        p($params);
        if(!_cv($params, 'id', 'nn') || !_cv($params, 'uid'))return false;
        $userData = User::select(['id','address'])->where('id', $params['id'])->first();

        $addresses = _psqlCell($userData->address);
//        p($addresses);
        foreach ($addresses as $k=>$v){
            /// if uid not exist for some address generate new one
            if(_cv($v, ['uid']) == $params['uid']){
                unset($addresses[$k]);
                break;
            };

        }

        $addresses = array_values($addresses);
//        $userData->address = _psqlupd($addresses);
        User::where('id', $params['id'])->update(['address'=>_psqlupd($addresses)]);

//        $userData->save();

        return $addresses;

    }

    public function comments()
    {
        return $this->hasMany(CommentsModel::class, 'master_id');
    }

}
