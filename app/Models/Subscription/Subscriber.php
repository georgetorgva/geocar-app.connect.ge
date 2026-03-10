<?php

namespace App\Models\Subscription;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use \Validator;

class Subscriber extends Model
{
    public $table = 'subscribers';
    public $timestamps = true;

    protected $fillable = [
        'email',
        'info',
        'lang',
        'status',
        'token'
    ];

    protected $visible = [
        'id',
        'email',
        'info',
        'created_at',
        'updated_at',
        'lang',
        'status'
    ];

    protected $searchable= [
        'email',
        'status'
    ];

    protected $editable = [
        'lang',
        'status'
    ];

    public function getList($input = [])
    {
        $paginationData = self::validatePaginationInput($input);

        $limit = $paginationData['limit'];
        $activePage = $paginationData['page'];
        $offset = ($activePage - 1) * $limit;

        $query = \DB::table($this->table)->select($this->visible)->where('status', '!=', 'deleted');

        $searchText = $input['searchText'] ?? null;
        $searchText = is_string($searchText) ? trim($searchText) : '';

        $searchByFields = $input['searchBy'] ?? [];

        if (is_array($searchByFields) && !empty($searchByFields))
        {
            $jsonFields = config('subscription.generalConfigs.jsonFields') ?? [];

            foreach ($input['searchBy'] as $fieldKey => $value)
            {
                if (!$value || !is_scalar($value)) continue;

                if (isset($jsonFields[$fieldKey]))
                {
                    $searchKey = 'info->' . $jsonFields[$fieldKey];

                    $query->where($searchKey, 'like', '%' . $value . '%');
                }

                elseif (in_array($fieldKey, $this->visible))
                {
                    $query->where($fieldKey, 'like', '%' . $value . '%');
                }
            }
        }

        else if ($searchText)
        {
            $jsonFields = config('subscription.generalConfigs.jsonFields') ?? [];
            $searchableFields = $this->searchable;
            $searchText = '%' . $searchText . '%';

            $query->where(function($subQuery) use ($jsonFields, $searchableFields, $searchText) {

                foreach ($jsonFields as $fieldName)
                {
                    $subQuery->orWhere('info->' . $fieldName, 'like', $searchText);
                }

                foreach ($searchableFields as $fieldName)
                {
                    $subQuery->orWhere($fieldName, 'like', $searchText);
                }
            });
        }

        $count = $query->count();

        $query->orderBy('id', 'desc')->offset($offset)->take($limit);

        $list = $query->get();

        foreach ($list as $record)
        {
            $record->info = json_decode($record->info, true);
        }

        return [
            'list' => $list,
            'listCount' => $count,
            'page' => $activePage
        ];
    }

    public function getItem($id)
    {
        $item = \DB::table($this->table)->select($this->visible)->where('id', $id)->first();

        $info = json_decode($item->info, true);

        if (is_array($info) && !empty($info))
        {
            foreach ($info as $key => $value)
            {
                $item->{'info.' . $key} = $value;
            }
        }

        unset($item->info);

        return $item;
    }

    public function updateItem($input)
    {
        $id = 0;

        $jsonFields = config('subscription.generalConfigs.jsonFields') ?? [];

        if (!empty($input['id']))
        {
            $updateData = [
                'info' => []
            ];

            $id = $input['id'];

            if (!empty($jsonFields))
            {
                foreach ($jsonFields as $formField => $jsonField)
                {
                    if (isset($input[$formField]) && is_scalar($input[$formField]))
                    {
                        $updateData['info'][$jsonField] = $input[$formField];
                    }
                }
            }

            foreach ($this->editable as $writableField)
            {
                if (isset($input[$writableField]) && is_scalar($input[$writableField]))
                {
                    $updateData[$writableField] = $input[$writableField];
                }
            }

            $updateData['info'] = json_encode($updateData['info'], JSON_UNESCAPED_UNICODE);

            \DB::table($this->table)->where('id', $id)->update($updateData);
        }

        else
        {
            $insertData = [
                'email' => $input['email'],
                'status' => $input['status'],
                'token' => bin2hex(random_bytes(30)),
                'lang' => $input['lang'] ?? null,
                'info' => []
            ];

            if (!empty($jsonFields))
            {
                foreach ($jsonFields as $formField => $jsonField)
                {
                    if (isset($input[$formField]) && is_scalar($input[$formField]))
                    {
                        $insertData['info'][$jsonField] = $input[$formField];
                    }
                }
            }

            $insertData['info'] = json_encode($insertData['info'], JSON_UNESCAPED_UNICODE);

            $id = \DB::table($this->table)->insertGetId($insertData);
        }

        return $this->getItem($id);
    }

    public function deleteItem($id)
    {
        \DB::table($this->table)->where('id', $id)->update(['status' => 'deleted']);

        return $this->getItem($id);
    }

    public function getAll()
    {
        $exportFields = config('subscription.generalConfigs.excelExportFields');

        $list = [];

        if (empty($exportFields))
        {
            $list = \DB::table($this->table)->select($this->visible)->where('status', '!=', 'deleted')->get();
        }

        else
        {
            $fieldsStr = "";

            $regularFields = $exportFields['regular'] ?? [];
            $jsonFields = $exportFields['json'] ?? [];

            if (!empty($regularFields))
            {
                $fieldsStr = implode(',', $regularFields);
            }

            if (!empty($jsonFields))
            {
                $jsonParts = [];

                foreach ($jsonFields as $field)
                {
                    $jsonParts[] = "JSON_VALUE(`info`,'$.{$field}') AS `{$field}`";
                }

                $jsonStr = implode(',', $jsonParts);

                $fieldsStr = empty($fieldsStr) ? $jsonStr : $fieldsStr . ',' . $jsonStr;
            }

            $list = empty($fieldsStr) ? \DB::table($this->table)->select($this->visible)->where('status', '!=', 'deleted')->get() : \DB::table($this->table)->selectRaw($fieldsStr)->where('status', '!=', 'deleted')->get();
        }

        return ['list' => $list];
    }

    // helpers

    public static function validatePaginationInput($input)
    {
        $rules = [
            'limit' => 'nullable|integer|min:1',
            'page' => 'nullable|integer|min:1'
        ];

        $paginationData = [
            'page' => 1,
            'limit' => 10
        ];

        $validator = Validator::make($input, $rules);

        if (!$validator->fails())
        {
            $paginationData['page'] = $input['page'];
            $paginationData['limit'] = $input['limit'];
        }

        return $paginationData;
    }
}
