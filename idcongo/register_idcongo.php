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

    // 1. Vérification des champs de base indispensables (Nom et Prénom)
    if (empty($data['nom']) || empty($data['prenom'])) {
        echo json_encode([
            "status" => "error", 
            "message" => "Veuillez remplir au moins le nom et le prénom."
        ]);
        exit;
    }

    $nom     = trim($data['nom']);
    $prenom  = trim($data['prenom']);
    $postnom = !empty($data['postnom']) ? trim($data['postnom']) : null;

    try {
        // 2. Génération automatique et sécurisée d'un Username unique
        if (!empty($data['username'])) {
            $username = trim($data['username']);
        } else {
            // Format de base : prenom.nom (ex: jean.dupont)
            $base_username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $prenom . '.' . $nom));
            $username = $base_username;

            // Vérifier en BDD si le username existe déjà pour éviter tout doublon
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM proprietaires WHERE username = :username");
            $stmtCheck->execute([':username' => $username]);
            $exists = $stmtCheck->fetchColumn();

            // S'il existe déjà, on génère un identifiant unique avec un suffixe
            if ($exists > 0) {
                $username = $base_username . rand(100, 9999);
            }
        }

        // 3. Gestion du mot de passe
        $raw_password = !empty($data['mot_de_passe']) ? $data['mot_de_passe'] : '123456';
        $mot_de_passe = password_hash($raw_password, PASSWORD_BCRYPT);

        $email       = !empty($data['email']) ? trim($data['email']) : $username . "@idcongo.cd";
        $sexe        = !empty($data['sexe']) ? trim($data['sexe']) : 'M';
        $photo_url   = !empty($data['photo_url']) ? $data['photo_url'] : null;

        // NOUVEAUX CHAMPS : Date de naissance, Lieu de naissance, Origine
        $date_naissance = !empty($data['date_naissance']) ? trim($data['date_naissance']) : null;
        $lieu_naissance = !empty($data['lieu_naissance']) ? trim($data['lieu_naissance']) : null;
        $origine        = !empty($data['origine']) ? trim($data['origine']) : null;

        $ville_province = !empty($data['ville_province']) ? trim($data['ville_province']) : null;
        $district       = !empty($data['district']) ? trim($data['district']) : null;
        $commune        = !empty($data['commune']) ? trim($data['commune']) : null;
        $adresse        = !empty($data['adresse']) ? trim($data['adresse']) : null;

        $nom_pere    = !empty($data['nom_pere']) ? trim($data['nom_pere']) : null;
        $nom_mere    = !empty($data['nom_mere']) ? trim($data['nom_mere']) : null;
        $nn          = !empty($data['nn']) ? trim($data['nn']) : null;
        $matricule   = !empty($data['matricule']) ? trim($data['matricule']) : null;
        $telephone   = !empty($data['telephone']) ? trim($data['telephone']) : null;
        $telephone_2 = !empty($data['telephone_2']) ? trim($data['telephone_2']) : null;

        $proche_nom       = !empty($data['proche_nom']) ? trim($data['proche_nom']) : null;
        $proche_adresse   = !empty($data['proche_adresse']) ? trim($data['proche_adresse']) : null;
        $proche_contact   = !empty($data['proche_contact']) ? trim($data['proche_contact']) : null;
        $proche_contact_2 = !empty($data['proche_contact_2']) ? trim($data['proche_contact_2']) : null;

        // 4. Insertion du propriétaire
        $sqlProprietaire = "INSERT INTO proprietaires 
            (username, email, mot_de_passe, photo_url, nom, postnom, prenom, sexe, date_naissance, lieu_naissance, origine, ville_province, district, commune, adresse, nom_pere, nom_mere, nn, matricule, telephone, telephone_2) 
            VALUES 
            (:username, :email, :mot_de_passe, :photo_url, :nom, :postnom, :prenom, :sexe, :date_naissance, :lieu_naissance, :origine, :ville_province, :district, :commune, :adresse, :nom_pere, :nom_mere, :nn, :matricule, :telephone, :telephone_2)";
        
        $stmtProp = $pdo->prepare($sqlProprietaire);
        $stmtProp->execute([
            ':username'       => $username,
            ':email'          => $email,
            ':mot_de_passe'   => $mot_de_passe,
            ':photo_url'      => $photo_url,
            ':nom'            => $nom,
            ':postnom'        => $postnom,
            ':prenom'         => $prenom,
            ':sexe'           => $sexe,
            ':date_naissance' => $date_naissance,
            ':lieu_naissance' => $lieu_naissance,
            ':origine'        => $origine,
            ':ville_province' => $ville_province,
            ':district'       => $district,
            ':commune'        => $commune,
            ':adresse'        => $adresse,
            ':nom_pere'       => $nom_pere,
            ':nom_mere'       => $nom_mere,
            ':nn'             => $nn,
            ':matricule'      => $matricule,
            ':telephone'      => $telephone,
            ':telephone_2'    => $telephone_2
        ]);

        $id_proprietaire = $pdo->lastInsertId();

        // 5. Insertion du proche si renseigné
        if ($proche_nom && $proche_contact) {
            $sqlProche = "INSERT INTO proches_identite (id_proprietaire, nom_complet, contact, contact_2, adresse) 
                          VALUES (:id_proprietaire, :nom_complet, :contact, :contact_2, :adresse)";
            $stmtProche = $pdo->prepare($sqlProche);
            $stmtProche->execute([
                ':id_proprietaire' => $id_proprietaire,
                ':nom_complet'     => $proche_nom,
                ':contact'         => $proche_contact,
                ':contact_2'       => $proche_contact_2,
                ':adresse'         => $proche_adresse
            ]);
        }

        // 6. Réponse JSON complète et blindée
        echo json_encode([
            "status"      => "success",
            "message"     => "Compte créé avec succès !",
            "username"    => $username,
            "identifiant" => $username,
            "id"          => $id_proprietaire,
            "data"        => [
                "username"    => $username,
                "identifiant" => $username,
                "id"          => $id_proprietaire
            ]
        ]);

    } catch (PDOException $e) {
        echo json_encode([
            "status"  => "error", 
            "message" => "Erreur SQL : " . $e->getMessage()
        ]);
    }
}
exit;
?>