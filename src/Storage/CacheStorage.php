<?php
/**
 * @package Chocolife.me
 * @author  Kamet Aziza <kamet.a@chocolife.kz>
 */

namespace Chocofamily\SmartHttp\Storage;

use Exception;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Ejsmont\CircuitBreaker\Storage\Adapter\BaseAdapter;
use Ejsmont\CircuitBreaker\Storage\StorageException;

/**
 * Class CacheStorage
 *
 * @package Chocofamily\SmartHttp\Storage
 */
class CacheStorage extends BaseAdapter
{
    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * CacheStorage constructor.
     *
     * @param CacheInterface $cache
     * @param int            $ttl
     * @param string         $cachePrefix
     */
    public function __construct(CacheInterface $cache, int $ttl = 3600, string $cachePrefix = '')
    {
        parent::__construct($ttl, $cachePrefix);
        $this->cache = $cache;
    }

    protected function checkExtension()
    {
    }

    /**
     * @param string $key
     *
     * @return mixed
     * @throws StorageException
     * @throws InvalidArgumentException
     */
    protected function load($key)
    {
        try {
            return $this->cache->get($this->getKey($key));
        } catch (Exception $e) {
            throw new StorageException("Failed to load cache key: $key", 1, $e);
        }
    }

    /**
     * @param string $key
     * @param string $value
     * @param int    $ttl
     *
     * @throws StorageException
     * @throws InvalidArgumentException
     */
    protected function save($key, $value, $ttl)
    {
        try {
            $this->cache->set($this->getKey($key), $value, $ttl);
        } catch (Exception $e) {
            throw new StorageException("Failed to save cache key: $key", 1, $e);
        }
    }

    private function getKey($key)
    {
        return $this->cachePrefix.'_'.$key;
    }
}
