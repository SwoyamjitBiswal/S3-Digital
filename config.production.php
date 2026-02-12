<?php
/**
 * Production Configuration
 * Production environment settings for S3 Digital
 */

// Load environment variables
require_once __DIR__ . '/includes/EnvLoader.php';
EnvLoader::load();

// Validate required environment variables
EnvLoader::validateRequired([
    'DB_HOST',
    'DB_NAME',
    'DB_USER',
    'DB_PASSWORD',
    'APP_KEY'
]);

// Application Configuration
define('APP_ENV', EnvLoader::get('APP_ENV', 'production'));
define('APP_DEBUG', EnvLoader::getBool('APP_DEBUG', false));
define('APP_URL', EnvLoader::get('APP_URL', 'https://localhost'));
define('APP_NAME', EnvLoader::get('APP_NAME', 'S3 Digital'));

// Database Configuration
define('DB_HOST', EnvLoader::get('DB_HOST', 'localhost'));
define('DB_PORT', EnvLoader::getInt('DB_PORT', 3306));
define('DB_NAME', EnvLoader::get('DB_NAME', 's3_digital'));
define('DB_USER', EnvLoader::get('DB_USER', 'root'));
define('DB_PASS', EnvLoader::get('DB_PASSWORD', ''));
define('DB_CHARSET', EnvLoader::get('DB_CHARSET', 'utf8mb4'));

// Security Configuration
define('APP_KEY', EnvLoader::get('APP_KEY'));
define('JWT_SECRET', EnvLoader::get('JWT_SECRET', 'default_jwt_secret'));
define('CSRF_TOKEN_EXPIRY', EnvLoader::getInt('CSRF_TOKEN_EXPIRY', 3600));

// Email Configuration
define('SMTP_HOST', EnvLoader::get('MAIL_HOST', 'localhost'));
define('SMTP_PORT', EnvLoader::getInt('MAIL_PORT', 587));
define('SMTP_USER', EnvLoader::get('MAIL_USERNAME', ''));
define('SMTP_PASS', EnvLoader::get('MAIL_PASSWORD', ''));
define('SMTP_FROM_EMAIL', EnvLoader::get('MAIL_FROM_ADDRESS', 'noreply@s3digital.com'));
define('SMTP_FROM_NAME', EnvLoader::get('MAIL_FROM_NAME', 'S3 Digital'));

// Payment Gateway Configuration
define('RAZORPAY_KEY_ID', EnvLoader::get('RAZORPAY_KEY_ID', ''));
define('RAZORPAY_KEY_SECRET', EnvLoader::get('RAZORPAY_KEY_SECRET', ''));

define('STRIPE_PUBLISHABLE_KEY', EnvLoader::get('STRIPE_PUBLISHABLE_KEY', ''));
define('STRIPE_SECRET_KEY', EnvLoader::get('STRIPE_SECRET_KEY', ''));
define('STRIPE_WEBHOOK_SECRET', EnvLoader::get('STRIPE_WEBHOOK_SECRET', ''));

define('PAYPAL_CLIENT_ID', EnvLoader::get('PAYPAL_CLIENT_ID', ''));
define('PAYPAL_CLIENT_SECRET', EnvLoader::get('PAYPAL_CLIENT_SECRET', ''));
define('PAYPAL_SANDBOX', EnvLoader::getBool('PAYPAL_SANDBOX', false));

