<?php
/**
 * Rate Limiter Class
 * Prevents brute force attacks and abuse
 */

class RateLimiter {
    private $pdo;
    private $maxAttempts = 5;
    private $decayMinutes = 15;
    private $tableName = 'rate_limit';
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->createTableIfNotExists();
        $this->cleanOldAttempts();
    }
    
    /**
     * Create rate limit table if it doesn't exist
     */
    private function createTableIfNotExists() {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tableName} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            action VARCHAR(50) NOT NULL DEFAULT 'login',
            attempts INT NOT NULL DEFAULT 1,
            first_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_ip_action (ip_address, action),
            INDEX idx_last_attempt (last_attempt)
        )";
        
        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            // Table creation failed, but might already exist
        }
    }
    
    /**
     * Clean old attempts from the database
     */
    private function cleanOldAttempts() {
        $sql = "DELETE FROM {$this->tableName} 
                WHERE last_attempt < DATE_SUB(NOW(), INTERVAL :minutes MINUTE)";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['minutes' => $this->decayMinutes * 2]);
        } catch (PDOException $e) {
            // Cleanup failed, not critical
        }
    }
    
    /**
     * Get client IP address
     */
    public function getClientIP() {
        // Check for IP behind proxy
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
    
    /**
     * Check if the rate limit has been exceeded
     */
    public function checkLimit($identifier = null, $action = 'login') {
        if ($identifier === null) {
            $identifier = $this->getClientIP();
        }
        
        // Clean old attempts periodically
        if (rand(1, 100) <= 10) {
            $this->cleanOldAttempts();
        }
        
        // Check current attempts
        $sql = "SELECT attempts, last_attempt 
                FROM {$this->tableName} 
                WHERE ip_address = :ip AND action = :action 
                AND last_attempt > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'ip' => $identifier,
                'action' => $action,
                'minutes' => $this->decayMinutes
            ]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                if ($result['attempts'] >= $this->maxAttempts) {
                    // Rate limit exceeded
                    return false;
                }
                
                // Increment attempts
                $this->incrementAttempts($identifier, $action);
            } else {
                // First attempt
                $this->recordAttempt($identifier, $action);
            }
            
            return true;
            
        } catch (PDOException $e) {
            // On error, allow the request (fail open)
            return true;
        }
    }
    
    /**
     * Check if user account is locked out
     */
    public function isAccountLocked($username) {
        $sql = "SELECT attempts, last_attempt 
                FROM {$this->tableName} 
                WHERE ip_address = :username AND action = 'account_lockout' 
                AND last_attempt > DATE_SUB(NOW(), INTERVAL 30 MINUTE)";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['username' => $username]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result && $result['attempts'] >= 5;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Lock out user account after failed attempts
     */
    public function lockoutAccount($username) {
        $sql = "INSERT INTO {$this->tableName} (ip_address, action, attempts) 
                VALUES (:username, 'account_lockout', 1)
                ON DUPLICATE KEY UPDATE 
                attempts = attempts + 1,
                last_attempt = CURRENT_TIMESTAMP";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['username' => $username]);
        } catch (PDOException $e) {
            // Lockout failed, not critical
        }
    }
    
    /**
     * Reset account lockout
     */
    public function resetAccountLockout($username) {
        $sql = "DELETE FROM {$this->tableName} 
                WHERE ip_address = :username AND action = 'account_lockout'";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['username' => $username]);
        } catch (PDOException $e) {
            // Reset failed, not critical
        }
    }
    
    /**
     * Record a new attempt
     */
    private function recordAttempt($identifier, $action) {
        $sql = "INSERT INTO {$this->tableName} (ip_address, action, attempts) 
                VALUES (:ip, :action, 1)
                ON DUPLICATE KEY UPDATE 
                attempts = attempts + 1,
                last_attempt = CURRENT_TIMESTAMP";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['ip' => $identifier, 'action' => $action]);
        } catch (PDOException $e) {
            // Recording failed, not critical
        }
    }
    
    /**
     * Increment attempts for existing record
     */
    private function incrementAttempts($identifier, $action) {
        $sql = "UPDATE {$this->tableName} 
                SET attempts = attempts + 1 
                WHERE ip_address = :ip AND action = :action";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['ip' => $identifier, 'action' => $action]);
        } catch (PDOException $e) {
            // Update failed, not critical
        }
    }
    
    /**
     * Reset attempts for a specific identifier
     */
    public function resetAttempts($identifier = null, $action = 'login') {
        if ($identifier === null) {
            $identifier = $this->getClientIP();
        }
        
        $sql = "DELETE FROM {$this->tableName} 
                WHERE ip_address = :ip AND action = :action";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['ip' => $identifier, 'action' => $action]);
        } catch (PDOException $e) {
            // Reset failed, not critical
        }
    }
    
    /**
     * Get remaining attempts
     */
    public function getRemainingAttempts($identifier = null, $action = 'login') {
        if ($identifier === null) {
            $identifier = $this->getClientIP();
        }
        
        $sql = "SELECT attempts 
                FROM {$this->tableName} 
                WHERE ip_address = :ip AND action = :action 
                AND last_attempt > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'ip' => $identifier,
                'action' => $action,
                'minutes' => $this->decayMinutes
            ]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return max(0, $this->maxAttempts - $result['attempts']);
            }
            
            return $this->maxAttempts;
            
        } catch (PDOException $e) {
            return $this->maxAttempts;
        }
    }
}
?>
