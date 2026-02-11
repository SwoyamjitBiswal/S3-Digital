<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

class SecurityManager {
    private $conn;
    private $userId;
    private $sessionId;
    
    public function __construct($userId = null, $sessionId = null) {
        global $conn;
        $this->conn = $conn;
        $this->userId = $userId ?: get_user_id();
        $this->sessionId = $sessionId ?: session_id();
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCsrfToken($expiryMinutes = 60) {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + ($expiryMinutes * 60));
        
        // Clean old tokens
        $this->cleanExpiredTokens();
        
        // Store token
        $stmt = mysqli_prepare($this->conn, "
            INSERT INTO csrf_tokens (token, user_id, session_id, expires_at)
            VALUES (?, ?, ?, ?)
        ");
        mysqli_stmt_bind_param($stmt, "siss", $token, $this->userId, $this->sessionId, $expiresAt);
        mysqli_stmt_execute($stmt);
        
        return $token;
    }
    
    /**
     * Validate CSRF token
     */
    public function validateCsrfToken($token) {
        if (empty($token)) {
            return false;
        }
        
        $stmt = mysqli_prepare($this->conn, "
            SELECT id FROM csrf_tokens 
            WHERE token = ? 
            AND (user_id = ? OR session_id = ?)
            AND expires_at > NOW()
        ");
        mysqli_stmt_bind_param($stmt, "sis", $token, $this->userId, $this->sessionId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            // Token is valid, remove it
            $this->removeToken($token);
            return true;
        }
        
        return false;
    }
    
    /**
     * Remove used token
     */
    private function removeToken($token) {
        $stmt = mysqli_prepare($this->conn, "
            DELETE FROM csrf_tokens WHERE token = ?
        ");
        mysqli_stmt_bind_param($stmt, "s", $token);
        mysqli_stmt_execute($stmt);
    }
    
    /**
     * Clean expired tokens
     */
    private function cleanExpiredTokens() {
        $stmt = mysqli_prepare($this->conn, "
            DELETE FROM csrf_tokens WHERE expires_at < NOW()
        ");
        mysqli_stmt_execute($stmt);
    }
    
    /**
     * Sanitize input data
     */
    public function sanitizeInput($data, $type = 'string') {
        if (is_array($data)) {
            return array_map([$this, 'sanitizeInput'], $data);
        }
        
        switch ($type) {
            case 'email':
                return filter_var($data, FILTER_SANITIZE_EMAIL);
            case 'url':
                return filter_var($data, FILTER_SANITIZE_URL);
            case 'int':
                return filter_var($data, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'html':
                return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            case 'string':
            default:
                return htmlspecialchars(trim($data), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }
    
    /**
     * Validate input data
     */
    public function validateInput($data, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? '';
            
            // Required validation
            if (in_array('required', $fieldRules) && empty($value)) {
                $errors[$field] = ucfirst($field) . ' is required';
                continue;
            }
            
            // Skip other validations if field is empty and not required
            if (empty($value) && !in_array('required', $fieldRules)) {
                continue;
            }
            
            // Type validation
            foreach ($fieldRules as $rule) {
                if (is_array($rule)) {
                    $ruleName = array_keys($rule)[0];
                    $ruleValue = $rule[$ruleName];
                } else {
                    $ruleName = $rule;
                    $ruleValue = null;
                }
                
                switch ($ruleName) {
                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field] = 'Please enter a valid email address';
                        }
                        break;
                        
                    case 'min':
                        if (strlen($value) < $ruleValue) {
                            $errors[$field] = ucfirst($field) . ' must be at least ' . $ruleValue . ' characters';
                        }
                        break;
                        
                    case 'max':
                        if (strlen($value) > $ruleValue) {
                            $errors[$field] = ucfirst($field) . ' must not exceed ' . $ruleValue . ' characters';
                        }
                        break;
                        
                    case 'min_num':
                        if (is_numeric($value) && $value < $ruleValue) {
                            $errors[$field] = ucfirst($field) . ' must be at least ' . $ruleValue;
                        }
                        break;
                        
                    case 'max_num':
                        if (is_numeric($value) && $value > $ruleValue) {
                            $errors[$field] = ucfirst($field) . ' must not exceed ' . $ruleValue;
                        }
                        break;
                        
                    case 'regex':
                        if (!preg_match($ruleValue, $value)) {
                            $errors[$field] = ucfirst($field) . ' format is invalid';
                        }
                        break;
                        
                    case 'alpha':
                        if (!preg_match('/^[a-zA-Z]+$/', $value)) {
                            $errors[$field] = ucfirst($field) . ' must contain only letters';
                        }
                        break;
                        
                    case 'alphanumeric':
                        if (!preg_match('/^[a-zA-Z0-9]+$/', $value)) {
                            $errors[$field] = ucfirst($field) . ' must contain only letters and numbers';
                        }
                        break;
                        
                    case 'password':
                        if (strlen($value) < 8) {
                            $errors[$field] = 'Password must be at least 8 characters';
                        } elseif (!preg_match('/[A-Z]/', $value)) {
                            $errors[$field] = 'Password must contain at least one uppercase letter';
                        } elseif (!preg_match('/[a-z]/', $value)) {
                            $errors[$field] = 'Password must contain at least one lowercase letter';
                        } elseif (!preg_match('/[0-9]/', $value)) {
                            $errors[$field] = 'Password must contain at least one number';
                        }
                        break;
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * XSS protection - remove malicious content
     */
    public function xssClean($data) {
        if (is_array($data)) {
            return array_map([$this, 'xssClean'], $data);
        }
        
        // Remove potentially dangerous characters
        $data = str_replace(['<', '>', '"', "'", '&'], ['&lt;', '&gt;', '&quot;', '&#x27;', '&amp;'], $data);
        
        // Remove JavaScript event handlers
        $data = preg_replace('/on\w+\s*=\s*["\']?[^"\']*["\']?/i', '', $data);
        
        // Remove JavaScript protocols
        $data = preg_replace('/javascript\s*:/i', '', $data);
        
        // Remove data URLs
        $data = preg_replace('/data\s*:\s*[^;]*;base64[^,]*,/i', '', $data);
        
        return $data;
    }
    
    /**
     * SQL injection protection
     */
    public function sqlProtect($data) {
        if (is_array($data)) {
            return array_map([$this, 'sqlProtect'], $data);
        }
        
        // Remove SQL keywords and patterns
        $sqlKeywords = [
            'DROP', 'DELETE', 'INSERT', 'UPDATE', 'CREATE', 'ALTER',
            'EXEC', 'EXECUTE', 'UNION', 'SELECT', 'FROM', 'WHERE',
            'OR', 'AND', 'LIKE', 'IN', 'BETWEEN', 'HAVING',
            'GROUP BY', 'ORDER BY', 'LIMIT', 'OFFSET'
        ];
        
        foreach ($sqlKeywords as $keyword) {
            $data = preg_replace('/\b' . $keyword . '\b/i', '', $data);
        }
        
        return $data;
    }
    
    /**
     * Rate limiting
     */
    public function checkRateLimit($action, $maxAttempts = 5, $timeWindow = 300) {
        $key = $action . '_' . ($this->userId ?: $this->sessionId);
        
        // Clean old rate limit records
        $stmt = mysqli_prepare($this->conn, "
            DELETE FROM security_logs 
            WHERE action = ? 
            AND created_at < DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        mysqli_stmt_bind_param($stmt, "si", $action, $timeWindow);
        mysqli_stmt_execute($stmt);
        
        // Check current attempts
        $stmt = mysqli_prepare($this->conn, "
            SELECT COUNT(*) as attempts 
            FROM security_logs 
            WHERE (user_id = ? OR session_id = ?) 
            AND action = ? 
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        mysqli_stmt_bind_param($stmt, "issi", $this->userId, $this->sessionId, $action, $timeWindow);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $attempts = $result->fetch_assoc()['attempts'];
        
        if ($attempts >= $maxAttempts) {
            $this->logSecurityEvent($action, 'failure', 'Rate limit exceeded');
            return false;
        }
        
        return true;
    }
    
    /**
     * Log security events
     */
    public function logSecurityEvent($action, $status = 'success', $details = null) {
        $ipAddress = $this->getClientIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $detailsJson = $details ? json_encode($details) : null;
        
        $stmt = mysqli_prepare($this->conn, "
            INSERT INTO security_logs (user_id, ip_address, user_agent, action, status, details)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        mysqli_stmt_bind_param($stmt, "isssss", $this->userId, $ipAddress, $userAgent, $action, $status, $detailsJson);
        mysqli_stmt_execute($stmt);
    }
    
    /**
     * Get client IP address
     */
    private function getClientIp() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Check for suspicious activity
     */
    public function checkSuspiciousActivity() {
        $ipAddress = $this->getClientIp();
        
        // Check for multiple failed login attempts
        $stmt = mysqli_prepare($this->conn, "
            SELECT COUNT(*) as failed_attempts 
            FROM security_logs 
            WHERE ip_address = ? 
            AND action = 'login' 
            AND status = 'failure' 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        mysqli_stmt_bind_param($stmt, "s", $ipAddress);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $failedAttempts = $result->fetch_assoc()['failed_attempts'];
        
        if ($failedAttempts >= 5) {
            $this->logSecurityEvent('suspicious_activity', 'suspicious', ['failed_logins' => $failedAttempts]);
            return true;
        }
        
        return false;
    }
    
    /**
     * Generate secure password hash
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 3]);
    }
    
    /**
     * Verify password
     */
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Generate secure random token
     */
    public function generateSecureToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Validate file upload
     */
    public function validateFileUpload($file, $allowedTypes = [], $maxSize = 5242880) {
        $errors = [];
        
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'Invalid file upload';
            return $errors;
        }
        
        // Check file size
        if ($file['size'] > $maxSize) {
            $errors[] = 'File size exceeds maximum allowed size';
        }
        
        // Check file type
        if (!empty($allowedTypes)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedTypes)) {
                $errors[] = 'File type not allowed';
            }
        }
        
        // Check for malicious content
        $content = file_get_contents($file['tmp_name']);
        if (strpos($content, '<?php') !== false) {
            $errors[] = 'File contains potentially malicious content';
        }
        
        return $errors;
    }
    
    /**
     * Secure file upload
     */
    public function secureFileUpload($file, $uploadDir, $allowedTypes = [], $maxSize = 5242880) {
        $errors = $this->validateFileUpload($file, $allowedTypes, $maxSize);
        
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        // Generate secure filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $this->generateSecureToken(16) . '.' . $extension;
        $filepath = $uploadDir . '/' . $filename;
        
        // Move file to secure location
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Set secure permissions
            chmod($filepath, 0644);
            
            $this->logSecurityEvent('file_upload', 'success', ['filename' => $filename]);
            
            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'original_name' => $file['name']
            ];
        }
        
        return ['success' => false, 'errors' => ['Failed to upload file']];
    }
    
    /**
     * Get security report
     */
    public function getSecurityReport($days = 30) {
        $stmt = mysqli_prepare($this->conn, "
            SELECT 
                action,
                status,
                COUNT(*) as count,
                COUNT(DISTINCT ip_address) as unique_ips,
                DATE(created_at) as date
            FROM security_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY action, status, DATE(created_at)
            ORDER BY date DESC, count DESC
        ");
        mysqli_stmt_bind_param($stmt, "i", $days);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $report = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $report[] = $row;
        }
        
        return $report;
    }
    
    /**
     * Get CSRF token for forms
     */
    public function getCsrfToken() {
        if (!isset($_SESSION['csrf_token']) || empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = $this->generateCsrfToken();
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Output CSRF token as HTML input
     */
    public function csrfField() {
        $token = $this->getCsrfToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Validate CSRF token from POST data
     */
    public function validateCsrfRequest() {
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
        return $this->validateCsrfToken($token);
    }
}
?>
