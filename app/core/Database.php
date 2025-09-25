<?php

namespace App\Core;

use PDO;
use PDOException;

/**
 * Class Database
 * @package Lacouisine
 */
class Database {

    private $db_host;
    private $db_name;
    private $db_user;
    private $db_pass;
    private $pdo;
    
    public function __construct($db_host='localhost',
                                $db_name='lacouisine',
                                $db_user='root',
                                $db_pass='password') {

        $this->db_host = $db_host;
        $this->db_name = $db_name;
        $this->db_user = $db_user;
        $this->db_pass = $db_pass;

    }

    /**
     * get the existing connection or create it
     * @return PDO
     */
    private function getPDO(): PDO{

        if ($this->pdo === null) {

            $dsn = "mysql:host={$this->db_host};dbname={$this->db_name};";
            try {
                $this->pdo = new PDO($dsn, $this->db_user, $this->db_pass);
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                die("Erreur de connexion : " . $e->getMessage());
            }
        }

        return $this->pdo;

    }

    /**
     * setting the SELECT query
     * @param mixed $query 
     * @param mixed $params
     * @return array
     */
    public function select($query, $params = []): array {

        $stmt = $this->getPDO()->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchAll();

    }

    /**
     * execute prepared request for INSERT / UPDATE / DELETE query
     * @param mixed $query
     * @param mixed $params
     * @return bool
     */
    public function execute($query, $params = []): bool {

        $stmt = $this->getPDO()->prepare($query);

        return $stmt->execute($params);

    }

    /**
     * get the last id inserted
     * @return bool|string
     */
    public function lastInsertId(): bool|string {

        return $this->getPDO()->lastInsertId();

    }
}