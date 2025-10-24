<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

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
    // Verificar se foi enviado um arquivo
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Nenhum arquivo enviado ou erro no upload");
    }

    $avatar = $_FILES['avatar'];
    $type = $_POST['type'] ?? 'general';

    // Definir pastas por tipo
    $folders = [
        'user' => '../assets/images/users/',
        'unit' => '../assets/images/units/', 
        'member' => '../assets/images/members/',
        'general' => '../assets/images/general/'
    ];

    $folder = $folders[$type] ?? $folders['general'];

    // Criar pasta se não existir
    if (!is_dir($folder)) {
        mkdir($folder, 0777, true);
        
        // Criar arquivo default.png em cada pasta
        $defaultImage = imagecreate(100, 100);
        $backgroundColor = imagecolorallocate($defaultImage, 240, 240, 240);
        $textColor = imagecolorallocate($defaultImage, 180, 180, 180);
        imagestring($defaultImage, 5, 10, 45, "NO IMAGE", $textColor);
        imagepng($defaultImage, $folder . 'default.png');
        imagedestroy($defaultImage);
    }

    // Validar tipo de arquivo
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $file_type = mime_content_type($avatar['tmp_name']);
    
    if (!in_array($file_type, $allowed_types)) {
        throw new Exception("Tipo de arquivo não permitido. Use apenas JPEG, PNG, GIF ou WebP.");
    }

    // Validar tamanho (máximo 5MB)
    if ($avatar['size'] > 5 * 1024 * 1024) {
        throw new Exception("Arquivo muito grande. Tamanho máximo: 5MB.");
    }

    // Gerar nome único para o arquivo
    $extension = pathinfo($avatar['name'], PATHINFO_EXTENSION);
    $uniq_id = uniqid();
    $filename = $uniq_id . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '_', $avatar['name']);
    $filepath = $folder . $filename;

    // Mover arquivo para o servidor
    if (move_uploaded_file($avatar['tmp_name'], $filepath)) {
        $baseUrl = getBaseUrl();
        echo json_encode([
            "success" => true,
            "message" => "Upload realizado com sucesso",
            "filename" => $filename,
            "filepath" => $filepath,
            "url" => $baseUrl . "/assets/images/" . $type . "s/" . $filename
        ]);
    } else {
        throw new Exception("Erro ao salvar arquivo no servidor");
    }

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Erro no upload: " . $e->getMessage()
    ]);
}
?>