<?php

namespace App\Services\GoogleAnalytics;

use App\Models\Admin\OptionsModel;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\RunRealtimeReportRequest;

class RealTimeService extends BaseAnalyticsService
{
    public function getRealTime()
    {
        $options = new OptionsModel();
        $propertyId = $options->getByKey('ga_property_id');

        try {

            $request = new RunRealtimeReportRequest([
                'property' => "properties/{$propertyId}",
                'dimensions' => [
                    new Dimension(['name' => 'minutesAgo']),
                    new Dimension(['name' => 'country'])
                ],
                'metrics' => [
                    new Metric(['name' => 'activeUsers'])
                ],
            ]);

            $response = $this->client->runRealtimeReport($request);

            $rows = $response->getRows();
            $activeUsersPerMinute = [];
            $totalActiveUsersLast30Minutes = 0;

            foreach ($rows as $row) {
                $minute = $row->getDimensionValues()[0]->getValue();
                $country = $row->getDimensionValues()[1]->getValue();
                $activeUsers = (int)$row->getMetricValues()[0]->getValue();

                if (!isset($activeUsersPerMinute[$minute])) {
                    $activeUsersPerMinute[$minute] = [];
                }
                $activeUsersPerMinute[$minute][$country] = $activeUsers;

                $totalActiveUsersLast30Minutes += $activeUsers;
            }

            return response()->json([
                'success' => true,
                'totalActiveUsersLast30Minutes' => $totalActiveUsersLast30Minutes,
                'activeUsersPerMinute' => $activeUsersPerMinute,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching real-time data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}
