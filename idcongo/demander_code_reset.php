<?php
require_once 'config.php';

$data = json_decode(file_get_contents('php://input'), true);
$identifiant = isset($data['identifiant']) ? trim($data['identifiant']) : '';

if (empty($identifiant)) {
    echo json_encode(['status' => 'error', 'message' => 'Veuillez fournir un nom d\'utilisateur ou un e-mail.']);
    exit();
}

try {
    // 1. Vérifier si l'utilisateur existe dans proprietaires
    $stmt = $pdo->prepare('SELECT id, email FROM proprietaires WHERE username = :id1 OR email = :id2 LIMIT 1');
    $stmt->execute(['id1' => $identifiant, 'id2' => $identifiant]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Aucun compte trouvé avec cet identifiant.']);
        exit();
    }

    // 2. Générer un code à 6 chiffres (ex: 482915)
    $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $email = $user['email'];

    // 3. Enregistrer ou mettre à jour le code dans la table codes_reset
    $stmtCode = $pdo->prepare('
        INSERT INTO codes_reset (email, code, date_creation) 
        VALUES (:email, :code, NOW()) 
        ON DUPLICATE KEY UPDATE code = :code_update, date_creation = NOW()
    ');
    
    $stmtCode->execute([
        'email' => $email,
        'code' => $code,
        'code_update' => $code
    ]);

    // 🟢 Pour les tests sur Expo, on renvoie le code dans le message (à retirer en production si tu envoies par mail)
    echo json_encode([
        'status' => 'success', 
        'message' => "Code de vérification généré : $code"
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur BDD : ' . $e->getMessage()]);
}
?>
