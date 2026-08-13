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

// 1. Inclusion manuelle des fichiers PHPMailer depuis le dossier idcongo/PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

$data = json_decode(file_get_contents('php://input'), true);
$identifiant = isset($data['identifiant']) ? trim($data['identifiant']) : '';

if (empty($identifiant)) {
    echo json_encode(['status' => 'error', 'message' => 'Veuillez fournir un nom d\'utilisateur ou un e-mail.']);
    exit();
}

try {
    // 2. Recherche du vrai propriétaire dans la BDD
    $stmt = $pdo->prepare('SELECT id, email, nom, prenom FROM proprietaires WHERE username = :id1 OR email = :id2 LIMIT 1');
    $stmt->execute(['id1' => $identifiant, 'id2' => $identifiant]);
    $user = $stmt->fetch();

    if (!$user || empty($user['email'])) {
        echo json_encode(['status' => 'error', 'message' => 'Aucun compte associé trouvé.']);
        exit();
    }

    $emailDestinataire = $user['email'];
    $nomComplet = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));

    // 3. Génération du code à 6 chiffres
    $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

    // 4. Sauvegarde / mise à jour dans la table codes_reset
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

    // 5. Instanciation et configuration de PHPMailer (Port 465 SSL)
    $mail = new PHPMailer(true);

    $smtpUser = getenv('SMTP_USER') ?: 'mabandwemarco@gmail.com';
    $smtpPass = getenv('SMTP_PASS') ?: ''; // Note: Pense à retirer le mot de passe en clair si tu l'as mis en Variable d'environnement Render !

    $mail->isSMTP();
    $mail->Host       = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtpUser;
    $mail->Password   = $smtpPass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL (Fortement recommandé sur Render)
    $mail->Port       = 465;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom($smtpUser, 'IDCONGO Sécurité');
    $mail->addAddress($emailDestinataire, $nomComplet);

    $mail->isHTML(true);
    $mail->Subject = 'Code de réinitialisation - IDCONGO';
    $mail->Body    = "
        <div style='font-family: Arial, sans-serif; padding: 20px;'>
            <h2 style='color: #0055A5;'>Code de vérification IDCONGO</h2>
            <p>Bonjour <b>" . htmlspecialchars($nomComplet) . "</b>,</p>
            <p>Voici votre code de réinitialisation :</p>
            <div style='background-color: #F0F4F8; padding: 15px; font-size: 24px; font-weight: bold; color: #CE1126; letter-spacing: 5px; width: fit-content; border-radius: 5px;'>
                $code
            </div>
        </div>
    ";

    $mail->send();

    echo json_encode([
        'status'  => 'success', 
        'message' => 'Un code de confirmation a été envoyé à l\'adresse e-mail associée.'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status'  => 'error', 
        'message' => 'Erreur d\'envoi d\'e-mail : ' . $mail->ErrorInfo
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur BDD : ' . $e->getMessage()]);
}
?>