// File Upload Configuration
define('UPLOAD_MAX_SIZE', EnvLoader::getInt('UPLOAD_MAX_SIZE', 10485760)); // 10MB
define('UPLOAD_ALLOWED_TYPES', EnvLoader::getArray('UPLOAD_ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'gif']));
define('UPLOAD_PATH', EnvLoader::get('UPLOAD_PATH', 'uploads/'));

// Session Configuration
define('SESSION_LIFETIME', EnvLoader::getInt('SESSION_LIFETIME', 120)); // minutes
define('SESSION_PATH', EnvLoader::get('SESSION_PATH', '/'));
define('SESSION_DOMAIN', EnvLoader::get('SESSION_DOMAIN', null));
define('SESSION_ENCRYPT', EnvLoader::getBool('SESSION_ENCRYPT', false));

// Cache Configuration
define('CACHE_DRIVER', EnvLoader::get('CACHE_DRIVER', 'file'));
define('CACHE_PREFIX', EnvLoader::get('CACHE_PREFIX', 's3digital'));

// Logging Configuration
define('LOG_LEVEL', EnvLoader::get('LOG_LEVEL', 'error'));
define('LOG_PATH', EnvLoader::get('LOG_PATH', 'logs/'));
define('LOG_MAX_FILES', EnvLoader::getInt('LOG_MAX_FILES', 30));

// AI Configuration
define('AI_API_KEY', EnvLoader::get('AI_API_KEY', ''));
define('AI_MODEL', EnvLoader::get('AI_MODEL', 'gpt-3.5-turbo'));
define('AI_MAX_TOKENS', EnvLoader::getInt('AI_MAX_TOKENS', 150));
define('AI_TEMPERATURE', EnvLoader::getFloat('AI_TEMPERATURE', 0.7));

// Performance Configuration
define('MEMORY_LIMIT', EnvLoader::get('MEMORY_LIMIT', '256M'));
define('MAX_EXECUTION_TIME', EnvLoader::getInt('MAX_EXECUTION_TIME', 30));
define('OPCACHE_ENABLE', EnvLoader::getBool('OPCACHE_ENABLE', true));
define('OPCACHE_MEMORY_CONSUMPTION', EnvLoader::getInt('OPCACHE_MEMORY_CONSUMPTION', 128));

// Security Headers
define('SECURE_HEADERS', EnvLoader::getBool('SECURE_HEADERS', true));
define('X_FRAME_OPTIONS', EnvLoader::get('X_FRAME_OPTIONS', 'SAMEORIGIN'));
define('X_CONTENT_TYPE_OPTIONS', EnvLoader::get('X_CONTENT_TYPE_OPTIONS', 'nosniff'));
define('X_XSS_PROTECTION', EnvLoader::get('X_XSS_PROTECTION', '1; mode=block'));
define('STRICT_TRANSPORT_SECURITY', EnvLoader::get('STRICT_TRANSPORT_SECURITY', 'max-age=31536000; includeSubDomains'));

// CDN Configuration
define('CDN_URL', EnvLoader::get('CDN_URL', ''));
define('CDN_ENABLED', EnvLoader::getBool('CDN_ENABLED', false));

// API Configuration
define('API_RATE_LIMIT', EnvLoader::getInt('API_RATE_LIMIT', 1000));
define('API_TIMEOUT', EnvLoader::getInt('API_TIMEOUT', 30));
define('API_VERSION', EnvLoader::get('API_VERSION', 'v1'));

// Backup Configuration
define('BACKUP_ENABLED', EnvLoader::getBool('BACKUP_ENABLED', true));
define('BACKUP_SCHEDULE', EnvLoader::get('BACKUP_SCHEDULE', 'daily'));
define('BACKUP_RETENTION', EnvLoader::getInt('BACKUP_RETENTION', 30));
define('BACKUP_PATH', EnvLoader::get('BACKUP_PATH', 'backups/'));

// Monitoring Configuration
define('MONITORING_ENABLED', EnvLoader::getBool('MONITORING_ENABLED', true));
define('HEALTH_CHECK_ENDPOINT', EnvLoader::get('HEALTH_CHECK_ENDPOINT', '/health'));
define('UPTIME_MONITORING', EnvLoader::getBool('UPTIME_MONITORING', true));

// Production-specific settings
if (APP_ENV === 'production') {
    // Error reporting
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    
    // Performance
    ini_set('memory_limit', MEMORY_LIMIT);
    ini_set('max_execution_time', MAX_EXECUTION_TIME);
    
    // Session security
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.use_strict_mode', 1);
    
    // File uploads
    ini_set('upload_max_filesize', UPLOAD_MAX_SIZE);
    ini_set('post_max_size', UPLOAD_MAX_SIZE * 2);
    
    // OPcache settings
    if (OPCACHE_ENABLE && function_exists('opcache_enable')) {
        ini_set('opcache.enable', 1);
        ini_set('opcache.memory_consumption', OPCACHE_MEMORY_CONSUMPTION);
        ini_set('opcache.max_accelerated_files', 10000);
        ini_set('opcache.revalidate_freq', 0);
        ini_set('opcache.validate_timestamps', 0);
        ini_set('opcache.save_comments', 1);
        ini_set('opcache.load_comments', 1);
    }
}

// Database connection with error handling
$conn = null;
try {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    
    if (!$conn) {
        throw new Exception("Database connection failed: " . mysqli_connect_error());
    }
    
    // Set charset
    mysqli_set_charset($conn, DB_CHARSET);
    
    // Set timezone
    mysqli_query($conn, "SET time_zone = '+00:00'");
    
} catch (Exception $e) {
    // Log error instead of displaying in production
    if (APP_ENV === 'production') {
        error_log("Database connection failed: " . $e->getMessage());
        // Show user-friendly error page
        http_response_code(503);
        include __DIR__ . '/errors/database.php';
        exit;
    } else {
        throw $e;
    }
}

// Set timezone
date_default_timezone_set('UTC');

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    $sessionParams = [
        'lifetime' => SESSION_LIFETIME * 60, // Convert to seconds
        'path' => SESSION_PATH,
        'domain' => SESSION_DOMAIN,
        'secure' => APP_ENV === 'production',
        'httponly' => true,
        'samesite' => 'Strict'
    ];
    
    if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
        session_set_cookie_params($sessionParams);
    } else {
        // Legacy session settings for older PHP versions
        session_set_cookie_params(
            $sessionParams['lifetime'],
            $sessionParams['path'],
            $sessionParams['domain'],
            $sessionParams['secure'],
            $sessionParams['httponly']
        );
    }
    
    session_start();
}

