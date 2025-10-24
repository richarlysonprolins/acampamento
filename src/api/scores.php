<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

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

    // GET - Listar pontuações
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Verificar se é para buscar estatísticas do dashboard
        if (isset($_GET['dashboard_stats'])) {
            getDashboardStats($conn);
        } 
        // Buscar ranking
        else if (isset($_GET['ranking'])) {
            getRanking($conn);
        }
        // Buscar histórico completo
        else {
            getScores($conn);
        }
    }
    // POST - Criar nova pontuação
    else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents("php://input"), true);
        
        if (isset($_GET['action']) && $_GET['action'] === 'update') {
            updateScore($conn, $input);
        } else {
            createScore($conn, $input);
        }
    }
    // DELETE - Excluir pontuação
    else if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        deleteScore($conn);
    }

    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Erro: " . $e->getMessage()
    ]);
}

function createScore($conn, $input) {
    // Validar campos obrigatórios
    if (!isset($input['type']) || !isset($input['game_id']) || !isset($input['unit_id']) || !isset($input['value'])) {
        echo json_encode(["success" => false, "message" => "Campos obrigatórios faltando"]);
        return;
    }

    // Se for individual, validar member_id
    if ($input['type'] === 'individual' && empty($input['member_id'])) {
        echo json_encode(["success" => false, "message" => "Para pontuação individual, selecione um membro"]);
        return;
    }

    $stmt = $conn->prepare("INSERT INTO scores (type, game_id, unit_id, member_id, value, timer_value, description, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    // Pegar usuário logado (simplificado - em produção usar sessão/token)
    $user_id = 1;
    
    $member_id = $input['type'] === 'individual' ? $input['member_id'] : null;
    $timer_value = isset($input['timer_value']) ? $input['timer_value'] : null;
    $description = isset($input['description']) ? $input['description'] : null;
    
    $stmt->bind_param(
        "siiiiisi", 
        $input['type'], 
        $input['game_id'], 
        $input['unit_id'], 
        $member_id, 
        $input['value'], 
        $timer_value, 
        $description,
        $user_id
    );
    
    if ($stmt->execute()) {
        echo json_encode([
            "success" => true, 
            "message" => "Pontuação registrada com sucesso",
            "score_id" => $stmt->insert_id
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Erro ao registrar pontuação: " . $stmt->error]);
    }
}

function getScores($conn) {
    $sql = "
        SELECT 
            s.id,
            s.type,
            s.value,
            s.timer_value,
            s.description,
            s.created_at,
            u.name as unit_name,
            u.color as unit_color,
            m.name as member_name,
            g.name as game_name,
            g.use_timer,
            us.full_name as created_by_name
        FROM scores s
        LEFT JOIN units u ON s.unit_id = u.id
        LEFT JOIN members m ON s.member_id = m.id
        LEFT JOIN games g ON s.game_id = g.id
        LEFT JOIN users us ON s.created_by = us.id
        ORDER BY s.created_at DESC
        LIMIT 100
    ";

    $result = $conn->query($sql);
    
    $scores = [];
    
    while ($row = $result->fetch_assoc()) {
        $scores[] = [
            'id' => $row['id'],
            'type' => $row['type'],
            'value' => (int)$row['value'],
            'timer_value' => $row['timer_value'] ? (int)$row['timer_value'] : null,
            'description' => $row['description'],
            'created_at' => $row['created_at'],
            'unit_name' => $row['unit_name'],
            'unit_color' => $row['unit_color'],
            'member_name' => $row['member_name'],
            'game_name' => $row['game_name'],
            'use_timer' => (bool)$row['use_timer'],
            'created_by_name' => $row['created_by_name']
        ];
    }

    echo json_encode([
        "success" => true,
        "data" => $scores
    ]);
}

function getDashboardStats($conn) {
    // Total de pontos hoje
    $today = date('Y-m-d');
    $sql_today = "SELECT SUM(value) as total_points FROM scores WHERE DATE(created_at) = '$today'";
    $result_today = $conn->query($sql_today);
    $today_points = $result_today->fetch_assoc()['total_points'] ?? 0;

    // Total de unidades
    $sql_units = "SELECT COUNT(*) as total_units FROM units WHERE is_active = 1";
    $result_units = $conn->query($sql_units);
    $total_units = $result_units->fetch_assoc()['total_units'] ?? 0;

    // Total de membros
    $sql_members = "SELECT COUNT(*) as total_members FROM members WHERE is_active = 1";
    $result_members = $conn->query($sql_members);
    $total_members = $result_members->fetch_assoc()['total_members'] ?? 0;

    // Ranking das unidades
    $sql_ranking = "
        SELECT 
            u.id,
            u.name,
            u.color,
            COALESCE(SUM(s.value), 0) as total_points
        FROM units u
        LEFT JOIN scores s ON u.id = s.unit_id
        WHERE u.is_active = 1
        GROUP BY u.id, u.name, u.color
        ORDER BY total_points DESC
        LIMIT 10
    ";
    
    $result_ranking = $conn->query($sql_ranking);
    $ranking = [];
    $position = 1;
    
    while ($row = $result_ranking->fetch_assoc()) {
        $ranking[] = [
            'position' => $position++,
            'name' => $row['name'],
            'color' => $row['color'],
            'total_points' => (int)$row['total_points']
        ];
    }

    // Atividades recentes
    $sql_activities = "
        SELECT 
            s.id,
            s.value,
            s.created_at,
            u.name as unit_name,
            m.name as member_name,
            g.name as game_name,
            us.full_name as user_name
        FROM scores s
        LEFT JOIN units u ON s.unit_id = u.id
        LEFT JOIN members m ON s.member_id = m.id
        LEFT JOIN games g ON s.game_id = g.id
        LEFT JOIN users us ON s.created_by = us.id
        ORDER BY s.created_at DESC
        LIMIT 10
    ";
    
    $result_activities = $conn->query($sql_activities);
    $activities = [];
    
    while ($row = $result_activities->fetch_assoc()) {
        $time_ago = timeAgo($row['created_at']);
        $activities[] = [
            'id' => $row['id'],
            'user' => $row['user_name'],
            'action' => "registrou {$row['value']} pontos para {$row['unit_name']}" . 
                       ($row['member_name'] ? " - {$row['member_name']}" : "") . 
                       " em {$row['game_name']}",
            'points' => $row['value'],
            'time' => $time_ago,
            'avatar' => 'https://i.pravatar.cc/100?u=' . md5($row['user_name'])
        ];
    }

    echo json_encode([
        "success" => true,
        "data" => [
            "stats" => [
                "totalPoints" => (int)$today_points,
                "totalUnits" => (int)$total_units,
                "totalMembers" => (int)$total_members,
                "ranking" => $ranking
            ],
            "recentActivities" => $activities
        ]
    ]);
}

function getRanking($conn) {
    $sql = "
        SELECT 
            u.id,
            u.name,
            u.color,
            u.avatar,
            COALESCE(SUM(s.value), 0) as total_points,
            COUNT(s.id) as total_activities
        FROM units u
        LEFT JOIN scores s ON u.id = s.unit_id
        WHERE u.is_active = 1
        GROUP BY u.id, u.name, u.color, u.avatar
        ORDER BY total_points DESC
    ";
    
    $result = $conn->query($sql);
    $ranking = [];
    $position = 1;
    
    while ($row = $result->fetch_assoc()) {
        $ranking[] = [
            'id' => $row['id'],
            'position' => $position++,
            'name' => $row['name'],
            'color' => $row['color'],
            'avatar' => $row['avatar'],
            'total_points' => (int)$row['total_points'],
            'total_activities' => (int)$row['total_activities']
        ];
    }

    echo json_encode([
        "success" => true,
        "data" => $ranking
    ]);
}

function updateScore($conn, $input) {
    if (!isset($input['id'])) {
        echo json_encode(["success" => false, "message" => "ID da pontuação não informado"]);
        return;
    }

    $stmt = $conn->prepare("UPDATE scores SET value = ?, description = ?, timer_value = ? WHERE id = ?");
    $stmt->bind_param("isii", $input['value'], $input['description'], $input['timer_value'], $input['id']);
    
    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Pontuação atualizada com sucesso"]);
    } else {
        echo json_encode(["success" => false, "message" => "Erro ao atualizar pontuação"]);
    }
}

function deleteScore($conn) {
    $input = json_decode(file_get_contents("php://input"), true);
    
    if (!isset($input['id'])) {
        echo json_encode(["success" => false, "message" => "ID da pontuação não informado"]);
        return;
    }

    $stmt = $conn->prepare("DELETE FROM scores WHERE id = ?");
    $stmt->bind_param("i", $input['id']);
    
    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Pontuação excluída com sucesso"]);
    } else {
        echo json_encode(["success" => false, "message" => "Erro ao excluir pontuação"]);
    }
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;

    if ($diff < 60) {
        return 'agora';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return "há {$mins} min";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return "há {$hours} h";
    } else {
        $days = floor($diff / 86400);
        return "há {$days} dia" . ($days > 1 ? 's' : '');
    }
}
?>