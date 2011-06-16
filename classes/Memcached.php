<?php

class Memcached
{
    // i just want to make sure....
    function Memcached()
    {
        trigger_error('The class "memcached" may only be invoked statically.', E_USER_ERROR);
    }

    protected static function getMC()
    {
        global $mc, $memcacheServer, $memcachePort;

        if ($mc == null) {
            $mc = new MemCache();
            $mc->connect($memcacheServer, $memcachePort);
        }
        return $mc;
    }

    // Define 1 hour std expiry time for objects
    public static function set($key, $value, $timeout = '3600')
    {
        $mc = Memcached::getMC();
        $result = $mc->replace($key, $value, 0, time() + $timeout);
        if ($result === FALSE)
            $result = $mc->set($key, $value, 0, time() + $timeout);
        if ($result !== TRUE && $result !== FALSE)
            return false;
        return true;
    }

    public static function get($key)
    {
        $mc = Memcached::getMC();
        return $mc->get($key);
    }

    // Erases a key after [$timeout] seconds
    // if that key exists
    public static function delete($key, $timeout = 0)
    {
        $mc = Memcached::getMC();
        return $mc->delete($key, $timeout);
    }

    public static function increment($key, $timeout = 3600)
    {
        $mc = Memcached::getMC();
        $mc->add($key, 0, 0, $timeout);
        return $mc->increment($key);
    }

    public static function decrement($key, $timeout = 3600)
    {
        $mc = Memcached::getMC();
        $mc->add($key, 0, 0, $timeout);
        return $mc->decrement($key);
    }
}
