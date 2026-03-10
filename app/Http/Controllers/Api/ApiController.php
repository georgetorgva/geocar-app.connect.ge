<?php

namespace App\Http\Controllers\Api;

use Illuminate\Routing\Controller;

class ApiController extends Controller
{

    public function response($data, $transformer)
    {
        if (is_null($transformer)) {
            return fractal($data, new BaseTransformer());
        }

        return fractal($data, $transformer);
    }
}
