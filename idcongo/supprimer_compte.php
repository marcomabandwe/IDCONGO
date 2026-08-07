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

// ... Reste de ton code PHP
// Empêcher l'affichage de warnings HTML dans les réponses JSON
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';

// Récupération des données JSON envoyées par React Native
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

$username     = $data['username'] ?? null;
$email        = $data['email'] ?? null;
$mot_de_passe = $data['mot_de_passe'] ?? $data['password'] ?? null;

if (empty($username) || empty($email) || empty($mot_de_passe)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Veuillez fournir le nom d\'utilisateur, l\'email et le mot de passe.'
    ]);
    exit();
}

try {
    // 1. Rechercher l'utilisateur dans la table `proprietaires`
    $stmt = $pdo->prepare('SELECT * FROM proprietaires WHERE username = :username OR email = :email LIMIT 1');
    $stmt->execute([
        'username' => $username,
        'email'    => $email
    ]);
    $userRecord = $stmt->fetch();

    if (!$userRecord) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Utilisateur introuvable.'
        ]);
        exit();
    }

    // 2. Vérification du mot de passe
    $hashEnBdd = $userRecord['mot_de_passe'] ?? $userRecord['password'] ?? '';
    $passwordValid = password_verify($mot_de_passe, $hashEnBdd);

    if (!$passwordValid) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Mot de passe incorrect.'
        ]);
        exit();
    }

    // 3. Début de la transaction de suppression
    $pdo->beginTransaction();

    $userId = $userRecord['id'];

    // Suppression des proches associés dans `proches_identite`
    $deleteProches = $pdo->prepare('DELETE FROM proches_identite WHERE id_proprietaire = :id_proprietaire');
    $deleteProches->execute(['id_proprietaire' => $userId]);

    // Suppression du compte propriétaire dans `proprietaires`
    $deleteUser = $pdo->prepare('DELETE FROM proprietaires WHERE id = :id');
    $deleteUser->execute(['id' => $userId]);

    // Valider la transaction
    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Compte et proches associés supprimés avec succès.'
    ]);

} catch (\PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'status' => 'error',
        'message' => 'Erreur lors de la suppression : ' . $e->getMessage()
    ]);
}
?>