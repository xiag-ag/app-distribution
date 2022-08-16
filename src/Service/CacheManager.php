<?php

namespace AppDistributionTool\Service;

/**
 * Class CacheManager
 * @package AppDistributionTool\Service
 */
class CacheManager
{
    /**
     * @var string
     */
    protected $cachePath;

    /**
     * CacheManager constructor.
     * @param string $cacheDirectory
     */
    public function __construct(string $cacheDirectory)
    {
        $this->cachePath = rtrim($cacheDirectory, '/') . '/apps.json';
    }

    /**
     * @return bool
     */
    public function checkCacheExpired(): bool
    {
        $oneDay = time() - 24 * 60 * 60;

        return !file_exists($this->cachePath) || (filemtime($this->cachePath) < $oneDay);
    }

    /**
     * @param mixed $cacheContent
     *
     * @return false|int
     *
     * @throws \JsonException
     */
    public function updateCache($cacheContent)
    {
        return file_put_contents($this->cachePath, json_encode($cacheContent, JSON_THROW_ON_ERROR, 512));
    }

    /**
     * @return false|string
     *
     * @throws \JsonException
     */
    public function getCacheContent()
    {
        return json_decode(file_get_contents($this->cachePath), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return bool
     */
    public function removeCache(): bool
    {
        return !file_exists($this->cachePath) || unlink($this->cachePath);
    }
}
