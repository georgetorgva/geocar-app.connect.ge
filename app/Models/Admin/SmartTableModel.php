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

    protected $allAttributes = [];
    protected $fillable = [];
    protected $guarded = [];

    /**
     * Fetch a single record matching the given parameters.
     *
     * Thin wrapper around getList() that forces limit=1 and skips the COUNT
     * query and the sitemap JOIN, since neither is needed for a single-row
     * lookup. Callers can still opt into the sitemap join by passing
     * ['withSitemap' => true].
     *
     * @param  array $params  Same filter/field options accepted by getList().
     *                        'id', 'slug', 'status', 'translate', 'fields', etc.
     * @return array          The first matching row as a flat associative array,
     *                        with meta data merged in, or [] if nothing matched.
     */
    public function getOne($params = [])
    {
        $params['limit']       = 1;
        $params['skipCount']   = true;
        $params['withSitemap'] = $params['withSitemap'] ?? false;

        $res = $this->getList($params);

        if (isset($res['list'][0])) return $res['list'][0];

        return [];
    }

    /**
     * Fetch a paginated, filtered, and localised list of records with EAV meta
     * data merged into each result row.
     *
     * This is the single central data-access method for every CMS entity. It
     * drives both the admin panel list views and all public API endpoints. The
     * query is built dynamically in 18 numbered steps (documented inline) from
     * the field configuration stored in $this->fieldConfigs. The entire pipeline
     * can be controlled through the $params array described below.
     *
     * =========================================================================
     * HOW FIELD CONFIGURATION WORKS
     * =========================================================================
     * $this->fieldConfigs tells the method which columns exist, which are
     * translatable, how they can be filtered, and how the query should be
     * ordered by default. It is set once per child model but can be overridden
     * per call via $params['fieldConfigs'].
     *
     * It is accepted in three forms:
     *
     *   a) Indexed array of config arrays
     *      [ configA, configB, ... ]
     *      All 'fields' keys from every config are merged into configA.
     *      Use this when a view needs columns from multiple content-type configs.
     *
     *   b) Single config array (has a 'fields' key at the top level)
     *      [ 'fields' => [...], 'regularFields' => [...], ... ]
     *      Passed directly to the query builder as-is.
     *
     *   c) Dot-notation config string
     *      e.g. 'adminpanel.content_types.news'
     *      Loaded at call time via Laravel's config() helper. This is the most
     *      common form — child models set it as a class property string.
     *
     * =========================================================================
     * QUERY EXECUTION ORDER (18 STEPS)
     * =========================================================================
     * The method builds a single query object ($qr) and decorates it in a
     * strict order. Understanding this order is important when debugging
     * unexpected results:
     *
     *   STEPS 1–3   Config resolution and JOIN metadata preparation
     *   STEP  4     Base query: DB::table($this->table)
     *   STEP  5     Meta filter JOINs — one LEFT JOIN per active meta filter
     *   STEP  6     Taxonomy filter JOINs — aliased JOINs + whereIn per group
     *   STEP  7     Config-defined table JOINs (fields['join'])
     *   STEP  8     All WHERE filter conditions (id, slug, status, search, …)
     *   STEP  9     COUNT query — runs HERE before decorative JOINs are added
     *   STEP  10    Taxonomy SELECT JOIN — GROUP_CONCAT for taxonomy column
     *   STEP  11    Relations SELECT subqueries — one per fields['relations'] entry
     *   STEP  12    Remaining SELECT expressions (main table, sitemap, custom)
     *   STEP  13    GROUP BY + HAVING
     *   STEP  14    LIMIT / OFFSET (pagination)
     *   STEP  15    ORDER BY (whitelisted field + direction)
     *   STEP  16    Main query execution
     *   STEP  17    Secondary meta query — single IN() fetch for all result IDs
     *   STEP  18    Per-row post-processing: meta merge, taxonomy decode,
     *               translation flatten, field whitelist
     *
     * The COUNT (step 9) intentionally runs before the GROUP_CONCAT and
     * relations JOINs (steps 10–12) because those joins are purely decorative
     * (they add columns to the SELECT but do not filter rows). Running COUNT
     * after them would force MySQL to execute a heavier query than necessary.
     *
     * =========================================================================
     * FILTERING PARAMS
     * =========================================================================
     *
     * --- 'id'  int | int[] ---
     *   Filter by primary key. A scalar is automatically wrapped in an array.
     *   Generates: WHERE {table}.id IN (1, 2, 3)
     *   Side effect: disables default ORDER BY and replaces it with
     *   FIELD({table}.id, 1, 2, 3) so the result set preserves the exact
     *   caller-supplied ID order. Useful for retrieving records in a specific
     *   sequence (e.g. manually sorted dashboard widgets).
     *
     * --- 'slug'  string | string[] ---
     *   Filter by the slug column. A scalar is wrapped in an array.
     *   Generates: WHERE {table}.slug IN ('news-article', 'about-us')
     *
     * --- 'status'  string | string[] ---
     *   Filter by the status column. A scalar is wrapped in an array.
     *   Generates: WHERE {table}.status IN ('published', 'draft')
     *   Also mirrored onto config-defined joined tables when sameStatus=true
     *   in the join config (STEP 7), so joined rows must have the same status.
     *
     * --- 'sort'  int ---
     *   Exact match on the integer sort column.
     *   Generates: WHERE sort = {value}
     *
     * --- 'searchText'  string ---
     *   Full-text LIKE search across two sources simultaneously, joined by OR:
     *
     *   1. Regular table columns — any column in fields['regularFields'] that
     *      has a 'searchable' key is included in a CONCAT() expression:
     *        CONCAT('-', IFNULL(table.col1,'-'), IFNULL(table.col2,'-')) LIKE '%…%'
     *
     *   2. Meta table values — a LEFT JOIN on the meta table (for the active
     *      locale and 'xx') is added and:
     *        {metaTable}.val LIKE '%…%'
     *      The matched meta value is also exposed as 'searchVal' in each row
     *      so the caller can highlight the matching fragment.
     *
     *   HTML tags are stripped from the search term via strip_tags() before
     *   binding. User input is bound with parameterised ? bindings (never
     *   interpolated directly into SQL).
     *
     * --- 'searchBy'  array ---
     *   Per-field targeted search using the adminListFields config section.
     *   Only fields that declare 'searchable' and 'tableKey' are considered.
     *   Two search modes:
     *     searchType='where'  → exact match: WHERE {col} = {value}
     *     (default)           → substring: LOCATE(?, {searchable expression})
     *   User values are always bound via ? to prevent SQL injection.
     *
     * --- 'taxonomy'  array<string, int[]> ---
     *   AND taxonomy filter. Each key is a taxonomy group name; each value is
     *   an array of taxonomy IDs the record must belong to.
     *   Example: ['color' => [3, 7], 'size' => [12]]
     *   Records must match ALL groups (AND logic). Each group adds its own
     *   aliased LEFT JOIN + whereIn so the conditions are independent.
     *   Alias 'taxonomies' is accepted for backward compatibility.
     *
     * --- 'taxonomies_or'  array<string, int[]> ---
     *   OR taxonomy filter. All IDs from all groups are merged into a single
     *   flat array and a single whereIn is applied:
     *     WHERE taxonomy_relation_or.taxonomy_id IN (3, 7, 12)
     *   Records matching ANY of the given IDs are included.
     *
     * --- 'relate.taxonomy'  mixed ---
     *   When truthy, forces the taxonomy SELECT JOIN (STEP 10) to be added
     *   even if no taxonomy filter is active. Use this when you need the
     *   'taxonomy' column in the output without filtering by it.
     *
     * --- 'whereRaw'  string[] ---
     *   Array of raw AND WHERE conditions appended verbatim.
     *   Example: ['pages.created_at > "2024-01-01"', 'pages.pid = 0']
     *   Each element becomes a separate ->whereRaw() call.
     *   WARNING: caller is responsible for SQL safety — no binding is applied.
     *
     * --- 'orWhereRaw'  string[] ---
     *   Array of raw OR WHERE conditions. All elements are wrapped in a single
     *   grouped OR block: WHERE (condA OR condB OR condC).
     *   WARNING: same SQL safety caveat as 'whereRaw'.
     *
     * --- 'having'  array<string, scalar[]> ---
     *   Raw AND HAVING conditions. Format: ['column' => [value1, value2]].
     *   Each pair generates: HAVING column=value (one per inner value).
     *
     * --- 'orHaving'  array<string, scalar[]> ---
     *   Same as 'having' but generates OR HAVING conditions.
     *
     * --- 'customJsonFilter'  array ---
     *   JSON column containment filter using Laravel's whereJsonContains().
     *   Requires a companion 'toFilterArray' param:
     *     'customJsonFilter' => ['0' => 'column_name']
     *     'toFilterArray'    => ['0' => ['value1', 'value2']]
     *   First value uses whereJsonContains, subsequent values use
     *   orWhereJsonContains.
     *
     * =========================================================================
     * META FIELD FILTERS (via fields['fields'] config)
     * =========================================================================
     * Fields declared in fields['fields'] with a 'dbFilter' key can be filtered
     * by passing $params['{fieldName}'] = value. A dedicated LEFT JOIN on the
     * meta table is added for that field only (alias: meta_{fieldName}).
     * Translatable fields join for the active locale; non-translatable fields
     * join for locale 'xx'.
     *
     * Supported dbFilter types for meta fields:
     *   'whereIn'  — val IN (v1, v2)
     *   'like'     — val LIKE '%value%'
     *   'where'    — val = value
     *   'range'    — val BETWEEN v1 AND v2  (or val = v1 if only one value)
     *   '>'        — val > value
     *   '<'        — val < value
     *
     * Fields with a 'whereRaw' key always get their JOIN added (regardless of
     * whether a filter param is present) and the raw condition is appended.
     *
     * =========================================================================
     * REGULAR FIELD FILTERS (via fields['regularFields'] config)
     * =========================================================================
     * Main-table columns declared in fields['regularFields'] with a 'dbFilter'
     * key are filtered by passing $params['{columnName}'] = value. No JOIN is
     * needed — the condition is applied directly to the main table column.
     * Supported dbFilter types are identical to meta field filters above.
     *
     * =========================================================================
     * CONFIG-DEFINED TABLE JOINS (fields['join'])
     * =========================================================================
     * Additional tables can be joined via the 'join' section of the field
     * config. Each entry supports:
     *   'joinTable'   — the table to join
     *   'joinField'   — column on joinTable to join on
     *   'joinOn'      — column on main table to join to
     *   'tablePrefix' — alias base (defaults to joinTable name)
     *   'sameStatus'  — when true, mirrors the status filter onto joined rows
     *   'whereRaw'    — array of raw conditions added to the JOIN ON clause
     *   'select'      — map of columns to expose in SELECT + optional filter rules
     *
     * The join alias is always "joinedTable_{tablePrefix}". Columns from joined
     * tables appear in results as "{tablePrefix}_{columnName}". Joined-table
     * columns that declare a filter type in 'select' can be filtered via
     * $params['{joinTable}_{columnName}'].
     *
     * =========================================================================
     * SELECTION PARAMS
     * =========================================================================
     *
     * --- 'fields'  string[] ---
     *   Output field whitelist applied after all data is merged. Only the listed
     *   keys are kept in each result row; all others are dropped.
     *   Example: ['id', 'slug', 'title'] → each row contains only those keys.
     *   Missing keys default to an empty string ''.
     *
     * --- 'regularFieldsSelect'  string[] ---
     *   Limits the main-table SELECT to specific columns instead of the default
     *   {table}.*. Useful for performance when only a few columns are needed.
     *   Example: ['id', 'slug', 'status'] → SELECT {table}.id, {table}.slug, …
     *
     * --- 'customSelect'  string ---
     *   Raw SQL expression appended to the SELECT list verbatim.
     *   Example: '(SELECT COUNT(*) FROM comments WHERE page_id = pages.id) AS comment_count'
     *
     * --- 'fieldConfigs'  mixed ---
     *   Overrides $this->fieldConfigs for this call only. Accepts any of the
     *   three fieldConfigs forms described above.
     *
     * --- 'meta_keys'  string[] ---
     *   Restricts the secondary meta query (STEP 17) to only the listed meta
     *   keys. Applied as a SQL WHERE key IN (...) clause, so unwanted meta rows
     *   are never loaded from the database. Without this, all meta rows for
     *   the result IDs are loaded and then all are merged into each row.
     *
     * --- 'withSitemap'  bool  (default: true) ---
     *   When true (the default), LEFT JOINs modules_sitemap_relations and
     *   exposes 'relatedSitemap' (the sitemap_id) in each result row.
     *   Pass false to skip this join when the sitemap column is not needed.
     *   getOne() always passes false to avoid the unnecessary join overhead.
     *
     * =========================================================================
     * ORDERING PARAMS
     * =========================================================================
     *
     * --- 'orderField' / 'sortField'  string ---
     *   Column to sort by. 'sortField' is an alias for backward compatibility.
     *   The value is whitelisted against all keys in fields['regularFields'],
     *   fields['fields'], and the built-in safe columns:
     *     id, sort, created_at, updated_at
     *   If an unlisted column name is passed it is silently replaced with the
     *   config default (fields['orderField']) or 'id'. This prevents SQL
     *   injection via orderByRaw.
     *
     * --- 'orderDirection' / 'sortDirection'  string ---
     *   Sort direction. 'sortDirection' is an alias for backward compatibility.
     *   Whitelisted to 'ASC', 'DESC', or 'RANDOM' (case-insensitive).
     *   Any other value is replaced with the config default or 'desc'.
     *   'RANDOM' generates ORDER BY RAND() — avoid on large tables.
     *
     * --- orderCast (field config flag, not a param) ---
     *   When a regularField or meta field declares 'orderCast' => true in its
     *   config, the sort expression becomes CONVERT({table}.{field}, SIGNED).
     *   This corrects lexicographic ordering when numbers are stored as strings
     *   (e.g. '10' would sort before '9' without the cast).
     *
     * --- 'group_by'  string[] ---
     *   Overrides the default GROUP BY [{table}.id]. Use when the query's
     *   natural grouping must be different (e.g. grouping by a joined column).
     *   Default grouping by primary key is necessary to collapse duplicate rows
     *   introduced by the one-to-many taxonomy and meta filter JOINs.
     *
     * =========================================================================
     * PAGINATION PARAMS
     * =========================================================================
     *
     * --- 'page'  int ---
     *   1-based page number. When provided together with 'limit', the query
     *   uses OFFSET = (page - 1) * limit. When omitted, no OFFSET is applied.
     *
     * --- 'limit'  int  (default: 10) ---
     *   Maximum number of rows to return. Defaults to 10 when not specified.
     *   When 'page' is also present, LIMIT + OFFSET pagination is applied.
     *   When 'page' is absent, only LIMIT is applied (no offset).
     *
     * --- 'skipCount'  bool ---
     *   When true, skips the COUNT query entirely and returns listCount=0.
     *   Used by getOne() to avoid an unnecessary COUNT on single-row lookups.
     *   Also useful in any context where the caller does not need total count
     *   (e.g. infinite-scroll UIs that never show a total page count).
     *
     * =========================================================================
     * LOCALISATION PARAMS
     * =========================================================================
     *
     * --- 'translate'  string ---
     *   Locale code to flatten into (e.g. 'ge', 'en'). When set, each result
     *   row goes through extractOnlyTranslated(), which:
     *     1. Finds all locale-keyed sub-arrays in the row (e.g. $row['ge'],
     *        $row['en'], $row['xx']) and merges them into the top level.
     *     2. Locale priority: requested locale > 'xx' (non-translatable).
     *     3. Adds 'localisedto' => '{locale}' to the row so the caller knows
     *        which locale was applied.
     *   Without 'translate', all locale buckets are returned as nested arrays
     *   (raw multi-language structure from the meta table).
     *   The locale is resolved via requestLan() which falls back to the
     *   request's own locale header when no explicit value is passed.
     *
     * =========================================================================
     * RETURN VALUE
     * =========================================================================
     * Always returns an array with exactly three keys:
     *
     *   'list'      => array[]
     *     Each element is a flat associative array representing one record.
     *     Main-table columns are present at the top level. Meta fields are
     *     merged in at the top level (with main-table values taking priority
     *     over identically-named meta keys). Additional synthesised keys:
     *       'taxonomy'       — decoded taxonomy map: ['slug' => [id, id], …]
     *                          present only when taxonomy config is active or
     *                          'relate.taxonomy' is passed
     *       'searchVal'      — the meta value that matched 'searchText',
     *                          present only when 'searchText' is used and
     *                          $this->metaTable is set
     *       'relatedSitemap' — sitemap_id from modules_sitemap_relations,
     *                          present only when withSitemap is true (default)
     *       'localisedto'    — locale code that was applied,
     *                          present only when 'translate' param is used
     *       "relation_{mod}" — JSON-string array of related IDs per module,
     *                          present for each entry in fields['relations']
     *       "{prefix}_{col}" — columns from config-defined joined tables
     *
     *   'listCount' => int
     *     Total number of matching records before pagination, used to calculate
     *     total page count. 0 when 'skipCount' => true is passed.
     *
     *   'page'      => int
     *     The current page number, echoed from $params['page'] or 1 if absent.
     *
     * @param  array $params  Filter, selection, ordering, and pagination options.
     *                        All keys are optional. Unknown keys are ignored.
     * @return array{list: array[], listCount: int, page: int}
     */
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
        $listCount = _cv($params, 'skipCount') ? 0 : $qr->count(DB::raw("DISTINCT({$this->table}.id)"));

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

        if (_cv($params, 'page')) {
            $qr->skip(($params['page'] - 1) * $params['limit'])->take($params['limit']);
        } elseif (_cv($params, 'limit')) {
            $qr->take($params['limit']);
        }

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
                $qr->orderByRaw("{$this->table}.{$orderField} {$orderDirection}");
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
        $metaFields    = _cv($fields, 'fields');
        $locales       = config('app.locales');

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
                $ret[$k] = static::extractOnlyTranslated($ret[$k], $translate, $metaFields, $locales);
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

    /**
     * Create or update a record, including its EAV meta, taxonomy relations,
     * module relations, and sitemap link — all within a single DB transaction.
     *
     * When $data['id'] is absent or falsy the record is created (INSERT).
     * When $data['id'] is present the existing record is updated (UPDATE).
     * The entire operation is atomic: if any write fails the transaction is
     * rolled back and the exception propagates to the caller.
     *
     * -------------------------------------------------------------------------
     * VALIDATION
     * -------------------------------------------------------------------------
     * Child-class $rules are applied via Laravel Validator before any DB write,
     * unless $data['status'] === 'deleted' (soft-delete bypass).
     * Returns ['success' => false, 'message' => '...'] on validation failure
     * without touching the database.
     *
     * -------------------------------------------------------------------------
     * SUPPORTED $data KEYS
     * -------------------------------------------------------------------------
     * Main table:
     *   'id'             int         — record to update; omit or 0 to insert
     *   'status'         string      — record status ('published', 'deleted', …)
     *                                 defaults to 'published' on insert
     *   'user_id'        int         — assigned on insert from Auth::user()->id;
     *                                 admins may override it explicitly
     *   <fillable cols>  mixed       — any column listed in $this->fillable;
     *                                 arrays are JSON-encoded via _psqlupd()
     *
     * EAV meta (requires $this->metaTable to be set):
     *   '<locale_code>'  array       — translatable meta fields for that locale
     *                                 (e.g. 'ge' => ['title' => '…', 'body' => '…'])
     *   'xx'             array       — non-translatable meta fields
     *                                 (e.g. 'xx' => ['price' => 100])
     *
     * Taxonomy relations (requires $this->taxonomyRelationTable to be set):
     *   'taxonomy'       array       — taxonomy IDs to associate; existing
     *                                 relations are replaced (delete + reinsert)
     *
     * Module relations (configured via fieldConfigs['relations']):
     *   "relation_{module}" array   — related IDs for that module; existing
     *                                 relations for that module are replaced
     *   'relationNode'   string      — optional node discriminator forwarded
     *                                 to RelationsModel::doRelations()
     *
     * Sitemap:
     *   'relatedSitemap' int         — sitemap_id to link to this record via
     *                                  SiteMapModel::doRelation()
     *
     * -------------------------------------------------------------------------
     * RETURN VALUE
     * -------------------------------------------------------------------------
     * On success : int   — the id of the created or updated record.
     * On validation failure : array — ['success' => false, 'message' => string]
     *
     * @param  array $data  Record data; see keys above.
     * @return int|array
     */
    public function updItem($data = [])
    {
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

        return DB::transaction(function () use ($data, $id, $locales) {

            /** get or create table entry */
            $upd = $this->firstOrNew( ['id'=>$id] );

            /** updatetable regular fields data */
            foreach ($this->fillable as $k=>$v){

                /// if editing does not change user
                if($v == 'user_id' && $id) continue;

                if(!isset($data[$v]))continue;

                $upd[$v] = is_array($data[$v])?_psqlupd($data[$v]):$data[$v];

            }

            /// if change user_id from admin panel allow user changing
            if(Auth::user() && Auth::user()->status=='admin' && array_search('user_id', $this->fillable)!==false && _cv($data, 'user_id', 'nn')){
                $upd['user_id'] = $data['user_id'];
            }

            $upd->save();

            if (is_array($this->fieldConfigs) && isset($this->fieldConfigs[0])) {
                $fields = $this->fieldConfigs[0];
                foreach ($this->fieldConfigs as $value) {
                    foreach ($value['fields'] as $key => $field) {
                        $fields['fields'][$key] = $field;
                    }
                }
            } elseif (_cv($this->fieldConfigs, 'fields')) {
                $fields = $this->fieldConfigs;
            } else {
                $fields = config($this->fieldConfigs);
            }

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
                    $SmartTableMetaModel->upd($metaParams);
                }
                /// xx for not translatable fields
                $metaParams['meta'] = _cv($data, ['xx']);
                $metaParams['lan'] = 'xx';
                $SmartTableMetaModel->upd($metaParams);
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
                    if(!isset($data["relation_{$v['module']}"]))continue;

                    $relatedData = [];
                    $relatedData['dataList'] = $data["relation_{$v['module']}"];
                    $relatedData['relationTable'] = $v['table'];
                    $relatedData['relationTableName'] = $v['module'];
                    $relatedData['firstKeyName'] = $v['id'];
                    $relatedData['firstKeyValue'] = $upd->id;
                    $relatedData['secondKeyName'] = $v['data_id'];
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

        });
    }

    /**
     * Update a single column on an existing record by ID.
     *
     * Lighter alternative to updItem() when only one field needs to change.
     * Does not touch meta, taxonomy, relations, or the sitemap — it writes
     * exactly one column in one UPDATE query with no prior SELECT.
     *
     * -------------------------------------------------------------------------
     * VALIDATION
     * -------------------------------------------------------------------------
     * Three fields are required before any DB work is attempted:
     *   'id'    — must be present and numeric; the record to update
     *   'field' — must be present; the column name to change
     *   'value' — must be present; the new value to write
     *
     * Returns ['error' => '...'] on validation failure without touching the DB.
     *
     * -------------------------------------------------------------------------
     * SECURITY — FIELD WHITELIST
     * -------------------------------------------------------------------------
     * 'field' is validated against $this->fillable before the UPDATE is issued.
     * If the requested column is not in the fillable list the method returns
     * ['error' => 'Field not allowed.'] and no query is executed.
     * This prevents callers from patching arbitrary columns (e.g. user_id,
     * created_at) by passing a crafted 'field' value through the request.
     *
     * -------------------------------------------------------------------------
     * SUPPORTED $data KEYS
     * -------------------------------------------------------------------------
     *   'id'     int     — primary key of the record to update (required)
     *   'field'  string  — column name to update; must be in $this->fillable
     *   'value'  mixed   — new value to write to that column
     *
     * -------------------------------------------------------------------------
     * RETURN VALUE
     * -------------------------------------------------------------------------
     *   int   — the record ID on success
     *   false — record not found
     *   array — ['error' => string] on validation failure or disallowed field
     *
     * @param  array $data  Must contain 'id', 'field', and 'value'.
     * @return int|false|array
     */
    public function updField($data = [])
    {
        /** validate table regular data depend on child class rules */
        $rules['id']    = ['required', 'numeric'];
        $rules['field'] = ['required'];
        $rules['value'] = ['required'];

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) { return ['error' => $validator->errors()->first()]; }

        /** find existing record */
        $upd = $this->find($data['id']);

        if (!$upd) return false;

        if (!in_array($data['field'], $this->fillable)) {
            return ['error' => 'Field not allowed.'];
        }

        DB::table($this->table)
            ->where('id', $data['id'])
            ->update([$data['field'] => $data['value']]);

        return $data['id'];

    }

    /**
     * Soft-delete a record by setting its status column to 'deleted'.
     *
     * The record remains in the database and can be restored by updating its
     * status back to 'published' (or any other active status). Meta data,
     * taxonomy relations, and module relations are left intact so the record
     * can be fully recovered.
     *
     * -------------------------------------------------------------------------
     * INPUT NORMALIZATION
     * -------------------------------------------------------------------------
     * $data may be passed either as an associative array ['id' => 5] or as a
     * plain scalar (e.g. deleteItem(5)). A scalar is automatically wrapped into
     * ['id' => value] before any further processing.
     *
     * -------------------------------------------------------------------------
     * EXISTENCE CHECK
     * -------------------------------------------------------------------------
     * An EXISTS query is issued before the UPDATE. This ensures the return
     * value reflects whether the record was found, not whether any rows were
     * changed. A record that already has status='deleted' still returns its ID
     * rather than 0 (which the UPDATE alone would produce when 0 rows change).
     *
     * -------------------------------------------------------------------------
     * SUPPORTED $data KEYS
     * -------------------------------------------------------------------------
     *   'id'  int  — primary key of the record to soft-delete (required, > 0)
     *
     * -------------------------------------------------------------------------
     * RETURN VALUE
     * -------------------------------------------------------------------------
     *   int   — the record ID on success
     *   false — id missing / not a positive integer, or record not found
     *
     * @param  array|int $data  Associative array with 'id' key, or a plain int ID.
     * @return int|false
     */
    public function deleteItem($data = [])
    {
        if (!is_array($data)) $data = ['id' => $data];

        if (!_cv($data, 'id', 'nn')) return false;

        $exists = DB::table($this->table)->where('id', $data['id'])->exists();

        if (!$exists) return false;

        DB::table($this->table)->where('id', $data['id'])->update(['status' => 'deleted']);

        return $data['id'];

    }

    /**
     * Permanently delete a record and all its associated data.
     *
     * Unlike deleteItem(), this issues a physical DELETE from the main table
     * and cascades the removal to all dependent tables within a single
     * transaction. The operation is irreversible — there is no way to recover
     * the record once this method completes.
     *
     * -------------------------------------------------------------------------
     * CASCADED DELETIONS (inside one transaction)
     * -------------------------------------------------------------------------
     * 1. Main table row:        DELETE FROM {table} WHERE id = {id}
     * 2. EAV meta rows:         DELETE FROM {metaTable}
     *                               WHERE table_id = {id} AND table = '{table}'
     *                           Skipped when $this->metaTable is empty.
     * 3. Taxonomy relations:    DELETE FROM {taxonomyRelationTable}
     *                               WHERE data_id = {id} AND table = '{table}'
     *                           Skipped when $this->taxonomyRelationTable is empty.
     *
     * If any of the three deletes fails the transaction is rolled back and an
     * exception propagates to the caller — no partial state is left behind.
     *
     * NOTE: module relation tables (fields['relations']) are not cascaded here
     * because their table names are defined in fieldConfigs, which is not
     * loaded by this method. If the model has module relations, clean them up
     * manually before or after calling hardDeleteItem().
     *
     * -------------------------------------------------------------------------
     * INPUT NORMALIZATION
     * -------------------------------------------------------------------------
     * $data may be passed either as an associative array ['id' => 5] or as a
     * plain scalar (e.g. hardDeleteItem(5)). A scalar is automatically wrapped
     * into ['id' => value] before any further processing.
     *
     * -------------------------------------------------------------------------
     * EXISTENCE CHECK
     * -------------------------------------------------------------------------
     * An EXISTS query is issued before the transaction begins. If the record
     * is not found the method returns false immediately with no DB writes.
     *
     * -------------------------------------------------------------------------
     * SUPPORTED $data KEYS
     * -------------------------------------------------------------------------
     *   'id'  int  — primary key of the record to delete (required, > 0)
     *
     * -------------------------------------------------------------------------
     * RETURN VALUE
     * -------------------------------------------------------------------------
     *   int   — the deleted record ID on success
     *   false — id missing / not a positive integer, or record not found
     *
     * @param  array|int $data  Associative array with 'id' key, or a plain int ID.
     * @return int|false
     */
    public function hardDeleteItem($data = [])
    {
        if (!is_array($data)) $data = ['id' => $data];

        if (!_cv($data, 'id', 'nn')) return false;

        $exists = DB::table($this->table)->where('id', $data['id'])->exists();

        if (!$exists) return false;

        DB::transaction(function () use ($data) {
            DB::table($this->table)->where('id', $data['id'])->delete();

            if ($this->metaTable) {
                DB::table($this->metaTable)
                    ->where('table_id', $data['id'])
                    ->where('table', $this->table)
                    ->delete();
            }

            if ($this->taxonomyRelationTable) {
                DB::table($this->taxonomyRelationTable)
                    ->where('data_id', $data['id'])
                    ->where('table', $this->table)
                    ->delete();
            }
        });

        return $data['id'];

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

    /**
     * Flatten a multi-locale result row into a single-level array for one locale.
     *
     * After getList() merges EAV meta data, each row contains locale-keyed
     * sub-arrays (e.g. $row['ge'], $row['en']) for translatable fields and a
     * special 'xx' bucket for non-translatable fields. This method collapses
     * all of that into a flat associative array for the requested locale, then
     * removes the raw locale buckets from the row so the caller receives a
     * clean, single-level structure.
     *
     * -------------------------------------------------------------------------
     * MERGE STRATEGY AND KEY PRIORITY
     * -------------------------------------------------------------------------
     * The final row is assembled in three layers using the + union operator,
     * which preserves the FIRST occurrence of each key:
     *
     *   1. $data              — main-table columns (highest priority)
     *   2. $notTranslatableData ('xx' bucket) — non-translatable meta fields
     *   3. $translatedData    — locale-specific meta fields (lowest priority)
     *
     * Main-table columns are never overwritten by meta values, even if a meta
     * field shares the same name as a column (e.g. 'status', 'slug').
     *
     * -------------------------------------------------------------------------
     * LOCALE RESOLUTION
     * -------------------------------------------------------------------------
     * The effective locale is resolved in this order:
     *   1. $translate if it exists as a key in $locales
     *   2. array_key_first($locales) — the application's default locale
     *
     * This means passing an unrecognised locale code silently falls back to
     * the default rather than returning an empty row.
     *
     * -------------------------------------------------------------------------
     * FIELD CONFIG FILTERING
     * -------------------------------------------------------------------------
     * When $fields (the 'fields' section of fieldConfigs) is provided, any key
     * in the locale bucket that is NOT marked 'translate' => 1 in the config
     * is stripped from $translatedData before merging. This prevents
     * non-translatable meta fields from leaking into the translated output
     * when they were also stored under a locale key.
     *
     * -------------------------------------------------------------------------
     * LOCALE BUCKET CLEANUP
     * -------------------------------------------------------------------------
     * Before the final merge, all locale-keyed sub-arrays ('xx' and every key
     * in $locales) are removed from $data so they do not appear in the output
     * row alongside the already-flattened values.
     *
     * -------------------------------------------------------------------------
     * PARAMETERS
     * -------------------------------------------------------------------------
     * @param  array       $data       Full result row as returned by mergeToMetaData(),
     *                                 containing main-table columns plus locale buckets.
     * @param  string|null $translate  Locale code to flatten into (e.g. 'ge', 'en').
     *                                 Null or an unrecognised code falls back to the
     *                                 application's default locale.
     * @param  array|null  $fields     The 'fields' section of fieldConfigs, used to
     *                                 determine which keys in the locale bucket are
     *                                 genuinely translatable. Pass null or [] to skip
     *                                 field-level filtering.
     * @param  array|null  $locales    Pre-resolved app locales array (from
     *                                 config('app.locales')). When null, loaded from
     *                                 config at call time. Pass the pre-resolved value
     *                                 when calling inside a loop to avoid repeated
     *                                 config() calls per row.
     *
     * -------------------------------------------------------------------------
     * RETURN VALUE
     * -------------------------------------------------------------------------
     * @return array  Flat associative array with:
     *                  - all main-table columns
     *                  - non-translatable meta fields from 'xx' merged in
     *                  - translatable meta fields for the resolved locale merged in
     *                  - 'localisedto' => string  the locale code that was applied
     *                  - locale bucket keys removed ('xx', 'ge', 'en', …)
     */
    public static function extractOnlyTranslated($data = [], $translate = null, $fields = [], $locales = null){
        $locales = $locales ?? config('app.locales');
        $locale = isset($locales[$translate]) ? $translate : array_key_first($locales);

        $notTranslatableData = _cv($data, 'xx', 'ar')    ?: [];
        $translatedData      = _cv($data, $locale, 'ar') ?: [];

        /// if exists field configs
        /// check field is translatable or not; leave translatable fields; unset non translatable fields from translated object
        if (is_array($fields) && count($fields) > 0) {
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

        $data = $data + $notTranslatableData + $translatedData;

        $data['localisedto'] = $locale;

        return $data;
    }

    public function checkUser($id){
        $check = DB::table($this->table)->where('id', $id)->where('user_id', Auth::user()->id)->first();
        if(!$check){
            return response(['status'=>'error', 'message'=>'User not belongs to this record!']);
        }
    }

    /**
     * Update the sort order of multiple records in a single atomic query.
     *
     * Accepts an ordered list of record IDs (as sent by a drag-and-drop UI)
     * and writes the new sort value for every ID in one UPDATE statement using
     * a CASE WHEN expression — avoiding the N+1 query pattern of updating
     * each record individually.
     *
     * The entire operation runs inside a DB transaction. If the UPDATE fails
     * the sort order is left unchanged.
     *
     * -------------------------------------------------------------------------
     * HOW SORT VALUES ARE CALCULATED
     * -------------------------------------------------------------------------
     * Each record receives a sort value equal to its zero-based position in
     * $list plus $startFrom (the pagination offset, see $listParams below).
     *
     * Example — page 1, no pagination offset:
     *   $list = [12, 7, 3]
     *   → id 12 gets sort = 0
     *   → id  7 gets sort = 1
     *   → id  3 gets sort = 2
     *
     * Example — page 2, sliceseparator = 10:
     *   $startFrom = 10
     *   $list = [5, 9, 1]
     *   → id 5 gets sort = 10
     *   → id 9 gets sort = 11
     *   → id 1 gets sort = 12
     *
     * Non-numeric values in $list are silently skipped.
     *
     * -------------------------------------------------------------------------
     * PARAMETERS
     * -------------------------------------------------------------------------
     * @param  array $list        Ordered array of record IDs. Must be a numeric
     *                            array where $list[0] is a valid integer ID.
     *                            Non-numeric elements are ignored.
     *                            Example: [12, 7, 3, 5]
     *
     * @param  array $listParams  Optional pagination context used to calculate
     *                            the sort offset for pages beyond the first:
     *                              'currentPage'    int  — 1-based page number
     *                              'sliceseparator' int  — page size (rows per page)
     *                            When either value is absent or zero, $startFrom
     *                            defaults to 0 (no offset applied).
     *
     * -------------------------------------------------------------------------
     * RETURN VALUE
     * -------------------------------------------------------------------------
     * @return bool  true  — sort order updated successfully
     *               false — $list is empty or contains no numeric IDs
     */
    public function updSort($list = [], $listParams = [])
    {
        if (!_cv($list, 0, 'num')) return false;

        $startFrom = 0;

        if (_cv($listParams, 'currentPage', 'nn') && _cv($listParams, 'sliceseparator', 'nn')) {
            $startFrom = ($listParams['currentPage'] * $listParams['sliceseparator']) - $listParams['sliceseparator'];
        }

        DB::transaction(function () use ($list, $startFrom) {
            $cases = '';
            $ids   = [];

            foreach ($list as $k => $v) {
                if (!is_numeric($v)) continue;
                $cases .= 'WHEN ' . (int)$v . ' THEN ' . ($k + $startFrom) . ' ';
                $ids[]  = (int)$v;
            }

            if ($ids) {
                DB::table($this->table)
                    ->whereIn('id', $ids)
                    ->update(['sort' => DB::raw('CASE id ' . $cases . 'END')]);
            }
        });

        return true;
    }

    /**
    custom import methods
     */

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

    public function cleanFieldName($name = ''){
        return str_replace([' ', '-'], '_', $name);
    }


}
