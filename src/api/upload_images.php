<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

$targetFolder = $_POST['folder'] ?? ''; // Ex: users, units, members
$imagem = $_FILES['file'] ?? null;

if (!$imagem || $imagem['error'] !== 0 || !$targetFolder) {
    echo json_encode(["success" => false, "message" => "Imagem ou pasta inválida."]);
    exit;
}

$baseDir = __DIR__ . "/../assets/images/" . $targetFolder;
if (!file_exists($baseDir)) {
    mkdir($baseDir, 0777, true);
}

$uniq_id = uniqid();
$fileName = $uniq_id . "_" . basename($imagem["name"]);
$caminhoServidor = $baseDir . "/" . $fileName;
$caminhoBanco = "assets/images/$targetFolder/$fileName";

if (move_uploaded_file($imagem["tmp_name"], $caminhoServidor)) {
    echo json_encode([
        "success" => true,
        "message" => "Imagem enviada com sucesso!",
        "path" => $caminhoBanco
    ]);
} else {
    echo json_encode(["success" => false, "message" => "Falha ao mover imagem."]);
}
?>
