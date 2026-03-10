<?php

namespace App\Http\Transformers;

use App\Models\Address\Options;
use League\Fractal\TransformerAbstract;

class AddressTransformer extends TransformerAbstract
{

    protected $availableIncludes = [
        'city','country',
    ];

    public function transform(Options $address)
    {
        return $address->transform();
    }

    public function includeCity(Options $address)
    {
        $city = $address->city;
        return $this->item($city, new CityTransformer);
    }

    public function includeCountry(Options $address)
    {
        $country = $address->country;
        return $this->item($country, new CountryTransformer);
    }
}
