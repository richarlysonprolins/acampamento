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

    // POST - Criar ou atualizar unidade
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents("php://input"), true);
        $action = $_GET['action'] ?? 'create';

        // Processar avatar - armazenar apenas o nome do arquivo
        $avatar = $input['avatar'] ?? '';
        
        // Se for uma URL completa, extrair apenas o nome do arquivo
        $baseUrl = getBaseUrl();
        $unitsPath = $baseUrl . "/assets/images/units/";
        if ($avatar && strpos($avatar, $unitsPath) !== false) {
            $avatar = str_replace($unitsPath, '', $avatar);
        }

        if ($action === 'create') {
            $stmt = $conn->prepare("INSERT INTO units (name, club_id, color, avatar) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("siss", $input['name'], $input['club_id'], $input['color'], $avatar);
            
            if ($stmt->execute()) {
                echo json_encode(["success" => true, "message" => "Unidade criada com sucesso"]);
            } else {
                echo json_encode(["success" => false, "message" => "Erro ao criar unidade"]);
            }
        } 
        elseif ($action === 'update') {
            $stmt = $conn->prepare("UPDATE units SET name=?, club_id=?, color=?, avatar=? WHERE id=?");
            $stmt->bind_param("sissi", $input['name'], $input['club_id'], $input['color'], $avatar, $input['id']);
            
            if ($stmt->execute()) {
                echo json_encode(["success" => true, "message" => "Unidade atualizada com sucesso"]);
            } else {
                echo json_encode(["success" => false, "message" => "Erro ao atualizar unidade"]);
            }
        }
    }
    // GET - Listar unidades
    else {
        $sql = "
            SELECT 
                u.id,
                u.name,
                u.color,
                u.avatar,
                u.club_id,
                c.name as club_name,
                COUNT(m.id) as member_count
            FROM units u
            LEFT JOIN clubs c ON u.club_id = c.id
            LEFT JOIN members m ON u.id = m.unit_id AND m.is_active = TRUE
            GROUP BY u.id, u.name, u.color, u.avatar, u.club_id, c.name
            ORDER BY u.name
        ";

        $result = $conn->query($sql);
        
        $units = [];
        $baseUrl = getBaseUrl();
        
        while ($row = $result->fetch_assoc()) {
            // Construir URL completa para o avatar
            $avatar = null;
            if (!empty($row['avatar'])) {
                $avatar = $baseUrl . "/assets/images/units/" . $row['avatar'];
            } else {
                $avatar = $baseUrl . "/assets/images/units/default.png";
            }
            
            $units[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'color' => $row['color'],
                'avatar' => $avatar,
                'club_id' => $row['club_id'],
                'club_name' => $row['club_name'],
                'member_count' => (int)$row['member_count']
            ];
        }

        echo json_encode([
            "success" => true,
            "data" => $units
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