<?php

namespace App\Models\Admin;

use App\Models\User\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

class SmartTableModel extends Model
{
    protected $table = '';
    protected $metaTable = '';
    protected $taxonomyRelationTable = '';
    protected $rules = [];
    public  $timestamps = true;
    protected $error = false;
    protected $meta;
    protected $fieldConfigs;

    ///
///   protected $allAttributes = [];
    protected $fillable = [];
    protected $guarded = [];

    public function getOne($params = [])
    {
        $params['limit'] = 1;

        $res = $this -> getList($params);

        if (isset($res['list'][0])) return $res['list'][0];

        return [];
    }

    public function getList($params = [])
    {
        // =====================================================================
        // STEP 1 — Resolve field configuration
        //
        // fieldConfigs can be supplied in three forms:
        //   a) Array of config objects  → fields from all are merged into [0]
        //   b) Single config array with a 'fields' key → used as-is
        //   c) String (dot-notation key) → loaded via Laravel config()
        //
        // Child models set $this->fieldConfigs in their constructor or as a
        // class property. Callers can override it per-call via params['fieldConfigs'].
        // =====================================================================
        if (_cv($params, 'fieldConfigs')) $this->fieldConfigs = $params['fieldConfigs'];

        if (is_array($this->fieldConfigs) && isset($this->fieldConfigs[0])) {
            // Multiple config arrays supplied — merge all 'fields' into the first config
            $fields = $this->fieldConfigs[0];
            foreach ($this->fieldConfigs as $value) {
                foreach ($value['fields'] as $key => $field) {
                    $fields['fields'][$key] = $field;
                }
            }
        } else if (_cv($this->fieldConfigs, 'fields')) {
            // Direct config array passed (already has 'fields' key)
            $fields = $this->fieldConfigs;
        } else {
            // String config key — load from config files (e.g. 'adminpanel.content_types.news')
            $fields = config($this->fieldConfigs);
        }

        // =====================================================================
        // STEP 2 — Initialize state
        //
        // $enableOrdering: set to false when filtering by specific IDs so that
        //   FIELD() ordering (which preserves caller-supplied ID order) is used.
        // $selectPart: accumulates all SELECT expressions, assembled at query time.
        // sortDirection/sortField are accepted as aliases for orderDirection/orderField
        //   to support older callers.
        // =====================================================================
        $translate      = requestLan(_cv($params, 'translate'));
        $enableOrdering = true;
        $selectPart     = [];

        $returnData = [
            'listCount' => 0,
            'list'      => [],
            'page'      => _cv($params, 'page', 'nn') ? $params['page'] : 1,
        ];

        if (_cv($params, ['sortDirection']) && !_cv($params, ['orderDirection'])) $params['orderDirection'] = $params['sortDirection'];
        if (_cv($params, ['sortField'])     && !_cv($params, ['orderField']))     $params['orderField']     = $params['sortField'];

        // =====================================================================
        // STEP 3 — Prepare config-defined join table metadata
        //
        // Reads the 'join' section of field config and builds three structures:
        //
        //   $tableJoinSelect        — (unused directly, kept for future use)
        //   $tableJoinSelectFilters — maps param keys to joined-table filter rules;
        //                             used in STEP 8 to apply WHERE conditions on
        //                             columns from config-joined tables
        //   $tableJoinOn            — list of join definitions applied in STEP 7;
        //                             deferred so that JOINs are applied before WHERE
        //
        // The alias pattern "joinedTable_{tableName}" avoids collisions when the
        // same table is joined more than once under different prefixes.
        //
        // NOTE: $tableName is now computed outside the inner 'select' loop to
        // correctly handle join entries that have no 'select' block.
        // =====================================================================
        $tableJoinSelect        = [];
        $tableJoinSelectFilters = [];
        $tableJoinOn            = [];

        if (_cv($fields, ['join'], 'ar')) {
            foreach ($fields['join'] as $k => $v) {
                // tablePrefix overrides joinTable as the alias base name
                $tableName = _cv($v, 'tablePrefix') ? $v['tablePrefix'] : $v['joinTable'];

                if (isset($v['select'])) {
                    foreach ($v['select'] as $kk => $vv) {
                        $whereQuery  = isset($vv['where']) ? $vv['where'] : $vv;
                        $selectQuery = isset($vv['select']) ? $vv['select'] : "joinedTable_{$tableName}.{$kk}";

                        $selectPart[] = "{$selectQuery} as {$tableName}_{$kk}";

                        // Register filter rule so STEP 8 knows how to WHERE on this column
                        $tableJoinSelectFilters["{$v['joinTable']}_{$kk}"] = [
                            'filterType' => $whereQuery,
                            'table'      => "joinedTable_{$tableName}",
                            'fieldAs'    => "{$v['joinTable']}_{$kk}",
                            'field'      => $kk,
                        ];
                    }
                }

                $tableJoinOn[] = [
                    'joinTable'   => $v['joinTable'],
                    'joinField'   => $v['joinField'],
                    'joinOn'      => $v['joinOn'],
                    'tablePrefix' => $tableName,
                    'sameStatus'  => $v['sameStatus'] ?? true,
                    'whereRaw'    => _cv($v, 'whereRaw'),
                ];
            }
        }

        // =====================================================================
        // STEP 4 — Start base query builder
        // =====================================================================
        $qr = DB::table($this->table);

        // =====================================================================
        // STEP 5 — Meta table: per-field filter JOINs only
        //
        // For each field in fields['fields'] that has an active filter param
        // or a whereRaw clause, we LEFT JOIN the meta table under the alias
        // "meta_{fieldName}" so WHERE conditions can reference those rows.
        //
        // Translatable fields join for the current request language; non-
        // translatable fields join for the language-neutral slot ('xx').
        //
        // *** IMPORTANT — GROUP_CONCAT subquery removed ***
        // The original code embedded a GROUP_CONCAT subquery that packed every
        // meta row into a single string per record and then had PHP unpack it
        // via decodeJoinedMetaData(). This caused:
        //   — A heavy subquery in the FROM/JOIN clause on every getList call
        //   — GROUP_CONCAT string size limits on wide meta tables
        //   — Extra PHP string-parsing overhead (explode on custom separators)
        //
        // Meta data is now loaded in a separate targeted query after the main
        // result set is known (see STEP 17). The output structure is identical.
        // =====================================================================
        if ($this->metaTable && _cv($fields, ['fields'])) {
            foreach ($fields['fields'] as $k => $v) {
                // Only join if there is an active filter or an unconditional whereRaw
                if (!isset($params[$k]) && !isset($v['whereRaw'])) continue;

                $metaTableName = "meta_{$k}";
                $qr->leftJoin("{$this->metaTable} as {$metaTableName}", function ($join) use ($k, $v, $translate, $metaTableName) {
                    if (isset($v['translate']) && $v['translate'] == 1) {
                        // Translatable field: join for the requested language only
                        $join->on("{$metaTableName}.table_id", '=', "{$this->table}.id")
                            ->where("{$metaTableName}.key",   $k)
                            ->where("{$metaTableName}.lan",   $translate)
                            ->where("{$metaTableName}.table", $this->table);
                    } else {
                        // Non-translatable field: language-neutral slot ('xx')
                        $join->on("{$metaTableName}.table_id", '=', "{$this->table}.id")
                            ->where("{$metaTableName}.key",   $k)
                            ->where("{$metaTableName}.lan",   'xx')
                            ->where("{$metaTableName}.table", $this->table);
                    }
                });

                if (isset($v['whereRaw'])) {
                    $qr->whereRaw($v['whereRaw']);
                }

                // Apply the configured filter type for this meta field
                if (isset($v['dbFilter']) && isset($params[$k])) {
                    if ($v['dbFilter'] == 'whereIn') {
                        if (!is_array($params[$k])) $params[$k] = [$params[$k]];
                        $qr->whereIn("{$metaTableName}.val", $params[$k]);
                    } elseif ($v['dbFilter'] == 'like') {
                        $qr->where("{$metaTableName}.val", 'like', "%{$params[$k]}%");
                    } elseif ($v['dbFilter'] == 'where') {
                        $qr->where("{$metaTableName}.val", $params[$k]);
                    } elseif ($v['dbFilter'] == 'range') {
                        if (!is_array($params[$k])) $params[$k] = [$params[$k]];
                        if (count($params[$k]) >= 2) {
                            $qr->whereBetween("{$metaTableName}.val", $params[$k]);
                        } else {
                            $qr->where("{$metaTableName}.val", $params[$k]);
                        }
                    } elseif ($v['dbFilter'] == '>') {
                        $qr->where("{$metaTableName}.val", '>', $params[$k]);
                    } elseif ($v['dbFilter'] == '<') {
                        $qr->where("{$metaTableName}.val", '<', $params[$k]);
                    }
                }
            }
        }

        // =====================================================================
        // STEP 6 — Taxonomy: filter JOINs only (SELECT-side join deferred)
        //
        // When taxonomy filtering is active, per-group LEFT JOINs and whereIn
        // conditions are added here — before the COUNT query — so they affect
        // which records are counted and returned.
        //
        // The base taxonomy_relation + taxonomy JOIN used for the GROUP_CONCAT
        // SELECT expression is intentionally deferred to STEP 10 (after COUNT).
        // It produces no WHERE conditions and its rows are deduplicated by the
        // GROUP BY in STEP 13, so moving it after COUNT is safe and reduces the
        // complexity of the COUNT query.
        //
        // AND filter: each taxonomy group gets its own aliased JOIN + whereIn.
        //   Records must belong to ALL specified taxonomy groups (AND logic).
        //
        // OR filter: all taxonomy IDs across groups are merged into a single
        //   whereIn (OR logic: record must match any of the given IDs).
        // =====================================================================
        if (_cv($fields, 'taxonomy', 'ar') || _cv($params, 'relate.taxonomy')) {
            // Backward compat: 'taxonomies' is accepted as alias for 'taxonomy'
            if (!_cv($params, 'taxonomy', 'ar') && _cv($params, 'taxonomies', 'ar')) {
                $params['taxonomy'] = $params['taxonomies'];
            }

            // AND taxonomy filter
            if (_cv($params, 'taxonomy', 'ar')) {
                foreach ($params['taxonomy'] as $attrKey => $attrValues) {
                    if (count($params['taxonomy'][$attrKey]) == 0) continue;

                    $qr->leftJoin(
                        "{$this->taxonomyRelationTable} as {$this->taxonomyRelationTable}_{$attrKey}",
                        function ($join) use ($attrKey) {
                            $join->on("{$this->taxonomyRelationTable}_{$attrKey}.data_id", '=', "{$this->table}.id")
                                ->where("{$this->taxonomyRelationTable}_{$attrKey}.table", $this->table);
                        }
                    )->whereIn("{$this->taxonomyRelationTable}_{$attrKey}.taxonomy_id", $attrValues);
                }
            }

            // OR taxonomy filter
            if (_cv($params, 'taxonomies_or', 'ar')) {
                $tmpTaxonomiesOrIds = [];
                foreach ($params['taxonomies_or'] as $attrKey => $attrValues) {
                    if (count($params['taxonomies_or'][$attrKey]) == 0) continue;
                    $tmpTaxonomiesOrIds = array_merge($tmpTaxonomiesOrIds, $attrValues);
                }
                $qr->leftJoin(
                    "{$this->taxonomyRelationTable} as taxonomy_relation_or",
                    function ($join) {
                        $join->on('taxonomy_relation_or.data_id', '=', $this->table . '.id')
                            ->where('taxonomy_relation_or.table', $this->table);
                    }
                )->whereIn('taxonomy_relation_or.taxonomy_id', $tmpTaxonomiesOrIds);
            }
        }

        // =====================================================================
        // STEP 7 — Apply config-defined table JOINs ($tableJoinOn)
        //
        // Joins declared in fields['join'] (prepared in STEP 3) are applied here,
        // before the COUNT query, because $tableJoinSelectFilters WHERE conditions
        // (applied in STEP 8) reference the aliased tables produced by these joins.
        //
        // Each join uses alias "joinedTable_{tableName}" to avoid name collisions.
        // When sameStatus === true, the status filter is mirrored onto the joined
        // table so that joined rows also match the requested status.
        // =====================================================================
        foreach ($tableJoinOn as $v) {
            $tableName = _cv($v, 'tablePrefix') ? $v['tablePrefix'] : $v['joinTable'];

            $qr->leftJoin("{$v['joinTable']} as joinedTable_{$tableName}", function ($join) use ($tableName, $v, $params) {
                $join->on("joinedTable_{$tableName}.{$v['joinField']}", '=', "{$this->table}.{$v['joinOn']}");

                // Mirror the status filter onto the joined table when configured
                if (_cv($v, 'sameStatus') === true && _cv($params, 'status', 'ar')) {
                    $join->whereIn("joinedTable_{$tableName}.status", $params['status']);
                }

                if (_cv($v, 'whereRaw', 'ar')) {
                    foreach ($v['whereRaw'] as $vv) {
                        $join->whereRaw($vv);
                    }
                }
            });
        }

        // =====================================================================
        // STEP 8 — Apply all WHERE filter conditions
        //
        // All user-supplied filter params are translated into WHERE clauses here.
        // This entire block runs BEFORE the COUNT query (STEP 9) to ensure the
        // count reflects the correctly filtered result set.
        //
        // Sections:
        //   id           — whereIn + FIELD() ordering (disables default ORDER BY)
        //   slug         — whereIn
        //   status       — whereIn
        //   searchText   — LIKE on searchable regular fields OR meta.val
        //                  (uses parameterized bindings — replaces addslashes)
        //   searchBy     — per-field WHERE / LOCATE on adminListFields
        //                  (user value bound via ? — replaces direct interpolation)
        //   regularFields— per-field dbFilter rules (whereIn/like/where/range/>/<)
        //   whereRaw     — array of raw AND conditions
        //   orWhereRaw   — array of raw OR conditions (wrapped in a group)
        //   sort         — exact match on sort column
        //   tableJoinSelectFilters — WHERE on columns from config-defined joins
        //   customJsonFilter — JSON column containment filters
        // =====================================================================

        // --- id ---
        // When filtering by specific IDs, disable default ordering and use FIELD()
        // so MySQL returns rows in the exact caller-supplied ID order.
        if (_cv($params, ['id']) && !_cv($params, ['id'], 'ar')) $params['id'] = [$params['id']];
        if (_cv($params, 'id', 'ar')) {
            $enableOrdering = false;
            $qr->whereIn("{$this->table}.id", $params['id'])
                ->orderByRaw("FIELD({$this->table}.id, " . implode(',', $params['id']) . ")");
        }

        // --- slug ---
        if (_cv($params, ['slug']) && !_cv($params, ['slug'], 'ar')) $params['slug'] = [$params['slug']];
        if (_cv($params, 'slug', 'ar')) $qr->whereIn("{$this->table}.slug", $params['slug']);

        // --- status ---
        if (_cv($params, ['status']) && !_cv($params, ['status'], 'ar')) $params['status'] = [$params['status']];
        if (_cv($params, 'status', 'ar')) $qr->whereIn("{$this->table}.status", $params['status']);

        // --- searchText ---
        // Searches across all 'searchable' regular fields (via CONCAT LIKE) and
        // all meta values for the active language (via meta.val LIKE).
        // Switched from addslashes() + string interpolation to parameterized
        // bindings (?), which is the correct way to prevent SQL injection.
        if (_cv($params, ['searchText'])) {
            $safeSearch      = strip_tags($params['searchText']);
            $searchWhereParts = [];

            // Build a CONCAT of all searchable regular-table columns
            if (_cv($fields, ['regularFields'])) {
                $concatExpr = '"-"';
                foreach ($fields['regularFields'] as $k => $v) {
                    if (isset($v['searchable'])) {
                        $concatExpr .= ", IFNULL({$this->table}.{$k}, '-')";
                    }
                }
                $searchWhereParts[] = ['expr' => "CONCAT({$concatExpr}) LIKE ?", 'binding' => "%{$safeSearch}%"];
            }

            // Include all meta values for the current language in the search scope
            if ($this->metaTable) {
                $qr->leftJoin($this->metaTable, function ($join) use ($translate) {
                    $join->on("{$this->metaTable}.table_id", '=', $this->table . '.id')
                        ->where("{$this->metaTable}.table", $this->table)
                        ->whereIn("{$this->metaTable}.lan", [$translate, 'xx']);
                });
                $searchWhereParts[] = ['expr' => "{$this->metaTable}.val LIKE ?", 'binding' => "%{$safeSearch}%"];
                // Expose the matched meta value so callers can highlight the match
                $selectPart[] = "{$this->metaTable}.val as searchVal";
            }

            // Wrap all search conditions in a single OR group
            if (count($searchWhereParts) >= 1) {
                $qr->where(function ($q) use ($searchWhereParts) {
                    foreach ($searchWhereParts as $i => $part) {
                        if ($i === 0) {
                            $q->whereRaw($part['expr'], [$part['binding']]);
                        } else {
                            $q->orWhereRaw($part['expr'], [$part['binding']]);
                        }
                    }
                });
            }
        }

        // --- searchBy ---
        // Per-field search on adminListFields. Supports exact WHERE match or
        // LOCATE-based substring search. User value is bound via ? to prevent
        // SQL injection (replaced the previous direct string interpolation).
        if (_cv($params, 'searchBy', 'ar') && _cv($fields, ['adminListFields'])) {
            foreach ($fields['adminListFields'] as $k => $v) {
                if (!_cv($v, ['searchable']) || !isset($v['tableKey']) || !_cv($params['searchBy'], [$v['tableKey']])) continue;

                if (_cv($v, ['searchType']) == 'where') {
                    // $v['tableKey'] is a column name from config (trusted)
                    $qr->where($params['searchBy'][$v['tableKey']], $v['searchable']);
                } else {
                    // $v['searchable'] is a column expression from config (trusted); user value bound via ?
                    $qr->whereRaw("LOCATE(?, {$v['searchable']})", [$params['searchBy'][$v['tableKey']]]);
                }
            }
        }

        // --- regularFields filters ---
        // Applies per-field dbFilter rules declared in fields['regularFields']
        if (isset($fields['regularFields'])) {
            foreach ($fields['regularFields'] as $k => $v) {
                if (!isset($v['dbFilter']) || !isset($params[$k]) || empty($params[$k])) continue;

                if ($v['dbFilter'] == 'whereIn') {
                    if (!is_array($params[$k])) $params[$k] = [$params[$k]];
                    $qr->whereIn("{$this->table}.{$k}", $params[$k]);
                } elseif ($v['dbFilter'] == 'like') {
                    if (is_array($params[$k])) $params[$k] = implode('', $params[$k]);
                    $qr->where("{$this->table}.{$k}", 'like', "%{$params[$k]}%");
                } elseif ($v['dbFilter'] == 'where') {
                    if (is_array($params[$k])) $params[$k] = implode('', $params[$k]);
                    $qr->where("{$this->table}.{$k}", $params[$k]);
                } elseif ($v['dbFilter'] == 'range') {
                    if (!is_array($params[$k])) $params[$k] = [$params[$k]];
                    if (count($params[$k]) >= 2) {
                        $qr->whereBetween("{$this->table}.{$k}", $params[$k]);
                    } else {
                        $qr->where("{$this->table}.{$k}", $params[$k]);
                    }
                } elseif ($v['dbFilter'] == '>') {
                    if (is_array($params[$k])) $params[$k] = implode('', $params[$k]);
                    $qr->where("{$this->table}.{$k}", '>', $params[$k]);
                } elseif ($v['dbFilter'] == '<') {
                    if (is_array($params[$k])) $params[$k] = implode('', $params[$k]);
                    $qr->where("{$this->table}.{$k}", '<', $params[$k]);
                }
            }
        }

        // --- whereRaw (AND conditions) ---
        if (_cv($params, ['whereRaw'], 'ar')) {
            foreach ($params['whereRaw'] as $v) {
                $qr->whereRaw($v);
            }
        }

        // --- orWhereRaw (OR conditions, grouped) ---
        if (_cv($params, ['orWhereRaw'], 'ar')) {
            $qr->where(function ($q) use ($params) {
                foreach ($params['orWhereRaw'] as $v) {
                    $q->orWhereRaw($v);
                }
            });
        }

        // --- exact sort column match ---
        if (_cv($params, 'sort')) $qr->where('sort', $params['sort']);

        // --- filter by columns from config-defined joined tables ---
        // These reference the aliased tables added in STEP 7.
        if (count($tableJoinSelectFilters) >= 1) {
            foreach ($tableJoinSelectFilters as $k => $v) {
                if (!isset($params[$k]) || empty($params[$k])) continue;

                if ($v['filterType'] == 'whereIn') {
                    if (!is_array($params[$k])) $params[$k] = [$params[$k]];
                    $qr->whereIn("{$v['table']}.{$v['field']}", $params[$k]);
                } elseif ($v['filterType'] == 'like') {
                    $qr->where("{$v['table']}.{$v['field']}", 'like', "%{$params[$k]}%");
                } elseif ($v['filterType'] == 'where') {
                    $qr->where("{$v['table']}.{$v['field']}", $params[$k]);
                } elseif ($v['filterType'] == 'range') {
                    if (!is_array($params[$k])) $params[$k] = [$params[$k]];
                    if (count($params[$k]) >= 2) {
                        $qr->whereBetween("{$v['table']}.{$v['field']}", $params[$k]);
                    } else {
                        $qr->where("{$v['table']}.{$v['field']}", $params[$k]);
                    }
                }
            }
        }

        // --- JSON column containment filter ---
        if (_cv($params, 'customJsonFilter')) {
            foreach ($params['customJsonFilter'] as $k => $column) {
                foreach ($params['toFilterArray'][$k] as $kk => $value) {
                    if ($kk === 0) {
                        $qr->whereJsonContains($column, $value);
                        continue;
                    }
                    $qr->orWhereJsonContains($column, $value);
                }
            }
        }

        // =====================================================================
        // STEP 9 — Count total matching records
        //
        // Executed HERE — after all WHERE conditions and filter JOINs (STEPS 5–8),
        // but BEFORE the decorative JOINs added in STEPS 10–12 (taxonomy
        // GROUP_CONCAT, relations subqueries, sitemap join).
        //
        // WHY THIS MATTERS:
        // In the original code, the count ran after all joins were attached to
        // $qr, forcing MySQL to execute the full decorated query just to produce
        // a count. By counting here, the COUNT query contains only the joins
        // that actually influence which rows are matched (filter joins), not the
        // heavier joins that are only needed for the SELECT clause.
        //
        // DISTINCT is still required to collapse duplicate rows produced by
        // the one-to-many taxonomy and meta filter JOINs added in STEPS 5–6.
        // =====================================================================
        $listCount = $qr->count(DB::raw("DISTINCT({$this->table}.id)"));

        // =====================================================================
        // STEP 10 — Taxonomy: base JOIN for SELECT GROUP_CONCAT (after COUNT)
        //
        // Deferred to after the count because this join only affects what data
        // is returned in the SELECT — not which records are matched.
        //
        // Joins taxonomy_relation + taxonomy tables so we can produce a
        // JSON-like string of {id: "taxonomy_slug"} pairs for each record via
        // GROUP_CONCAT. This string is decoded in STEP 18 via reverseArray().
        //
        // The per-group AND/OR filter JOINs (which DO affect matching) were
        // already applied in STEP 6 using separate aliased joins, so there is
        // no conflict with the "taxonomy_relation" alias used here.
        // =====================================================================
        if (_cv($fields, 'taxonomy', 'ar') || _cv($params, 'relate.taxonomy')) {
            $qr->leftJoin(
                "{$this->taxonomyRelationTable} as taxonomy_relation",
                function ($join) {
                    $join->on('taxonomy_relation.data_id', '=', $this->table . '.id')
                        ->where('taxonomy_relation.table', $this->table);
                }
            )->leftJoin('taxonomy', 'taxonomy.id', '=', 'taxonomy_relation.taxonomy_id');

            // Produces: {"1":"category_name","2":"other_category"}
            // The GROUP BY in STEP 13 collapses multiple taxonomy rows per record.
            // Decoded in STEP 18: reverseArray() flips it to {name: [ids]}.
            $selectPart[] = 'CONCAT("{", GROUP_CONCAT(DISTINCT CONCAT("\"", `taxonomy`.`id`, "\":","\"", `taxonomy`.`taxonomy`, "\"")), "}") AS `taxonomy`';
        }

        // =====================================================================
        // STEP 11 — Relations: subquery JOINs for SELECT (after COUNT)
        //
        // Each relation entry in fields['relations'] produces a correlated
        // subquery that aggregates related IDs as a JSON array string "[1,2,3]".
        //
        // Deferred to after COUNT because these are purely additive to the
        // SELECT clause and do not filter or constrain any rows.
        // =====================================================================
        if (_cv($fields, 'relations', 'ar')) {
            foreach ($fields['relations'] as $k => $v) {
                $qr->leftJoin(
                    DB::raw("
                        (SELECT {$v['id']},
                                CONCAT(\"[\", GROUP_CONCAT(DISTINCT relation_{$v['module']}.{$v['data_id']}), \"]\") AS `relation_{$v['module']}`
                         FROM {$v['table']} AS relation_{$v['module']}
                         WHERE `table` = '{$v['module']}'
                         GROUP BY {$v['id']}
                        ) AS relation_{$v['module']}"),
                    "relation_{$v['module']}.{$v['id']}",
                    '=',
                    "{$this->table}.id"
                );
                $selectPart[] = "relation_{$v['module']}";
            }
        }

        // =====================================================================
        // STEP 12 — Build remaining SELECT expressions (after COUNT)
        //
        // All SELECT parts that do not affect filtering or counting are assembled
        // here: custom config fields, main table columns, caller-supplied
        // expressions, and the optional sitemap relation ID.
        //
        // Sitemap join: previously always executed on every query regardless of
        // whether the caller used relatedSitemap. Now opt-out: pass
        // params['withSitemap'] = false to skip the join when not needed.
        // Default behavior (join included) is preserved for all existing callers.
        // =====================================================================

        // Fields declared in config's customSelectFields
        if (_cv($fields, ['customSelectFields'], 'ar')) {
            foreach ($fields['customSelectFields'] as $v) {
                $selectPart[] = $v;
            }
        }

        // Main table: select specific fields if regularFieldsSelect is provided, otherwise SELECT *
        $selectPart[] = _cv($params, ['regularFieldsSelect'], 'ar')
            ? "{$this->table}." . implode(", {$this->table}.", $params['regularFieldsSelect'])
            : "{$this->table}.*";

        // Caller-supplied raw SELECT expression (e.g. computed columns, subselects)
        if (_cv($params, 'customSelect')) {
            $selectPart[] = $params['customSelect'];
        }

        // Sitemap join: skipped only when caller explicitly passes withSitemap = false.
        // This allows callers that do not use relatedSitemap to avoid the join overhead.
        if (!array_key_exists('withSitemap', $params) || $params['withSitemap'] !== false) {
            $qr->leftJoin('modules_sitemap_relations', 'modules_sitemap_relations.table_id', "{$this->table}.id");
            $selectPart[] = 'modules_sitemap_relations.sitemap_id as relatedSitemap';
        }

        // =====================================================================
        // STEP 13 — GROUP BY and HAVING
        //
        // Default group by the primary key to collapse duplicate rows introduced
        // by the one-to-many JOINs (taxonomy, meta filters). Override via
        // params['group_by'] when a different grouping is needed.
        //
        // HAVING conditions are appended after GROUP BY. Both AND (having) and
        // OR (orHaving) variants are supported via raw expressions.
        // =====================================================================
        $groupBy = ["{$this->table}.id"];
        if (_cv($params, 'group_by')) $groupBy = $params['group_by'];
        $qr->groupBy($groupBy);

        if (_cv($params, ['having'], 'ar')) {
            foreach ($params['having'] as $k => $v) {
                foreach ($v as $vv) {
                    $qr->havingRaw($k . '=' . $vv);
                }
            }
        }

        if (_cv($params, ['orHaving'], 'ar')) {
            foreach ($params['orHaving'] as $k => $v) {
                foreach ($v as $vv) {
                    $qr->orHavingRaw($k . '=' . $vv);
                }
            }
        }

        // =====================================================================
        // STEP 14 — Pagination (LIMIT / OFFSET)
        //
        // Default page size is 10 when no limit is specified.
        // When both 'page' and 'limit' are provided, SKIP drives offset-based
        // pagination. For very large datasets with deep page numbers, consider
        // cursor-based pagination via a custom whereRaw + after_id approach.
        // =====================================================================
        $params['limit'] = $params['limit'] ?? 10;

        if (_cv($params, 'limit')) $qr->take($params['limit']);
        if (_cv($params, 'page'))  $qr->skip(($params['page'] - 1) * $params['limit'])->take($params['limit']);

        // =====================================================================
        // STEP 15 — Ordering
        //
        // Order field is whitelisted against fields declared in regularFields
        // and fields (meta field configs), plus a set of safe built-in columns.
        // This prevents SQL injection via the orderField param — previously the
        // param value was interpolated directly into orderByRaw() with no check.
        //
        // orderDirection is whitelisted to ASC, DESC, or RANDOM.
        //
        // Special cases:
        //   RANDOM    → ORDER BY RAND() (shuffled result set)
        //   orderCast → CONVERT(field, SIGNED) for correct numeric-string sorting
        //               when a column stores numbers as VARCHAR/TEXT
        // =====================================================================
        $defaultOrderField     = _cv($fields, 'orderField')     ? $fields['orderField']     : 'id';
        $defaultOrderDirection = _cv($fields, 'orderDirection') ? $fields['orderDirection'] : 'desc';

        $orderField     = _cv($params, 'orderField')     ? $params['orderField']     : $defaultOrderField;
        $orderDirection = _cv($params, 'orderDirection') ? $params['orderDirection'] : $defaultOrderDirection;

        // Whitelist orderField: only allow columns declared in field configs or safe defaults
        $allowedOrderFields = array_merge(
            array_keys($fields['regularFields'] ?? []),
            array_keys($fields['fields']        ?? []),
            ['id', 'sort', 'created_at', 'updated_at']
        );
        if (!in_array($orderField, $allowedOrderFields)) $orderField = $defaultOrderField;

        // Whitelist orderDirection to prevent injection via raw interpolation
        if (!in_array(strtoupper($orderDirection), ['ASC', 'DESC', 'RANDOM'])) {
            $orderDirection = $defaultOrderDirection;
        }

        if ($enableOrdering) {
            if (strtoupper($orderDirection) === 'RANDOM') {
                // Full random shuffle — note: ORDER BY RAND() is slow on large tables
                $qr->orderByRaw('RAND()');
            } elseif (
                _cv($fields, ['regularFields', $orderField, 'orderCast']) ||
                _cv($fields, ['fields',        $orderField, 'orderCast'])
            ) {
                // Numeric-string column: cast to SIGNED so "10" sorts after "9"
                $qr->orderByRaw("CONVERT({$this->table}.{$orderField}, SIGNED) {$orderDirection}");
            } else {
                $qr->orderByRaw("{$orderField} {$orderDirection}");
            }
        }

        // =====================================================================
        // STEP 16 — Execute main query
        // =====================================================================
        $qr->selectRaw(DB::raw(implode(',', $selectPart)));
        $list = $qr->get();

        if (!$list) return $returnData;

        $ret = _psql(_toArray($list));

        // =====================================================================
        // STEP 17 — Load meta data via a dedicated secondary query
        //
        // REPLACES the GROUP_CONCAT subquery approach from the original code.
        //
        // Original approach:
        //   A subquery embedded in the main FROM/JOIN packed every meta row
        //   into one string per record using a custom separator pattern:
        //     "key----SEP----val----SEP----lan----END----key2..."
        //   PHP then unpacked these via decodeJoinedMetaData().
        //
        // Problems with GROUP_CONCAT approach:
        //   — Added a correlated subquery to every getList call even when no
        //     meta data was ultimately needed
        //   — GROUP_CONCAT has a server-side max length (group_concat_max_len)
        //     that silently truncates data on wide meta tables
        //   — String packing/unpacking (explode on custom separators) is fragile
        //     and slower than native PHP array operations
        //   — Inflated the main query size, increasing query plan complexity
        //
        // New approach (2-query pattern):
        //   1. Collect the IDs returned by the main query
        //   2. Run a single flat SELECT on the meta table: WHERE table_id IN(ids)
        //   3. Group meta rows in PHP by [table_id][lan][key] = decoded_value
        //   4. Merge into each row in STEP 18 — output structure is identical
        //      to what decodeJoinedMetaData($str, 1) + mergeToMetaData() produced
        //
        // Benefits:
        //   — Main query no longer carries any GROUP_CONCAT subquery overhead
        //   — Meta query uses the (table, table_id) index with a clean IN() clause
        //   — No string packing/unpacking; PHP groupBy is O(n)
        //   — meta_keys restriction is applied directly as a SQL WHERE IN clause
        //     rather than being filtered post-decode in PHP
        //   — No silent data truncation risk
        // =====================================================================
        $metaByRow = [];

        if ($this->metaTable && count($ret) > 0) {
            $ids = array_column($ret, 'id');

            $metaQr = DB::table($this->metaTable)
                ->whereIn('table_id', $ids)
                ->where('table', $this->table)
                ->select(['table_id', 'key', 'val', 'lan']);

            // When meta_keys is specified, restrict to only those keys in SQL
            // (avoids loading and discarding unwanted meta rows in PHP)
            $metaKeysFilter = _cv($params, 'meta_keys', 'ar');
            if (is_array($metaKeysFilter)) {
                $metaQr->whereIn('key', $metaKeysFilter);
            }

            // Build: [table_id][lan][key] = value
            // This matches the structure that decodeJoinedMetaData($str, 1) returned:
            //   ['ge' => ['title' => '...', 'body' => '...'], 'xx' => ['price' => '...']]
            foreach ($metaQr->get() as $metaRow) {
                $decoded = json_decode($metaRow->val, true);
                $metaByRow[$metaRow->table_id][$metaRow->lan][$metaRow->key] = is_array($decoded) ? $decoded : $metaRow->val;
            }
        }

        // =====================================================================
        // STEP 18 — Post-process each result row
        //
        // Four sequential transformations per row:
        //
        //   1. Meta merge: attach meta data from STEP 17 via mergeToMetaData().
        //      allAttributes ensures main-table column values take priority over
        //      any identically-named meta keys.
        //
        //   2. Taxonomy decode: the GROUP_CONCAT result is a JSON-like string;
        //      _psqlRow() (called inside _psql on $list) may have already decoded
        //      it to an array. reverseArray() flips {id: "name"} → {name: [ids]}.
        //
        //   3. Translation flatten: when params['translate'] is set,
        //      extractOnlyTranslated() collapses locale-keyed sub-arrays
        //      (e.g. 'ge', 'en', 'xx') into a single flat array for the
        //      requested language, adding 'localisedto' to the row.
        //
        //   4. Field whitelist: when params['fields'] is set, only the listed
        //      keys are kept in the output row.
        // =====================================================================
        $metaFields = _cv($fields, 'fields');

        foreach ($ret as $k => $v) {

            // 1. Merge meta data (replaces old metas GROUP_CONCAT decode)
            if ($this->metaTable) {
                $rowMeta = $metaByRow[$v['id']] ?? [];
                $ret[$k] = mergeToMetaData($v, $rowMeta, $this->allAttributes);
            }

            // 2. Decode taxonomy GROUP_CONCAT string into structured array
            if (isset($v['taxonomy'])) {
                $ret[$k]['taxonomy'] = $this->reverseArray($v['taxonomy']);
            }

            // 3. Flatten locale-keyed meta into a single-level array
            if (_cv($params, 'translate')) {
                $ret[$k] = $this->extractOnlyTranslated($ret[$k], $translate, $metaFields);
            }

            // 4. Restrict output to caller-specified field whitelist
            if (_cv($params, 'fields', 'ar')) {
                $tmp = [];
                foreach ($params['fields'] as $vv) {
                    $tmp[$vv] = $ret[$k][$vv] ?? '';
                }
                $ret[$k] = $tmp;
            }
        }

        $returnData['listCount'] = $listCount;
        $returnData['list']      = $ret;
        $returnData['page']      = _cv($params, 'page', 'nn') ? $params['page'] : 1;

        return $returnData;
    }

    public function updItem($data = [])
    {
//        p($data);
        $locales = config('app.locales');

        if(_cv($data, 'status') !== 'deleted'){
            /** validate table regular data depend on child class rules */
            $validator = Validator::make($data, $this->rules);
            if ($validator->fails()) { return ['success'=>false,'message'=>$validator->errors()->first()]; }
        }

        $id = _cv($data, 'id', 'nn');

        if(!$id){
            if(!_cv($data, ['user_id']))$data['user_id'] = Auth::user()->id ?? null;
            if(!_cv($data, ['status']))$data['status'] = 'published';
        }

        /** get or create table entry */
        $upd = $this->firstOrNew( ['id'=>$id] );

//        p($this->fillable);
        /** updatetable regular fields data */
        foreach ($this->fillable as $k=>$v){

            /// if editing does not change user
            if($v == 'user_id' && $id) continue;

            if(!isset($data[$v]))continue;

            $upd[$v] = is_array($data[$v])?_psqlupd($data[$v]):$data[$v];

        }

        /// if change user_id from admin panel allow user changing
        if(Auth::user() && Auth::user()->status=='admin' && array_search('user_id', $this->attributes)!==false && _cv($data, 'user_id', 'nn')){
            $upd['user_id'] = $data['user_id'];
        }

//        p($upd);
        $upd->save();

        $upd->id;

        $fields = (_cv($this->fieldConfigs, 'fields'))?$this->fieldConfigs:config($this->fieldConfigs);
//p($fields);
        /** update table meta fields */
        if($this->metaTable){
            $SmartTableMetaModel = new SmartTableMetaModel();


            $metaParams['fields'] = _cv($fields, ['fields']);
            $metaParams['table'] = $this->metaTable;
            $metaParams['table_id'] = $upd->id;
            $metaParams['parentTable'] = $this->table;

            foreach ($locales as $k=>$v){
                if(!isset($data[$k]))continue;
                $metaParams['lan'] = $k;
                $metaParams['meta'] = $data[$k];
                $rr = $SmartTableMetaModel->upd($metaParams);
            }
            /// xx for not translatable fields
            $metaParams['meta'] = _cv($data, ['xx']);
            $metaParams['lan'] = 'xx';
            $rr = $SmartTableMetaModel->upd($metaParams);
        }
        /** update table related taxonomies */
        $taxonomies = _cv($data, 'taxonomy', 'ar');

        if ($taxonomies)
        {
            $attributesRelationParams['dataList'] = $taxonomies;
            $attributesRelationParams['relationTable'] = $this->taxonomyRelationTable;
            $attributesRelationParams['relationTableName'] = $this->table;
            $attributesRelationParams['firstKeyName'] = 'data_id';
            $attributesRelationParams['firstKeyValue'] = $upd->id;
            $attributesRelationParams['secondKeyName'] = 'taxonomy_id';
            $attributesRelationParams['mainExtraFields'] = ['table'=>$this->table];
            $attributesRelationParams['formKeyName'] = 'dataList';


            RelationsModel::doRelations($attributesRelationParams);
        }

        if(_cv($fields, 'relations', 'ar')){

            foreach ($fields['relations'] as $k=>$v){
                $relationsClearOptions['relationTable'] = $v['table'];
                $relationsClearOptions['firstKeyName'] = $v['id'];
                $relationsClearOptions['firstKeyValue'] = $upd->id;
                RelationsModel::removeRelations($relationsClearOptions);
            }

            foreach ($fields['relations'] as $k=>$v){
//                p("relation_{$v['module']}");
                if(!isset($data["relation_{$v['module']}"]))continue;

                $relatedData = [];
                $relatedData['dataList'] = $data["relation_{$v['module']}"];
                $relatedData['relationTable'] = $v['table'];
                $relatedData['relationTableName'] = $v['module'];
                $relatedData['firstKeyName'] = $v['id'];
                $relatedData['firstKeyValue'] = $upd->id;
                $relatedData['secondKeyName'] = $v['data_id'];
//                $relatedData['mainExtraFields'] = ['table'=>$this->table];
                $relatedData['formKeyName'] = 'dataList';

                if( _cv($data, 'relationNode') ){
                    $relatedData['relationNode'] = $data['relationNode'];
                }

                RelationsModel::doRelations($relatedData);
            }
        }


        if(_cv($data, 'relatedSitemap')){
            SiteMapModel::doRelation([ 'sitemap_id'=>$data['relatedSitemap'], 'table_id'=>$upd->id, 'table'=>$this->table ]);
        }


        return $upd->id;
    }

    public function updField($data = [])
    {

        $locales = config('app.locales');

        /** validate table regular data depend on child class rules */
        $rules['id'] = ['required','numeric'];
        $rules['field'] = ['required'];
        // $rules['value'] = ['required'];

        $validator = \Validator::make($data, $rules);
//        p($validator);
        if ($validator->fails()) { return ['error'=>$validator->errors()->first()]; }

        $id = _cv($data, 'id', 'nn');

        if(!$id){
            if(!_cv($data, ['user_id']))$data['user_id'] = Auth::user()->id;
            if(!_cv($data, ['status']))$data['status'] = 'published';
        }

        /** get or create table entry */
        $upd = $this->find( $data['id'] );

        if(!isset($upd->id) || !$upd->id)return false;

        $upd[$data['field']] = $data['value'];
//        p($upd);

        $upd->save();

        return $upd->id;

    }

    public function deleteItem($data = [])
    {
        if(!_cv($data, 'id', 'nn'))return false;

        $deleteStatus = DB::table($this->table)->where('id', $data['id'])->update(['status' => 'deleted']);

        return $deleteStatus?$data['id']:0;

    }

    public function hardDeleteItem($data = [])
    {
        if(!_cv($data, 'id', 'nn'))return false;

        $deleteStatus = DB::table($this->table)->where('id', $data['id'])->limit(1)->delete();

        return $deleteStatus?$data['id']:0;

    }

    public function importData($data = [])
    {
//        p($data);

        $headers = _cv($data, 'data.header');
        $importData = _cv($data, 'data.results');

        $moduleConf = config($this->fieldConfigs);

        $headersPrepared = [];

        foreach ($headers as $k=>$v){
            $fieldNameCleaned = $this->cleanFieldName($v);

            if(!_cv($moduleConf, ['regularFields', $fieldNameCleaned]) && !_cv($moduleConf, ['fields', $fieldNameCleaned]) && $fieldNameCleaned !== 'taxonomy')continue;
            $headersPrepared[$v] = $fieldNameCleaned;
        }

        $requiredFields = [];
        $existCheckFields = [];
        /// get all required fields from regular fields
        if(_cv($moduleConf, ['regularFields'])){
            foreach ($moduleConf['regularFields'] as $k=>$v){
                /// collect required fields
                if(_cv($v, ['required'])==1)$requiredFields[$k] = $k;

                /// collect fields in which depend update or create new
                if(_cv($v, ['updateExist'])==1) $existCheckFields[$k] = $k;
            }
        }
        /// get all required fields from meta fields
        if(_cv($moduleConf, ['fields'])){
            foreach ($moduleConf['fields'] as $k=>$v){
                if(_cv($v, ['required'])==1)$requiredFields[$k] = $k;
            }
        }

        /// check if some required field not exists
        foreach ($requiredFields as $k=>$v){
            if(!array_search($v, $headersPrepared)) return ['success'=>false,'message'=>"field `{$v}` is required"];
//            if(!isset($headersPrepared[$v])) return ['success'=>false,'message'=>"field `{$v}` is required"];
        }

        $preparedData = [];
        foreach ($importData as $k=>$v){

            $tmp = [];
            foreach ($headersPrepared as $kk=>$vv){
                $tmp[$vv] = isset($v[$kk])?$v[$kk]:'';
            }

            if(isset($v['status']))$tmp['status'] = $v['status'];

            $preparedData[] = $tmp;
        }
//        p($preparedData);
        foreach ($preparedData as $k=>$v){

            $tmp = $this->prepareDataFromFlatArray($v);

            if(!isset($tmp['status']))$tmp['status'] = 'published';

            $tmp['id'] = $this->findExistItemId($existCheckFields, $tmp);

//            p($tmp);
//            continue;
            $res = $this->updItem($tmp);
            if(!is_numeric($res))return $res;
        }

        return true;

    }

    public function prepareDataFromFlatArray($flatData = []){
        $ret = [];
        $moduleConf = config($this->fieldConfigs);
//        p($moduleConf);
        $locale = requestLan();

        if(_cv($flatData, 'taxonomy')){
            $ret['taxonomy'] = $this->prepareImportableTaxonomyValues($flatData['taxonomy']);
        }

        if(isset($flatData['status']))$ret['status'] = strip_tags($flatData['status']);

        if(!_cv($moduleConf,['regularFields'], 'ar'))$moduleConf['regularFields'] = [];
        foreach ($moduleConf['regularFields'] as $k=>$v){
            if(!isset($flatData[$k]))continue;
            $ret[$k] = $this->prepareImportableValues($flatData[$k], $v);
        }

        if(!_cv($moduleConf,['fields'], 'ar'))$moduleConf['fields'] = [];
        foreach ($moduleConf['fields'] as $k=>$v){
            if(!isset($flatData[$k]))continue;

            if(_cv($v, ['translate'])){
                $ret[$locale][$k] = $this->prepareImportableValues($flatData[$k], $v);
            }else{
                $ret['xx'][$k] = $this->prepareImportableValues($flatData[$k], $v);
            }

        }

        return $ret;
    }

    public function prepareImportableTaxonomyValues($data = ''){
//        p($data);
        $data = explode(';', $data);
        $res = [];
        foreach ($data as $k=>$v){
            $v = explode(':', $v);
            if(!_cv($v, 1))continue;

            $v[0] = trim($v[0]);
            $v[1] = explode(',', trim($v[1]));

            $res[$v[0]] = [];
            foreach ($v[1] as $kk=>$vv){
                $vv = trim($vv);
                if(!is_numeric($vv))continue;
                $res[$v[0]][] = trim($vv);
            }

        }

        return $res;
    }

    public function reverseArray($sourceArray)
    {
        if (!is_array($sourceArray)) return [];

        $destArray = [];

        foreach ($sourceArray as $key => $value)
        {
            $destArray[$value][] = $key;
        }

        return $destArray;
    }

    public function extractOnlyTranslated($data = [], $translate = false, $fields = []){
//p($data);
//p($fields);
        $locales = config('app.locales');
        $locale = (isset($locales[$translate]))?$translate:array_key_first($locales);

        $notTranslatableData = _cv($data, 'xx', 'ar')?$data['xx']:[];
        $translatedData = _cv($data, $locale, 'ar')?$data[$locale]:[];

        /// if exists field configs
        /// check field is translatable or not; leave translatable fields; unset non translatable fields from translated object
        if(is_array($fields) && count($fields)>1){
            foreach ($fields as $k=>$v){
                if(isset($v['translate']) && $v['translate']==1)continue;
                if(isset($translatedData[$k]))unset($translatedData[$k]);
            }
        }


        /// unset translatable objects
        if(isset($data["xx"])) unset($data["xx"]);
        foreach ($locales as $kk=>$vv){
            if(isset($data[$kk])) unset($data[$kk]);
        }

        $data = array_merge($data, $notTranslatableData, $translatedData);

        $data['localisedto'] = $locale;

        return $data;
    }

    public function cleanFieldName($name = ''){
        return str_replace([' ', '-'], '_', $name);
    }

    public function prepareImportableValues($value='', $fieldConfig=[]){
        if(_cv($fieldConfig, 'type')=='select'){
            $tmp = _psqlCell($value);
            if(is_array($tmp)){
                $value = $tmp;
            }else if(strpos($value, ',')){
                $value = explode(',', $value);
            }else if(empty($value)){
                $value = [];
            }else{
                $value = [$value];
            }

        }

        return $value;
    }

    public function findExistItemId($checkFields=[], $data=[]){
        if(!count($checkFields))return '';

        $find['status'] = ['published', 'hidden'];
        foreach ($checkFields as $k=>$v){
            $find[$k] = _cv($data, $k);
        }

        $tmp = $this->getOne($find);

        if(_cv($tmp, 'id', 'nn'))return $tmp['id'];

        return '';
    }

    public function checkUser($id){
        $check = DB::table($this->table)->where('id', $id)->where('user_id', Auth::user()->id)->first();
        if(!$check){
            return response(['status'=>'error', 'message'=>'User not belongs to this record!']);
        }
    }

    public function updSort($list = [], $listParams = [])
    {
        $startFrom = 0;

        if (_cv($listParams, 'currentPage', 'nn') && _cv($listParams, 'sliceseparator', 'nn')) {
            $startFrom = ($listParams['currentPage'] * $listParams['sliceseparator']) - $listParams['sliceseparator'];
        }

        foreach ($list as $k => $v) {
            if (!is_numeric($v)) continue;
            DB::table($this->table)->where('id', $v)->update(['sort' => ($k + $startFrom)]);
        }

        return false;
    }

}
