<?php

namespace App\Http\Controllers\Api;

use App\Models\Admin\PageModel;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Admin\OptionsModel;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class CustomController extends Controller
{
    public function getLatestQuarterContentByTimelineYear(Request $request)
    {
        $response['success'] = false;
        $response['error'] = '';
        $response['data'] = null;

        $input = $request->only([
            'content_type',
            'taxonomy',
            'taxonomy_slug',
            'locale'
        ]);

        $rules = [
            'content_type' => 'bail|required|string|max:200',
            'taxonomy' => 'bail|required|string|max:200',
            'taxonomy_slug' => 'bail|required|string|max:200',
            'locale' => 'bail|nullable|string|in:en,ge,ka,ru'
        ];

        $validator = \Validator::make($input, $rules);

        if ($validator->fails())
        {
            $response['error'] = $validator->errors()->first();

            return $response;
        }

        $latestContentByTimelineYearAndQuarter = \DB::table('pages')->select('pages.id')
                                                                    ->join('modules_taxonomy_relations', 'modules_taxonomy_relations.data_id', '=', 'pages.id')
                                                                    ->join('taxonomy', 'taxonomy.id', '=', 'modules_taxonomy_relations.taxonomy_id')
                                                                    ->join('pages_meta', 'pages_meta.table_id', '=', 'pages.id')
                                                                    ->where('pages.status', 'published')
                                                                    ->where('taxonomy.taxonomy', 'timeline_year')
                                                                    ->where('pages_meta.key', 'quarter')
                                                                    ->whereIn('pages.id', function ($childQuery) use ($input) {
                                                                        $childQuery->select('pages.id')
                                                                                   ->from('pages')
                                                                                   ->join('modules_taxonomy_relations', 'modules_taxonomy_relations.data_id', '=', 'pages.id')
                                                                                   ->join('taxonomy', 'taxonomy.id', '=', 'modules_taxonomy_relations.taxonomy_id')
                                                                                   ->join('pages_meta', 'pages_meta.table_id', '=', 'pages.id')
                                                                                   ->where('pages.status', 'published')
                                                                                   ->where('taxonomy.taxonomy', $input['taxonomy'])
                                                                                   ->where('taxonomy.slug', $input['taxonomy_slug'])
                                                                                   ->where('pages.content_type', $input['content_type'])
                                                                                   ->groupBy('pages.id');
                                                                    })
                                                                    ->orderBy('taxonomy.slug', 'desc')
                                                                    ->orderBy('pages_meta.val', 'desc')
                                                                    ->groupBy('pages.id')
                                                                    ->first();

        $response['success'] = true;

        if (!$latestContentByTimelineYearAndQuarter)
        {
            return $response;
        }

        $page = new PageModel;

        $filterParameters = [
            'id' => $latestContentByTimelineYearAndQuarter->id
        ];

        if ($request->locale)
        {
            $filterParameters['translate'] = $request->locale;
        }

        $response['data'] = $page->getOne($filterParameters);

        return $response;
    }
}
