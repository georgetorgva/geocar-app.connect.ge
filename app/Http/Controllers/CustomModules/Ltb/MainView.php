<?php

namespace App\Http\Controllers\CustomModules\Ltb;

use App\Models\BuildingDevelopment\ProjectModel;
use App\Models\User\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Shop\WalletsModel;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use App\Models\CustomModules\Ltb\CommentsModel;
use App\Models\CustomModules\Ltb\ProjectsModel;
use App\Http\Controllers\Admin\User\UserController;
use App\Models\CustomModules\TagAndMatch\LeadModel;
use App\Models\CustomModules\TagAndMatch\CampaignModel;
use App\Http\Controllers\Admin\FormAggregators\MainAggregator;

/**
 * main controller for all the content types
 */
class MainView extends Controller
{

    //
    protected $mainModel;
    protected $error = false;

    /** get users */
    public function getMasters($params = [])
    {

        if (!_cv($params, 'status')) $params['status'] = ['master'];
        if (!_cv($params, 'limit')) $params['limit'] = 10;

        $masters = new User();
        $ret = $masters->getList($params);

        return response($ret);
    }
    /** get user */
    public function getMaster($params = [])
    {
        $user = new User();
        //        $params['status'] = 'public';
        $list = $user->getOne($params);

        $list = filterRecords([$list], ['id', 'fullname', 'email', 'last_activate', 'numberOfComments', 'phone', 'publicRating', 'rating', 'status', 'additional_info.master.economyProductShare', 'additional_info.master.standardProductShare', 'additional_info.master.premiumProductShare', 'additional_info.master.profilePicture', 'additional_info.master.region', 'additional_info.master.workingExperience', 'additional_info.master.email', 'additional_info.master.viber', 'additional_info.master.whatsapp', 'additional_info.master.mobile']);

        return response($list[0]);
    }
    // Projects API-s
    public function getProject($params = [])
    {
        $params['status'] = ['published'];
        $params['translate'] = requestLan();


        $project = new ProjectsModel();
        $list = $project->getOne($params);

        return response($list);
    }

    public function getProjects($params = [])
    {
        if (!_cv($params, 'limit')) $params['limit'] = 10;

        $params['status'] = ['published'];
//        $params['whereRaw'] = ["JSON_EXTRACT(joinedTable_user.additional_info, '$.contragents.masterVisibility') = true", "joinedTable_user.status = 'master'"];
        $params['customSelect'] = 'ROUND(AVG(joinedTable_ltb_comments.rating), 1) as public_rating, COUNT(DISTINCT joinedTable_ltb_comments.id) as public_rating_count';

        // Order By
        $params['sortDirection'] = 'DESC';
        $params['sortField'] = 'joinedTable_user.sort';
        $params['translate'] = requestLan();

        if (_cv($params, 'customFilter', 'ar')) {
            $params['sortDirection'] = 'DESC, public_rating_count';
            $params['sortField'] = 'user_rating';

            if (isset($params['customFilter']['user_additional_info']['master'])) {
                $customJsonFilter = [];
                foreach ($params['customFilter']['user_additional_info']['master'] as $k => $JsonFilter) {
                    $customJsonFilter[] = 'joinedTable_user.additional_info->master->' . $k;
                    $toFilterArray[] = $JsonFilter;
                }

                $params['customJsonFilter'] = $customJsonFilter ?? null;
                $params['toFilterArray'] = $toFilterArray ?? null;
            }
            if (isset($params['customFilter']['user_rating']) || isset($params['customFilter']['public_rating'])) {
                foreach ($params['customFilter'] as $key => $value) {
                    $filter[$key] = $value;
                    unset($filter['user_additional_info']);
                }
                if(_cv($filter, 'user_rating')){
                    foreach($filter['user_rating'] as $key => $rating){
                        for ($i = 1; $i <= 9; $i++) {
                            $filter['user_rating'][] = (float) ($rating .'.'.$i);
                        }
                    }
                }
                if(_cv($filter, 'public_rating')){
                    foreach($filter['public_rating'] as $key => $rating){
                        for ($i = 1; $i <= 9; $i++) {
                            $filter['public_rating'][] = (float) ($rating .'.'.$i);
                        }
                    }
                }
                $params['orHaving'] = $filter;
            }
        }
//p($params);
        $project = new ProjectsModel();
        $list = $project->getList($params);
//        p($list);
        $list['list'] = filterRecords($list['list'], ['id', 'user_id', 'status', 'images', 'description', 'title','description', 'user_fullname', 'user_rating', 'public_rating', 'taxonomy', 'public_rating_count', 'user_additional_info.master.profilePicture', 'user_additional_info.master.region', 'user_additional_info.master.workOccupation']);

        return response($list);
    }
    // Comments API-s
    public function getComments($params = [])
    {
        if (!_cv($params, 'limit'))
            $params['limit'] = 9;

        if (_cv($params, 'isRandom') == true) {
            $params['sortDirection'] = 'RAND()';
            $params['sortField'] = ' ';
        }

        $params['status'] = ['published'];

        $comments = new CommentsModel();
        $list = $comments->getList($params);

        $list['list'] = filterRecords($list['list'], ['id', 'author_id', 'author_fullname', 'image', 'video', 'author_status', 'commentary', 'id', 'master_fullname', 'master_id', 'name', 'rating', 'slug', 'sort', 'date', 'author_additional_info.master.profilePicture', 'author_additional_info.person.profilePicture', 'taxonomy']);

        return response($list);
    }


}
