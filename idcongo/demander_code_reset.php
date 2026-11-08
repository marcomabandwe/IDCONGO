<?php
// En-têtes CORS pour autoriser l'application mobile et le Web
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

if (empty($identifiant)) {
    echo json_encode(['status' => 'error', 'message' => 'Veuillez saisir votre identifiant ou email.']);
    exit();
}

// 1. Rechercher l'utilisateur dans la base de données
$stmt = $pdo->prepare('SELECT id, email, username FROM proprietaires WHERE username = :id1 OR email = :id2 LIMIT 1');
$stmt->execute(['id1' => $identifiant, 'id2' => $identifiant]);
$user = $stmt->fetch();

if (!$user || empty($user['email'])) {
    echo json_encode(['status' => 'error', 'message' => 'Aucun compte associé à cet identifiant ou adresse e-mail manquante.']);
    exit();
}

$emailDestinataire = $user['email'];
$codeConfirmation = sprintf("%06d", mt_rand(100000, 999999));

try {
    // 2. Stocker le code dans la table codes_reset
    $stmtDel = $pdo->prepare('DELETE FROM codes_reset WHERE email = :email');
    $stmtDel->execute(['email' => $emailDestinataire]);

    $stmtIns = $pdo->prepare('INSERT INTO codes_reset (email, code) VALUES (:email, :code)');
    $stmtIns->execute(['email' => $emailDestinataire, 'code' => $codeConfirmation]);

    // 3. Récupération sécurisée de la clé API Brevo (depuis Render ou secrets.php)
    $brevoApiKey = getenv('BREVO_API_KEY');
    if (!$brevoApiKey && file_exists('secrets.php')) {
        $secrets = require 'secrets.php';
        $brevoApiKey = $secrets['brevo_api_key'] ?? '';
    }

    if (empty($brevoApiKey)) {
        echo json_encode(['status' => 'error', 'message' => 'Clé API Brevo non configurée sur le serveur.']);
        exit();
    }

    $expediteurEmail = 'mabandwemarco@gmail.com';
    $expediteurNom = 'IDCONGO Support';
    $sujet = "Code de réinitialisation - IDCONGO";
    $contenuHtml = "
        <h3>Bonjour {$user['username']},</h3>
        <p>Voici votre code de confirmation pour réinitialiser votre mot de passe IDCONGO :</p>
        <h2 style='color: #CE1126; letter-spacing: 3px;'>{$codeConfirmation}</h2>
        <p>Si vous n'avez pas demandé ce changement, vous pouvez ignorer cet e-mail.</p>
    ";

    $payload = [
        'sender' => ['name' => $expediteurNom, 'email' => $expediteurEmail],
        'to' => [['email' => $emailDestinataire, 'name' => $user['username']]],
        'subject' => $sujet,
        'htmlContent' => $contenuHtml
    ];

    // Envoi via l'API HTTP v3 de Brevo
    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json',
        'api-key: ' . $brevoApiKey,
        'content-type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Un code de confirmation a été envoyé à votre adresse e-mail.'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => "Erreur d'envoi Brevo (Code HTTP $httpCode)."
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => "Erreur technique : " . $e->getMessage()
    ]);
}
?>
