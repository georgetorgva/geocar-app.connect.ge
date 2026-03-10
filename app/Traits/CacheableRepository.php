<?php

namespace App\Traits;

use Cache;
use Closure;

trait CacheableRepository
{
    public function remember(
        string $method,
        array $args,
        int $cacheTTL,
        Closure $saveCallback,
        array $additionalTags = []
    ) {
        $cacheKey = $this->getCacheKey($method, $args);
        return Cache::tags($this->buildCacheTags($method, $additionalTags))
            ->remember($cacheKey, $cacheTTL, $saveCallback);
    }

    public function rememberForever(
        string $method,
        array $args,
        Closure $saveCallback,
        array $additionalTags = []
    ) {
        $cacheKey = $this->getCacheKey($method, $args);
        return Cache::tags($this->buildCacheTags($method, $additionalTags))
            ->rememberForever($cacheKey, $saveCallback);
    }

    public function buildCacheTags(string $tag, array $additionalTags = []): array
    {
        $defaultTags = [ static::decorateCacheTag($tag) ];
        if (!empty($additionalTags)) {
            $mutatedAdditionalTags = collect($additionalTags)->map(function ($additionalTag) use ($tag) {
                return static::decorateCacheTag($tag, $additionalTag);
            })->toArray();
            $defaultTags = array_merge($defaultTags, $mutatedAdditionalTags);
        }
        return $defaultTags;
    }

    public static function decorateCacheTag($tag, $suffix = null)
    {
        $tagName = get_called_class() . '@' . $tag;

        if ($suffix) {
            $tagName = $tagName . '@' . $suffix;
        }

        return $tagName;
    }

    public function getCacheKey(string $method, $args = null): string
    {
        if (is_array($args)) {
            $args = array_sort_recursive($args);
        }
        $args = serialize($args);
        $key = sprintf('%s@%s-%s', get_called_class(), $method, sha1($args));
        return $key;
    }

    public function clearCacheForMethod($method)
    {
        return Cache::tags($this->getCacheTagForMethod($method))->flush();
    }

    public function clearCacheByTags(array $tags)
    {
        $tags = collect($tags);
        $tags->each(function ($tag) {
            $tag = static::decorateCacheTag($tag);
            Cache::tags($tag)->flush();
        });
    }
}
