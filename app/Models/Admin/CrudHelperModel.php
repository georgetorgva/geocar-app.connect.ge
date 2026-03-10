<?php

namespace App\Models\Admin;

trait CrudHelperModel
{

    public function getListParamsChecker($params = [], $paramTypes = [], $defaults=[]){
//        p($params);

        foreach ($defaults as $k=>$v){
            if(!isset($params[$k]))$params[$k] = $v;
        }

        /// define required but undefined variables
        if(!_cv($params, 'listCount', 'nn'))$params['listCount'] = 0;
        $params['pageNumber'] = _cv($params, 'pageNumber', 'nn')?$params['pageNumber']:1;
        $params['table'] = _cv($params, 'table')?"{$params['table']}.":'';
        $params['table'] = str_replace('..', '.', $params['table']);

        $paging = $this->paging($params);
        $params['offset'] = $paging['offset'];
        $params['limit'] = $paging['limit'];

        $params['orderByRaw'] = $this->orderByGenerator($params);
        $params['date'] = $this->datesGenerator($params);

        if(_cv($paramTypes, ['whereIn'], 'ar')){
            foreach ($paramTypes['whereIn'] as $k=>$v){
                $params[$v] = $this->prepareWhereIns($params, $v);
            }
        }


        return $params;
    }


    public function paging($params = []){
        $paging['offset'] = 0;

        /// default page limit
        $paging['limit'] = 10;
            if(_cv($params, 'perPage', 'nn'))$paging['limit'] = $params['perPage'];

        if(!_cv($params, 'listCount', 'nn'))return $paging;

        $paging['offset'] = ($params['pageNumber'] * $paging['limit']) - $paging['limit'] > $params['listCount']?0:($params['pageNumber'] * $paging['limit'])-$paging['limit'];

        return $paging;
    }

    public function orderByGenerator($params = []){
    /// default order direction
    $orderDirection = 'asc';
    $sortField = 'id';

        if (_cv($params, 'page_order') == 'ASC') {
            $orderDirection = 'asc';
        } else if (_cv($params, 'page_order') == 'RANDOM') {
            $orderDirection = 'rand()';
        } else if (_cv($params, 'page_order')) {
            $orderDirection = 'desc';
        } else if (_cv($params, 'orderDirection')) {
            $orderDirection = strtolower($params['orderDirection']);
        }

        if (_cv($params, 'orderBy')) {
            $sortField = $params['orderBy'];
        } else if (_cv($params, 'sortField')) {
            $sortField = $params['sortField'];
        }

        return $sortField ? " {$params['table']}{$sortField} {$orderDirection} ":'';
    }

    public function datesGenerator($params = []){
        /// default order direction
        $ret = [];

        if(!isset($params['date']) && _cv($params, 'searchDate', 'ar'))$params['date'] = $params['searchDate'];

        if(!isset($params['date']))return $ret;
        if(!is_array($params['date']))$ret[0] = $params['date'];
        if(_cv($params, 'date.0'))$ret[0] = $params['date'][0];
        if(_cv($params, 'date.1'))$ret[1] = $params['date'][1];

        return $ret;
    }

    public function prepareWhereIns($params = [], $paramName=''){
        if(!isset($params[$paramName]) || empty($params[$paramName]))return false;

        if(!is_array($params[$paramName])){
            return [$params[$paramName]];
        }

        if(_cv($params, [$paramName], 'ar')){
            foreach ($params[$paramName] as $k=>$v){
                if(empty($v))unset($params[$paramName][$k]);
            }
        }

        return _cv($params, [$paramName], 'ar');
    }

}
