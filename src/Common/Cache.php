<?php

namespace App\Common;

use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;

class Cache
{
    private static Cache $instance ;
    private AdapterInterface $cache;

    private function __construct ()
    {
        $redisConnection = RedisAdapter::createConnection('redis://redis');
        $this->cache = new RedisAdapter($redisConnection, 'hotel_', 0);
    }


    public static function get(): AdapterInterface
    {
        if (!isset(self::$instance)) {
            self::$instance = new Cache();
        }

        return self::$instance->cache;
    }
}