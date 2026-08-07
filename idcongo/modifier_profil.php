<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST');

require_once 'config.php';

$json = file_get_contents("php://input");
$data = json_decode($json, true);

if (!$data || !isset($data['username'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Identifiant manquant.'
    ]);
    exit();
}

$username = $data['username'];

// Récupération de l'ensemble des champs
$nom = $data['nom'] ?? '';
$postnom = $data['postnom'] ?? '';
$prenom = $data['prenom'] ?? '';
$sexe = $data['sexe'] ?? 'M';
$date_naissance = $data['date_naissance'] ?? '';
$lieu_naissance = $data['lieu_naissance'] ?? '';
$origine = $data['origine'] ?? '';
$ville_province = $data['ville_province'] ?? '';
$district = $data['district'] ?? '';
$commune = $data['commune'] ?? '';
$adresse = $data['adresse'] ?? '';
$nom_pere = $data['nom_pere'] ?? '';
$nom_mere = $data['nom_mere'] ?? '';
$nn = $data['nn'] ?? '';
$matricule = $data['matricule'] ?? '';
$telephone = $data['telephone'] ?? '';
$telephone_2 = $data['telephone_2'] ?? '';
$photo_base64 = $data['photo_base64'] ?? null;

try {
    // Traitement de la photo si une nouvelle photo en base64 est envoyée
    $photo_url_query = "";
    $params = [
        ':nom' => $nom,
        ':postnom' => $postnom,
        ':prenom' => $prenom,
        ':sexe' => $sexe,
        ':date_naissance' => $date_naissance,
        ':lieu_naissance' => $lieu_naissance,
        ':origine' => $origine,
        ':ville_province' => $ville_province,
        ':district' => $district,
        ':commune' => $commune,
        ':adresse' => $adresse,
        ':nom_pere' => $nom_pere,
        ':nom_mere' => $nom_mere,
        ':nn' => $nn,
        ':matricule' => $matricule,
        ':telephone' => $telephone,
        ':telephone_2' => $telephone_2,
        ':username' => $username
    ];

    if ($photo_base64) {
        // Option A: Sauvegarde dans un dossier physique sur le serveur
        $dir = "uploads/";
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
        
        $image_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $photo_base64));
        $file_name = $dir . "photo_" . time() . "_" . $username . ".jpg";
        file_put_contents($file_name, $image_data);
        
        $photo_url_query = ", photo_url = :photo_url";
        $params[':photo_url'] = $file_name;
    }

    $sql = "UPDATE proprietaires SET 
                nom = :nom,
                postnom = :postnom,
                prenom = :prenom,
                sexe = :sexe,
                date_naissance = :date_naissance,
                lieu_naissance = :lieu_naissance,
                origine = :origine,
                ville_province = :ville_province,
                district = :district,
                commune = :commune,
                adresse = :adresse,
                nom_pere = :nom_pere,
                nom_mere = :nom_mere,
                nn = :nn,
                matricule = :matricule,
                telephone = :telephone,
                telephone_2 = :telephone_2
                $photo_url_query
            WHERE username = :username";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        'status' => 'success',
        'message' => 'Profil mis à jour avec succès.',
        'updated_user' => array_merge($data, [
            'photo_url' => isset($params[':photo_url']) ? $params[':photo_url'] : ($data['photo_url'] ?? null)
        ])
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Erreur lors de la mise à jour : ' . $e->getMessage()
    ]);
}
?>