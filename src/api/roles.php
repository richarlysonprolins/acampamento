<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

ini_set('display_errors', 0);
error_reporting(0);

try {
    $servername = "sql302.infinityfree.com";
    $username = "if0_40247958";
    $password_db = "upEz38Dpv8";
    $dbname = "if0_40247958_sistema_desbravadores";

    $conn = new mysqli($servername, $username, $password_db, $dbname);

    if ($conn->connect_error) {
        throw new Exception("Erro de conexão: " . $conn->connect_error);
    }

    $sql = "SELECT id, name FROM roles WHERE is_active = TRUE ORDER BY name";
    $result = $conn->query($sql);
    
    $roles = [];
    
    while ($row = $result->fetch_assoc()) {
        $roles[] = [
            'id' => $row['id'],
            'name' => $row['name']
        ];
    }

    echo json_encode([
        "success" => true,
        "data" => $roles
    ]);

    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Erro: " . $e->getMessage()
    ]);
}
?>