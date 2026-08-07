<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

require_once 'config.php';

$data = json_decode(file_get_contents("php://input"), true) ?? $_POST;

$id_proprietaire = $data['id_proprietaire'] ?? null;
$nom_complet     = $data['nom_complet'] ?? null;
$contact         = $data['contact'] ?? null;
$contact_2       = $data['contact_2'] ?? null;
$adresse         = $data['adresse'] ?? null;

if (!$id_proprietaire || !$nom_complet || !$contact) {
    echo json_encode(["status" => "error", "message" => "Veuillez remplir les champs obligatoires (Propriétaire, Nom, Contact)."]);
    exit;
}

try {
    $sql = "INSERT INTO proches_identite (id_proprietaire, nom_complet, contact, contact_2, adresse) 
            VALUES (:id_proprietaire, :nom_complet, :contact, :contact_2, :adresse)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id_proprietaire' => $id_proprietaire,
        ':nom_complet'     => $nom_complet,
        ':contact'         => $contact,
        ':contact_2'       => $contact_2,
        ':adresse'         => $adresse
    ]);

    echo json_encode([
        "status" => "success",
        "message" => "Nouveau proche ajouté avec succès.",
        "id" => $pdo->lastInsertId()
    ]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Erreur SQL : " . $e->getMessage()]);
}
exit;
?>