<?php

namespace App\Common;

use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;

class Cache
{
    private static Cache $instance ;
    private AdapterInterface $cache;

    private function __construct ()
    {
        if(isset($_GET['skip_cache'])){
            $this->cache = new NullAdapter();
        }else{

            $this->cache = new RedisAdapter(RedisAdapter::createConnection('redis://redis'), 'hotel_', 0);
            if (isset($_GET['clear_cache'])) {
                $this->cache->clear();
            }

        }
    }


    public static function get(): AdapterInterface
    {
        if (!isset(self::$instance)) {
            self::$instance = new Cache();
        }

        return self::$instance->cache;
    }
}