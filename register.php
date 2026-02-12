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

// Password strength validation function
function validatePasswordStrength($password) {
    if (strlen($password) < 8) return false;
    if (!preg_match('/[A-Z]/', $password)) return false; // uppercase
    if (!preg_match('/[a-z]/', $password)) return false; // lowercase
    if (!preg_match('/[0-9]/', $password)) return false; // number
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) return false; // special char
    return true;
}

if ($_POST) {
    // Check rate limit for registration
    if (!$rateLimiter->checkLimit($clientIP, 'register')) {
        $error = "Too many registration attempts. Please try again later.";
    } else {
        // Validate CSRF token
        validateCSRF();
    
    // Get and validate input
    $login_name = trim($_POST['login_name'] ?? '');
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $display_name = !empty($_POST['display_name']) ? trim($_POST['display_name']) : $login_name;
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $bio = !empty($_POST['bio']) ? trim($_POST['bio']) : null;
    
    // Basic validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } elseif (strlen($login_name) < 3) {
        $error = "Login name must be at least 3 characters long";
    } elseif (!validatePasswordStrength($_POST['password'])) {
        $error = "Password must be at least 8 characters long and contain uppercase, lowercase, number, and special character";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, display_name, password, bio) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$login_name, $email, $display_name, $password, $bio]);
            
            // Get the new user ID for logging
            $newUserId = $pdo->lastInsertId();
            $securityLogger->logRegistration($login_name, $newUserId);
            
            // Don't auto-login after registration, redirect to login page
            $_SESSION['registration_success'] = true;
            header("Location: login.php");
            exit();
        } catch(PDOException $e) {
            // Log the actual error for debugging
            error_log("Registration error: " . $e->getMessage());
            // Generic error to prevent username enumeration
            $error = "Registration failed. Please try again with different credentials.";
        }
    }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cosmic Defender - Register</title>
    <link rel="stylesheet" type="text/css" href="style.css">
</head>                                                                                                                                                                          6
<body>
    <div class="auth-container">
        <h2>Register</h2>
        <?php if (isset($error)) echo "<p class='error'>" . htmlspecialchars($error) . "</p>"; ?>
        <form method="post">
            <?php echo getCSRFField(); ?>
            <input type="text" name="login_name" placeholder="Login  name (min 3 characters)" required minlength="3">
            <input type="email" name="email" placeholder="Email address" required>
            <input type="password" name="password" placeholder="Password (min 8 chars, uppercase, lowercase, number, special)" required minlength="8" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*(),.?":{}|<>]).{8,}" title="Password must be at least 8 characters long and contain uppercase, lowercase, number, and special character">
            <input type="text" name="display_name" placeholder="Display name (optional)">
            <textarea name="bio" placeholder="Tell us about yourself (optional)" rows="3"></textarea>
            <button type="submit">Register</button>
        </form>
        <a href="login.php">Already have an account? Login</a>
    </div>
</body>
</html>