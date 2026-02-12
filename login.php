<?php
require_once 'security_headers.php';
require 'db.php';
require_once 'csrf.php';
require_once 'rate_limiter.php';
require_once 'security_logger.php';
session_start();

// Initialize rate limiter and security logger
$rateLimiter = new RateLimiter($pdo);
$securityLogger = new SecurityLogger($pdo);
$clientIP = $rateLimiter->getClientIP();

// Check for registration success message
$success_message = '';
if (isset($_SESSION['registration_success'])) {
    $success_message = "Registration successful! Please login with your credentials.";
    unset($_SESSION['registration_success']);
}

if ($_POST) {
    // Get input first for account lockout check
    $username = trim($_POST['username'] ?? '');
    
    // Check if account is locked out
    if ($rateLimiter->isAccountLocked($username)) {
        $error = "Account temporarily locked due to too many failed login attempts. Please try again in 30 minutes.";
    } 
    // Check rate limit
    elseif (!$rateLimiter->checkLimit($clientIP)) {
        $error = "Too many login attempts. Please try again later.";
    } else {
        // Validate CSRF token
        validateCSRF();
        
        // Get input (no sanitization needed for database storage with prepared statements)
        $password = $_POST['password'];
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Successful login - reset rate limits and lockouts
            $rateLimiter->resetAttempts($clientIP);
            $rateLimiter->resetAccountLockout($username);
            
            // Update last_login timestamp
            $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            // Log successful login
            $securityLogger->logLoginAttempt($username, true, $user['id']);
            
            // Regenerate session ID for security
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['display_name'] = $user['display_name'] ?: $user['username'];
            $_SESSION['avatar_url'] = $user['avatar_url'];
            $_SESSION['bio'] = $user['bio'];
            header("Location: game.php");
            exit();
        } else {
            // Failed login - check if we should lock the account
            $remainingAttempts = $rateLimiter->getRemainingAttempts($clientIP);
            
            // Log failed login attempt
            $securityLogger->logLoginAttempt($username, false);
            
            if ($remainingAttempts <= 1) {
                $rateLimiter->lockoutAccount($username);
                $securityLogger->logAccountLockout($username);
                $error = "Account temporarily locked due to too many failed login attempts. Please try again in 30 minutes.";
            } else {
                $error = "Invalid credentials. {$remainingAttempts} attempts remaining.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cosmic Defender - Login</title>
    <link rel="stylesheet" type="text/css" href="style.css">
</head>
<body>
    <div class="auth-container">
        <h2>Login</h2>
        <?php if ($success_message) echo "<p class='success'>" . htmlspecialchars($success_message) . "</p>"; ?>
        <?php if (isset($error)) echo "<p class='error'>" . htmlspecialchars($error) . "</p>"; ?>
        <form method="post">
            <?php echo getCSRFField(); ?>
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
        <p><a href="forgot_password.php">Forgot Password?</a></p>
        <p>Don't have an account <a href="register.php"> Click Here!</a></p>
    </div>
</body>
</html>