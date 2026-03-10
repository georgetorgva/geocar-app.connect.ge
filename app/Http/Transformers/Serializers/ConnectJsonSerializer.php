<?php

namespace App\Http\Transformers\Serializers;

use InvalidArgumentException;
use League\Fractal\Pagination\PaginatorInterface;
use League\Fractal\Resource\ResourceInterface;
use Spatie\Fractalistic\ArraySerializer;

class ConnectJsonSerializer extends ArraySerializer
{
    protected $baseUrl;
    protected $rootObjects;

    /**
     * JsonApiSerializer constructor.
     *
     * @param string $baseUrl
     */
    public function __construct($baseUrl = null)
    {
        $this->baseUrl = $baseUrl;
        $this->rootObjects = [];
    }

    /**
     * Serialize a collection.
     *
     * @param string $resourceKey
     * @param array $data
     *
     * @return array
     */
    public function collection($resourceKey, array $data)
    {
        $resources = [];

        foreach ($data as $key => $resource) {
            $resourceArrayKey = isset($resource['__json_options']['__key']) ? $resource['__json_options']['__key'] : $key;
            $resources[$resourceArrayKey] = $this->item($resourceKey, $resource)['data'];
        }

        return ['data' => $resources];
    }

    public function mergeIncludes($transformedData, $includedData)
    {
        // If the serializer does not want the includes to be side-loaded then
        // the included data must be merged with the transformed data.
        if (! $this->sideloadIncludes()) {
            if (!isset($transformedData['relationships'])) {
                $transformedData['relationships'] = [];
            }
            $transformedData['relationships'] = array_merge($transformedData['relationships'], $includedData);
            //return array_merge($transformedData['relationships'], $includedData);
        }
        if (empty($transformedData['relationships'])) {
            $transformedData['relationships'] = new \stdClass;
        }
        return $transformedData;
    }

    /**
     * Serialize an item.
     *
     * @param string $resourceKey
     * @param array $data
     *
     * @return array
     */
    public function item($resourceKey, array $data)
    {
        $json_options = isset($data['__json_options']) ? $data['__json_options'] : [];
        $id = $this->getIdFromData($data);
        $resource = [
            'data' => [
                'type' => isset($json_options['__type']) ? $json_options['__type'] : $resourceKey,
                'id' => $id,
                'attributes' => empty($data) ? [] : $data,
            ],
        ];

       

        unset($resource['data']['attributes']['id']);
        unset($resource['data']['attributes']['__json_options']);

        //dump($resource);

        if (isset($resource['data']['attributes']['relationships'])) {
            $relationships = $data['relationships'];
            $resource['data']['relationships'] = $relationships;
            unset($resource['data']['attributes']['relationships']);
        }

        if (isset($resource['data']['attributes']['links'])) {
            $custom_links = $data['links'];
            unset($resource['data']['attributes']['links']);
        }

        if (isset($resource['data']['attributes']['meta'])) {
            $resource['data']['meta'] = $data['meta'];
            unset($resource['data']['attributes']['meta']);
        }

        if ($this->shouldIncludeLinks()) {
            $resource['data']['links'] = [
                'self' => "{$this->baseUrl}/$resourceKey/$id",
            ];
            if (isset($custom_links)) {
                $resource['data']['links'] = array_merge($custom_links, $resource['data']['links']);
            }
        }
        if (empty($resource['data']['attributes']) && is_array($resource['data']['attributes'])) {
            $resource['data']['attributes'] = new \stdClass;
        }
        return $resource;
    }



    /**
     * Serialize the paginator.
     *
     * @param PaginatorInterface $paginator
     *
     * @return array
     */
    public function paginator(PaginatorInterface $paginator)
    {
        $pagination = [
            'total' => (int) $paginator->getTotal(),
            'count' => (int) $paginator->getCount(),
            'limit' => (int) $paginator->getPerPage(),
            'offset' => (int) $paginator->getCurrentPage()
        ];

        return ['pagination' => $pagination];
    }

    /**
     * Serialize the meta.
     *
     * @param array $meta
     *
     * @return array
     */
    public function meta(array $meta)
    {
        $result = [];
        if (!empty($meta)) {
            $result['meta'] = $meta;
        }
        return $result;
    }

    /**
     * @return array
     */
    public function null()
    {
        return [
            'data' => null,
        ];
    }

