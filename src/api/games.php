<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

ini_set('display_errors', 0);
error_reporting(0);

try {
    $servername = "localhost";
    $username = "root";
    $password_db = "";
    $dbname = "sistema_desbravadores";

    $conn = new mysqli($servername, $username, $password_db, $dbname);

    if ($conn->connect_error) {
        throw new Exception("Erro de conexão: " . $conn->connect_error);
    }

    // POST - Criar ou atualizar brincadeira
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents("php://input"), true);
        $action = $_GET['action'] ?? 'create';

        if ($action === 'create') {
            $stmt = $conn->prepare("INSERT INTO games (name, description, use_timer, fixed_points, color) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssiis", $input['name'], $input['description'], $input['use_timer'], $input['fixed_points'], $input['color']);
            
            if ($stmt->execute()) {
                echo json_encode(["success" => true, "message" => "Brincadeira criada com sucesso"]);
            } else {
                echo json_encode(["success" => false, "message" => "Erro ao criar brincadeira"]);
            }
        } 
        elseif ($action === 'update') {
            $stmt = $conn->prepare("UPDATE games SET name=?, description=?, use_timer=?, fixed_points=?, color=? WHERE id=?");
            $stmt->bind_param("ssiisi", $input['name'], $input['description'], $input['use_timer'], $input['fixed_points'], $input['color'], $input['id']);
            
            if ($stmt->execute()) {
                echo json_encode(["success" => true, "message" => "Brincadeira atualizada com sucesso"]);
            } else {
                echo json_encode(["success" => false, "message" => "Erro ao atualizar brincadeira"]);
            }
        }
    }
    // GET - Listar brincadeiras
    else {
        $sql = "SELECT id, name, description, use_timer, fixed_points, color FROM games ORDER BY name";
        $result = $conn->query($sql);
        
        $games = [];
        
        while ($row = $result->fetch_assoc()) {
            $games[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'description' => $row['description'],
                'use_timer' => (bool)$row['use_timer'],
                'fixed_points' => (int)$row['fixed_points'],
                'color' => $row['color']
            ];
        }

        echo json_encode([
            "success" => true,
            "data" => $games
        ]);
    }

    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Erro: " . $e->getMessage()
    ]);
}
?>