<?php
require_once 'security_headers.php';
require 'db.php';
require_once 'csrf.php';
require_once 'security_logger.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$success = '';
$error = '';

// Check for success parameter from redirect
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success = "Profile updated successfully!";
}

// Get current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get user game statistics
$stats = [
    'total_games' => 0,
    'completed_games' => 0,
    'high_score' => 0,
    'total_score' => 0,
    'avg_score' => 0,
    'total_rounds' => 0,
    'avg_rounds' => 0,
    'total_asteroids_destroyed' => 0,
    'total_shots_fired' => 0,
    'avg_accuracy' => 0,
    'best_round' => 0
];

try {
    // Get games statistics
    $gamesStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_games,
            COUNT(CASE WHEN is_completed = TRUE THEN 1 END) as completed_games,
            COALESCE(MAX(final_score), 0) as high_score,
            COALESCE(SUM(final_score), 0) as total_score,
            COALESCE(AVG(final_score), 0) as avg_score,
            COALESCE(SUM(final_round), 0) as total_rounds,
            COALESCE(AVG(final_round), 0) as avg_rounds
        FROM games 
        WHERE user_id = ? AND is_completed = TRUE
    ");
    $gamesStmt->execute([$_SESSION['user_id']]);
    $gamesStats = $gamesStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($gamesStats) {
        $stats['total_games'] = $gamesStats['total_games'];
        $stats['completed_games'] = $gamesStats['completed_games'];
        $stats['high_score'] = $gamesStats['high_score'];
        $stats['total_score'] = $gamesStats['total_score'];
        $stats['avg_score'] = round($gamesStats['avg_score'], 0);
        $stats['total_rounds'] = $gamesStats['total_rounds'];
        $stats['avg_rounds'] = round($gamesStats['avg_rounds'], 1);
    }
    
    // Get rounds statistics
    $roundsStmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(asteroids_destroyed), 0) as total_asteroids_destroyed,
            COALESCE(SUM(shots_fired), 0) as total_shots_fired,
            COALESCE(AVG(accuracy_percentage), 0) as avg_accuracy,
            COALESCE(MAX(round_number), 0) as best_round
        FROM rounds r
        JOIN games g ON r.game_id = g.id
        WHERE g.user_id = ? AND g.is_completed = TRUE
    ");
    $roundsStmt->execute([$_SESSION['user_id']]);
    $roundsStats = $roundsStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($roundsStats) {
        $stats['total_asteroids_destroyed'] = $roundsStats['total_asteroids_destroyed'];
        $stats['total_shots_fired'] = $roundsStats['total_shots_fired'];
        $stats['avg_accuracy'] = round($roundsStats['avg_accuracy'], 1);
        $stats['best_round'] = $roundsStats['best_round'];
    }
    
} catch(PDOException $e) {
    error_log("Stats fetch error: " . $e->getMessage());
}

