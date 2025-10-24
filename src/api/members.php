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

    // POST - Criar ou atualizar membro
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents("php://input"), true);
        $action = $_GET['action'] ?? 'create';

        // Processar avatar - armazenar apenas o nome do arquivo
        $avatar = $input['avatar'] ?? '';
        
        // Se for uma URL completa, extrair apenas o nome do arquivo
        $baseUrl = getBaseUrl();
        $membersPath = $baseUrl . "/assets/images/members/";
        if ($avatar && strpos($avatar, $membersPath) !== false) {
            $avatar = str_replace($membersPath, '', $avatar);
        }

        if ($action === 'create') {
            $stmt = $conn->prepare("INSERT INTO members (name, role_id, unit_id, avatar, birth_date, is_active) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("siissi", $input['name'], $input['role_id'], $input['unit_id'], $avatar, $input['birth_date'], $input['is_active']);
            
            if ($stmt->execute()) {
                echo json_encode(["success" => true, "message" => "Membro criado com sucesso"]);
            } else {
                echo json_encode(["success" => false, "message" => "Erro ao criar membro"]);
            }
        } 
        elseif ($action === 'update') {
            $stmt = $conn->prepare("UPDATE members SET name=?, role_id=?, unit_id=?, avatar=?, birth_date=?, is_active=? WHERE id=?");
            $stmt->bind_param("siissii", $input['name'], $input['role_id'], $input['unit_id'], $avatar, $input['birth_date'], $input['is_active'], $input['id']);
            
            if ($stmt->execute()) {
                echo json_encode(["success" => true, "message" => "Membro atualizada com sucesso"]);
            } else {
                echo json_encode(["success" => false, "message" => "Erro ao atualizar membro"]);
            }
        }
    }
    // GET - Listar membros
    else {
        $sql = "
            SELECT 
                m.id,
                m.name,
                m.avatar,
                m.birth_date,
                m.is_active,
                m.role_id,
                m.unit_id,
                u.name as unit_name,
                r.name as role_name
            FROM members m
            LEFT JOIN units u ON m.unit_id = u.id
            LEFT JOIN roles r ON m.role_id = r.id
            ORDER BY m.name
        ";

        $result = $conn->query($sql);
        
        $members = [];
        $baseUrl = getBaseUrl();
        
        while ($row = $result->fetch_assoc()) {
            // Construir URL completa para o avatar
            $avatar = null;
            if (!empty($row['avatar'])) {
                $avatar = $baseUrl . "/assets/images/members/" . $row['avatar'];
            } else {
                $avatar = $baseUrl . "/assets/images/members/default.png";
            }
            
            $members[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'avatar' => $avatar,
                'birth_date' => $row['birth_date'],
                'is_active' => (bool)$row['is_active'],
                'role_id' => $row['role_id'],
                'unit_id' => $row['unit_id'],
                'unit_name' => $row['unit_name'],
                'role_name' => $row['role_name'] ?: 'Membro'
            ];
        }

        echo json_encode([
            "success" => true,
            "data" => $members
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