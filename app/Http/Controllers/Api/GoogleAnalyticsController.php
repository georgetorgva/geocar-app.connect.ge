<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GoogleAnalytics\GoogleAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class GoogleAnalyticsController extends Controller
{
    private GoogleAnalyticsService $analyticsService;

    public function __construct(GoogleAnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    public function index(Request $request) : JsonResponse
    {
        $homeReports = $this->analyticsService->homeReports->getHomeReports($request);
        return response()->json($homeReports);
    }

    public function realtime() : JsonResponse
    {
        $realTimeData = $this->analyticsService->realTime->getRealTime();
        return response()->json($realTimeData);
    }

    public function trafficAcquisition(Request $request) : JsonResponse
    {
        $trafficAcquisition = $this->analyticsService->trafficAcquisition->getTrafficAcquisition($request);
        return response()->json($trafficAcquisition);
    }

    public function events(Request $request) : JsonResponse
    {
        $events = $this->analyticsService->events->getEvents($request);
        return response()->json($events);
    }

    public function pages(Request $request) : JsonResponse
    {
        $pages = $this->analyticsService->pages->getPages($request);
        return response()->json($pages);
    }
}