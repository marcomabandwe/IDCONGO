<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

require_once 'config.php';

$data = json_decode(file_get_contents("php://input"), true) ?? $_POST;

$id              = $data['id'] ?? null; // ID du proche dans 'proches_identite'
$id_proprietaire = $data['id_proprietaire'] ?? null;
$nom_complet     = $data['nom_complet'] ?? null;
$contact         = $data['contact'] ?? null;
$contact_2       = $data['contact_2'] ?? null;
$adresse         = $data['adresse'] ?? null;

if (!$id || !$id_proprietaire) {
    echo json_encode(["status" => "error", "message" => "Identifiant du proche ou du propriétaire manquant."]);
    exit;
}

try {
    $sql = "UPDATE proches_identite 
            SET nom_complet = :nom_complet, 
                contact = :contact, 
                contact_2 = :contact_2, 
                adresse = :adresse 
            WHERE id = :id AND id_proprietaire = :id_proprietaire";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nom_complet'     => $nom_complet,
        ':contact'         => $contact,
        ':contact_2'       => $contact_2,
        ':adresse'         => $adresse,
        ':id'              => $id,
        ':id_proprietaire' => $id_proprietaire
    ]);

    echo json_encode([
        "status" => "success",
        "message" => "Proche modifié avec succès dans la base de données."
    ]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Erreur SQL : " . $e->getMessage()]);
}
exit;
?>