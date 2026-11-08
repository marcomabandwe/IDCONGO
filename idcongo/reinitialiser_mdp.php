<?php
// En-têtes CORS pour autoriser le Web (React Native Web)
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
$identifiant = $data['identifiant'] ?? '';
$code = $data['code'] ?? '';
$nouveau_mdp = $data['nouveau_mdp'] ?? '';

if (empty($identifiant) || empty($code) || empty($nouveau_mdp)) {
    echo json_encode(['status' => 'error', 'message' => 'Données incomplètes. Veuillez remplir tous les champs.']);
    exit();
}

// 1. Retrouver l'e-mail de l'utilisateur via son username ou email
$stmtUser = $pdo->prepare('SELECT email FROM proprietaires WHERE username = :id1 OR email = :id2 LIMIT 1');
$stmtUser->execute(['id1' => $identifiant, 'id2' => $identifiant]);
$user = $stmtUser->fetch();

if (!$user || empty($user['email'])) {
    echo json_encode(['status' => 'error', 'message' => 'Utilisateur introuvable.']);
    exit();
}

$email = $user['email'];

// 2. Vérifier si le code OTP correspond dans la table `codes_reset`
$stmtCode = $pdo->prepare('SELECT * FROM codes_reset WHERE email = :email AND code = :code LIMIT 1');
$stmtCode->execute(['email' => $email, 'code' => $code]);
$validCode = $stmtCode->fetch();

if (!$validCode) {
    echo json_encode(['status' => 'error', 'message' => 'Code de confirmation incorrect.']);
    exit();
}

// 3. Mettre à jour le mot de passe de l'utilisateur
$hash = password_hash($nouveau_mdp, PASSWORD_BCRYPT);
$stmtUpdate = $pdo->prepare('UPDATE proprietaires SET mot_de_passe = :mdp WHERE email = :email');
$success = $stmtUpdate->execute([
    'mdp' => $hash,
    'email' => $email
]);

if ($success) {
    // 4. Supprimer le code utilisé pour qu'il ne serve plus
    $stmtDel = $pdo->prepare('DELETE FROM codes_reset WHERE email = :email');
    $stmtDel->execute(['email' => $email]);

    echo json_encode(['status' => 'success', 'message' => 'Mot de passe réinitialisé avec succès.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Impossible de mettre à jour le mot de passe.']);
}
?>
