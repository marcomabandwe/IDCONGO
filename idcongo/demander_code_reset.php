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

// --- Utiliser le port 465 avec SMTPSecure = SSL ---
    $mail->isSMTP();
    $mail->Host       = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = getenv('SMTP_USER') ?: 'mabandwemarco@gmail.com'; 
    $mail->Password   = getenv('SMTP_PASS') ?: 'uque ssld gnxs phly'; 

    // 🟢 CHANGEMENT ICI : Passer de STARTTLS à SMTPS (SSL)
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // ou simplement 'ssl'
    $mail->Port       = 465; // On force le port 465
    $mail->CharSet    = 'UTF-8';

$data = json_decode(file_get_contents('php://input'), true);
$identifiant = isset($data['identifiant']) ? trim($data['identifiant']) : '';

if (empty($identifiant)) {
    echo json_encode(['status' => 'error', 'message' => 'Veuillez fournir un nom d\'utilisateur ou un e-mail.']);
    exit();
}

try {
    // 1. Recherche du vrai propriétaire dans la BDD
    $stmt = $pdo->prepare('SELECT id, email, nom, prenom FROM proprietaires WHERE username = :id1 OR email = :id2 LIMIT 1');
    $stmt->execute(['id1' => $identifiant, 'id2' => $identifiant]);
    $user = $stmt->fetch();

    if (!$user || empty($user['email'])) {
        echo json_encode(['status' => 'error', 'message' => 'Aucun compte associé trouvé.']);
        exit();
    }

    $emailDestinataire = $user['email'];
    $nomComplet = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));

    // 2. Génération du code à 6 chiffres
    $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

    // 3. Sauvegarde dans codes_reset
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

    // 4. Configuration et envoi du mail via SMTP
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = getenv('SMTP_USER') ?: 'votre.email@gmail.com'; // Ton adresse Gmail
    $mail->Password   = getenv('SMTP_PASS') ?: 'votre_mot_de_passe_app'; // Ton Mot de passe d'application
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = getenv('SMTP_PORT') ?: 587;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom(getenv('SMTP_USER') ?: 'no-reply@idcongo.com', 'IDCONGO Sécurité');
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
        'message' => 'Un code de confirmation a été envoyé à votre e-mail.'
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
