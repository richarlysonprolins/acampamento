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

// Função para obter a URL base do projeto
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script = $_SERVER['SCRIPT_NAME'];
    $path = dirname($script);
    
    // Remove a parte /api do caminho se existir
    $basePath = str_replace('/api', '', $path);
    
    return $protocol . '://' . $host . $basePath;
}

try {
    $servername = "localhost";
    $username = "root";
    $password_db = "";
    $dbname = "sistema_desbravadores";

    $conn = new mysqli($servername, $username, $password_db, $dbname);

    if ($conn->connect_error) {
        throw new Exception("Erro de conexão: " . $conn->connect_error);
    }

    $baseUrl = getBaseUrl();

    // Buscar ranking de unidades com pontuação total
    $sql = "
        SELECT 
            u.id,
            u.name,
            u.color,
            u.avatar,
            COALESCE(SUM(s.value), 0) as total_points,
            COUNT(DISTINCT m.id) as member_count
        FROM units u
        LEFT JOIN scores s ON u.id = s.unit_id
        LEFT JOIN members m ON u.id = m.unit_id AND m.is_active = TRUE
        GROUP BY u.id, u.name, u.color, u.avatar
        ORDER BY total_points DESC
    ";

    $result = $conn->query($sql);
    
    $ranking = [];
    $position = 1;
    
    while ($row = $result->fetch_assoc()) {
        // Construir URL completa para o avatar da unidade
        $avatar = null;
        if (!empty($row['avatar'])) {
            $avatar = $baseUrl . "/assets/images/units/" . $row['avatar'];
        } else {
            $avatar = $baseUrl . "/assets/images/units/default.png";
        }
        
        $ranking[] = [
            'id' => $row['id'],
            'position' => $position,
            'name' => $row['name'],
            'color' => $row['color'],
            'avatar' => $avatar,
            'total_points' => (int)$row['total_points'],
            'member_count' => (int)$row['member_count']
        ];
        $position++;
    }

    $conn->close();

    echo json_encode([
        "success" => true,
        "data" => $ranking
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Erro: " . $e->getMessage()
    ]);
}
?>