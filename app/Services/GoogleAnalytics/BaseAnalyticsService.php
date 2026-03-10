<?php

namespace App\Services\GoogleAnalytics;
use App\Models\Admin\OptionsModel;
use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;

class BaseAnalyticsService
{
    protected $client;

    public function __construct()
    {
        $options = new OptionsModel();
        $credentialsJson = $options->getByKey('google_application_credentials');

        $credentials = json_decode($credentialsJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON format for Google credentials');
        }

        $this->client = new BetaAnalyticsDataClient([
            'credentials' => $credentials,
        ]);
    }
}
