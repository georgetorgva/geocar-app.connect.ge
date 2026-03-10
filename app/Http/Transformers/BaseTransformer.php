<?php

namespace App\Http\Transformers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use League\Fractal\TransformerAbstract;

class BaseTransformer extends TransformerAbstract
{
    /**
     * @param array $data
     *
     * @return array
     */
    public function transform($data)
    {
        if (isset($data['relations'])) {
            $relations = $data['relations'];
            $this->defaultIncludes = array_keys($relations);
        }

        return $this->createTranformedOutputData($data);
    }

    public function __call($name, $arguments)
    {
        $relation = strtolower(str_replace('include', '', $name));
        $item = $arguments[0]->$relation;
        if (is_null($item)) {
            return null;
        }
        return $this->item($item, new BaseTransformer());
    }

    protected function createTranformedOutputData($data, $output = null)
    {
        if (!$output) {
            $output = [];
        }

        if ($data instanceof Collection) {
            return $data->toArray();
        } elseif ($data instanceof Model) {
            return $data->toArray();
        }
        return $output;
    }
}
