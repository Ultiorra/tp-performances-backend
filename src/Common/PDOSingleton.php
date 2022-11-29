<?php

namespace App\Common;

class PDOSingleton
{
    private \PDO $pdo;
    private static PDOSingleton $instance ;
    private function __construct(){
        $this->pdo = new \PDO( "mysql:host=db;dbname=tp;charset=utf8mb4", "root", "root" );
    }
    public static function getInstance() : \PDO
    {
        if (!isset(self::$instance)) {
            self::$instance = new PDOSingleton();
        }
        return self::$instance->pdo;
    }

}