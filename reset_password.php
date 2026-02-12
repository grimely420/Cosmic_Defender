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

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

// Validate token
if (empty($token)) {
    $error = "Invalid reset token.";
} else {
    $stmt = $pdo->prepare("SELECT pr.*, u.username FROM password_resets pr JOIN users u ON pr.user_id = u.id WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used = FALSE");
    $stmt->execute([$token]);
    $resetRequest = $stmt->fetch();
    
    if (!$resetRequest) {
        $error = "Invalid or expired reset token.";
    }
}

if ($_POST && $resetRequest) {
    // Validate CSRF token
    validateCSRF();
    
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Password strength validation
    if (!validatePasswordStrength($password)) {
        $error = "Password must be at least 8 characters long and contain uppercase, lowercase, number, and special character";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match";
    } else {
        try {
            // Update password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $resetRequest['user_id']]);
            
            // Mark reset token as used
            $stmt = $pdo->prepare("UPDATE password_resets SET used = TRUE, used_at = NOW() WHERE id = ?");
            $stmt->execute([$resetRequest['id']]);
            
            // Log password reset
            $securityLogger->logEvent('password_reset_completed', $resetRequest['user_id'], $resetRequest['username'], "Password successfully reset", 'medium');
            
            $success = "Password has been reset successfully. You can now login with your new password.";
            
            // Redirect to login after 3 seconds
            header("refresh:3;url=login.php");
            
        } catch (PDOException $e) {
            error_log("Password reset error: " . $e->getMessage());
            $error = "Failed to reset password. Please try again.";
        }
    }
}

// Password strength validation function
function validatePasswordStrength($password) {
    if (strlen($password) < 8) return false;
    if (!preg_match('/[A-Z]/', $password)) return false; // uppercase
    if (!preg_match('/[a-z]/', $password)) return false; // lowercase
    if (!preg_match('/[0-9]/', $password)) return false; // number
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) return false; // special char
    return true;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cosmic Defender - Reset Password</title>
    <link rel="stylesheet" type="text/css" href="style.css">
</head>
<body>
    <div class="auth-container">
        <h2>Reset Password</h2>
        
        <?php if ($success): ?>
            <p class='success'><?php echo htmlspecialchars($success); ?></p>
            <p>Redirecting to login page...</p>
        <?php else: ?>
            <?php if ($error) echo "<p class='error'>" . htmlspecialchars($error) . "</p>"; ?>
            
            <?php if ($resetRequest): ?>
                <form method="post">
                    <?php echo getCSRFField(); ?>
                    <input type="password" name="password" placeholder="New Password" required minlength="8" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*(),.?":{}|<>]).{8,}" title="Password must be at least 8 characters long and contain uppercase, lowercase, number, and special character">
                    <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
                    <button type="submit">Reset Password</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
        
        <p><a href="login.php">Back to Login</a></p>
    </div>
</body>
</html>
