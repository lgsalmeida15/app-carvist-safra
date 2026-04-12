<?php
/**
 * Configuração de Conexão com PostgreSQL
 */
require_once __DIR__ . '/includes/Env.php';
Env::load(__DIR__ . '/.env');

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5433';
$dbname = getenv('DB_NAME') ?: 'carvist';
$user = getenv('DB_USER') ?: 'postgres';
$password = getenv('DB_PASS') ?: '';

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}
