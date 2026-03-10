<?php

namespace App\Services\GoogleAnalytics;

class GoogleAnalyticsService
{
    public HomeReportsService $homeReports;
    public RealTimeService $realTime;
    public TrafficAcquisitionService $trafficAcquisition;

    public EventsServices $events;

    public PagesServices $pages;

    public function __construct(
        HomeReportsService $homeReportsService,
        RealTimeService $realTimeService,
        TrafficAcquisitionService $trafficAcquisitionService,
        EventsServices $eventsServices,
        PagesServices $pagesServices
    ) {
        $this->homeReports = $homeReportsService;
        $this->realTime = $realTimeService;
        $this->trafficAcquisition = $trafficAcquisitionService;
        $this->events = $eventsServices;
        $this->pages = $pagesServices;
    }
}
