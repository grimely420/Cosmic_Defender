<?php
require_once 'security_headers.php';
session_start();
require 'db.php';

// Fetch leaderboard data with avatar information
$leaderboard = [];
try {
    // Explicitly select only needed columns to prevent data leakage
    $stmt = $pdo->prepare("SELECT rank, username, display_name, avatar_url, score, round_reached, achieved_at FROM leaderboard ORDER BY rank ASC LIMIT :limit");
    $stmt->bindValue(':limit', 10, PDO::PARAM_INT);
    $stmt->execute();
    $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // Silently fail if view doesn't exist
    error_log("Leaderboard fetch failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Cosmic Defender - Login</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <style>
        .landing-container {
            text-align: center;
            padding: 30px 50px;
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        /* Game Title Section */
        .game-title-section {
            text-align: center;
            margin-bottom: 40px;
            padding: 30px;
            background: linear-gradient(135deg, rgba(26, 26, 46, 0.8) 0%, rgba(15, 15, 35, 0.8) 100%);
            border-radius: 15px;
            border: 1px solid rgba(76, 175, 80, 0.3);
            -webkit-backdrop-filter: blur(10px);
            backdrop-filter: blur(10px);
        }
        
        .game-title {
            font-size: 4em;
            font-weight: bold;
            color: #4CAF50;
            text-shadow: 0 0 20px rgba(76, 175, 80, 0.5);
            margin-bottom: 15px;
            letter-spacing: 3px;
            text-transform: uppercase;
            animation: glow 2s ease-in-out infinite alternate;
        }
        
        @keyframes glow {
            from { text-shadow: 0 0 20px rgba(76, 175, 80, 0.5); }
            to { text-shadow: 0 0 30px rgba(76, 175, 80, 0.8), 0 0 40px rgba(76, 175, 80, 0.6); }
        }
        
        .game-subtitle {
            font-size: 1.5em;
            color: #ffffff;
            margin-bottom: 20px;
            opacity: 0.9;
        }
        
        .game-story {
            font-size: 1.1em;
            color: #cccccc;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
            font-style: italic;
        }
        
        .game-description {
            font-size: 1.2em;
            color: #ccc;
            margin-bottom: 40px;
            max-width: 600px;
        }
        
        .auth-buttons {
            display: flex;
            gap: 20px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 15px 30px;
            font-size: 1.1em;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #4CAF50;
            color: white;
        }
        
        .btn-secondary {
            background: transparent;
            color: #4CAF50;
            border: 2px solid #4CAF50;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.3);
        }
        
        .features {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin: 50px 0;
            flex-wrap: wrap;
        }
        
        .feature {
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 8px;
            width: 200px;
        }
        
        .feature h3 {
            color: #4CAF50;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="landing-container">
        <!-- Game Title and Story Section -->
        <div class="game-title-section">
            <h1 class="game-title">COSMIC DEFENDER</h1>
            <p class="game-subtitle">The Fate of Humanity Rests in Your Hands</p>
            <p class="game-story">
                In the year 2157, Earth's last remaining starfighter pilot stands against an endless asteroid field 
                threatening the remnants of human civilization. As asteroids containing precious energy crystals 
                hurtle toward the last colony, you must destroy them to harvest their power while avoiding certain death. 
                Each crystal brings humanity one step closer to survival. Will you be our savior or our final chapter?
            </p>
        </div>
        
        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="auth-buttons">
                <a href="game.php" class="btn btn-primary">Play Game</a>
                <a href="logout.php" class="btn btn-secondary">Logout</a>
            </div>
        <?php else: ?>
            <div class="auth-buttons">
                <a href="login.php" class="btn btn-primary">Login</a>
                <a href="register.php" class="btn btn-secondary">Register</a>
            </div>
        <?php endif; ?>
        
        <div class="features">
            <div class="feature">
                <h3>🎯 Progressive Difficulty</h3>
                <p>Game gets harder each round with more asteroids</p>
            </div>
            <div class="feature">
                <h3>🏆 Score Tracking</h3>
                <p>Track your scores and compete for the top spot</p>
            </div>
            <div class="feature">
                <h3>🎮 Multiplayer</h3>
                <p>Play against other players and climb the leaderboard</p>
            </div>
            <div class="feature">
                <h3>💾 Save Progress</h3>
                <p>Your games and scores are saved automatically</p>
            </div>
        </div>
        
        <div class="game-preview">
            <h2>Preview Game</h2>
            <canvas id="previewCanvas" width="400" height="300" style="border: 2px solid #333; margin-top: 20px;"></canvas>
        </div>
        
        <?php if (!empty($leaderboard)): ?>
        <div class="leaderboard-section">
            <h2 style="color: #4CAF50; margin-bottom: 20px;">🏆 Top Players Leaderboard</h2>
            <table class="leaderboard-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Player</th>
                        <th>Score</th>
                        <th>Round</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leaderboard as $entry): ?>
                    <tr>
                        <td class="rank-<?php echo $entry['rank']; ?>">#<?php echo $entry['rank']; ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <?php if (!empty($entry['avatar_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($entry['avatar_url']); ?>" alt="Avatar" width="30" height="30" style="border-radius: 50%; object-fit: cover;">
                                <?php else: ?>
                                    <span style="font-size: 20px;">👤</span>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($entry['display_name'] ?: $entry['username']); ?>
                            </div>
                        </td>
                        <td><?php echo number_format($entry['score']); ?></td>
                        <td><?php echo $entry['round_reached']; ?></td>
                        <td><?php echo date('M d, Y', strtotime($entry['achieved_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Simple preview animation
        const canvas = document.getElementById('previewCanvas');
        const ctx = canvas.getContext('2d');
        
        function drawPreview() {
            ctx.fillStyle = 'black';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            // Draw some sample coins and spaceship
            ctx.strokeStyle = 'white';
            ctx.fillStyle = 'white';
            
            // Spaceship (more detailed design)
            ctx.save();
            ctx.translate(canvas.width/2, canvas.height/2);
            
            // Main body
            ctx.beginPath();
            ctx.moveTo(20, 0);
            ctx.lineTo(-15, -12);
            ctx.lineTo(-10, -8);
            ctx.lineTo(-10, 8);
            ctx.lineTo(-15, 12);
            ctx.closePath();
            ctx.stroke();
            
            // Cockpit
            ctx.beginPath();
            ctx.arc(5, 0, 4, 0, Math.PI * 2);
            ctx.stroke();
            
            // Engine flames
            ctx.strokeStyle = 'orange';
            ctx.beginPath();
            ctx.moveTo(-10, -5);
            ctx.lineTo(-20, -8);
            ctx.moveTo(-10, 5);
            ctx.lineTo(-20, 8);
            ctx.stroke();
            
            ctx.restore();
            
            // Coins instead of asteroids
            function drawCoin(x, y, radius) {
                // Outer circle
                ctx.beginPath();
                ctx.arc(x, y, radius, 0, Math.PI * 2);
                ctx.stroke();
                
                // Inner circle (coin design)
                ctx.beginPath();
                ctx.arc(x, y, radius * 0.7, 0, Math.PI * 2);
                ctx.stroke();
                
                // Dollar sign in center
                ctx.font = `${radius}px Arial`;
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText('$', x, y);
            }
            
            drawCoin(100, 100, 20);
            drawCoin(300, 150, 15);
            drawCoin(200, 250, 10);
        }
        
        drawPreview();
    </script>
</body>
</html>