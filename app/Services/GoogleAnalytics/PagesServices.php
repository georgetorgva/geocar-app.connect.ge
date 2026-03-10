<?php

namespace App\Services\GoogleAnalytics;

use App\Models\Admin\OptionsModel;
use Google\Analytics\Data\V1beta\RunReportRequest;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\DateRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PagesServices extends BaseAnalyticsService
{
    public function getPages(Request $request) : JsonResponse
    {
        $dateRange = $request->input('dateRange');
        $startDate = $request->input('startDate');
        $endDate = $request->input('endDate');
        $options = new OptionsModel();
        $propertyId = $options->getByKey('ga_property_id');

        try {

            $start_date = null;
            $end_date = 'today';

            if ($startDate && $endDate) {
                $start_date = $startDate;
                $end_date = $endDate;
            } elseif ($dateRange) {
                $start_date = $dateRange;
            }

            // Build the request
            $request = (new RunReportRequest())
                ->setProperty('properties/' . $propertyId)
                ->setDimensions([
                    new Dimension(['name' => 'pageTitle']),
                    new Dimension(['name' => 'pagePath']),
                ])
                ->setMetrics([
                    new Metric(['name' => 'screenPageViews']),
                    new Metric(['name' => 'activeUsers']),
                    new Metric(['name' => 'screenPageViewsPerUser']),
                    new Metric(['name' => 'eventCount']),
                    new Metric(['name' => 'conversions']),
                    new Metric(['name' => 'totalRevenue']),
                ])
                ->setDateRanges([
                    new DateRange(['start_date' => $start_date, 'end_date' => $end_date]),
                ]);

            $response = $this->client->runReport($request);

            // Parse the response
            $rows = $response->getRows();
            $pagesData = [];

            $totals = [
                'pageTitle' => 'Total',
                'pagePath' => '',
                'screenPageViews' => 0,
                'activeUsers' => 0,
                'screenPageViewsPerUser' => 0,
                'eventCount' => 0,
                'conversions' => 0,
                'totalRevenue' => 0,
                'count' => 0,
            ];

            foreach ($rows as $row) {
                $pageTitle = $row->getDimensionValues()[0]->getValue();
                $pagePath = $row->getDimensionValues()[1]->getValue();
                $screenPageViews = (int) $row->getMetricValues()[0]->getValue();
                $activeUsers = (int) $row->getMetricValues()[1]->getValue();
                $screenPageViewsPerUser = (float) $row->getMetricValues()[2]->getValue();
                $eventCount = (int) $row->getMetricValues()[3]->getValue();
                $conversions = (int) $row->getMetricValues()[4]->getValue();
                $totalRevenue = (float) $row->getMetricValues()[5]->getValue();

                // Update totals
                $totals['screenPageViews'] += $screenPageViews;
                $totals['activeUsers'] += $activeUsers;
                $totals['screenPageViewsPerUser'] += $screenPageViewsPerUser;
                $totals['eventCount'] += $eventCount;
                $totals['conversions'] += $conversions;
                $totals['totalRevenue'] += $totalRevenue;
                $totals['count']++;

                $pagesData[] = [
                    'pageTitle' => $pageTitle,
                    'pagePath' => $pagePath,
                    'screenPageViews' => $screenPageViews,
                    'activeUsers' => $activeUsers,
                    'screenPageViewsPerUser' => number_format($screenPageViewsPerUser, 2),
                    'eventCount' => $eventCount,
                    'conversions' => $conversions,
                    'totalRevenue' => number_format($totalRevenue, 2),
                ];
            }

            // Calculate averages for totals
            if ($totals['count'] > 0) {
                $totals['screenPageViewsPerUser'] = number_format($totals['screenPageViewsPerUser'] / $totals['count'], 2);
                $totals['totalRevenue'] = number_format($totals['totalRevenue'], 2);
            }

            unset($totals['count']);

            // Add totals to the beginning of the data array
            array_unshift($pagesData, $totals);

            return response()->json([
                'success' => true,
                'data' => $pagesData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching pages and screens data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}
