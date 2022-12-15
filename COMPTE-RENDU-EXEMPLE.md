Vous pouvez utiliser ce [GSheets](https://docs.google.com/spreadsheets/d/13Hw27U3CsoWGKJ-qDAunW9Kcmqe9ng8FROmZaLROU5c/copy?usp=sharing) pour suivre l'évolution de l'amélioration de vos performances au cours du TP 

## Question 2 : Utilisation Server Timing API

**Temps de chargement initial de la page** : 35s

**Choix des méthodes à analyser** :

- `getReviews` 9.27s
- `getCheapestRoom` 16.13s
- `getMetas` 4.25s



## Question 3 : Réduction du nombre de connexions PDO

**Temps de chargement de la page** : 29.2

**Temps consommé par `getDB()`** 

- **Avant** 1.18s

- **Après** 2.90ms


## Question 4 : Délégation des opérations de filtrage à la base de données

**Temps de chargement globaux** 

- **Avant** TEMPS

- **Après** TEMPS


#### Amélioration de la méthode `getMeta` et donc de la méthode `getMetas` :

- **Avant** 3.10s

```sql
SELECT * FROM wp_usermeta
```

- **Après** 1.62s

```sql
SELECT * FROM wp_usermeta WHERE user_id=:userId and meta_key=:key
```



#### Amélioration de la méthode `getReviews` :

- **Avant** 9.27s

```sql
SELECT * FROM wp_posts, wp_postmeta WHERE wp_posts.post_author = :hotelId AND wp_posts.ID = wp_postmeta.post_id AND meta_key = 'rating' AND post_type = 'review'
```

- **Après** 7.66s

```sql
SELECT count(meta_value), AVG(meta_value) FROM wp_posts, wp_postmeta WHERE wp_posts.post_author = :hotelId AND wp_posts.ID = wp_postmeta.post_id AND meta_key = 'rating' AND post_type = 'review'
```



#### Amélioration de la méthode `getCheapestRoom` :

- **Avant** 17.43s

```sql
SELECT * FROM wp_posts WHERE post_author = :hotelId AND post_type = 'room'
```

- **Après** 13.10s

```sql
SELECT * FROM wp_posts
               INNER JOIN wp_postmeta as surfaceData ON surfaceData.post_id = wp_posts.ID AND surfaceData.meta_key = 'surface'
               INNER JOIN wp_postmeta as priceData ON priceData.post_id = wp_posts.ID AND priceData.meta_key = 'price'
               INNER JOIN wp_postmeta as roomsData ON roomsData.post_id = wp_posts.ID AND roomsData.meta_key = 'bedrooms_count'
               INNER JOIN wp_postmeta as bathRoomsData ON bathRoomsData.post_id = wp_posts.ID AND bathRoomsData.meta_key = 'bathrooms_count'
               INNER JOIN wp_postmeta as typeData ON typeData.post_id = wp_posts.ID AND typeData.meta_key = 'type'
WHERE post_author = '200' AND post_type = 'room' AND surfaceData.meta_value >= 130 AND surfaceData.meta_value <= 150 AND priceData.meta_value >= 200 AND priceData.meta_value <= 230 AND roomsData.meta_value  >= 5 AND bathRoomsData.meta_value >= 5 AND typeData.meta_value IN ("Maison","Appartement") ORDER BY priceData.meta_value ASC LIMIT 1
```



## Question 5 : Réduction du nombre de requêtes SQL pour `GET_METAS`

|                              | **Avant** | **Après** |
|------------------------------|-----------|-----------|
| Nombre d'appels de `getDB()` | 2201      | 601       |
 | Temps de `GET_METAS`         | 1.54      | 1.38s     |

## Question 6 : Création d'un service basé sur une seule requête SQL

|                              | **Avant** | **Après** |
|------------------------------|-----------|-----------|
| Nombre d'appels de `getDB()` | 601       | 1         |
| Temps de chargement global   | 21.5s     | 3.77s     |

**Requête SQL**

```SQL
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


        FROM tp.wp_posts AS post
          INNER JOIN tp.wp_postmeta AS priceData ON post.ID = priceData.post_id
          AND priceData.meta_key = 'price'
          INNER JOIN wp_postmeta as surfaceData ON surfaceData.post_id = post.ID AND surfaceData.meta_key = 'surface'
          INNER JOIN wp_postmeta as roomsData ON roomsData.post_id = post.ID AND roomsData.meta_key = 'bedrooms_count'
          INNER JOIN wp_postmeta as bathRoomsData ON bathRoomsData.post_id = post.ID AND bathRoomsData.meta_key = 'bathrooms_count'
          INNER JOIN wp_postmeta as typeData ON typeData.post_id = post.ID AND typeData.meta_key = 'type'
          INNER JOIN wp_postmeta as img_meta ON img_meta.post_id = post.ID AND img_meta.meta_key = 'coverImage'
          WHERE
        post.post_type = 'room' GROUP BY post.ID
        ) AS postData ON user.ID = postData.post_author 
        WHERE surface >= 130 AND surface <= 150 
        AND price >= 200 AND price<= 230 
        AND bedroom  >= 5 AND bathroom >= 5
        AND
        (111.111 * DEGREES(ACOS(LEAST(1.0, COS(RADIANS(CAST(geo_latData.meta_value AS DECIMAL(10, 6))))
        * COS(RADIANS(CAST('46.988708' AS DECIMAL(10, 6))))
        * COS(RADIANS(CAST( geo_lngData .meta_value  AS DECIMAL(10, 6)) - CAST('3.160778' AS DECIMAL(10, 6))))
        + SIN(RADIANS(CAST(geo_latData.meta_value AS DECIMAL(10, 6))))
        * SIN(RADIANS(CAST('46.988708' AS DECIMAL(10, 6))))))) <= CAST('30' AS DECIMAL(10, 6))) 
        AND type IN ("Maison","Appartement") 
        GROUP BY user.ID
```

## Question 7 : ajout d'indexes SQL

**Indexes ajoutés**

- `wp_postmeta` : `post_id`
- `wp_posts` : `post_author`
- `wp_usermeta` : `user_id`

**Requête SQL d'ajout des indexes** 

```sql
ALTER TABLE `wp_postmeta` ADD INDEX(`post_id`);
ALTER TABLE `wp_posts` ADD INDEX(`post_author`);
ALTER TABLE `wp_usermeta` ADD INDEX(`user_id`);
```

| Temps de chargement de la page | Sans filtre | Avec filtres |
|--------------------------------|-------------|--------------|
| `UnoptimizedService`           | 30s         | 1.27         |
| `OneRequestService`            | 3.77s       | 1.07s        |
[Filtres à utiliser pour mesurer le temps de chargement](http://localhost/?types%5B%5D=Maison&types%5B%5D=Appartement&price%5Bmin%5D=200&price%5Bmax%5D=230&surface%5Bmin%5D=130&surface%5Bmax%5D=150&rooms=5&bathRooms=5&lat=46.988708&lng=3.160778&search=Nevers&distance=30)




## Question 8 : restructuration des tables

**Temps de chargement de la page**

| Temps de chargement de la page | Sans filtre | Avec filtres |
|--------------------------------|-------------|--------------|
| `OneRequestService`            | 1.24s       | 0.42s        |
| `ReworkedHotelService`         | 2.38s       | 0.38s        |

[Filtres à utiliser pour mesurer le temps de chargement](http://localhost/?types%5B%5D=Maison&types%5B%5D=Appartement&price%5Bmin%5D=200&price%5Bmax%5D=230&surface%5Bmin%5D=130&surface%5Bmax%5D=150&rooms=5&bathRooms=5&lat=46.988708&lng=3.160778&search=Nevers&distance=30)

### Table `hotels` (200 lignes)

```SQL
-- REQ SQL CREATION TABLE
CREATE TABLE `hotels` (
                       `id` bigint(255) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                       `name` varchar(255) NOT NULL,
                       `email` varchar(255) NOT NULL,
                       `address_1` varchar(255) NOT NULL,
                       `address_2` varchar(255) NOT NULL,
                       `address_city` varchar(255) NOT NULL,
                       `address_zipcode` varchar(255) NOT NULL,
                       `address_country` varchar(100) NOT NULL,
                       `geo_lat` float NOT NULL,
                       `geo_lng` float NOT NULL,
                       `phone` varchar(255) NOT NULL,
                       `image_url` longtext NOT NULL
);
```

```SQL
-- REQ SQL INSERTION DONNÉES DANS LA TABLE
INSERT INTO hotels (
 SELECT
  USER.ID                         as id,
  USER.display_name               as name,
  USER.user_email                 as email,
  address_1_meta.meta_value       as address_1,
  address_2_meta.meta_value       as address_2,
  address_city_meta.meta_value    as address_city,
  address_zip_meta.meta_value     as address_zip,
  address_country_meta.meta_value as hotel_address_country,

  geo_lat_meta.meta_value         as geo_lat,
  geo_lng_meta.meta_value         as geo_lng,
  phone_meta.meta_value           as phone,
  coverImage_meta.meta_value      as image_url

 FROM wp_users as USER
       INNER JOIN wp_usermeta as address_1_meta       ON address_1_meta.user_id       = USER.ID AND address_1_meta.meta_key       = 'address_1'
       INNER JOIN wp_usermeta as address_2_meta       ON address_2_meta.user_id       = USER.ID AND address_2_meta.meta_key       = 'address_2'
       INNER JOIN wp_usermeta as address_city_meta    ON address_city_meta.user_id    = USER.ID AND address_city_meta.meta_key    = 'address_city'
       INNER JOIN wp_usermeta as address_zip_meta     ON address_zip_meta.user_id     = USER.ID AND address_zip_meta.meta_key     = 'address_zip'
       INNER JOIN wp_usermeta as address_country_meta ON address_country_meta.user_id = USER.ID AND address_country_meta.meta_key = 'address_country'
       INNER JOIN wp_usermeta as geo_lat_meta         ON geo_lat_meta.user_id         = USER.ID AND geo_lat_meta.meta_key         = 'geo_lat'
       INNER JOIN wp_usermeta as geo_lng_meta         ON geo_lng_meta.user_id         = USER.ID AND geo_lng_meta.meta_key         = 'geo_lng'
       INNER JOIN wp_usermeta as coverImage_meta      ON coverImage_meta.user_id      = USER.ID AND coverImage_meta.meta_key      = 'coverImage'
       INNER JOIN wp_usermeta as phone_meta           ON phone_meta.user_id           = USER.ID AND phone_meta.meta_key           = 'phone'

 GROUP BY USER.ID
);
```

### Table `rooms` (1 200 lignes)

```SQL
CREATE TABLE `rooms` (
                      `id` bigint(255) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                      `id_hotel` bigint(255) UNSIGNED NOT NULL,
                      `title` varchar(255) NOT NULL,
                      `price` float NOT NULL,
                      `image` varchar(400) NOT NULL,
                      `bedrooms` int UNSIGNED NOT NULL,
                      `bathrooms` int UNSIGNED NOT NULL,
                      `surface` FLOAT UNSIGNED NOT NULL,
                      `type` varchar(255) NOT NULL
);
ALTER TABLE `rooms`
    ADD KEY `id_hotel` (`id_hotel`),
    ADD CONSTRAINT `fk_rooms_hotel` FOREIGN KEY (`id_hotel`) REFERENCES `hotels` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;
```

```SQL
-- REQ SQL INSERTION DONNÉES DANS LA TABLE
INSERT INTO rooms(
 SELECT
  POST.ID                   as id,
  POST.post_author          as hotel_id,
  POST.post_title           as title,
  priceData.meta_value     as price,
  img_meta.meta_value       as image,
  roomsData.meta_value  as bedrooms,
  bathRoomsData.meta_value as bathrooms,
  surfaceData.meta_value   as surface,
  typeData.meta_value      as type

 FROM wp_posts as POST
       INNER JOIN tp.wp_postmeta as priceData ON post.ID = priceData.post_id AND priceData.meta_key = 'price'
       INNER JOIN wp_postmeta as surfaceData ON surfaceData.post_id = post.ID AND surfaceData.meta_key = 'surface'
       INNER JOIN wp_postmeta as roomsData ON roomsData.post_id = post.ID AND roomsData.meta_key = 'bedrooms_count'
       INNER JOIN wp_postmeta as bathRoomsData ON bathRoomsData.post_id = post.ID AND bathRoomsData.meta_key = 'bathrooms_count'
       INNER JOIN wp_postmeta as typeData ON typeData.post_id = post.ID AND typeData.meta_key = 'type'
       INNER JOIN wp_postmeta as img_meta ON img_meta.post_id = post.ID AND img_meta.meta_key = 'coverImage'
 WHERE
  POST.post_type          = 'room'
 GROUP BY POST.ID
);
```

### Table `reviews` (19 700 lignes)

```SQL
-- REQ SQL CREATION TABLE
CREATE TABLE `reviews` (
                        `id` bigint(255) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                        `id_hotel` bigint(255) UNSIGNED NOT NULL,
                        `review` int UNSIGNED NOT NULL
) ENGINE=InnoDB;
ALTER TABLE `reviews`
    ADD KEY `id_hotel` (`id_hotel`),
    ADD CONSTRAINT `fk_reviews_hotel` FOREIGN KEY (`id_hotel`) REFERENCES `hotels` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;
```

```SQL
-- REQ SQL INSERTION DONNÉES DANS LA TABLE
INSERT INTO reviews(
 SELECT
  0                          as id,
  USER.ID                   as hotel_id,
  review_meta.meta_value     as review

 FROM wp_users as USER
       INNER JOIN wp_posts    as rating_post          ON rating_post.post_author = USER.ID     AND rating_post.post_type = 'review'
  INNER JOIN wp_postmeta as review_meta          ON review_meta.post_id = rating_post.ID   AND review_meta.meta_key  = 'rating'
);
```


```SQL
-- REQ SQL CREATION INDEX
ALTER TABLE `rooms` ADD INDEX(`id`);
ALTER TABLE `reviews` ADD INDEX(`id_hotel`);
ALTER TABLE `hotels` ADD INDEX(`id`);
```

## Question 9 : API
**Temps de chargement de la page**

| Sans API | Avec API |
|----------|----------|
| 2.38s    | 64s      |
## Question 13 : Implémentation d'un cache Redis

**Temps de chargement de la page**

| Sans Cache | Avec Cache |
|------------|------------|
| 64s        | 1.54s      |
[URL pour ignorer le cache sur localhost](http://localhost?skip_cache)

Je tiens a précisé que j'ai une connexion pourave (rendez moi la fibre de l'iut svp) je sais pas si ca joue ou si j'ai raté un truc au moment du passage à l'API mais quand je vois les temps éclatés au sol que j'ai ca me fait pleurer,
cordialement.
## Question 14 : Compression GZIP

**Comparaison des poids de fichier avec et sans compression GZIP**

|                       | Sans  | Avec   |
|-----------------------|-------|--------|
| Total des fichiers JS | 1.1mb | 248kb  |
| `lodash.js`           | 561kb | 98.5kb |

ca à marcher d'un coup, je crois c'est en vidant le cache de mon navigateur que ca a marché, je sais pas si c'est normal mais bon.

## Question 15 : Cache HTTP fichiers statiques

**Poids transféré de la page**

- **Avant** : 97.2kb
- **Après** : 56.5kb


## Question 17 : Cache NGINX

**Temps de chargement cache FastCGI**

- **Avant** : TEMPS
- **Après** : TEMPS

#### Que se passe-t-il si on actualise la page après avoir coupé la base de données ?

REPONSE

#### Pourquoi ?

REPONSE
