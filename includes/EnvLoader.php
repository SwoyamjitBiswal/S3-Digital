<?php
/**
 * Environment Configuration Loader
 * Loads environment variables from .env file
 */

class EnvLoader {
    private static $loaded = false;
    
    /**
     * Load environment variables from .env file
     */
    public static function load($path = null) {
        if (self::$loaded) {
            return;
        }
        
        $path = $path ?: __DIR__ . '/../.env';
        
        if (!file_exists($path)) {
            throw new Exception("Environment file not found: {$path}");
        }
        
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos($line, '#') === 0) {
                continue;
            }
            
            // Parse key=value pairs
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                $value = self::removeQuotes($value);
                
                // Set environment variable
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
        
        self::$loaded = true;
    }
    
    /**
     * Get environment variable
     */
    public static function get($key, $default = null) {
        if (!self::$loaded) {
            self::load();
        }
        
        return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?? $default;
    }
    
    /**
     * Check if environment variable exists
     */
    public static function has($key) {
        if (!self::$loaded) {
            self::load();
        }
        
        return isset($_ENV[$key]) || isset($_SERVER[$key]) || getenv($key) !== false;
    }
    
    /**
     * Remove quotes from value
     */
    private static function removeQuotes($value) {
        if (strlen($value) >= 2) {
            if (($value[0] === '"' && $value[-1] === '"') || 
                ($value[0] === "'" && $value[-1] === "'")) {
                return substr($value, 1, -1);
            }
        }
        return $value;
    }
    
    /**
     * Get boolean environment variable
     */
    public static function getBool($key, $default = false) {
        $value = self::get($key, $default);
        
        if (is_bool($value)) {
            return $value;
        }
        
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * Get integer environment variable
     */
    public static function getInt($key, $default = 0) {
        return (int) self::get($key, $default);
    }
    
    /**
     * Get float environment variable
     */
    public static function getFloat($key, $default = 0.0) {
        return (float) self::get($key, $default);
    }
    
    /**
     * Get array environment variable (comma-separated)
     */
    public static function getArray($key, $default = []) {
        $value = self::get($key, '');
        
        if (empty($value)) {
            return $default;
        }
        
        return array_map('trim', explode(',', $value));
    }
    
    /**
     * Validate required environment variables
     */
    public static function validateRequired(array $required) {
        $missing = [];
        
        foreach ($required as $key) {
            if (!self::has($key) || empty(self::get($key))) {
                $missing[] = $key;
            }
        }
        
        if (!empty($missing)) {
            throw new Exception("Required environment variables are missing: " . implode(', ', $missing));
        }
    }
    
    /**
     * Get all environment variables
     */
    public static function all() {
        if (!self::$loaded) {
            self::load();
        }
        
        return $_ENV;
    }
}
?>
