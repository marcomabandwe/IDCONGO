<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

require_once 'config.php';

$data = json_decode(file_get_contents("php://input"), true) ?? $_POST;

$id              = $data['id'] ?? null; // ID unique du proche
$id_proprietaire = $data['id_proprietaire'] ?? null;

if (!$id || !$id_proprietaire) {
    echo json_encode(["status" => "error", "message" => "Identifiant du proche ou du propriétaire manquant."]);
    exit;
}

try {
    $sql = "DELETE FROM proches_identite WHERE id = :id AND id_proprietaire = :id_proprietaire";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id'              => $id,
        ':id_proprietaire' => $id_proprietaire
    ]);

    echo json_encode([
        "status" => "success",
        "message" => "Proche supprimé de la base de données avec succès."
    ]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Erreur SQL : " . $e->getMessage()]);
}
exit;
?>