<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST");

ini_set('display_errors', 0);
error_reporting(0);

require_once 'config.php';

$data = json_decode(file_get_contents("php://input"), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($data['identifiant']) || empty($data['mot_de_passe'])) {
        echo json_encode([
            "status" => "error", 
            "message" => "Veuillez remplir tous les champs."
        ]);
        exit;
    }

    $identifiant = trim($data['identifiant']);
    $mot_de_passe = $data['mot_de_passe'];

    try {
        // 1. Requête préparée avec deux paramètres distincts : :email et :username
        $stmt = $pdo->prepare("SELECT username, mot_de_passe FROM proprietaires WHERE email = :email OR username = :username LIMIT 1");
        
        // 2. Transmettre les deux paramètres au tableau d'exécution
        $stmt->execute([
            ':email' => $identifiant,
            ':username' => $identifiant
        ]);
        
        $user = $stmt->fetch();

        // 3. Vérification du mot de passe haché
        if ($user && password_verify($mot_de_passe, $user['mot_de_passe'])) {
            echo json_encode([
                "status" => "success",
                "message" => "Connexion réussie !",
                "username" => $user['username']
            ]);
        } else {
            echo json_encode([
                "status" => "error", 
                "message" => "Identifiant ou mot de passe incorrect."
            ]);
        }

    } catch (PDOException $e) {
        echo json_encode([
            "status" => "error", 
            "message" => "Erreur SQL : " . $e->getMessage()
        ]);
    }
}
exit;
?>