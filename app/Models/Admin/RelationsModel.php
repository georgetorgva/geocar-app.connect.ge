<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * main model for relations
 */

class RelationsModel extends Model
{
    public static function doRelations($params)
    {
//        p($params);
        $valuesArrayKeyName = isset($params['formKeyName'])?$params['formKeyName']:'dataList';

        $optionsForRelations = [
            'relationTable' => $params['relationTable'],
            'firstKeyName' => $params['firstKeyName'],
            'firstKeyValue' => $params['firstKeyValue'],
            'secondKeyName' => $params['secondKeyName']
        ];

        if(isset($params['relationTableName'])) $optionsForRelations['relationTableName'] = $params['relationTableName'];
        if(isset($params['relationNode'])) $optionsForRelations['relationNode'] = $params['relationNode'];

        self::removeRelations($optionsForRelations);

        if (isset($params['quantityKeyName']))
        {
            $optionsForRelations['quantityKeyName'] = $params['quantityKeyName'];

            foreach ($params[$valuesArrayKeyName] as $key => $value)
            {
                self::addRelation($optionsForRelations, [$key, $value]);
            }

        } else {
            $mergedArray = self::getMergedArray($params[$valuesArrayKeyName]);

            if (isset($params['tableRelationField']))
            {
                $optionsForRelations['tableRelationField'] = $params['tableRelationField'];
                $optionsForRelations['tableRelationFieldValue'] = $params['tableRelationFieldValue'];
                if(isset($params['relationTableName'])) $optionsForRelations['relationTableName'] = $params['relationTableName'];
            }

            foreach ($mergedArray as $value)
            {
                self::addRelation($optionsForRelations, $value);
            }
        }

        return true;
    }

    public static function removeRelations($options)
    {
        if(isset($options['relationTableName']) && isset($options['relationNode']) ){
//            DB::table($options['relationTable']) -> where($options['firstKeyName'], $options['firstKeyValue'])->where('table', $options['relationTableName'])->where('node', $options['relationNode']) -> delete();
            DB::table($options['relationTable']) -> where($options['firstKeyName'], $options['firstKeyValue'])->where('table', $options['relationTableName']) -> delete();
        }else if(isset($options['relationTableName'])){
            DB::table($options['relationTable']) -> where($options['firstKeyName'], $options['firstKeyValue'])->where('table', $options['relationTableName']) -> delete();
        }else{
            DB::table($options['relationTable']) -> where($options['firstKeyName'], $options['firstKeyValue'])-> delete();
        }

    }

    public static function addRelation($options, $value)
    {
        $insertionArray = [
            $options['firstKeyName'] => $options['firstKeyValue']
        ];

        if (isset($options['relationNode']))
        {
            $insertionArray['node'] = $options['relationNode'];
        }

        if (isset($options['quantityKeyName']))
        {
            $insertionArray[$options['secondKeyName']] = $value[0];
            $insertionArray[$options['quantityKeyName']] = $value[1];
        }

        elseif (isset($options['tableRelationField']))
        {
            $insertionArray[$options['secondKeyName']] = $value;
            $insertionArray[$options['tableRelationField']] = $options['tableRelationFieldValue'];
        }

        else{
            if(_cv($options, 'relationTableName'))$insertionArray['table'] = $options['relationTableName'];
            $insertionArray[$options['secondKeyName']] = $value;
        }
//p($insertionArray);
        DB::table($options['relationTable']) -> insert($insertionArray);
    }

    public static function getMergedArray($array=[])
    {

        $mergedArray = [];
        if(!is_array($array))$array = [];
        array_walk_recursive($array, function($item, $key) use (&$mergedArray) {
            if (!is_array($item)) $mergedArray[] = $item;
        });

        $mergedArray = array_unique($mergedArray);

        return $mergedArray;
    }
}
