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

// Generate secure token
function generateResetToken() {
    return bin2hex(random_bytes(32));
}

// Send password reset email
function sendResetEmail($email, $token) {
    $resetLink = "https://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . urlencode($token);
    
    $subject = "Password Reset - Asteroids Game";
    $message = "Hello,\n\n";
    $message .= "You requested a password reset for your Asteroids Game account.\n\n";
    $message .= "Click the link below to reset your password:\n";
    $message .= $resetLink . "\n\n";
    $message .= "This link will expire in 1 hour.\n\n";
    $message .= "If you didn't request this password reset, please change your password.\n\n";
    $message .= "Best regards,\n";
    $message .= "Asteroids Game Team";
    
    $headers = "From: noreply@asteroidsgame.markstechsolution.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    return mail($email, $subject, $message, $headers);
}

if ($_POST) {
    // Check rate limit for password reset requests
    if (!$rateLimiter->checkLimit($clientIP, 'password_reset')) {
        $error = "Too many password reset requests. Please try again later.";
    } else {
        // Validate CSRF token
        validateCSRF();
        
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address";
        } else {
            // Check if user exists
            $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Generate and store reset token
                $token = generateResetToken();
                $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour from now
                
                $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, email, token, expires_at) VALUES (?, ?, ?, ?)");
                $stmt->execute([$user['id'], $email, $token, $expires]);
                
                // Log password reset request
                $securityLogger->logEvent('password_reset_request', $user['id'], $user['username'], "Password reset requested for email: {$email}", 'medium');
                
                // Send reset email
                if (sendResetEmail($email, $token)) {
                    $success = "Password reset link has been sent to your email address.";
                } else {
                    $error = "Failed to send reset email. Please try again.";
                }
            } else {
                // Don't reveal if email exists or not
                $success = "If an account with that email exists, a password reset link has been sent.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cosmic Defender - Forgot Password</title>
    <link rel="stylesheet" type="text/css" href="style.css">
</head>
<body>
    <div class="auth-container">
        <h2>Forgot Password</h2>
        <p>Enter your email address to receive a password reset link.</p>
        
        <?php if (isset($success)) echo "<p class='success'>" . htmlspecialchars($success) . "</p>"; ?>
        <?php if (isset($error)) echo "<p class='error'>" . htmlspecialchars($error) . "</p>"; ?>
        
        <form method="post">
            <?php echo getCSRFField(); ?>
            <input type="email" name="email" placeholder="Email address" required>
            <button type="submit">Send Reset Link</button>
        </form>
        
        <p><a href="login.php">Back to Login</a></p>
    </div>
</body>
</html>