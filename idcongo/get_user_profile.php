<?php
ini_set('display_errors', 0);
error_reporting(0);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

require_once 'config.php';

$username = isset($_GET['username']) ? trim($_GET['username']) : null;

if (!$username) {
    $data = json_decode(file_get_contents("php://input"), true);
    $username = isset($data['username']) ? trim($data['username']) : null;
}

if (empty($username)) {
    echo json_encode(["status" => "error", "message" => "Nom d'utilisateur manquant."]);
    exit;
}

try {
    // 1. Récupération du propriétaire
    $stmt = $pdo->prepare("SELECT * FROM proprietaires WHERE username = :user OR email = :email LIMIT 1");
    $stmt->execute([
        ':user'  => $username,
        ':email' => $username
    ]);
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        unset($user['mot_de_passe']);

        // 2. Récupération de TOUS les proches liés à ce propriétaire (fetchAll)
        $proches = [];
        if (isset($user['id'])) {
            $stmtProche = $pdo->prepare("SELECT * FROM proches_identite WHERE id_proprietaire = :id ORDER BY id DESC");
            $stmtProche->execute([':id' => $user['id']]);
            $proches = $stmtProche->fetchAll(PDO::FETCH_ASSOC); // Récupère la liste
        }

        echo json_encode([
            "status" => "success",
            "user"   => $user,
            "proches" => $proches // Renvoie un tableau avec tous ses proches
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Utilisateur non trouvé dans la BDD."]);
    }

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Erreur de base de données : " . $e->getMessage()]);
}
exit;
?>