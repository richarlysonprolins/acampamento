<?php
// CORS COMPLETO - COLOCAR NO TOPO ABSOLUTO
header("Access-Control-Allow-Origin: https://acampamentodesbravadores.netlify.app");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 86400"); // 24 horas

// RESPOSTA IMEDIATA PARA OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// AGORA SEU CÓDIGO NORMAL
ini_set('display_errors', 0);
error_reporting(0);

try {
    $input = file_get_contents("php://input");
    
    if (empty($input)) {
        throw new Exception("Dados vazios");
    }
    
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("JSON inválido: " . json_last_error_msg());
    }

    $servername = "sql302.infinityfree.com";
    $username = "if0_40247958";
    $password_db = "upEz38Dpv8";
    $dbname = "if0_40247958_sistema_desbravadores";

    $conn = new mysqli($servername, $username, $password_db, $dbname);

    if ($conn->connect_error) {
        throw new Exception("Erro de conexão: " . $conn->connect_error);
    }

    $user = $data["user"] ?? '';
    $password = $data["password"] ?? '';

    if (empty($user) || empty($password)) {
        throw new Exception("Usuário ou senha vazios");
    }

    // Busca o usuário
    $stmt = $conn->prepare("SELECT id, username, email, password, full_name, role, created_at FROM users WHERE (username = ? OR email = ?) AND is_active = TRUE");
    $stmt->bind_param("ss", $user, $user);
    
    if (!$stmt->execute()) {
        throw new Exception("Erro na consulta: " . $stmt->error);
    }
    
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(["success" => false, "message" => "Usuário não encontrado"]);
        exit();
    }

    $user_data = $result->fetch_assoc();

    // Limpar a senha do banco
    $senha_banco_limpa = trim($user_data['password']);

    // Verifica a senha
    $login_ok = false;

    if (strpos($senha_banco_limpa, '$2y$') === 0) {
        $login_ok = password_verify($password, $senha_banco_limpa);
    } else {
        $login_ok = ($password === $senha_banco_limpa);
    }

    if ($login_ok) {
        // PERMISSÕES SIMPLES (sem consulta à tabela permissions)
        $permissions = [
            'admin' => [
                "can_manage_users" => true,
                "can_manage_units" => true,
                "can_manage_members" => true,
                "can_register_scores" => true,
                "can_view_reports" => true,
                "can_register_games" => true

            ],
            'secretary' => [
                "can_manage_users" => false,
                "can_manage_units" => true,
                "can_manage_members" => true,
                "can_register_scores" => true,
                "can_view_reports" => true,
                "can_register_games" => true
            ],
            'viewer' => [
                "can_manage_users" => false,
                "can_manage_units" => false,
                "can_manage_members" => false,
                "can_register_scores" => false,
                "can_view_reports" => true,
                "can_register_games" => false
            ]
        ];
        
        $user_permissions = $permissions[$user_data['role']] ?? $permissions['viewer'];
        
        echo json_encode([
            "success" => true,
            "message" => "Login realizado com sucesso!",
            "user" => [
                "id" => $user_data['id'],
                "username" => $user_data['username'],
                "email" => $user_data['email'],
                "full_name" => $user_data['full_name'],
                "role" => $user_data['role'],
                "created_at" => $user_data['created_at'],
                "permissions" => $user_permissions
            ],
            "token" => base64_encode($user_data['id'] . ':' . $user_data['username'])
        ]);
        
    } else {
        echo json_encode([
            "success" => false, 
            "message" => "Senha incorreta"
        ]);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Erro: " . $e->getMessage()
    ]);
}
?>