<?php

namespace App\Http\Controllers\Services;

use App;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class Idx extends App\Http\Controllers\Api\ApiController {

    public function getStockData(Request $request)
    {
        $stockApiUrls = [
            'cgeo_stock' => 'http://ir.tools.investis.com/Clients/uk/georgia_capital/xml/xml.aspx',
            'bgeo_stock' => 'https://irs.tools.investis.com/Clients/uk/bank_of_georgia_holdings/xml/xml.aspx'
        ];

        $response = [];

        foreach ($stockApiUrls as $cacheKey => $serviceUrl)
        {
            $response[$cacheKey] = Cache::has($cacheKey) ? Cache::get($cacheKey) : $this->requestStockData($serviceUrl, $cacheKey, 300);
        }

        return $response;
    }

    private function requestStockData($serviceUrl, $cacheKey, $cacheLifetime)
    {
        $response = [
            'api_output' => [],
            'success' => false,
            'error' => ''
        ];

        try
        {
            $apiResponse = Http::get($serviceUrl);

            if ($apiResponse->successful())
            {
                $xmlString = $apiResponse->body();

                $parsedXmlData = simplexml_load_string($xmlString);

                $response['api_output'] = json_decode(json_encode($parsedXmlData), true);

                $fieldsToTrim = ['Currency', 'Name', 'Change'];

                foreach ($fieldsToTrim as $field)
                {
                    if (isset($response['api_output'][$field]) && is_string($response['api_output'][$field]))
                    {
                        $response['api_output'][$field] = trim($response['api_output'][$field]);
                    }
                }

                $response['success'] = true;

                Cache::store('file')->put($cacheKey, $response, $cacheLifetime);
            }

            else
            {
                $response['error'] = 'api error';
            }
        }

        catch (\Exception $exception)
        {
            $response['error'] = $exception->getMessage();
        }

        return $response;
    }
}
