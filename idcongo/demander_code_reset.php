<?php

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

// 1. Inclusion manuelle des fichiers PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$data = json_decode(file_get_contents('php://input'), true);
$identifiant = $data['identifiant'] ?? '';

if (empty($identifiant)) {
    echo json_encode(['status' => 'error', 'message' => 'Veuillez saisir votre identifiant ou email.']);
    exit();
}

// 2. Rechercher l'utilisateur dans la base de données
$stmt = $pdo->prepare('SELECT id, email, username FROM proprietaires WHERE username = :id1 OR email = :id2 LIMIT 1');
$stmt->execute(['id1' => $identifiant, 'id2' => $identifiant]);
$user = $stmt->fetch();

if (!$user || empty($user['email'])) {
    echo json_encode(['status' => 'error', 'message' => 'Aucun compte associé à cet identifiant ou adresse e-mail manquante.']);
    exit();
}

$emailDestinataire = $user['email'];
$codeConfirmation = sprintf("%06d", mt_rand(100000, 999999)); // Code à 6 chiffres

try {
    // 3. Stocker le code dans la table `codes_reset`
    $stmtDel = $pdo->prepare('DELETE FROM codes_reset WHERE email = :email');
    $stmtDel->execute(['email' => $emailDestinataire]);

    $stmtIns = $pdo->prepare('INSERT INTO codes_reset (email, code) VALUES (:email, :code)');
    $stmtIns->execute(['email' => $emailDestinataire, 'code' => $codeConfirmation]);

    // 4. Configuration et envoi du mail via Gmail
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    
    // ⚠️ Remplace par ton adresse Gmail et ton mot de passe d'application à 16 caractères
    $mail->Username   = 'mabandwemarco@gmail.com'; 
    $mail->Password   = 'ymtc xyzw ohdx iboa'; 
    
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';

    // Expéditeur & Destinataire
    $mail->setFrom('mabandwemarco@gmail.com', 'IDCONGO Support');
    $mail->addAddress($emailDestinataire, $user['username']);

    // Contenu du message
    $mail->isHTML(true);
    $mail->Subject = "Code de réinitialisation - IDCONGO";
    $mail->Body    = "
        <h3>Bonjour {$user['username']},</h3>
        <p>Voici votre code de confirmation pour réinitialiser votre mot de passe IDCONGO :</p>
        <h2 style='color: #CE1126; letter-spacing: 3px;'>{$codeConfirmation}</h2>
        <p>Si vous n'avez pas demandé ce changement, vous pouvez ignorer cet e-mail.</p>
    ";

    $mail->send();

    echo json_encode([
        'status' => 'success',
        'message' => 'Un code de confirmation a été envoyé à votre adresse e-mail.'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => "Erreur lors de l'envoi de l'e-mail : {$mail->ErrorInfo}"
    ]);
}
?>