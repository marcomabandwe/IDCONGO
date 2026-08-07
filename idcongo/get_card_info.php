<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET");

require_once "config.php";

$username = isset($_GET["username"]) ? trim($_GET["username"]) : "";

if ($username == "") {
    echo json_encode([
        "status" => "error",
        "message" => "Identifiant manquant."
    ]);
    exit;
}

try {

    $sql = $pdo->prepare("
        SELECT *
        FROM proprietaires
        WHERE username = :username
        LIMIT 1
    ");

    $sql->execute([
        ":username" => $username
    ]);

    $user = $sql->fetch(PDO::FETCH_ASSOC);

    if (!$user) {

        echo json_encode([
            "status" => "error",
            "message" => "Cette carte n'existe pas."
        ]);

        exit;
    }

    unset($user["mot_de_passe"]);

    $sql2 = $pdo->prepare("
        SELECT *
        FROM proches_identite
        WHERE id_proprietaire = :id
        ORDER BY id DESC
    ");

    $sql2->execute([
        ":id" => $user["id"]
    ]);

    $proches = $sql2->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([

        "status"=>"success",

        "user"=>$user,

        "proches"=>$proches

    ]);

}
catch(PDOException $e){

    echo json_encode([

        "status"=>"error",

        "message"=>$e->getMessage()

    ]);

}

?>