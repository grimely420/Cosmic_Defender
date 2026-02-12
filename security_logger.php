<?php
/**
 * Security Logging System
 * Logs security events for monitoring and audit purposes
 */

class SecurityLogger {
    private $pdo;
    private $tableName = 'security_logs';
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->createTableIfNotExists();
    }
    
    /**
     * Create security logs table if it doesn't exist
     */
    private function createTableIfNotExists() {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tableName} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_type VARCHAR(50) NOT NULL,
            user_id INT NULL,
            username VARCHAR(255) NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT NULL,
            event_details TEXT NULL,
            severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_event_type (event_type),
            INDEX idx_user_id (user_id),
            INDEX idx_ip_address (ip_address),
            INDEX idx_created_at (created_at),
            INDEX idx_severity (severity)
        )";
        
        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            // Table creation failed, but might already exist
            error_log("Security logger table creation failed: " . $e->getMessage());
        }
    }
    
    /**
     * Log a security event
     */
    public function logEvent($eventType, $userId = null, $username = null, $details = null, $severity = 'medium') {
        $ipAddress = $this->getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $sql = "INSERT INTO {$this->tableName} 
                (event_type, user_id, username, ip_address, user_agent, event_details, severity) 
                VALUES (:event_type, :user_id, :username, :ip_address, :user_agent, :event_details, :severity)";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'event_type' => $eventType,
                'user_id' => $userId,
                'username' => $username,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'event_details' => $details,
                'severity' => $severity
            ]);
        } catch (PDOException $e) {
            // Logging failed, but we don't want to break the application
            error_log("Security logging failed: " . $e->getMessage());
        }
    }
    
    /**
     * Log login attempt
     */
    public function logLoginAttempt($username, $success, $userId = null) {
        $eventType = $success ? 'login_success' : 'login_failed';
        $details = $success ? 'Successful login' : 'Failed login attempt';
        $severity = $success ? 'low' : 'medium';
        
        $this->logEvent($eventType, $userId, $username, $details, $severity);
    }
    
    /**
     * Log account lockout
     */
    public function logAccountLockout($username, $userId = null) {
        $this->logEvent('account_lockout', $userId, $username, 'Account locked due to failed attempts', 'high');
    }
    
    /**
     * Log registration
     */
    public function logRegistration($username, $userId = null) {
        $this->logEvent('user_registration', $userId, $username, 'New user registration', 'low');
    }
    
    /**
     * Log suspicious activity
     */
    public function logSuspiciousActivity($details, $userId = null, $username = null) {
        $this->logEvent('suspicious_activity', $userId, $username, $details, 'high');
    }
    
    /**
     * Log rate limit violation
     */
    public function logRateLimitViolation($action, $userId = null, $username = null) {
        $details = "Rate limit exceeded for action: {$action}";
        $this->logEvent('rate_limit_violation', $userId, $username, $details, 'medium');
    }
    
    /**
     * Get recent security events
     */
    public function getRecentEvents($limit = 100, $severity = null) {
        $sql = "SELECT * FROM {$this->tableName}";
        $params = [];
        
        if ($severity) {
            $sql .= " WHERE severity = :severity";
            $params['severity'] = $severity;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT :limit";
        $params['limit'] = $limit;
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Failed to fetch security events: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Clean old logs (older than 90 days)
     */
    public function cleanOldLogs() {
        $sql = "DELETE FROM {$this->tableName} WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Failed to clean old security logs: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, 
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
?>
