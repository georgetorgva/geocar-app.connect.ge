<?php

namespace App\Http\Transformers;

use App\Models\User\User;
use League\Fractal\TransformerAbstract;

class UserTransformer extends TransformerAbstract
{

    protected $availableIncludes = [
        'avatar',
    ];

    public function transform(User $user)
    {
        return $user->transform();
    }

    public function includeAvatar(User $user)
    {
        $media = $user->getFirstMedia('avatars');
        return $this->item($media, new MediaTransformer);
    }
}
