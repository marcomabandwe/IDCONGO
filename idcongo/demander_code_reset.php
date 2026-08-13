<?php
require_once 'config.php';

// Inclusion de PHPMailer via Composer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

$data = json_decode(file_get_contents('php://input'), true);
$identifiant = isset($data['identifiant']) ? trim($data['identifiant']) : '';

if (empty($identifiant)) {
    echo json_encode(['status' => 'error', 'message' => 'Veuillez fournir un nom d\'utilisateur ou un e-mail.']);
    exit();
}

try {
    // 1. Recherche de l'utilisateur dans la BDD
    $stmt = $pdo->prepare('SELECT id, email, nom, prenom FROM proprietaires WHERE username = :id1 OR email = :id2 LIMIT 1');
    $stmt->execute(['id1' => $identifiant, 'id2' => $identifiant]);
    $user = $stmt->fetch();

    if (!$user || empty($user['email'])) {
        echo json_encode(['status' => 'error', 'message' => 'Aucun compte ou adresse e-mail associé trouvé.']);
        exit();
    }

    $emailDestinataire = $user['email'];
    $nomComplet = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));

    // 2. Génération d'un code unique à 6 chiffres
    $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

    // 3. Enregistrement / Mise à jour dans la table codes_reset
    $stmtCode = $pdo->prepare('
        INSERT INTO codes_reset (email, code, date_creation) 
        VALUES (:email, :code, NOW()) 
        ON DUPLICATE KEY UPDATE code = :code_update, date_creation = NOW()
    ');
    
    $stmtCode->execute([
        'email'       => $emailDestinataire,
        'code'        => $code,
        'code_update' => $code
    ]);

    // 4. Configuration et envoi de l'e-mail avec PHPMailer
    $mail = new PHPMailer(true);

    // --- Configuration Serveur SMTP ---
    $mail->isSMTP();
    $mail->Host       = getenv('SMTP_HOST') ?: 'smtp.gmail.com'; // Ex: smtp.gmail.com
    $mail->SMTPAuth   = true;
    $mail->Username   = getenv('SMTP_USER') ?: 'votre.email@gmail.com'; // Votre e-mail expéditeur
    $mail->Password   = getenv('SMTP_PASS') ?: 'votre_mot_de_passe_app'; // Mot de passe d'application SMTP
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = getenv('SMTP_PORT') ?: 587;
    $mail->CharSet    = 'UTF-8';

    // --- Destinataires et Contenu ---
    $mail->setFrom(getenv('SMTP_USER') ?: 'no-reply@idcongo.com', 'IDCONGO Sécurité');
    $mail->addAddress($emailDestinataire, $nomComplet);

    $mail->isHTML(true);
    $mail->Subject = 'Code de réinitialisation - IDCONGO';
    $mail->Body    = "
        <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
            <h2 style='color: #0055A5;'>Réinitialisation de votre mot de passe</h2>
            <p>Bonjour <b>" . htmlspecialchars($nomComplet) . "</b>,</p>
            <p>Vous avez demandé la réinitialisation de votre mot de passe sur l'application IDCONGO.</p>
            <p>Voici votre code de confirmation à 6 chiffres :</p>
            <div style='background-color: #F0F4F8; padding: 15px; font-size: 24px; font-weight: bold; color: #CE1126; letter-spacing: 5px; width: fit-content; border-radius: 5px;'>
                $code
            </div>
            <p style='margin-top: 15px; font-size: 12px; color: #777;'>Si vous n'êtes pas à l'origine de cette demande, veuillez ignorer cet e-mail.</p>
        </div>
    ";

    $mail->send();

    echo json_encode([
        'status'  => 'success', 
        'message' => 'Un code de confirmation a été envoyé à votre adresse e-mail.'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error', 
        'message' => 'Impossible d\'envoyer l\'e-mail : ' . $mail->ErrorInfo
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur BDD : ' . $e->getMessage()]);
}
?>
