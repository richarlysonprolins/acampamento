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
    $servername = "localhost";
    $username = "root";
    $password_db = "";
    $dbname = "sistema_desbravadores";

    $conn = new mysqli($servername, $username, $password_db, $dbname);

    if ($conn->connect_error) {
        throw new Exception("Erro de conexão: " . $conn->connect_error);
    }

    $game_id = $_GET['game_id'] ?? 0;

    $sql = "
        SELECT 
            u.id as unit_id,
            u.name as unit_name,
            u.color,
            MIN(s.timer_value) as best_time
        FROM scores s
        LEFT JOIN units u ON s.unit_id = u.id
        WHERE s.game_id = ? AND s.timer_value IS NOT NULL
        GROUP BY u.id, u.name, u.color
        ORDER BY best_time ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $game_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $rankings = [];
    
    while ($row = $result->fetch_assoc()) {
        $rankings[] = [
            'unit_id' => $row['unit_id'],
            'unit_name' => $row['unit_name'],
            'color' => $row['color'],
            'best_time' => (int)$row['best_time']
        ];
    }

    echo json_encode([
        "success" => true,
        "data" => $rankings
    ]);

    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Erro: " . $e->getMessage()
    ]);
}
?>