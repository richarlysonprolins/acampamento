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
    $servername = "sql302.infinityfree.com";
    $username = "if0_40247958";
    $password_db = "upEz38Dpv8";
    $dbname = "if0_40247958_sistema_desbravadores";

    $conn = new mysqli($servername, $username, $password_db, $dbname);

    if ($conn->connect_error) {
        throw new Exception("Erro de conexão: " . $conn->connect_error);
    }

    // POST - Criar ou atualizar usuário
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents("php://input"), true);
        $action = $_GET['action'] ?? 'create';

        // Processar avatar - armazenar apenas o nome do arquivo
        $avatar = $input['avatar'] ?? '';
        
        // Se for uma URL completa, extrair apenas o nome do arquivo
        $baseUrl = getBaseUrl();
        $usersPath = $baseUrl . "/assets/images/users/";
        if ($avatar && strpos($avatar, $usersPath) !== false) {
            $avatar = str_replace($usersPath, '', $avatar);
        }

        if ($action === 'create') {
            // Criar usuário
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, full_name, avatar, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $password_hash = password_hash($input['password'], PASSWORD_DEFAULT);
            $stmt->bind_param("ssssssi", $input['username'], $input['email'], $password_hash, $input['role'], $input['full_name'], $avatar, $input['is_active']);
            
            if ($stmt->execute()) {
                echo json_encode(["success" => true, "message" => "Usuário criado com sucesso"]);
            } else {
                echo json_encode(["success" => false, "message" => "Erro ao criar usuário"]);
            }
        } 
        elseif ($action === 'update') {
            // Atualizar usuário
            $stmt = $conn->prepare("UPDATE users SET username=?, email=?, role=?, full_name=?, avatar=?, is_active=? WHERE id=?");
            $stmt->bind_param("sssssii", $input['username'], $input['email'], $input['role'], $input['full_name'], $avatar, $input['is_active'], $input['id']);
            
            if ($stmt->execute()) {
                echo json_encode(["success" => true, "message" => "Usuário atualizado com sucesso"]);
            } else {
                echo json_encode(["success" => false, "message" => "Erro ao atualizar usuário"]);
            }
        }
    }
    // GET - Listar usuários
    else {
        $sql = "SELECT id, username, email, full_name, role, avatar, phone, is_active, created_at FROM users ORDER BY full_name";
        $result = $conn->query($sql);
        
        $users = [];
        $baseUrl = getBaseUrl();
        
        while ($row = $result->fetch_assoc()) {
            // Construir URL completa para o avatar
            $avatar = null;
            if (!empty($row['avatar'])) {
                $avatar = $baseUrl . "/assets/images/users/" . $row['avatar'];
            } else {
                $avatar = $baseUrl . "/assets/images/users/default.png";
            }
            
            $users[] = [
                'id' => $row['id'],
                'username' => $row['username'],
                'email' => $row['email'],
                'full_name' => $row['full_name'],
                'role' => $row['role'],
                'avatar' => $avatar,
                'phone' => $row['phone'],
                'is_active' => (bool)$row['is_active'],
                'created_at' => $row['created_at']
            ];
        }

        echo json_encode([
            "success" => true,
            "data" => $users
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