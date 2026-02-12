<?php
require_once 'security_headers.php';
require 'db.php';
require_once 'rate_limiter.php';
session_start();

// Set proper Content-Type header for JSON responses
header('Content-Type: application/json; charset=utf-8');

// Security: Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized']));
}

// Security: Rate limiting for game actions
$rateLimiter = new RateLimiter($pdo);
$clientIP = $rateLimiter->getClientIP();

if (!$rateLimiter->checkLimit($clientIP, 'game_action')) {
    http_response_code(429);
    exit(json_encode(['error' => 'Too many requests']));
}

// Security: Validate JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action'])) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid request']));
}

// Security: Validate and sanitize all input values
function validateGameInput($value, $type = 'int', $min = 0, $max = PHP_INT_MAX) {
    if ($type === 'int') {
        if (!is_numeric($value)) return false;
        $value = intval($value);
        if ($value < $min || $value > $max) return false;
        return $value;
    }
    return false;
}

// Action received: $input['action']

if ($input['action'] === 'start_game') {
    // Security: Check if user already has too many active games
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM games WHERE user_id = ? AND is_completed = FALSE");
    $checkStmt->execute([$_SESSION['user_id']]);
    $activeGames = $checkStmt->fetchColumn();
    
    if ($activeGames > 5) { // Allow max 5 incomplete games total
        http_response_code(429);
        exit(json_encode(['error' => 'Too many active games. Please complete existing games first.']));
    }
    
    // Starting new game for user
    $stmt = $pdo->prepare("INSERT INTO games (user_id) VALUES (?)");
    $stmt->execute([$_SESSION['user_id']]);
    $gameId = $pdo->lastInsertId();
    // Game ID generated
    echo json_encode(['game_id' => $gameId]);
} elseif ($input['action'] === 'save_round') {
    // Security: Validate all inputs
    $gameId = validateGameInput($input['game_id'] ?? 0, 'int', 1);
    $roundNumber = validateGameInput($input['round_number'] ?? 0, 'int', 1, 1000);
    $score = validateGameInput($input['score'] ?? 0, 'int', 0, 10000000);
    $asteroidsDestroyed = validateGameInput($input['asteroids_destroyed'] ?? 0, 'int', 0, 10000);
    $shotsFired = validateGameInput($input['shots_fired'] ?? 0, 'int', 0, 10000);
    $accuracy = validateGameInput($input['accuracy'] ?? 0, 'int', 0, 100);
    
    if (!$gameId || !$roundNumber || $score === false || $asteroidsDestroyed === false || $shotsFired === false || $accuracy === false) {
        http_response_code(400);
        exit(json_encode(['error' => 'Invalid game data']));
    }
    
    // Security: Verify game ownership
    $verifyStmt = $pdo->prepare("SELECT user_id FROM games WHERE id = ? AND user_id = ? AND is_completed = FALSE");
    $verifyStmt->execute([$gameId, $_SESSION['user_id']]);
    if (!$verifyStmt->fetch()) {
        http_response_code(403);
        exit(json_encode(['error' => 'Invalid game']));
    }
    
    // Security: Check for reasonable game progression
    $checkStmt = $pdo->prepare("SELECT MAX(round_number) as max_round FROM rounds WHERE game_id = ?");
    $checkStmt->execute([$gameId]);
    $lastRound = $checkStmt->fetchColumn() ?? 0;
    
    if ($roundNumber > $lastRound + 1) {
        http_response_code(400);
        exit(json_encode(['error' => 'Invalid round progression']));
    }
    
    // Saving round statistics
    $stmt = $pdo->prepare("INSERT INTO rounds (game_id, round_number, score, asteroids_destroyed, shots_fired, accuracy_percentage) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $gameId,
        $roundNumber,
        $score,
        $asteroidsDestroyed,
        $shotsFired,
        $accuracy
    ]);
    echo json_encode(['success' => true]);
} elseif ($input['action'] === 'save_final_score') {
    // Security: Validate all inputs
    $gameId = validateGameInput($input['game_id'] ?? 0, 'int', 1);
    $roundNumber = validateGameInput($input['round_number'] ?? 0, 'int', 1, 1000);
    $score = validateGameInput($input['score'] ?? 0, 'int', 0, 10000000);
    $asteroidsDestroyed = validateGameInput($input['asteroids_destroyed'] ?? 0, 'int', 0, 10000);
    $shotsFired = validateGameInput($input['shots_fired'] ?? 0, 'int', 0, 10000);
    $accuracy = validateGameInput($input['accuracy'] ?? 0, 'int', 0, 100);
    
    if (!$gameId || !$roundNumber || $score === false || $asteroidsDestroyed === false || $shotsFired === false || $accuracy === false) {
        http_response_code(400);
        exit(json_encode(['error' => 'Invalid game data']));
    }
    
    try {
        // Security: Verify game ownership and not already completed
        $verifyStmt = $pdo->prepare("SELECT user_id, is_completed FROM games WHERE id = ? AND user_id = ?");
        $verifyStmt->execute([$gameId, $_SESSION['user_id']]);
        $game = $verifyStmt->fetch();
        
        if (!$game) {
            http_response_code(403);
            exit(json_encode(['error' => 'Invalid game']));
        }
        
        if ($game['is_completed']) {
            http_response_code(400);
            exit(json_encode(['error' => 'Game already completed']));
        }
        
        // Security: Validate score progression
        $checkStmt = $pdo->prepare("SELECT SUM(score) as total_score FROM rounds WHERE game_id = ?");
        $checkStmt->execute([$gameId]);
        $previousScore = $checkStmt->fetchColumn() ?? 0;
        
        // Score should be reasonable (not jump by more than 50000 in one round)
        if ($score - $previousScore > 50000) {
            http_response_code(400);
            exit(json_encode(['error' => 'Invalid score progression']));
        }
        
        // First save the final round with detailed stats
        $stmt = $pdo->prepare("INSERT INTO rounds (game_id, round_number, score, asteroids_destroyed, shots_fired, accuracy_percentage) VALUES (?, ?, ?, ?, ?, ?)");
        $result1 = $stmt->execute([
            $gameId,
            $roundNumber,
            $score,
            $asteroidsDestroyed,
            $shotsFired,
            $accuracy
        ]);
        
        // Then end the game
        $stmt = $pdo->prepare("UPDATE games SET ended_at = NOW(), final_score = ?, final_round = ?, is_completed = TRUE WHERE id = ? AND user_id = ?");
        $result2 = $stmt->execute([$score, $roundNumber, $gameId, $_SESSION['user_id']]);
        // Game updated
        
        // Verify the update worked
        // Step 3: Game update verified
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        // Error saving final score
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>