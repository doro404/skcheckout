<?php
// Definir constantes apenas se n達o estiverem definidas
if (!defined('DB_HOST')) define('DB_HOST', 'svcloud-01.ksbyte.com.br');
if (!defined('DB_USER')) define('DB_USER', 'wuyhiqve_checkout');
if (!defined('DB_PASS')) define('DB_PASS', 'wuyhiqve_checkout');
if (!defined('DB_NAME')) define('DB_NAME', 'wuyhiqve_checkout');

try {
    // Criar a conex達o PDO
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
        DB_USER,
        DB_PASS,
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
        )
    );
} catch (PDOException $e) {
    die("Erro de conex達o: " . $e->getMessage());
} 