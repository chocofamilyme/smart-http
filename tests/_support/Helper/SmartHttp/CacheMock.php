<?php
/**
 * @package Chocolife.me
 * @author  Kamet Aziza <kamet.a@chocolife.kz>
 */

namespace Helper\SmartHttp;

use Psr\SimpleCache\CacheInterface;

/**
 * Class CacheMock
 *
 * @package Helper\SmartHttp
 */
class CacheMock implements CacheInterface
{
    /**
     * @var array
     */
    private $data = [];

    /**
     * @var string
     */
    private $lastKey;

    public function get($keyName, $lifetime = null)
    {
        return $this->data[$keyName] ?? null;
    }

    public function set($keyName = null, $content = null, $lifetime = null, $stopBuffer = null)
    {
        $this->lastKey        = $keyName;
        $this->data[$keyName] = $content;
    }

    public function delete($keyName)
    {
        if (isset($this->data[$keyName])) {
            unset($this->data[$keyName]);
        }
    }

    public function has($keyName = null)
    {
        return isset($this->data[$keyName]);
    }

    public function clear()
    {
        $this->data = [];
    }

    public function getMultiple($keys, $default = null)
    {
        // TODO: Implement getMultiple() method.
    }

    public function setMultiple($values, $ttl = null)
    {
        // TODO: Implement setMultiple() method.
    }

    public function deleteMultiple($keys)
    {
        // TODO: Implement deleteMultiple() method.
    }
}
