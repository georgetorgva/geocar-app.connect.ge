<?php

namespace App\Services\GoogleAnalytics;

use App\Models\Admin\OptionsModel;
use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\RunReportRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class TrafficAcquisitionService extends BaseAnalyticsService
{
    public function getTrafficAcquisition(Request $request) : JsonResponse
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

            $request = (new RunReportRequest())
                ->setProperty('properties/' . $propertyId)
                ->setDimensions([
                    new Dimension(['name' => 'sessionSourceMedium'])
                ])
                ->setMetrics([
                    new Metric(['name' => 'sessions']),
                    new Metric(['name' => 'engagedSessions']),
                    new Metric(['name' => 'engagementRate']),
                    new Metric(['name' => 'screenPageViewsPerSession']),
                    new Metric(['name' => 'eventsPerSession']),
                    new Metric(['name' => 'eventCount']),
                    new Metric(['name' => 'keyEvents']),
                    new Metric(['name' => 'sessionKeyEventRate']),
                    new Metric(['name' => 'totalRevenue']),
                ])
                ->setDateRanges([
                    new DateRange(['start_date' => $start_date, 'end_date' => $end_date]),
                ]);

            $response = $this->client->runReport($request);

            $rows = $response->getRows();
            $trafficData = [];

            $totals = [
                'channelGroup' => 'Total',
                'sessions' => 0,
                'engagedSessions' => 0,
                'engagementRate' => 0,
                'screenPageViewsPerSession' => 0,
                'eventsPerSession' => 0,
                'eventCount' => 0,
                'keyEvents' => 0,
                'sessionKeyEventRate' => 0,
                'totalRevenue' => 0,
                'count' => 0
            ];

            foreach ($rows as $row) {
                $channelGroup = $row->getDimensionValues()[0]->getValue();
                $sessions = (int) $row->getMetricValues()[0]->getValue();
                $engagedSessions = (int) $row->getMetricValues()[1]->getValue();
                $engagementRate = (float) $row->getMetricValues()[2]->getValue() * 100; // Convert to percentage
                $screenPageViewsPerSession = round((float) $row->getMetricValues()[3]->getValue(), 2);
                $eventsPerSession = round((float) $row->getMetricValues()[4]->getValue(), 2);
                $eventCount = (int) $row->getMetricValues()[5]->getValue();
                $keyEvents = (int) $row->getMetricValues()[6]->getValue();
                $sessionKeyEventRate = (float) $row->getMetricValues()[7]->getValue() * 100; // Convert to percentage
                $totalRevenue = round((float) $row->getMetricValues()[8]->getValue(), 2);

                $totals['sessions'] += $sessions;
                $totals['engagedSessions'] += $engagedSessions;
                $totals['engagementRate'] += $engagementRate;
                $totals['screenPageViewsPerSession'] += $screenPageViewsPerSession;
                $totals['eventsPerSession'] += $eventsPerSession;
                $totals['eventCount'] += $eventCount;
                $totals['keyEvents'] += $keyEvents;
                $totals['sessionKeyEventRate'] += $sessionKeyEventRate;
                $totals['totalRevenue'] += $totalRevenue;
                $totals['count']++;

                $trafficData[] = [
                    'channelGroup' => $channelGroup,
                    'sessions' => $sessions,
                    'engagedSessions' => $engagedSessions,
                    'engagementRate' => number_format($engagementRate, 2) . '%',
                    'screenPageViewsPerSession' => number_format($screenPageViewsPerSession, 2),
                    'eventsPerSession' => number_format($eventsPerSession, 2),
                    'eventCount' => $eventCount,
                    'keyEvents' => $keyEvents,
                    'sessionKeyEventRate' => number_format($sessionKeyEventRate, 2) . '%',
                    'totalRevenue' => number_format($totalRevenue, 2),
                ];
            }

            if ($totals['count'] > 0) {
                $totals['engagementRate'] = number_format($totals['engagementRate'] / $totals['count'], 2) . '%';
                $totals['screenPageViewsPerSession'] = number_format($totals['screenPageViewsPerSession'] / $totals['count'], 2);
                $totals['eventsPerSession'] = number_format($totals['eventsPerSession'] / $totals['count'], 2);
                $totals['sessionKeyEventRate'] = number_format($totals['sessionKeyEventRate'] / $totals['count'], 2) . '%';
                $totals['totalRevenue'] = number_format($totals['totalRevenue'], 2);
            }

            unset($totals['count']);

            array_unshift($trafficData, $totals);

            return response()->json([
                'success' => true,
                'data' => $trafficData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching traffic acquisition data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}
