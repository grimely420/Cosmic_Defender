<?php
require_once 'security_headers.php';
require 'db.php';
require_once 'csrf.php';
require_once 'security_logger.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized']));
}

// Set proper Content-Type header
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    validateCSRF();
    
    $file = $_FILES['avatar'];
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        exit(json_encode(['error' => 'Upload failed']));
    }
    
    // Check file type (only allow images)
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        exit(json_encode(['error' => 'Only JPEG, PNG, GIF, and WebP images are allowed']));
    }
    
    // Check file size (max 2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        exit(json_encode(['error' => 'File size must be less than 2MB']));
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'avatar_' . $_SESSION['user_id'] . '_' . time() . '.' . $extension;
    $uploadPath = __DIR__ . '/assets/avatars/' . $filename;
    
    // Create avatars directory if it doesn't exist
    $avatarsDir = __DIR__ . '/assets/avatars/';
    if (!is_dir($avatarsDir)) {
        mkdir($avatarsDir, 0755, true);
    }
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        // Update database with new avatar URL
        $avatarUrl = 'assets/avatars/' . $filename;
        $stmt = $pdo->prepare("UPDATE users SET avatar_url = ? WHERE id = ?");
        $stmt->execute([$avatarUrl, $_SESSION['user_id']]);
        
        // Update session
        $_SESSION['avatar_url'] = $avatarUrl;
        
        // Log avatar update
        $securityLogger = new SecurityLogger($pdo);
        $securityLogger->logEvent('avatar_upload', $_SESSION['user_id'], $_SESSION['username'], 'User uploaded new avatar', 'low');
        
        exit(json_encode(['success' => true, 'avatar_url' => $avatarUrl]));
    } else {
        exit(json_encode(['error' => 'Failed to save file']));
    }
} else {
    exit(json_encode(['error' => 'Invalid request']));
}
?>
