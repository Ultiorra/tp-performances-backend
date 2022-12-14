<?php

namespace App\Services\Hotel;

use App\Common\FilterException;
use App\Common\PDOSingleton;
use App\Common\SingletonTrait;
use App\Entities\HotelEntity;
use App\Entities\RoomEntity;
use App\Services\Reviews\APIReviewsService;
use App\Services\Room\RoomService;
use PDO;

class ReworkedHotelService extends OneRequestHotelService
{


    use SingletonTrait;
    private readonly APIReviewsService $reviewService;


    public function list(array $args = []): array
    {
        $stmt = $this->buildQuery($args);
        $VerifiedArgs=[];
        if ( isset( $args['lat'] ) && isset( $args['lng'] ) && isset( $args['distance'] ) ) {
            $VerifiedArgs[':lat'] = $args['lat'];
            $VerifiedArgs[':lng'] = $args['lng'];
            $VerifiedArgs[':dist'] = $args['distance'];
        }
        $stmt->execute($VerifiedArgs);

        foreach ( $stmt->fetchAll( PDO::FETCH_ASSOC ) as $row ) {
            try {
                $results[] = $this->convertEntityFromArray( $row, $args );

            } catch ( FilterException ) {
                // Des FilterException peuvent être déclenchées pour exclure certains hotels des résultats
            }
        }

        return $results ?? [];

    }
    protected function __construct () {
        parent::__construct( new RoomService() );
        $this->reviewService = new APIReviewsService('http://cheap-trusted-reviews.fake/');
    }

    public function buildQuery(array $args = []): \PDOStatement
    {
        $whereClause = [];
        if ( isset( $args['surface']['min'] )  )
            $whereClause[] = 'rooms.surface  >= ' . $args['surface']['min'];


        if ( isset( $args['surface']['max'] )  )
            $whereClause[] = 'rooms.surface  <= ' . $args['surface']['max'];


        if ( isset( $args['price']['min'] ) )
            $whereClause[] = 'rooms.price >= ' . $args['price']['min'];

        if ( isset( $args['price']['max'] ) )
            $whereClause[] = 'rooms.price<= ' . $args['price']['max'];

        if ( isset( $args['rooms'] )  )
            $whereClause[] = 'rooms.bedrooms   >= ' . $args['rooms'];

        if ( isset( $args['bathRooms'] ) )
            $whereClause[] = 'rooms.bathrooms  >= ' . $args['bathRooms'];


        $distanceWhereClause = '';
        if ( isset( $args['lat'] ) && isset( $args['lng'] ) && isset( $args['distance'] ) ) {
            $distanceWhereClause = '
            (111.111 * DEGREES(ACOS(LEAST(1.0, COS(RADIANS(CAST(hotels.geo_lat  AS DECIMAL(10, 6))))
                        * COS(RADIANS(CAST(:lat AS DECIMAL(10, 6))))
            * COS(RADIANS(CAST( hotels.geo_lng    AS DECIMAL(10, 6)) - CAST(:lng AS DECIMAL(10, 6))))
                        + SIN(RADIANS(CAST(hotels.geo_lat  AS DECIMAL(10, 6))))
                        * SIN(RADIANS(CAST(:lat AS DECIMAL(10, 6))))))) <= CAST(:dist AS DECIMAL(10, 6))) ';
        }
        if ( isset( $args['types'] ) && ! empty( $args['types'] )  )
            $whereClause[] = 'type IN ("' . implode( '","', $args['types'] ) . '")';


        $SqlQuery = '
        SELECT
            hotels.id              as hotelId,
            hotels.name            as hotelName,
            hotels.address_1       as hotel_address_1,
            hotels.address_2       as hotel_address_2,
            hotels.address_city    as hotel_address_city,
            hotels.address_zipcode as hotel_address_zip,
            hotels.address_country as hotel_address_country,
            hotels.geo_lat         as geo_lat,
            hotels.geo_lng         as geo_lng,
            hotels.image_url       as hotel_image_url,
            hotels.phone           as hotel_phone,
            COUNT(DISTINCT reviews.id)      as reviewCount,
            AVG(reviews.review)    as reviewMoy,
            rooms.id               as cheapestRoomId,
            rooms.title            as title,
            rooms.bathrooms        as bathroom,
            rooms.bedrooms         as bedroom,
            rooms.image            as room_image_url,
            rooms.surface          as surface,
            rooms.type             as type,
            MIN(rooms.price)       as price
            FROM hotels
                INNER JOIN rooms ON rooms.id_hotel = hotels.id ' . ( ! empty( $whereClause ) ? ' AND ' . implode( ' AND ', $whereClause ) : '' ) .'
                INNER JOIN reviews ON reviews.id_hotel = hotels.id
                 ' . ( ! empty( $distanceWhereClause ) ? ' WHERE ' . $distanceWhereClause : '' ) . ' GROUP BY hotels.id, rooms.id';

        $stmt = $this->getDB()->prepare( $SqlQuery );
        return $stmt;

    }
    public function convertEntityFromArray(array $args): HotelEntity
    {
        $reviews = $this->reviewService->get($args['hotelId']);
        $hotel = parent::convertEntityFromArray( $args )
            ->setRating( (int) $reviews['data']['rating'] )
            ->setRatingCount( (int) $reviews['data']['count'] );
        return $hotel;

    }

}
