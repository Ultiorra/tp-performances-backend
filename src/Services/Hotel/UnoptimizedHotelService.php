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
class UnoptimizedHotelService extends AbstractHotelService {
  
  use SingletonTrait;
  
  
  protected function __construct () {
    parent::__construct( new RoomService() );
  }
  
  
  /**
   * Récupère une nouvelle instance de connexion à la base de donnée
   *
   * @return PDO
   * @noinspection PhpUnnecessaryLocalVariableInspection
   */
  protected function getDB () : PDO {
      $timer = Timers::getInstance();
      $timerId = $timer->startTimer('getDB');
      $pdo = PDOSingleton::getInstance();
      $timer->endTimer('getDB', $timerId);
      return $pdo;
  }
  
  
  /**
   * Récupère une méta-donnée de l'instance donnée
   *
   * @param int    $userId
   * @param string $key
   *
   * @return string|null
   */
  protected function getMeta ( int $userId, string $key ) : ?string {
      $timer = Timers::getInstance();
      $timerId = $timer->startTimer('getMeta');
      $db = $this->getDB();
    $stmt = $db->prepare( "SELECT meta_value FROM wp_usermeta WHERE user_id=:userId and meta_key=:key" );
    $stmt->execute( [ 'userId' => $userId, 'key' => $key ] );
    $output = $stmt->fetchAll( PDO::FETCH_ASSOC );
    $timer->endTimer('getMeta', $timerId);
    return $output[0]['meta_value'] ;
  }
  
  
  /**
   * Récupère toutes les meta données de l'instance donnée
   *
   * @param HotelEntity $hotel
   *
   * @return array
   * @noinspection PhpUnnecessaryLocalVariableInspection
   */
  protected function getMetas ( HotelEntity $hotel ) : array {
    $timer = Timers::getInstance();
    $timerId = $timer->startTimer('getMETAS');
    $metaDatas = [
      'address' => [
        'address_1' => $this->getMeta( $hotel->getId(), 'address_1' ),
        'address_2' => $this->getMeta( $hotel->getId(), 'address_2' ),
        'address_city' => $this->getMeta( $hotel->getId(), 'address_city' ),
        'address_zip' => $this->getMeta( $hotel->getId(), 'address_zip' ),
        'address_country' => $this->getMeta( $hotel->getId(), 'address_country' ),
      ],
      'geo_lat' =>  $this->getMeta( $hotel->getId(), 'geo_lat' ),
      'geo_lng' =>  $this->getMeta( $hotel->getId(), 'geo_lng' ),
      'coverImage' =>  $this->getMeta( $hotel->getId(), 'coverImage' ),
      'phone' =>  $this->getMeta( $hotel->getId(), 'phone' ),
    ];
    $timer->endTimer('getMETAS', $timerId);
    return $metaDatas;
  }
  
  
  /**
   * Récupère les données liées aux évaluations des hotels (nombre d'avis et moyenne des avis)
   *
   * @param HotelEntity $hotel
   *
   * @return array{rating: int, count: int}
   * @noinspection PhpUnnecessaryLocalVariableInspection
   */
  protected function getReviews ( HotelEntity $hotel ) : array {
    // Récupère tous les avis d'un hotel
    $timer = Timers::getInstance();
    $timerId = $timer->startTimer('getREVIEWS');
    $stmt = $this->getDB()->prepare( "SELECT count(meta_value), AVG(meta_value) FROM wp_posts, wp_postmeta WHERE wp_posts.post_author = :hotelId AND wp_posts.ID = wp_postmeta.post_id AND meta_key = 'rating' AND post_type = 'review'" );
    $stmt->execute( [ 'hotelId' => $hotel->getId() ] );
    $reviews = $stmt->fetchAll( PDO::FETCH_ASSOC );


    $output = [
      'rating' => (int) $reviews[0]['AVG(meta_value)'],
      'count' => $reviews[0]['count(meta_value)'],
    ];
    $timer->endTimer('getREVIEWS', $timerId);
    return $output;
  }
  
  
  /**
   * Récupère les données liées à la chambre la moins chère des hotels
   *
   * @param HotelEntity $hotel
   * @param array{
   *   search: string | null,
   *   lat: string | null,
   *   lng: string | null,
   *   price: array{min:float | null, max: float | null},
   *   surface: array{min:int | null, max: int | null},
   *   rooms: int | null,
   *   bathRooms: int | null,
   *   types: string[]
   * }                  $args Une liste de paramètres pour filtrer les résultats
   *
   * @throws FilterException
   * @return RoomEntity
   */
  protected function getCheapestRoom ( HotelEntity $hotel, array $args = [] ) : RoomEntity {
    // On charge toutes les chambres de l'hôtel
      $timer = Timers::getInstance();
      $timerId = $timer->startTimer('getCHEAPESTROOM');
      $whereClause = [];

      if ( isset( $args['surface']['min'] )  )
        $whereClause[] = 'surfaceData.meta_value >= ' . $args['surface']['min'];


      if ( isset( $args['surface']['max'] )  )
        $whereClause[] = 'surfaceData.meta_value <= ' . $args['surface']['max'];


      if ( isset( $args['price']['min'] ) )
        $whereClause[] = 'priceData.meta_value >= ' . $args['price']['min'];

      if ( isset( $args['price']['max'] ) )
        $whereClause[] = 'priceData.meta_value <= ' . $args['price']['max'];

      if ( isset( $args['rooms'] )  )
        $whereClause[] = 'roomsData.meta_value  >= ' . $args['rooms'];

      if ( isset( $args['bathRooms'] ) )
        $whereClause[] = 'bathRoomsData.meta_value >= ' . $args['bathRooms'];

      if ( isset( $args['types'] ) && ! empty( $args['types'] )  )
        $whereClause[] = 'typeData.meta_value IN ("' . implode( '","', $args['types'] ) . '")';

    $stmt = $this->getDB()->prepare( "SELECT * FROM wp_posts 
    INNER JOIN wp_postmeta as surfaceData ON surfaceData.post_id = wp_posts.ID AND surfaceData.meta_key = 'surface' 
    INNER JOIN wp_postmeta as priceData ON priceData.post_id = wp_posts.ID AND priceData.meta_key = 'price'
    INNER JOIN wp_postmeta as roomsData ON roomsData.post_id = wp_posts.ID AND roomsData.meta_key = 'bedrooms_count' 
    INNER JOIN wp_postmeta as bathRoomsData ON bathRoomsData.post_id = wp_posts.ID AND bathRoomsData.meta_key = 'bathrooms_count'
    INNER JOIN wp_postmeta as typeData ON typeData.post_id = wp_posts.ID AND typeData.meta_key = 'type'    
        WHERE post_author = :hotelId AND post_type = 'room'" . ( ! empty( $whereClause ) ? ' AND ' . implode( ' AND ', $whereClause ) : '' ) . " ORDER BY priceData.meta_value ASC LIMIT 1" );
    $stmt->execute( [ 'hotelId' => $hotel->getId() ] );
    
    /**
     * On convertit les lignes en instances de chambres (au passage ça charge toutes les données).
     *
     * @var RoomEntity[] $rooms ;
     */
    $rooms = array_map( function ( $row ) {
      return $this->getRoomService()->get( $row['ID'] );
    }, $stmt->fetchAll( PDO::FETCH_ASSOC ) );
    

    
    // Si aucune chambre ne correspond aux critères, alors on déclenche une exception pour retirer l'hôtel des résultats finaux de la méthode list().
    if ( count( $rooms ) < 1 )
      throw new FilterException( "Aucune chambre ne correspond aux critères" );
    
    


    $timer->endTimer('getCHEAPESTROOM', $timerId);
    return $rooms[0];
  }
  
  
  /**
   * Calcule la distance entre deux coordonnées GPS
   *
   * @param $latitudeFrom
   * @param $longitudeFrom
   * @param $latitudeTo
   * @param $longitudeTo
   *
   * @return float|int
   */
  protected function computeDistance ( $latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo ) : float|int {
    return ( 111.111 * rad2deg( acos( min( 1.0, cos( deg2rad( $latitudeTo ) )
          * cos( deg2rad( $latitudeFrom ) )
          * cos( deg2rad( $longitudeTo - $longitudeFrom ) )
          + sin( deg2rad( $latitudeTo ) )
          * sin( deg2rad( $latitudeFrom ) ) ) ) ) );
  }
  
  
  /**
   * Construit une ShopEntity depuis un tableau associatif de données
   *
   * @throws Exception
   */
  protected function convertEntityFromArray ( array $data, array $args ) : HotelEntity {
    $hotel = ( new HotelEntity() )
      ->setId( $data['ID'] )
      ->setName( $data['display_name'] );
    
    // Charge les données meta de l'hôtel
    $metasData = $this->getMetas( $hotel );
    $hotel->setAddress( $metasData['address'] );
    $hotel->setGeoLat( $metasData['geo_lat'] );
    $hotel->setGeoLng( $metasData['geo_lng'] );
    $hotel->setImageUrl( $metasData['coverImage'] );
    $hotel->setPhone( $metasData['phone'] );
    
    // Définit la note moyenne et le nombre d'avis de l'hôtel
    $reviewsData = $this->getReviews( $hotel );
    $hotel->setRating( $reviewsData['rating'] );
    $hotel->setRatingCount( $reviewsData['count'] );
    
    // Charge la chambre la moins chère de l'hôtel
    $cheapestRoom = $this->getCheapestRoom( $hotel, $args );
    $hotel->setCheapestRoom($cheapestRoom);
    
    // Verification de la distance
    if ( isset( $args['lat'] ) && isset( $args['lng'] ) && isset( $args['distance'] ) ) {
      $hotel->setDistance( $this->computeDistance(
        floatval( $args['lat'] ),
        floatval( $args['lng'] ),
        floatval( $hotel->getGeoLat() ),
        floatval( $hotel->getGeoLng() )
      ) );
      
      if ( $hotel->getDistance() > $args['distance'] )
        throw new FilterException( "L'hôtel est en dehors du rayon de recherche" );
    }
    
    return $hotel;
  }
  
  
  /**
   * Retourne une liste de boutiques qui peuvent être filtrées en fonction des paramètres donnés à $args
   *
   * @param array{
   *   search: string | null,
   *   lat: string | null,
   *   lng: string | null,
   *   price: array{min:float | null, max: float | null},
   *   surface: array{min:int | null, max: int | null},
   *   bedrooms: int | null,
   *   bathrooms: int | null,
   *   types: string[]
   * } $args Une liste de paramètres pour filtrer les résultats
   *
   * @throws Exception
   * @return HotelEntity[] La liste des boutiques qui correspondent aux paramètres donnés à args
   */
  public function list ( array $args = [] ) : array {
    $db = $this->getDB();
    $stmt = $db->prepare( "SELECT * FROM wp_users" );
    $stmt->execute();
    
    $results = [];
    foreach ( $stmt->fetchAll( PDO::FETCH_ASSOC ) as $row ) {
      try {
        $results[] = $this->convertEntityFromArray( $row, $args );
      } catch ( FilterException ) {
        // Des FilterException peuvent être déclenchées pour exclure certains hotels des résultats
      }
    }
    
    
    return $results;
  }
}