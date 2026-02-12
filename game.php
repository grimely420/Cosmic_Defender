<?php
require_once 'security_headers.php';
require_once 'csrf.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Cosmic Defender - Game</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <style>
        /* Avatar Modal Styles */
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: linear-gradient(135deg, #1a1a2e 0%, #0f0f23 100%);
            padding: 30px;
            border-radius: 15px;
            border: 2px solid #4CAF50;
            max-width: 500px;
            width: 90%;
            color: white;
            box-shadow: 0 8px 32px rgba(76, 175, 80, 0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            border-bottom: 1px solid #333;
            padding-bottom: 15px;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #4CAF50;
            font-size: 24px;
            text-shadow: 0 0 10px rgba(76, 175, 80, 0.5);
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        
        .close:hover {
            color: #fff;
        }
        
        .upload-area {
            border: 2px dashed #4CAF50;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(76, 175, 80, 0.1);
        }
        
        .upload-area:hover {
            background: rgba(76, 175, 80, 0.2);
            border-color: #66BB6A;
        }
        
        .btn {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
        }
        
        .btn:hover {
            background: linear-gradient(135deg, #45a049 0%, #4CAF50 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
        }
        
        /* Welcome message styling */
        .welcome-message {
            color: #ffffff;
            font-size: 16px;
            margin-right: 20px;
        }
        
        /* Side Layout Styles - Proportional Scaling System */
        .game-layout {
            display: flex;
            align-items: flex-start;
            justify-content: center;
            gap: 2vw;
            margin-bottom: 2vh;
            padding: 0 2vw;
            width: 100%;
            height: 100%;
            box-sizing: border-box;
        }
        
        .player-info-side {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.5vh;
            background: linear-gradient(135deg, rgba(26, 26, 46, 0.8) 0%, rgba(15, 15, 35, 0.8) 100%);
            padding: 2vh;
            border-radius: 1.5vh;
            border: 1px solid rgba(76, 175, 80, 0.3);
            -webkit-backdrop-filter: blur(10px);
            backdrop-filter: blur(10px);
            width: 12vw;
            min-width: 80px;
            max-width: 200px;
            flex-shrink: 0;
            height: fit-content;
        }
        
        .game-center {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex-grow: 1;
            max-width: 70vw;
            height: 100%;
        }
        
        .game-stats-side {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.5vh;
            background: linear-gradient(135deg, rgba(26, 26, 46, 0.8) 0%, rgba(15, 15, 35, 0.8) 100%);
            padding: 2vh;
            border-radius: 1.5vh;
            border: 1px solid rgba(76, 175, 80, 0.3);
            -webkit-backdrop-filter: blur(10px);
            backdrop-filter: blur(10px);
            width: 12vw;
            min-width: 80px;
            max-width: 200px;
            flex-shrink: 0;
            height: fit-content;
        }
        
        .game-stats-side .game-stats {
            display: flex;
            flex-direction: column;
            gap: 1.5vh;
            font-size: clamp(10px, 1.2vw, 16px);
            font-weight: bold;
            min-width: 60px;
        }
        
        .game-stats-side .game-stats div {
            padding: 1vh 1vw;
            background: rgba(76, 175, 80, 0.2);
            border-radius: 1vh;
            border: 1px solid rgba(76, 175, 80, 0.3);
            transition: all 0.3s ease;
            text-align: center;
            font-size: clamp(9px, 1vw, 14px);
        }
        
        .game-stats-side .game-stats div:hover {
            background: rgba(76, 175, 80, 0.3);
            transform: translateY(-0.2vh);
        }
        
        /* Welcome message styling */
        .welcome-message {
            color: #ffffff;
            font-size: clamp(10px, 1.1vw, 16px);
            margin-right: 0.5vw;
            text-align: center;
        }
        
        /* Player avatar responsive */
        .player-avatar img,
        .player-avatar span {
            width: clamp(30px, 3.5vw, 60px) !important;
            height: clamp(30px, 3.5vw, 60px) !important;
            border-radius: 50%;
            border: 0.2vw solid #4CAF50;
            box-shadow: 0 0 1.5vw rgba(76, 175, 80, 0.5);
        }
        
        /* Responsive adjustments for different screen sizes */
        @media (max-width: 1024px) {
            .game-layout {
                gap: 1.5vw;
                padding: 0 1.5vw;
            }
            
            .player-info-side,
            .game-stats-side {
                width: 14vw;
                min-width: 70px;
                padding: 1.5vh;
            }
            
            .game-center {
                max-width: 65vw;
            }
        }
        
        @media (max-width: 768px) {
            .game-layout {
                gap: 1vw;
                padding: 0 1vw;
                flex-direction: column;
                align-items: center;
            }
            
            .player-info-side,
            .game-stats-side {
                width: 90vw;
                max-width: 300px;
                min-width: 200px;
                flex-direction: row;
                justify-content: space-around;
                padding: 1vh;
            }
            
            .game-center {
                max-width: 90vw;
                order: -1; /* Canvas comes first on mobile */
            }
            
            .game-stats-side .game-stats {
                flex-direction: row;
                flex-wrap: wrap;
                justify-content: center;
                gap: 1vw;
            }
            
            .game-stats-side .game-stats div {
                padding: 0.5vh 1vw;
                font-size: clamp(8px, 2.5vw, 12px);
            }
        }
        
        @media (max-width: 425px) {
            .game-layout {
                gap: 0.5vw;
                padding: 0 0.5vw;
            }
            
            .player-info-side,
            .game-stats-side {
                width: 95vw;
                min-width: 280px;
                padding: 0.8vh;
            }
            
            .game-center {
                max-width: 95vw;
            }
            
            .player-avatar img,
            .player-avatar span {
                width: clamp(25px, 4vw, 40px) !important;
                height: clamp(25px, 4vw, 40px) !important;
            }
        }
    </style>
</head>
<body>
    <div class="game-container">
        <!-- Game Header - Side Layout -->
        <div class="game-layout">
            <!-- Left Side - Player Info -->
            <div class="player-info-side">
                <div class="player-avatar" id="playerAvatar" style="cursor: pointer; position: relative;">
                    <?php if (!empty($_SESSION['avatar_url'])): ?>
                        <img src="<?php echo htmlspecialchars($_SESSION['avatar_url']); ?>" alt="Avatar" style="border-radius: 50%; vertical-align: middle;">
                        <div style="position: absolute; bottom: -2px; right: -2px; background: #4CAF50; border-radius: 50%; width: 12px; height: 12px; font-size: 8px; display: flex; align-items: center; justify-content: center;">📷</div>
                    <?php else: ?>
                        <span style="font-size: 24px;">👤</span>
                        <div style="position: absolute; bottom: -2px; right: -2px; background: #4CAF50; border-radius: 50%; width: 12px; height: 12px; font-size: 8px; display: flex; align-items: center; justify-content: center;">+</div>
                    <?php endif; ?>
                </div>
                <div class="welcome-message">
                    Welcome, <a href="profile.php" style="color: #4CAF50; text-decoration: none; font-weight: bold;">
                        <?php echo isset($_SESSION['display_name']) ? htmlspecialchars($_SESSION['display_name']) : htmlspecialchars($_SESSION['username'] ?? 'Pilot'); ?>
                    </a>
                    <span class="logout-link">| <a href="logout.php" style="color: #ff6b6b; text-decoration: none;">Mission Complete</a></span>
                </div>
            </div>
            
            <!-- Center - Game Canvas -->
            <div class="game-center">
                <canvas id="gameCanvas" width="800" height="600"></canvas>
                
                <!-- Play Now Button -->
                <div id="playNowButton" class="play-now-button">
                    <button id="startGameBtn" class="blinking-button">BEGIN MISSION</button>
                </div>
                
                <!-- Pause Indicator -->
                <div id="pauseIndicator" class="pause-indicator">MISSION PAUSED</div>
                
                <!-- Game Over Screen -->
                <div id="gameOver" class="hidden">
                    <h2>MISSION FAILED</h2>
                    <p>Crystals Collected: <span id="finalScore">0</span></p>
                    <p>Hull Integrity: Critical Failure</p>
                    <div class="game-over-choices">
                        <p>Humanity's fate hangs in the balance...</p>
                        <button id="playAgain">Retry Mission</button>
                        <button id="endGameBtn">Abandon Mission</button>
                    </div>
                </div>
            </div>
            
            <!-- Right Side - Game Stats -->
            <div class="game-stats-side">
                <div class="game-stats">
                    <div>Hull Integrity: <span id="lives">5</span></div>
                    <div>Wave: <span id="round">1</span></div>
                    <div>Crystals: <span id="score">0</span></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Avatar Modal -->
    <div id="avatarModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Upload Avatar</h3>
                <span class="close" id="closeModal">&times;</span>
            </div>
            <div class="current-avatar">
                <?php if (!empty($_SESSION['avatar_url'])): ?>
                    <img src="<?php echo htmlspecialchars($_SESSION['avatar_url']); ?>" alt="Current Avatar" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover;">
                <?php else: ?>
                    <div style="width: 80px; height: 80px; border-radius: 50%; background: #333; display: flex; align-items: center; justify-content: center; font-size: 40px;">👤</div>
                <?php endif; ?>
            </div>
            
            <form id="avatarForm" enctype="multipart/form-data">
                <?php echo getCSRFField(); ?>
                <div class="upload-area" id="uploadArea">
                    <input type="file" id="avatarFile" name="avatar" accept="image/*" style="display: none;">
                    <div id="uploadPreview" style="display: none; text-align: center; margin: 10px 0;">
                        <img id="previewImage" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover;">
                    </div>
                </div>
                <button type="button" class="btn" id="selectFileBtn" style="width: 100%; margin-top: 10px;">  <p>📷 Click to upload new avatar</p></button>
                <button type="submit" class="btn" id="confirmUploadBtn" style="width: 100%; margin-top: 10px; display: none; background: linear-gradient(135deg, #ff6b6b 0%, #ff4444 100%);">Confirm Upload</button>
            </form>
        </div>
    </div>
    
    <script src="game.js"></script>
    <script src="avatar.js"></script>
</body>
</html>