// Security headers
if (SECURE_HEADERS) {
    if (!headers_sent()) {
        header('X-Frame-Options: ' . X_FRAME_OPTIONS);
        header('X-Content-Type-Options: ' . X_CONTENT_TYPE_OPTIONS);
        header('X-XSS-Protection: ' . X_XSS_PROTECTION);
        
        if (APP_ENV === 'production' && isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: ' . STRICT_TRANSPORT_SECURITY);
        }
        
        // Content Security Policy
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://checkout.razorpay.com https://js.stripe.com https://www.paypal.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' https://api.razorpay.com https://api.stripe.com; frame-src https://checkout.razorpay.com https://js.stripe.com https://www.paypal.com;");
    }
}

// Error handler
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }
    
    $error_message = "Error: {$message} in {$file} on line {$line}";
    
    if (APP_ENV === 'production') {
        error_log($error_message);
        // Show user-friendly error page
        http_response_code(500);
        include __DIR__ . '/errors/general.php';
        exit;
    } else {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
});

// Exception handler
set_exception_handler(function($exception) {
    $error_message = "Uncaught exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine();
    
    if (APP_ENV === 'production') {
        error_log($error_message);
        // Show user-friendly error page
        http_response_code(500);
        include __DIR__ . '/errors/general.php';
        exit;
    } else {
        echo $error_message;
        exit;
    }
});

// Shutdown function for fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $error_message = "Fatal error: {$error['message']} in {$error['file']} on line {$error['line']}";
        
        if (APP_ENV === 'production') {
            error_log($error_message);
            // Show user-friendly error page
            http_response_code(500);
            include __DIR__ . '/errors/general.php';
            exit;
        }
    }
});

// Include functions
require_once __DIR__ . '/functions.php';

// Initialize security manager
require_once __DIR__ . '/includes/SecurityManager.php';

// Initialize AI components
require_once __DIR__ . '/includes/AiRecommendationEngine.php';
require_once __DIR__ . '/includes/AiChatbot.php';
require_once __DIR__ . '/includes/IntelligentSearch.php';
require_once __DIR__ . '/includes/AiContentGenerator.php';

// Initialize API router
require_once __DIR__ . '/includes/ApiRouter.php';
?>