if ($_POST) {
    validateCSRF();
    
    $display_name = trim($_POST['display_name'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET display_name = ?, bio = ? WHERE id = ?");
        $stmt->execute([$display_name, $bio ?: null, $_SESSION['user_id']]);
        
        // Update session variables
        $_SESSION['display_name'] = $display_name ?: $_SESSION['username'];
        $_SESSION['bio'] = $bio;
        
        $success = "Profile updated successfully!";
        
        // Log profile update
        $securityLogger = new SecurityLogger($pdo);
        $securityLogger->logEvent('profile_update', $_SESSION['user_id'], $_SESSION['username'], 'User updated profile', 'low');
        
        // Redirect to refresh the page and show updated profile
        header("Location: profile.php?success=1");
        exit();
        
    } catch(PDOException $e) {
        error_log("Profile update error: " . $e->getMessage());
        $error = "Failed to update profile. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Cosmic Defender - Profile</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <style>
        .profile-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 100%);
            border-radius: 10px;
            color: white;
        }
        
        .profile-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .avatar-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto 20px;
            background: #333;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .avatar-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #4CAF50;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #333;
            border-radius: 5px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            box-sizing: border-box;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .btn {
            background: #4CAF50;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        
        .btn:hover {
            background: #45a049;
        }
        
        .nav-links {
            text-align: center;
            margin-top: 20px;
        }
        
        .nav-links a {
            color: #4CAF50;
            text-decoration: none;
            margin: 0 10px;
        }
        
        .nav-links a:hover {
            text-decoration: underline;
        }
        
        .success { color: #4CAF50; }
        .error { color: #f44336; }
        
        /* Game Statistics Styling */
        .stats-section {
            background: linear-gradient(135deg, rgba(26, 26, 46, 0.8) 0%, rgba(15, 15, 35, 0.8) 100%);
            padding: 25px;
            border-radius: 15px;
            border: 1px solid rgba(76, 175, 80, 0.3);
            margin-bottom: 30px;
            -webkit-backdrop-filter: blur(10px);
            backdrop-filter: blur(10px);
        }
        
        .stats-section h3 {
            color: #4CAF50;
            margin-bottom: 20px;
            text-align: center;
            font-size: 24px;
            text-shadow: 0 0 10px rgba(76, 175, 80, 0.5);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
        }
        
        .stat-item {
            background: rgba(76, 175, 80, 0.1);
            padding: 15px;
            border-radius: 10px;
            border: 1px solid rgba(76, 175, 80, 0.2);
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-item:hover {
            background: rgba(76, 175, 80, 0.2);
            transform: translateY(-2px);
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #4CAF50;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 12px;
            color: #ccc;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <div class="profile-header">
            <h2>Player Profile</h2>
            
            <div class="avatar-preview">
                <?php if (!empty($user['avatar_url'])): ?>
                    <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>" alt="Avatar">
                <?php else: ?>
                    <div style="color: #666; font-size: 40px;">👤</div>
                <?php endif; ?>
            </div>
            
            <h3><?php echo htmlspecialchars($user['display_name'] ?: $user['username']); ?></h3>
            <p style="color: #666;">Member since: <?php echo date('M d, Y', strtotime($user['created_at'])); ?></p>
            <?php if ($user['last_login']): ?>
                <p style="color: #666;">Last login: <?php echo date('M d, Y H:i', strtotime($user['last_login'])); ?></p>
            <?php endif; ?>
        </div>
        
        <!-- Game Statistics Section -->
        <div class="stats-section">
            <h3>🎮 Game Statistics</h3>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($stats['high_score']); ?></div>
                    <div class="stat-label">High Score</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $stats['completed_games']; ?></div>
                    <div class="stat-label">Completed Games</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($stats['total_score']); ?></div>
                    <div class="stat-label">Total Score</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($stats['avg_score']); ?></div>
                    <div class="stat-label">Average Score</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $stats['best_round']; ?></div>
                    <div class="stat-label">Best Round</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo round($stats['avg_rounds'], 1); ?></div>
                    <div class="stat-label">Average Rounds</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($stats['total_asteroids_destroyed']); ?></div>
                    <div class="stat-label">Asteroids Destroyed</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($stats['total_shots_fired']); ?></div>
                    <div class="stat-label">Shots Fired</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $stats['avg_accuracy']; ?>%</div>
                    <div class="stat-label">Average Accuracy</div>
                </div>
            </div>
        </div>
        
        <?php if ($success) echo "<p class='success'>" . htmlspecialchars($success) . "</p>"; ?>
        <?php if ($error) echo "<p class='error'>" . htmlspecialchars($error) . "</p>"; ?>
        
        <form method="post">
            <?php echo getCSRFField(); ?>
            
            <div class="form-group">
                <label for="display_name">Display Name:</label>
                <input type="text" id="display_name" name="display_name" 
                       value="<?php echo htmlspecialchars($user['display_name'] ?? ''); ?>" 
                       placeholder="How others see your name">
            </div>
            
            <div class="form-group">
                <label>Avatar:</label>
                <p style="color: #666; font-size: 14px;">Upload your avatar by clicking your profile picture in the game!</p>
            </div>
            
            <div class="form-group">
                <label for="bio">Bio:</label>
                <textarea id="bio" name="bio" placeholder="Tell other players about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
            </div>
            
            <button type="submit" class="btn">Update Profile</button>
        </form>
        
        <div class="nav-links">
            <a href="game.php">🎮 Play Game</a>
            <a href="logout.php">🚪 Logout</a>
        </div>
    </div>
</body>
</html>