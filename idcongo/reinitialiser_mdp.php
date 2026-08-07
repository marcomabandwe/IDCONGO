<?php

// En-têtes CORS pour autoriser le Web (React Native Web)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, bypass-tunnel-reminder");

// Si c'est une requête OPTIONS (Preflight sent by browser), on arrête l'exécution ici
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}



error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

require_once 'config.php';

$data = json_decode(file_get_contents('php://input'), true);
$identifiant = $data['identifiant'] ?? '';
$nouveau_mdp = $data['nouveau_mdp'] ?? '';

if (empty($identifiant) || empty($nouveau_mdp)) {
    echo json_encode(['status' => 'error', 'message' => 'Données incomplètes.']);
    exit();
}

// Hachage du nouveau mot de passe
$hash = password_hash($nouveau_mdp, PASSWORD_BCRYPT);

$stmt = $pdo->prepare('UPDATE proprietaires SET mot_de_passe = :mdp WHERE username = :id1 OR email = :id2');
$success = $stmt->execute([
    'mdp' => $hash,
    'id1' => $identifiant,
    'id2' => $identifiant
]);

if ($success) {
    echo json_encode(['status' => 'success', 'message' => 'Mot de passe réinitialisé.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Impossible de mettre à jour le mot de passe.']);
}
?>