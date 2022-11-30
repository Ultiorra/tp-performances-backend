<?php

namespace App\Services\Hotel;

use App\Common\FilterException;
use App\Common\PDOSingleton;
use App\Common\SingletonTrait;
use App\Common\Timers;
use App\Entities\HotelEntity;
use App\Entities\RoomEntity;
use App\Services\Room\RoomService;
use Exception;
use PDO;

/**
 * Une classe utilitaire pour récupérer les données des magasins stockés en base de données
 */
class OneRequestHotelService extends AbstractHotelService{

    public function convertEntityFromArray(array $args): HotelEntity
    {

    }

    public function getDB(): PDO
    {

    }

    public function buildQuery(array $args): \PDOStatement
    {

    }
    public function list(array $args = []): array
    {
        // TODO: Implement list() method.
    }
}