    /**
     * Serialize the included data.
     *
     * @param ResourceInterface $resource
     * @param array $data
     *
     * @return array
     */
    public function includedData(ResourceInterface $resource, array $data)
    {
        list($serializedData, $linkedIds) = $this->pullOutNestedIncludedData($data);

        foreach ($data as $value) {
            foreach ($value as $includeObject) {
                if ($this->isNull($includeObject) || $this->isEmpty($includeObject)) {
                    continue;
                }

                $includeObjects = $this->createIncludeObjects($includeObject);
                list($serializedData, $linkedIds) = $this->serializeIncludedObjectsWithCacheKey($includeObjects, $linkedIds, $serializedData);
            }
        }

        return empty($serializedData) ? [] : ['included' => $serializedData];
    }

    /**
     * Indicates if includes should be side-loaded.
     *
     * @return bool
     */
    public function sideloadIncludes()
    {
        return false;
    }

    /**
     * @param array $data
     * @param array $includedData
     *
     * @return array
     */
    public function injectData($data, $includedData)
    {
        $relationships = $this->parseRelationships($includedData);

        if (!empty($relationships)) {
            $data = $this->fillRelationships($data, $relationships);
        }

        return $data;
    }

    /**
     * Hook to manipulate the final sideloaded includes.
     * The JSON API specification does not allow the root object to be included
     * into the sideloaded `included`-array. We have to make sure it is
     * filtered out, in case some object links to the root object in a
     * relationship.
     *
     * @param array $includedData
     * @param array $data
     *
     * @return array
     */
    public function filterIncludes($includedData, $data)
    {
        if (!isset($includedData['included'])) {
            return $includedData;
        }

        // Create the RootObjects
        $this->createRootObjects($data);

        // Filter out the root objects
        $filteredIncludes = array_filter($includedData['included'], [$this, 'filterRootObject']);

        // Reset array indizes
        $includedData['included'] = array_merge([], $filteredIncludes);

        return $includedData;
    }

    /**
     * Get the mandatory fields for the serializer
     *
     * @return array
     */
    public function getMandatoryFields()
    {
        return ['id'];
    }

    /**
     * Filter function to delete root objects from array.
     *
     * @param array $object
     *
     * @return bool
     */
    protected function filterRootObject($object)
    {
        return !$this->isRootObject($object);
    }

    /**
     * Set the root objects of the JSON API tree.
     *
     * @param array $objects
     */
    protected function setRootObjects(array $objects = [])
    {
        $this->rootObjects = array_map(function ($object) {
            return "{$object['type']}:{$object['id']}";
        }, $objects);
    }

    /**
     * Determines whether an object is a root object of the JSON API tree.
     *
     * @param array $object
     *
     * @return bool
     */
    protected function isRootObject($object)
    {
        $objectKey = "{$object['type']}:{$object['id']}";
        return in_array($objectKey, $this->rootObjects);
    }

    /**
     * @param array $data
     *
     * @return bool
     */
    protected function isCollection($data)
    {
        return array_key_exists('data', $data) &&
        array_key_exists(0, $data['data']);
    }

    /**
     * @param array $data
     *
     * @return bool
     */
    protected function isNull($data)
    {
        return array_key_exists('data', $data) && $data['data'] === null;
    }

    /**
     * @param array $data
     *
     * @return bool
     */
    protected function isEmpty($data)
    {
        return array_key_exists('data', $data) && $data['data'] === [];
    }

    /**
     * @param array $data
     * @param array $relationships
     *
     * @return array
     */
    protected function fillRelationships($data, $relationships)
    {
        if ($this->isCollection($data)) {
            foreach ($relationships as $key => $relationship) {
                $data = $this->fillRelationshipAsCollection($data, $relationship, $key);
            }
        } else { // Single resource
            foreach ($relationships as $key => $relationship) {
                $data = $this->fillRelationshipAsSingleResource($data, $relationship, $key);
            }
        }

        return $data;
    }

    /**
     * @param array $includedData
     *
     * @return array
     */
    protected function parseRelationships($includedData)
    {
        $relationships = [];

        foreach ($includedData as $key => $inclusion) {
            foreach ($inclusion as $includeKey => $includeObject) {
                $relationships = $this->buildRelationships($includeKey, $relationships, $includeObject, $key);
            }
        }

        return $relationships;
    }

    /**
     * @param array $data
     *
     * @return integer
     */
    protected function getIdFromData(array $data)
    {
        if (!isset($data['id'])) {
            return null;
        }

        return $data['id'];
    }

    /**
     * Keep all sideloaded inclusion data on the top level.
     *
     * @param array $data
     *
     * @return array
     */
    protected function pullOutNestedIncludedData(array $data)
    {
        $includedData = [];
        $linkedIds = [];

        foreach ($data as $value) {
            foreach ($value as $includeObject) {
                if (isset($includeObject['included'])) {
                    list($includedData, $linkedIds) = $this->serializeIncludedObjectsWithCacheKey($includeObject['included'], $linkedIds, $includedData);
                }
            }
        }

        return [$includedData, $linkedIds];
    }

