<?php

namespace App\Http\Transformers;

use App\Models\Media\Media;
use League\Fractal\TransformerAbstract;

class MediaTransformer extends TransformerAbstract
{

    public function transform(Media $media)
    {
        return $media->transform();
    }
}
