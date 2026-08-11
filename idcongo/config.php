<?php

// En-têtes CORS pour autoriser l'application mobile et le web
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, bypass-tunnel-reminder");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

// Récupération des identifiants via les variables d'environnement (Render)
// Si la variable n'existe pas, on utilise les valeurs par défaut
$host    = getenv('DB_HOST') ?: "mysql-9260506-mabandwemarco-edad.d.aivencloud.com";
$port    = getenv('DB_PORT') ?: "22154";
$db      = getenv('DB_NAME') ?: "idcongo";
$user    = getenv('DB_USER') ?: "avnadmin";
$pass    = getenv('DB_PASS') ?: ""; // Laisse vide ici dans le code !
$charset = "utf8mb4";

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_SSL_CA       => true,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     http_response_code(500);
     echo json_encode([
         "status" => "error",
         "message" => "Erreur de connexion à la base de données : " . $e->getMessage()
     ]);
     exit();
}
?>