    /**
     * Whether or not the serializer should include `links` for resource objects.
     *
     * @return bool
     */
    protected function shouldIncludeLinks()
    {
        return false;
    }

    /**
     * Check if the objects are part of a collection or not
     *
     * @param $includeObject
     *
     * @return array
     */
    private function createIncludeObjects($includeObject)
    {
        if ($this->isCollection($includeObject)) {
            $includeObjects = $includeObject['data'];

            return $includeObjects;
        } else {
            $includeObjects = [$includeObject['data']];

            return $includeObjects;
        }
    }

    /**
     * Sets the RootObjects, either as collection or not.
     *
     * @param $data
     */
    private function createRootObjects($data)
    {
        if ($this->isCollection($data)) {
            $this->setRootObjects($data['data']);
        } else {
            $this->setRootObjects([$data['data']]);
        }
    }


    /**
     * Loops over the relationships of the provided data and formats it
     *
     * @param $data
     * @param $relationship
     * @param $key
     *
     * @return array
     */
    private function fillRelationshipAsCollection($data, $relationship, $key)
    {
        foreach ($relationship as $index => $relationshipData) {
            $data['data'][$index]['relationships'][$key] = $relationshipData;

            if ($this->shouldIncludeLinks()) {
                $data['data'][$index]['relationships'][$key] = array_merge([
                    'links' => [
                        'self' => "{$this->baseUrl}/{$data['data'][$index]['type']}/{$data['data'][$index]['id']}/relationships/$key",
                        'related' => "{$this->baseUrl}/{$data['data'][$index]['type']}/{$data['data'][$index]['id']}/$key",
                    ],
                ], $data['data'][$index]['relationships'][$key]);
            }
        }

        return $data;
    }


    /**
     * @param $data
     * @param $relationship
     * @param $key
     *
     * @return array
     */
    private function fillRelationshipAsSingleResource($data, $relationship, $key)
    {
        $data['data']['relationships'][$key] = $relationship[0];

        if ($this->shouldIncludeLinks()) {
            $data['data']['relationships'][$key] = array_merge([
                'links' => [
                    'self' => "{$this->baseUrl}/{$data['data']['type']}/{$data['data']['id']}/relationships/$key",
                    'related' => "{$this->baseUrl}/{$data['data']['type']}/{$data['data']['id']}/$key",
                ],
            ], $data['data']['relationships'][$key]);

            return $data;
        }
        return $data;
    }

    /**
     * @param $includeKey
     * @param $relationships
     * @param $includeObject
     * @param $key
     *
     * @return array
     */
    private function buildRelationships($includeKey, $relationships, $includeObject, $key)
    {
        $relationships = $this->addIncludekeyToRelationsIfNotSet($includeKey, $relationships);

        if ($this->isNull($includeObject)) {
            $relationship = $this->null();
        } elseif ($this->isEmpty($includeObject)) {
            $relationship = [
                'data' => [],
            ];
        } elseif ($this->isCollection($includeObject)) {
            $relationship = ['data' => []];

            $relationship = $this->addIncludedDataToRelationship($includeObject, $relationship);
        } else {
            $relationship = [
                'data' => [
                    'type' => $includeObject['data']['type'],
                    'id' => $includeObject['data']['id'],
                ],
            ];
        }

        $relationships[$includeKey][$key] = $relationship;

        return $relationships;
    }

    /**
     * @param $includeKey
     * @param $relationships
     *
     * @return array
     */
    private function addIncludekeyToRelationsIfNotSet($includeKey, $relationships)
    {
        if (!array_key_exists($includeKey, $relationships)) {
            $relationships[$includeKey] = [];
            return $relationships;
        }

        return $relationships;
    }

    /**
     * @param $includeObject
     * @param $relationship
     *
     * @return array
     */
    private function addIncludedDataToRelationship($includeObject, $relationship)
    {
        foreach ($includeObject['data'] as $object) {
            $relationship['data'][] = [
                'type' => $object['type'],
                'id' => $object['id'],
            ];
        }

        return $relationship;
    }

    /**
     * @param $includeObjects
     * @param $linkedIds
     * @param $serializedData
     *
     * @return array
     */
    private function serializeIncludedObjectsWithCacheKey($includeObjects, $linkedIds, $serializedData)
    {
        foreach ($includeObjects as $object) {
            $includeType = $object['type'];
            $includeId = $object['id'];
            $cacheKey = "$includeType:$includeId";
            if (!array_key_exists($cacheKey, $linkedIds)) {
                $serializedData[] = $object;
                $linkedIds[$cacheKey] = $object;
            }
        }
        return [$serializedData, $linkedIds];
    }
}
