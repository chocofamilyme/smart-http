<?php
/**
 * @package Chocolife.me
 * @author  Kamet Aziza <kamet.a@chocolife.kz>
 */
namespace Chocofamily\SmartHttp\Storage;

use Ejsmont\CircuitBreaker\Storage\Adapter\BaseAdapter;
use Ejsmont\CircuitBreaker\Storage\StorageException;

class PhalconCacheAdapter extends BaseAdapter
{
    private $cache;

    public function __construct(\Phalcon\Cache\BackendInterface $cache, int $ttl = 3600, string $cachePrefix = '')
    {
        parent::__construct($ttl, $cachePrefix);
        $this->cache = $cache;
    }

    protected function checkExtension()
    {
    }

    protected function load($key)
    {
        try {
            return $this->cache->get($this->getKey($key));
        } catch (\Exception $e) {
            throw new StorageException("Failed to load cache key: $key", 1, $e);
        }
    }

    protected function save($key, $value, $ttl)
    {
        try {
            $this->cache->save($this->getKey($key), $value, $ttl);
        } catch (\Exception $e) {
            throw new StorageException("Failed to save cache key: $key", 1, $e);
        }
    }

    private function getKey($key)
    {
        return md5($this->cachePrefix.$key);
    }

}
