<?php

namespace App\Services\GoogleAnalytics;

use App\Models\Admin\OptionsModel;
use Google\Analytics\Data\V1beta\RunReportRequest;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\DateRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventsServices extends BaseAnalyticsService
{
    public function getEvents(Request $request): JsonResponse
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
                    new Dimension(['name' => 'eventName']), // Group by event name
                ])
                ->setMetrics([
                    new Metric(['name' => 'eventCount']),
                    new Metric(['name' => 'totalUsers']),
                    new Metric(['name' => 'eventCountPerUser']),
                    new Metric(['name' => 'totalRevenue']),
                ])
                ->setDateRanges([
                    new DateRange(['start_date' => $start_date, 'end_date' => $end_date]),
                ]);

            $response = $this->client->runReport($request);

            // Parse the response
            $rows = $response->getRows();
            $eventsData = [];

            $totals = [
                'eventName' => 'Total',
                'eventCount' => 0,
                'totalUsers' => 0,
                'eventCountPerUser' => 0,
                'totalRevenue' => 0,
                'count' => 0,
            ];

            foreach ($rows as $row) {
                $eventName = $row->getDimensionValues()[0]->getValue();
                $eventCount = (int) $row->getMetricValues()[0]->getValue();
                $totalUsers = (int) $row->getMetricValues()[1]->getValue();
                $eventCountPerUser = (float) $row->getMetricValues()[2]->getValue();
                $totalRevenue = (float) $row->getMetricValues()[3]->getValue();

                // Update totals
                $totals['eventCount'] += $eventCount;
                $totals['totalUsers'] += $totalUsers;
                $totals['eventCountPerUser'] += $eventCountPerUser;
                $totals['totalRevenue'] += $totalRevenue;
                $totals['count']++;

                $eventsData[] = [
                    'eventName' => $eventName,
                    'eventCount' => $eventCount,
                    'totalUsers' => $totalUsers,
                    'eventCountPerUser' => number_format($eventCountPerUser, 2),
                    'totalRevenue' => number_format($totalRevenue, 2),
                ];
            }

            // Calculate averages for totals
            if ($totals['count'] > 0) {
                $totals['eventCountPerUser'] = number_format($totals['eventCountPerUser'] / $totals['count'], 2);
                $totals['totalRevenue'] = number_format($totals['totalRevenue'], 2);
            }

            unset($totals['count']);

            // Add totals to the beginning of the data array
            array_unshift($eventsData, $totals);

            return response()->json([
                'success' => true,
                'data' => $eventsData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching events data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}