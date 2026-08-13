<?php
// En-têtes CORS
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

require_once 'config.php';

$data = json_decode(file_get_contents('php://input'), true);
$identifiant = isset($data['identifiant']) ? trim($data['identifiant']) : '';
$code        = isset($data['code']) ? trim($data['code']) : '';
$nouveau_mdp = isset($data['nouveau_mdp']) ? trim($data['nouveau_mdp']) : '';

if (empty($identifiant) || empty($code) || empty($nouveau_mdp)) {
    echo json_encode(['status' => 'error', 'message' => 'Veuillez remplir le code et le nouveau mot de passe.']);
    exit();
}

try {
    // 1. Récupérer l'e-mail officiel du propriétaire
    $stmtUser = $pdo->prepare('SELECT email FROM proprietaires WHERE username = :id1 OR email = :id2 LIMIT 1');
    $stmtUser->execute(['id1' => $identifiant, 'id2' => $identifiant]);
    $user = $stmtUser->fetch();

    if (!$user || empty($user['email'])) {
        echo json_encode(['status' => 'error', 'message' => 'Utilisateur introuvable.']);
        exit();
    }

    $email = $user['email'];

    // 2. Vérifier si le code soumis correspond à celui stocké dans codes_reset
    $stmtCheck = $pdo->prepare('SELECT * FROM codes_reset WHERE email = :email AND code = :code LIMIT 1');
    $stmtCheck->execute(['email' => $email, 'code' => $code]);
    $resetRequest = $stmtCheck->fetch();

    if (!$resetRequest) {
        echo json_encode(['status' => 'error', 'message' => 'Le code de confirmation est incorrect ou expiré.']);
        exit();
    }

    // 3. Mettre à jour le mot de passe dans la table proprietaires
    $hash = password_hash($nouveau_mdp, PASSWORD_BCRYPT);
    $stmtUpdate = $pdo->prepare('UPDATE proprietaires SET mot_de_passe = :mdp WHERE email = :email');
    $stmtUpdate->execute(['mdp' => $hash, 'email' => $email]);

    // 4. Nettoyer et supprimer le code utilisé
    $stmtDelete = $pdo->prepare('DELETE FROM codes_reset WHERE email = :email');
    $stmtDelete->execute(['email' => $email]);

    echo json_encode(['status' => 'success', 'message' => 'Votre mot de passe a été modifié avec succès !']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur BDD : ' . $e->getMessage()]);
}
?>
