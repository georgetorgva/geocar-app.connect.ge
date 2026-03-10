<?php

namespace App\Traits;

trait ConnectTransformatorTrait
{

    public function transform($includeRelations = false): array
    {
        $fields = $this->transformFields;
        if (!isset($fields)) {
            $attributes = $this->toArray();
            foreach ($attributes as $k => $field) {
                $fields[$k] = $k;
            }
        }
        $transformed = [];

        $properties = $this->toArray();
        $hiddenProperties = $this->getHidden();

        foreach ($this->getArrayableAppends() as $key => $value) {
            $fields[$key] = $value;
        }

        foreach ($properties as $key => $property) {
            if (isset($fields[$key])) {
                $transformed[$fields[$key]] = $property;
                unset($fields[$key]);
            }
        }

        if (!empty($fields)) {
            foreach ($fields as $key => $field) {
                if (in_array($key, $hiddenProperties)) {
                    continue;
                }
                $transformed[$field] = $this->$field;
            }
        }

        $transformed['__json_options']['__type'] = class_basename(get_class($this));

        return $transformed;
    }

    public function getTransformFields(): array
    {
        return $this->transformFields;
    }

    public static function transformPostFields(array $postFields): array
    {
        $model = new static();
        $modelTransformedFields = array_flip($model->getTransformFields());

        $transformed = [];
        foreach ($postFields as $fieldKey => $postValue) {
            if (isset($modelTransformedFields[$fieldKey])) {
                $transformed[$modelTransformedFields[$fieldKey]] = $postValue;
            }
        }
        return $transformed;
    }
}
