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
    $servername = "sql302.infinityfree.com";
    $username = "if0_40247958";
    $password_db = "upEz38Dpv8";
    $dbname = "if0_40247958_sistema_desbravadores";

    $conn = new mysqli($servername, $username, $password_db, $dbname);

    if ($conn->connect_error) {
        throw new Exception("Erro de conexão: " . $conn->connect_error);
    }

    $baseUrl = getBaseUrl();

    // DADOS REAIS DO BANCO
    
    // 1. Total de Unidades
    $stmt_units = $conn->prepare("SELECT COUNT(*) as total FROM units");
    $stmt_units->execute();
    $result_units = $stmt_units->get_result();
    $total_units = $result_units->fetch_assoc()['total'];
    $stmt_units->close();

    // 2. Total de Membros
    $stmt_members = $conn->prepare("SELECT COUNT(*) as total FROM members WHERE is_active = TRUE");
    $stmt_members->execute();
    $result_members = $stmt_members->get_result();
    $total_members = $result_members->fetch_assoc()['total'];
    $stmt_members->close();

    // 3. Total de Pontos Hoje
    $stmt_points = $conn->prepare("SELECT COALESCE(SUM(value), 0) as total FROM scores WHERE DATE(created_at) = CURDATE()");
    $stmt_points->execute();
    $result_points = $stmt_points->get_result();
    $total_points = $result_points->fetch_assoc()['total'];
    $stmt_points->close();

    // 4. Ranking das Unidades (para posição do usuário e top unidades)
    $stmt_ranking = $conn->prepare("
        SELECT 
            u.id,
            u.name, 
            u.color,
            u.avatar,
            COALESCE(SUM(s.value), 0) as total_points 
        FROM units u 
        LEFT JOIN scores s ON u.id = s.unit_id 
        GROUP BY u.id, u.name, u.color, u.avatar
        ORDER BY total_points DESC
    ");
    $stmt_ranking->execute();
    $result_ranking = $stmt_ranking->get_result();
    
    $ranking = [];
    $position = 1;
    while ($row = $result_ranking->fetch_assoc()) {
        // Construir URL completa para o avatar da unidade
        $avatar = null;
        if (!empty($row['avatar'])) {
            $avatar = $baseUrl . "/assets/images/units/" . $row['avatar'];
        } else {
            $avatar = $baseUrl . "/assets/images/units/default.png";
        }
        
        $ranking[] = [
            'position' => $position,
            'name' => $row['name'],
            'points' => (int)$row['total_points'],
            'total_points' => (int)$row['total_points'],
            'color' => $row['color'],
            'avatar' => $avatar
        ];
        $position++;
    }
    $stmt_ranking->close();

    // 5. Atividades Recentes (últimas pontuações)
    $stmt_activities = $conn->prepare("
        SELECT 
            s.id,
            s.type,
            s.value,
            s.description,
            s.created_at,
            u.name as unit_name,
            u.avatar as unit_avatar,
            m.name as member_name,
            m.avatar as member_avatar
        FROM scores s
        LEFT JOIN units u ON s.unit_id = u.id
        LEFT JOIN members m ON s.member_id = m.id
        ORDER BY s.created_at DESC 
        LIMIT 5
    ");
    $stmt_activities->execute();
    $result_activities = $stmt_activities->get_result();
    
    $recent_activities = [];
    while ($row = $result_activities->fetch_assoc()) {
        $activity_type = $row['type'] === 'individual' ? 'pontuação individual' : 'pontuação coletiva';
        $user_display = $row['type'] === 'individual' ? $row['member_name'] : $row['unit_name'];
        
        // Determinar avatar baseado no tipo de pontuação
        $avatar = null;
        if ($row['type'] === 'individual') {
            if (!empty($row['member_avatar'])) {
                $avatar = $baseUrl . "/assets/images/members/" . $row['member_avatar'];
            } else {
                $avatar = $baseUrl . "/assets/images/members/default.png";
            }
        } else {
            if (!empty($row['unit_avatar'])) {
                $avatar = $baseUrl . "/assets/images/units/" . $row['unit_avatar'];
            } else {
                $avatar = $baseUrl . "/assets/images/units/default.png";
            }
        }
        
        $recent_activities[] = [
            'id' => $row['id'],
            'user' => $user_display,
            'action' => "registrou $activity_type",
            'points' => (int)$row['value'],
            'time' => time_elapsed_string($row['created_at']),
            'avatar' => $avatar
        ];
    }
    $stmt_activities->close();

    $conn->close();

    // Resposta com dados reais
    echo json_encode([
        "success" => true,
        "data" => [
            "stats" => [
                "totalUnits" => (int)$total_units,
                "totalMembers" => (int)$total_members,
                "totalPoints" => (int)$total_points,
                "ranking" => $ranking
            ],
            "recentActivities" => $recent_activities
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Erro: " . $e->getMessage()
    ]);
}

// Função para formatar tempo (ex: "há 5 minutos")
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'ano',
        'm' => 'mês',
        'w' => 'semana',
        'd' => 'dia',
        'h' => 'hora',
        'i' => 'minuto',
        's' => 'segundo',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? 'Há ' . implode(', ', $string) : 'Agora mesmo';
}
?>