<?php
// On inclut config.php (qui gère déjà CORS, l'encodage JSON et la connexion $pdo)
require_once 'config.php';

// Récupération de l'identifiant (GET, POST JSON, ou Form Data)
$username = isset($_GET['username']) ? trim($_GET['username']) : null;

if (!$username) {
    $rawInput = file_get_contents("php://input");
    $data = json_decode($rawInput, true);
    if (isset($data['username'])) {
        $username = trim($data['username']);
    } elseif (isset($data['email'])) {
        $username = trim($data['email']);
    } elseif (isset($data['id'])) {
        $username = trim($data['id']);
    }
}

if (empty($username)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Nom d'utilisateur ou ID manquant."]);
    exit;
}

try {
    // 1. Récupération du propriétaire (recherche par username, email ou ID)
    $stmt = $pdo->prepare("SELECT * FROM proprietaires WHERE username = :user OR email = :email OR id = :id_alt LIMIT 1");
    $stmt->execute([
        ':user'   => $username,
        ':email'  => $username,
        ':id_alt' => $username
    ]);
    
    $user = $stmt->fetch();

    if ($user) {
        // Masquer le mot de passe pour la sécurité
        unset($user['mot_de_passe']);
        unset($user['password']);

        // --- GESTION DU CHEMIN DE LA PHOTO DANS idcongo/uploads ---
        $photoField = $user['photo_url'] ?? $user['photo'] ?? $user['avatar'] ?? null;

        if (!empty($photoField)) {
            // Nettoyage des slashes en début de chaîne
            $cleanPath = ltrim($photoField, '/');

            // Si ce n'est ni un Data Base64 ni une URL absolue (http/https)
            if (!preg_match('/^data:image/', $cleanPath) && !preg_match('/^https?:\/\//i', $cleanPath)) {
                
                // Si le chemin commence par uploads/ sans idcongo/ devant, on ajoute idcongo/
                if (strpos($cleanPath, 'uploads/') === 0) {
                    $cleanPath = 'idcongo/' . $cleanPath;
                } 
                // Si le chemin ne contient ni idcongo/ ni uploads/, on ajoute idcongo/uploads/
                elseif (strpos($cleanPath, 'idcongo/uploads/') !== 0) {
                    $cleanPath = 'idcongo/uploads/' . $cleanPath;
                }
            }

            // Uniformisation des clés
            $user['photo_url'] = $cleanPath;
            $user['photo']     = $cleanPath;
        } else {
            $user['photo_url'] = null;
            $user['photo']     = null;
        }
        // -----------------------------------------------------------

        // Détection automatique de l'ID du propriétaire
        $ownerId = $user['id'] ?? $user['id_proprietaire'] ?? null;

        // 2. Récupération de tous les proches liés à ce propriétaire
        $proches = [];
        if ($ownerId !== null) {
            $stmtProche = $pdo->prepare("SELECT * FROM proches_identite WHERE id_proprietaire = :id ORDER BY id DESC");
            $stmtProche->execute([':id' => $ownerId]);
            $proches = $stmtProche->fetchAll();

            // Ajustement des images des proches
            foreach ($proches as &$proche) {
                $prochePhoto = $proche['photo_url'] ?? $proche['photo'] ?? null;
                if (!empty($prochePhoto)) {
                    $pClean = ltrim($prochePhoto, '/');
                    if (!preg_match('/^data:image/', $pClean) && !preg_match('/^https?:\/\//i', $pClean)) {
                        if (strpos($pClean, 'uploads/') === 0) {
                            $pClean = 'idcongo/' . $pClean;
                        } elseif (strpos($pClean, 'idcongo/uploads/') !== 0) {
                            $pClean = 'idcongo/uploads/' . $pClean;
                        }
                    }
                    $proche['photo_url'] = $pClean;
                    $proche['photo']     = $pClean;
                }
            }
        }

        echo json_encode([
            "status"  => "success",
            "user"    => $user,
            "proches" => $proches
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Utilisateur non trouvé dans la BDD."]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Erreur BDD : " . $e->getMessage()]);
}
exit;
?>
