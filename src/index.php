<?php

namespace App;

const __PROJECT_ROOT__  = __DIR__;

use App\Common\Cache;
use App\Controllers\Hotel\HotelListController;
use App\Services\Hotel\OneRequestHotelService;
use App\Services\Hotel\ReworkedHotelService;
use App\Services\Hotel\UnoptimizedHotelService;

require_once __DIR__ . "/vendor/autoload.php";


//$hotelService = OneRequestHotelService::getInstance();

$hotelService = ReworkedHotelService::getInstance();

//$hotelService = UnoptimizedHotelService::getInstance();

$controller = new HotelListController( $hotelService );
$controller->render();
