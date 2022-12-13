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

    use SingletonTrait;
    public function convertEntityFromArray(array $args): HotelEntity
    {
        $hotel = new HotelEntity();
        $hotel->setId($args['hotelId']);
        $hotel->setName($args['hotelName']);
        $hotel->setAddress([
            'address_1' => $args['hotel_address_1'],
            'address_2' => $args['hotel_address_2'],
            'address_city' => $args['hotel_address_city'],
            'address_zip' => $args['hotel_address_zip'],
            'address_country' => $args['hotel_address_country'],
        ]);
        $hotel->setGeoLat($args['geo_lat']);
        $hotel->setGeoLng($args['geo_lng']);
        $hotel->setRatingCount($args['reviewCount']);
        $hotel->setRating((int) $args['reviewMoy']);
        $hotel->setImageUrl($args['hotel_image_url']);
        $hotel->setPhone($args['hotel_phone']);
        $hotel->setCheapestRoom(
            (new RoomEntity())
                ->setId($args['cheapestRoomId'])
                ->setPrice($args['price'])
                ->setSurface($args['surface'])
                ->setBedRoomsCount($args['bedroom'])
                ->setBathRoomsCount($args['bathroom'])
                ->setTitle($args['title'])
                ->setCoverImageUrl($args['room_image_url'])
                ->setType($args['type'])

        );
        return $hotel;

    }

    protected function __construct () {
        parent::__construct( new RoomService() );
    }
    public function getDB(): PDO
    {
        $pdo = PDOSingleton::getInstance();
        return $pdo;
    }

    public function buildQuery(array $args): \PDOStatement
    {
        $whereClause = [];
        if ( isset( $args['surface']['min'] )  )
            $whereClause[] = 'surface >= ' . $args['surface']['min'];


        if ( isset( $args['surface']['max'] )  )
            $whereClause[] = 'surface <= ' . $args['surface']['max'];


        if ( isset( $args['price']['min'] ) )
            $whereClause[] = 'price >= ' . $args['price']['min'];

        if ( isset( $args['price']['max'] ) )
            $whereClause[] = 'price<= ' . $args['price']['max'];

        if ( isset( $args['rooms'] )  )
            $whereClause[] = 'bedroom  >= ' . $args['rooms'];

        if ( isset( $args['bathRooms'] ) )
            $whereClause[] = 'bathroom >= ' . $args['bathRooms'];



        if ( isset( $args['lat'] ) && isset( $args['lng'] ) && isset( $args['distance'] ) ) {
             $whereClause[] = '
            (111.111 * DEGREES(ACOS(LEAST(1.0, COS(RADIANS(CAST(geo_latData.meta_value AS DECIMAL(10, 6))))
                        * COS(RADIANS(CAST(:lat AS DECIMAL(10, 6))))
            * COS(RADIANS(CAST( geo_lngData .meta_value  AS DECIMAL(10, 6)) - CAST(:lng AS DECIMAL(10, 6))))
                        + SIN(RADIANS(CAST(geo_latData.meta_value AS DECIMAL(10, 6))))
                        * SIN(RADIANS(CAST(:lat AS DECIMAL(10, 6))))))) <= CAST(:dist AS DECIMAL(10, 6)))';
        }
        if ( isset( $args['types'] ) && ! empty( $args['types'] )  )
            $whereClause[] = 'type IN ("' . implode( '","', $args['types'] ) . '")';


        $SqlQuery = "
        SELECT
         user.ID as hotelId,
         user.display_name as hotelName,
         address_1Data.meta_value       as hotel_address_1,
         address_2Data.meta_value       as hotel_address_2,
         address_cityData.meta_value    as hotel_address_city,
         address_zipData.meta_value     as hotel_address_zip,
         address_countryData.meta_value as hotel_address_country,
         postData.ID as cheapestRoomId,
         postData.price as price,
         postData.surface as surface,
         postData.bedroom as bedroom,
         postData.bathroom as bathroom,
         postData.post_title as title,
         postData.coverImage as room_image_url,
         
         postData.type as type,
         COUNT(reviewData.meta_value)   as reviewCount,
         AVG(reviewData.meta_value)     as reviewMoy,
         geo_latData.meta_value        as geo_lat,
         geo_lngData .meta_value        as geo_lng,
         coverImageData.meta_value      as hotel_image_url,
         phoneData.meta_value           as hotel_phone
         
        
         FROM
         wp_users AS USER
        
         INNER JOIN wp_usermeta as address_1Data       ON address_1Data.user_id       = USER.ID     AND address_1Data.meta_key       = 'address_1'
         INNER JOIN wp_usermeta as address_2Data       ON address_2Data.user_id       = USER.ID     AND address_2Data.meta_key       = 'address_2'
         INNER JOIN wp_usermeta as address_cityData    ON address_cityData.user_id    = USER.ID     AND address_cityData.meta_key    = 'address_city'
         INNER JOIN wp_usermeta as address_zipData     ON address_zipData.user_id     = USER.ID     AND address_zipData.meta_key     = 'address_zip'
         INNER JOIN wp_usermeta as address_countryData ON address_countryData.user_id = USER.ID     AND address_countryData.meta_key = 'address_country'
         INNER JOIN wp_usermeta as geo_latData         ON geo_latData.user_id         = USER.ID     AND geo_latData.meta_key         = 'geo_lat'
         INNER JOIN wp_usermeta as geo_lngData         ON geo_lngData.user_id         = USER.ID     AND geo_lngData.meta_key         = 'geo_lng'
         INNER JOIN wp_usermeta as coverImageData      ON coverImageData.user_id      = USER.ID     AND coverImageData.meta_key      = 'coverImage'
         INNER JOIN wp_usermeta as phoneData           ON phoneData.user_id           = USER.ID     AND phoneData.meta_key           = 'phone'
         INNER JOIN wp_posts    as rating_postData     ON rating_postData.post_author = USER.ID     AND rating_postData.post_type    = 'review'
         INNER JOIN wp_postmeta as reviewData          ON reviewData.post_id = rating_postData.ID   AND reviewData.meta_key          = 'rating'
        
         -- room
         INNER JOIN (
             SELECT
             post.ID,
             post.post_author,
             post.post_title,
             MIN(CAST(priceData.meta_value AS UNSIGNED)) AS price,
             CAST(surfaceData.meta_value  AS UNSIGNED) AS surface,
             CAST(roomsData.meta_value AS UNSIGNED) AS bedroom,
             CAST(bathRoomsData.meta_value AS UNSIGNED) AS bathroom,
             img_meta.meta_value       as coverImage,
             typeData.meta_value   AS type
            
            
             FROM
             tp.wp_posts AS post
             -- price
             INNER JOIN tp.wp_postmeta AS priceData ON post.ID = priceData.post_id
             AND priceData.meta_key = 'price'
             INNER JOIN wp_postmeta as surfaceData ON surfaceData.post_id = post.ID AND surfaceData.meta_key = 'surface'
             INNER JOIN wp_postmeta as roomsData ON roomsData.post_id = post.ID AND roomsData.meta_key = 'bedrooms_count'
             INNER JOIN wp_postmeta as bathRoomsData ON bathRoomsData.post_id = post.ID AND bathRoomsData.meta_key = 'bathrooms_count'
             INNER JOIN wp_postmeta as typeData ON typeData.post_id = post.ID AND typeData.meta_key = 'type'
             INNER JOIN wp_postmeta as img_meta ON img_meta.post_id = post.ID AND img_meta.meta_key = 'coverImage'
             WHERE
             post.post_type = 'room'
             GROUP BY
             post.ID
         ) AS postData ON user.ID = postData.post_author" . ( ! empty( $whereClause ) ? ' WHERE ' . implode( ' AND ', $whereClause ) : '' ) . " GROUP BY user.ID";

        $stmt = $this->getDB()->prepare($SqlQuery);
        return $stmt;
    }
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
}