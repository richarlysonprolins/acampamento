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

    // POST - Atualizar perfil, senha ou avatar
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents("php://input"), true);
        $action = $_GET['action'] ?? '';

        // ID do usuário fixo por enquanto (em produção, pegar do token JWT)
        $user_id = 1;

        if ($action === 'update') {
            // Buscar role atual do usuário
            $current_user_sql = "SELECT role FROM users WHERE id = ?";
            $current_user_stmt = $conn->prepare($current_user_sql);
            $current_user_stmt->bind_param("i", $user_id);
            $current_user_stmt->execute();
            $current_user_result = $current_user_stmt->get_result();
            $current_user = $current_user_result->fetch_assoc();
            
            // Se for admin ou secretary, permite editar username
            if (in_array($current_user['role'], ['admin', 'secretary'])) {
                $stmt = $conn->prepare("UPDATE users SET username=?, full_name=?, email=?, phone=?, bio=? WHERE id=?");
                $stmt->bind_param("sssssi", $input['username'], $input['full_name'], $input['email'], $input['phone'], $input['bio'], $user_id);
            } else {
                // Para viewers, não permite editar username
                $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, phone=?, bio=? WHERE id=?");
                $stmt->bind_param("ssssi", $input['full_name'], $input['email'], $input['phone'], $input['bio'], $user_id);
            }
            
            if ($stmt->execute()) {
                echo json_encode(["success" => true, "message" => "Perfil atualizado com sucesso"]);
            } else {
                echo json_encode(["success" => false, "message" => "Erro ao atualizar perfil: " . $stmt->error]);
            }
        } 
        elseif ($action === 'update_avatar') {
            // Atualizar apenas o avatar
            $stmt = $conn->prepare("UPDATE users SET avatar=? WHERE id=?");
            $stmt->bind_param("si", $input['avatar'], $user_id);
            
            if ($stmt->execute()) {
                echo json_encode(["success" => true, "message" => "Avatar atualizado com sucesso"]);
            } else {
                echo json_encode(["success" => false, "message" => "Erro ao atualizar avatar"]);
            }
        }
        elseif ($action === 'change_password') {
            // Verificar senha atual
            $check_stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $check_stmt->bind_param("i", $user_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            $user = $result->fetch_assoc();

            if (!$user) {
                echo json_encode(["success" => false, "message" => "Usuário não encontrado"]);
                exit;
            }

            // Verificar senha atual
            if (password_verify($input['current'], $user['password'])) {
                // Atualizar senha
                $new_password_hash = password_hash($input['new'], PASSWORD_DEFAULT);
                $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update_stmt->bind_param("si", $new_password_hash, $user_id);
                
                if ($update_stmt->execute()) {
                    echo json_encode(["success" => true, "message" => "Senha alterada com sucesso"]);
                } else {
                    echo json_encode(["success" => false, "message" => "Erro ao alterar senha"]);
                }
            } else {
                echo json_encode(["success" => false, "message" => "Senha atual incorreta"]);
            }
        }
    }
    // GET - Buscar dados do perfil
    else {
        // ID do usuário fixo por enquanto
        $user_id = 1;

        // Buscar dados do usuário
        $user_sql = "SELECT id, username, email, full_name, role, avatar, phone, bio, is_active, created_at, last_login FROM users WHERE id = ?";
        $user_stmt = $conn->prepare($user_sql);
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        $user_data = $user_result->fetch_assoc();

        if (!$user_data) {
            throw new Exception("Usuário não encontrado");
        }

        // Buscar estatísticas
        $stats_sql = "SELECT COUNT(*) as scores_registered FROM scores WHERE created_by = ?";
        $stats_stmt = $conn->prepare($stats_sql);
        $stats_stmt->bind_param("i", $user_id);
        $stats_stmt->execute();
        $stats_result = $stats_stmt->get_result();
        $stats_data = $stats_result->fetch_assoc();

        // Processar avatar
        $baseUrl = getBaseUrl();
        $avatar = null;
        if (!empty($user_data['avatar'])) {
            $avatar = $baseUrl . "/assets/images/users/" . $user_data['avatar'];
        } else {
            $avatar = $baseUrl . "/assets/images/users/default.png";
        }

        $response_data = [
            "user" => [
                'id' => $user_data['id'],
                'username' => $user_data['username'],
                'email' => $user_data['email'],
                'full_name' => $user_data['full_name'],
                'role' => $user_data['role'],
                'avatar' => $avatar,
                'phone' => $user_data['phone'],
                'bio' => $user_data['bio'] ?? '',
                'is_active' => (bool)$user_data['is_active'],
                'created_at' => $user_data['created_at'],
                'last_login' => $user_data['last_login']
            ],
            "stats" => [
                'scoresRegistered' => (int)$stats_data['scores_registered'],
                'lastLogin' => $user_data['last_login']
            ]
        ];

        echo json_encode([
            "success" => true,
            "data" => $response_data
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