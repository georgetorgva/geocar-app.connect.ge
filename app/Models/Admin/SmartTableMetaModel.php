<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use \Validator;
use Illuminate\Support\Facades\DB;

class SmartTableMetaModel extends Model
{
    protected $table = '';
    public $timestamps = false;


    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    //
    protected $allAttributes = [
        'id',
        'table',
        'key',
        'val',
        'lan',
        'table_id',
    ];
    protected $fillable = [
        'id',
        'key',
        'lan',
        'table',
        'table_id',
        'created_at',
        'updated_at',
    ];

    /**
     * Write EAV meta fields for one record + locale to the meta table.
     *
     * Separates the incoming fields into two buckets — rows that already exist
     * (need UPDATE) and rows that are new (need INSERT) — then executes at most
     * two queries: one batch INSERT and one CASE WHEN UPDATE, instead of one
     * query per field (N+1).
     *
     * -------------------------------------------------------------------------
     * HOW TRANSLATABLE / NON-TRANSLATABLE FIELDS ARE HANDLED
     * -------------------------------------------------------------------------
     * When $data['fields'] is provided (the 'fields' section of fieldConfigs),
     * each field's 'translate' flag controls which locale slot it is written to:
     *
     *   translate == 1  — translatable field, stored under a locale key ('ge', 'en', …)
     *                     skipped when $lan == 'xx'
     *   translate != 1  — non-translatable field, stored under 'xx'
     *                     skipped when $lan != 'xx'
     *
     * This ensures translatable and non-translatable fields never mix slots
     * regardless of what $data['meta'] contains.
     *
     * -------------------------------------------------------------------------
     * QUERY PATTERN
     * -------------------------------------------------------------------------
     * 1. getListRaw()  — 1 SELECT to load existing meta rows for the locale.
     * 2. INSERT        — single multi-row INSERT for all new fields (if any).
     * 3. UPDATE        — single CASE WHEN UPDATE for all changed fields (if any).
     *
     * Total: at most 3 queries per upd() call, regardless of field count.
     * Previously: 1 SELECT + N individual UPDATE or INSERT (N+1 pattern).
     *
     * -------------------------------------------------------------------------
     * VALIDATION
     * -------------------------------------------------------------------------
     * Returns ['success' => false, 'message' => '...'] without touching the DB
     * if any required param is missing or invalid.
     *
     * -------------------------------------------------------------------------
     * SUPPORTED $data KEYS
     * -------------------------------------------------------------------------
     * @param  array $data {
     *   'table'       string       — meta table name (e.g. 'pages_meta')
     *   'parentTable' string       — parent table name stored in meta 'table' column
     *   'table_id'    int          — primary key of the parent record
     *   'lan'         string(2)    — locale slot to write ('ge', 'en', 'xx', …)
     *   'meta'        array        — field key → value map to persist
     *   'fields'      array|null   — 'fields' section of fieldConfigs; controls
     *                                which keys are processed and their translate flag.
     *                                When null/empty all keys in 'meta' are written.
     * }
     *
     * -------------------------------------------------------------------------
     * RETURN VALUE
     * -------------------------------------------------------------------------
     * @return true|array  true on success; ['success' => false, 'message' => string] on validation failure.
     */
    public function upd($data = [])
    {
        $validator = \Validator::make($data, [
            'table'       => ['required'],
            'parentTable' => ['required'],
            'table_id'    => ['required', 'numeric'],
            'meta'        => ['required'],
            'lan'         => ['required', 'size:2'],
        ]);

        if ($validator->fails()) { return ['success' => false, 'message' => $validator->errors()->first()]; }

        $metaFields = $data['meta'];
        if (!is_array($metaFields)) return false;

        $table       = $data['table'];
        $meta        = $data['meta'];
        $tableId     = $data['table_id'];
        $parentTable = $data['parentTable'];
        $lan         = $data['lan'];
        $fields      = $data['fields'] ?? [];

        $metaData = $this->getListRaw(['table' => $table, 'table_id' => $tableId, 'parentTable' => $parentTable, 'lan' => $lan]);

        $toUpdate = [];
        $toInsert = [];
        $now      = now()->toDateTimeString();

        foreach ($metaFields as $k => $v) {

            if ($fields && !isset($fields[$k])) continue;

            // Guard: resolve field config safely
            $fieldConfig = $fields[$k] ?? [];

            // Skip translatable fields when writing the non-translatable ('xx') slot and vice-versa
            if (_cv($fieldConfig, ['translate']) == 1 && $lan == 'xx') continue;
            if (_cv($fieldConfig, ['translate']) != 1 && $lan != 'xx') continue;

            $k     = trim(strip_tags($k));
            $value = $meta[$k] ?? '';
            if (is_array($value)) $value = _psqlupd($value);

            if (_cv($metaData, [$k, 'id'])) {
                $toUpdate[] = ['id' => (int)$metaData[$k]['id'], 'val' => $value];
            } else {
                $toInsert[] = [
                    'table'      => $parentTable,
                    'table_id'   => $tableId,
                    'lan'        => $lan,
                    'key'        => $k,
                    'val'        => $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($toInsert)) {
            DB::table($table)->insert($toInsert);
        }

        if (!empty($toUpdate)) {
            $cases    = '';
            $bindings = [];
            $ids      = [];

            foreach ($toUpdate as $row) {
                $cases      .= 'WHEN ' . $row['id'] . ' THEN ? ';
                $bindings[]  = $row['val'];
                $ids[]       = $row['id'];
            }

            DB::statement(
                "UPDATE `{$table}` SET `val` = CASE `id` {$cases} END WHERE `id` IN (" . implode(',', $ids) . ")",
                $bindings
            );
        }

        return true;
    }


    /**
     * Load all existing meta rows for one record + locale as a key-indexed map.
     *
     * Fetches every meta row that matches the given table/table_id/lan/parentTable
     * combination and returns them indexed by their 'key' column so callers can
     * do O(1) lookups by field name.
     *
     * -------------------------------------------------------------------------
     * PARAMS
     * -------------------------------------------------------------------------
     * @param  array $params {
     *   'table'       string  — meta table to query (e.g. 'pages_meta')
     *   'table_id'    int     — primary key of the parent record
     *   'lan'         string  — locale code ('ge', 'en', 'xx', …)
     *   'parentTable' string  — parent table name stored in the meta 'table' column
     * }
     *
     * -------------------------------------------------------------------------
     * RETURN VALUE
     * -------------------------------------------------------------------------
     * @return array  Key-indexed map: [ 'fieldName' => ['id' => int, 'key' => string, 'val' => mixed], … ]
     *                Returns [] when any required param is missing.
     */
    public function getListRaw($params = [])
    {
        if (!isset($params['table'], $params['table_id'], $params['lan'], $params['parentTable'])) return [];

        $metaData = DB::table($params['table'])
            ->select('id', 'key', 'val')
            ->where('table_id',  $params['table_id'])
            ->where('lan',       $params['lan'])
            ->where('table',     $params['parentTable'])
            ->get();

        $ret = [];

        foreach (_psql(_toArray($metaData)) as $v) {
            $ret[$v['key']] = $v;
        }

        return $ret;
    }


}
