<?php

namespace App\Services\Reviews;

use App\Common\Cache;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Cache\CacheItem;
use Symfony\Contracts\Cache\ItemInterface;

class CachedAPIReviewsService extends APIReviewsService
{
    /**
     * @throws InvalidArgumentException
     */
    public function get(int $hotelId): array
    {
        $item = Cache::get()->getItem('review_' . $hotelId);
        if ($item->get() === null) {
            $item->set(parent::get($hotelId));
            Cache::get()->save($item);
        }
        return $item->get();
    }
}
