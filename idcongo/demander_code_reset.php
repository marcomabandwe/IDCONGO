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

  // Récupération sécurisée via la variable d'environnement de Render
    $resendApiKey = getenv('RESEND_API_KEY');

    if (!$resendApiKey) {
        echo json_encode(['status' => 'error', 'message' => 'Clé API Resend non configurée sur le serveur.']);
        exit();
    }
    $payloadEmail = [
        'from'    => 'IDCONGO <onboarding@resend.dev>', // Adresse de test Resend
        'to'      => [$emailDestinataire],
        'subject' => 'Code de réinitialisation - IDCONGO',
        'html'    => "
            <div style='font-family: Arial, sans-serif; padding: 20px;'>
                <h2 style='color: #0055A5;'>Code de vérification IDCONGO</h2>
                <p>Bonjour <b>" . htmlspecialchars($nomComplet) . "</b>,</p>
                <p>Voici votre code de réinitialisation :</p>
                <div style='background-color: #F0F4F8; padding: 15px; font-size: 24px; font-weight: bold; color: #CE1126; letter-spacing: 5px; width: fit-content; border-radius: 5px;'>
                    $code
                </div>
            </div>
        "
    ];

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $resendApiKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payloadEmail));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 || $httpCode === 201) {
        echo json_encode([
            'status'  => 'success', 
            'message' => 'Un code de confirmation a été envoyé à votre e-mail.'
        ]);
    } else {
        echo json_encode([
            'status'  => 'error', 
            'message' => 'Erreur API Mail (Code HTTP ' . $httpCode . ') : ' . $response
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur BDD : ' . $e->getMessage()]);
}
?>
