<?php
/**
 * @package Chocolife.me
 * @author  Kamet Aziza <kamet.a@chocolife.kz>
 */

namespace Helper\SmartHttp;

class CacheMock implements \Phalcon\Cache\BackendInterface
{
    private $data = [];
    private $lastKey;

    public function start($keyName, $lifetime = null)
    {
        // TODO: Implement start() method.
    }

    public function stop($stopBuffer = null)
    {
        // TODO: Implement stop() method.
    }

    public function getFrontend()
    {
        // TODO: Implement getFrontend() method.
    }

    public function getOptions()
    {
        // TODO: Implement getOptions() method.
    }

    public function isFresh()
    {
        // TODO: Implement isFresh() method.
    }

    public function isStarted()
    {
        // TODO: Implement isStarted() method.
    }

    public function setLastKey($lastKey)
    {
        // TODO: Implement setLastKey() method.
    }

    public function getLastKey()
    {
        return $this->lastKey;
    }

    public function get($keyName, $lifetime = null)
    {
        if (!isset($this->data[$keyName])) {
            $this->save($keyName, 0);
        }

        return $this->data[$keyName];
    }

    public function save($keyName = null, $content = null, $lifetime = null, $stopBuffer = null)
    {
        $this->lastKey        = $keyName;
        $this->data[$keyName] = $content;
    }

    public function delete($keyName)
    {
        // TODO: Implement delete() method.
    }

    public function queryKeys($prefix = null)
    {
        // TODO: Implement queryKeys() method.
    }

    public function exists($keyName = null, $lifetime = null)
    {
        return array_key_exists($keyName, $this->data);
    }
}
