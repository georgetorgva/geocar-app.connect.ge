<?php

namespace App\Services\GoogleAnalytics;

use App\Models\Admin\OptionsModel;
use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\RunReportRequest;

class HomeReportsService extends BaseAnalyticsService
{
    public function getHomeReports($params): array
    {

        $startDate = $params['start_date'] ?? '7daysAgo';

        $options = new OptionsModel();
        $propertyId = $options->getByKey('ga_property_id');

        $dateRequest = (new RunReportRequest())
            ->setProperty('properties/' . $propertyId)
            ->setDimensions([
                new Dimension(['name' => 'date']),
            ])
            ->setMetrics([
                new Metric(['name' => 'activeUsers']),
                new Metric(['name' => 'newUsers']),
                new Metric(['name' => 'sessions']),
                new Metric(['name' => 'keyEvents']),
            ])
            ->setDateRanges([
                new DateRange(['start_date' => $startDate, 'end_date' => 'today']),
            ]);

        $dateResponse = $this->client->runReport($dateRequest);

        $dateAnalyticsData = [];
        foreach ($dateResponse->getRows() as $row) {
            $dataRow = [];
            foreach ($row->getDimensionValues() as $dimension) {
                $dataRow['date'] = $dimension->getValue();
            }
            foreach ($row->getMetricValues() as $index => $metric) {
                $dataRow['metrics'][$index] = $metric->getValue();
            }
            $dateAnalyticsData[] = $dataRow;
        }

        $totalRequest = (new RunReportRequest())
            ->setProperty('properties/' . $propertyId)
            ->setMetrics([
                new Metric(['name' => 'activeUsers']),
                new Metric(['name' => 'newUsers']),
                new Metric(['name' => 'sessions']),
                new Metric(['name' => 'keyEvents']),
            ])
            ->setDateRanges([
                new DateRange(['start_date' => $startDate, 'end_date' => 'today']),
            ]);

        $totalResponse = $this->client->runReport($totalRequest);

        $totalAnalyticsData = [];
        foreach ($totalResponse->getRows() as $row) {
            foreach ($row->getMetricValues() as $index => $metric) {
                $totalAnalyticsData[$index] = $metric->getValue();
            }
        }

        $analyticsData = [
            'total' => $totalAnalyticsData,
            'byDate' => $dateAnalyticsData,
        ];

        return $analyticsData;
    }

}
