<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

class ApiRouter {
    private $routes = [];
    private $middleware = [];
    private $security;
    
    public function __construct() {
        $this->security = new SecurityManager();
    }
    
    /**
     * Register API route
     */
    public function route($method, $path, $handler, $middleware = []) {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
            'middleware' => array_merge($this->middleware, $middleware)
        ];
    }
    
    /**
     * Add global middleware
     */
    public function middleware($middleware) {
        $this->middleware[] = $middleware;
    }
    
    /**
     * Handle incoming request
     */
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Remove query string from path
        $path = strtok($path, '?');
        
        // Find matching route
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $this->pathMatches($route['path'], $path)) {
                return $this->executeRoute($route);
            }
        }
        
        // No route found
        $this->sendResponse(['error' => 'Route not found'], 404);
    }
    
    /**
     * Check if path matches route pattern
     */
    private function pathMatches($routePath, $requestPath) {
        // Convert route path to regex pattern
        $pattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', $routePath);
        $pattern = '#^' . $pattern . '$#';
        
        if (preg_match($pattern, $requestPath, $matches)) {
            // Extract route parameters
            $params = [];
            preg_match_all('/\{([^}]+)\}/', $routePath, $paramNames);
            
            for ($i = 1; $i < count($matches); $i++) {
                $paramName = $paramNames[1][$i - 1];
                $params[$paramName] = $matches[$i];
            }
            
            $_REQUEST['route_params'] = $params;
            return true;
        }
        
        return false;
    }
    
    /**
     * Execute route with middleware
     */
    private function executeRoute($route) {
        try {
            // Execute middleware
            foreach ($route['middleware'] as $middleware) {
                $result = $this->executeMiddleware($middleware);
                if ($result !== true) {
                    return $result;
                }
            }
            
            // Execute handler
            $handler = $route['handler'];
            if (is_callable($handler)) {
                $response = call_user_func($handler);
            } elseif (is_string($handler)) {
                $response = $this->executeController($handler);
            } else {
                throw new Exception('Invalid route handler');
            }
            
            $this->sendResponse($response);
            
        } catch (Exception $e) {
            $this->sendResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Execute middleware
     */
    private function executeMiddleware($middleware) {
        if (is_callable($middleware)) {
            return call_user_func($middleware);
        } elseif (is_string($middleware)) {
            return $this->executeMiddlewareMethod($middleware);
        }
        
        return true;
    }
    
    /**
     * Execute middleware method
     */
    private function executeMiddlewareMethod($middleware) {
        switch ($middleware) {
            case 'auth':
                return $this->authMiddleware();
            case 'admin':
                return $this->adminMiddleware();
            case 'csrf':
                return $this->csrfMiddleware();
            case 'rate_limit':
                return $this->rateLimitMiddleware();
            default:
                return true;
        }
    }
    
    /**
     * Authentication middleware
     */
    private function authMiddleware() {
        if (!is_logged_in()) {
            $this->sendResponse(['error' => 'Authentication required'], 401);
            return false;
        }
        return true;
    }
    
    /**
     * Admin middleware
     */
    private function adminMiddleware() {
        if (!is_admin_logged_in()) {
            $this->sendResponse(['error' => 'Admin access required'], 403);
            return false;
        }
        return true;
    }
    
    /**
     * CSRF middleware
     */
    private function csrfMiddleware() {
        if (!$this->security->validateCsrfRequest()) {
            $this->sendResponse(['error' => 'Invalid CSRF token'], 403);
            return false;
        }
        return true;
    }
    
    /**
     * Rate limiting middleware
     */
    private function rateLimitMiddleware() {
        if (!$this->security->checkRateLimit('api_request', 100, 300)) {
            $this->sendResponse(['error' => 'Rate limit exceeded'], 429);
            return false;
        }
        return true;
    }
    
    /**
     * Execute controller method
     */
    private function executeController($handler) {
        $parts = explode('@', $handler);
        $controllerName = $parts[0];
        $methodName = $parts[1];
        
        $controllerFile = __DIR__ . '/../controllers/' . $controllerName . '.php';
        
        if (!file_exists($controllerFile)) {
            throw new Exception('Controller not found');
        }
        
        require_once $controllerFile;
        
        if (!class_exists($controllerName)) {
            throw new Exception('Controller class not found');
        }
        
        $controller = new $controllerName();
        
        if (!method_exists($controller, $methodName)) {
            throw new Exception('Controller method not found');
        }
        
        return $controller->$methodName();
    }
    
    /**
     * Send JSON response
     */
    private function sendResponse($data, $statusCode = 200) {
        header_remove();
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
        
        http_response_code($statusCode);
        
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    /**
     * Register common API routes
     */
    public function registerCommonRoutes() {
        // Product routes
        $this->route('GET', '/api/products', 'ProductController@index');
        $this->route('GET', '/api/products/{id}', 'ProductController@show');
        $this->route('GET', '/api/products/search', 'ProductController@search');
        $this->route('GET', '/api/products/suggestions', 'ProductController@suggestions');
        
        // Cart routes
        $this->route('GET', '/api/cart', 'CartController@index', ['auth', 'csrf']);
        $this->route('POST', '/api/cart/add', 'CartController@add', ['auth', 'csrf']);
        $this->route('PUT', '/api/cart/update', 'CartController@update', ['auth', 'csrf']);
        $this->route('DELETE', '/api/cart/remove', 'CartController@remove', ['auth', 'csrf']);
        
        // Order routes
        $this->route('GET', '/api/orders', 'OrderController@index', ['auth']);
        $this->route('GET', '/api/orders/{id}', 'OrderController@show', ['auth']);
        $this->route('POST', '/api/orders', 'OrderController@create', ['auth', 'csrf']);
        
        // User routes
        $this->route('GET', '/api/user/profile', 'UserController@profile', ['auth']);
        $this->route('PUT', '/api/user/profile', 'UserController@updateProfile', ['auth', 'csrf']);
        $this->route('POST', '/api/user/login', 'UserController@login', ['csrf', 'rate_limit']);
        $this->route('POST', '/api/user/register', 'UserController@register', ['csrf', 'rate_limit']);
        $this->route('POST', '/api/user/logout', 'UserController@logout', ['auth']);
        
        // AI routes
        $this->route('GET', '/api/ai/recommendations', 'AiController@recommendations', ['auth']);
        $this->route('POST', '/api/ai/chat', 'AiController@chat', ['auth', 'csrf']);
        $this->route('GET', '/api/ai/search/suggestions', 'AiController@searchSuggestions');
        $this->route('POST', '/api/ai/track-behavior', 'AiController@trackBehavior', ['auth']);
        
        // Admin routes
        $this->route('GET', '/api/admin/products', 'AdminProductController@index', ['admin']);
        $this->route('POST', '/api/admin/products', 'AdminProductController@store', ['admin', 'csrf']);
        $this->route('PUT', '/api/admin/products/{id}', 'AdminProductController@update', ['admin', 'csrf']);
        $this->route('DELETE', '/api/admin/products/{id}', 'AdminProductController@delete', ['admin', 'csrf']);
        
        $this->route('GET', '/api/admin/content-generator', 'AdminAiController@contentGenerator', ['admin']);
        $this->route('POST', '/api/admin/content-generator', 'AdminAiController@generateContent', ['admin', 'csrf']);
        
        // Security routes
        $this->route('GET', '/api/security/csrf-token', 'SecurityController@csrfToken');
        $this->route('POST', '/api/security/validate-csrf', 'SecurityController@validateCsrf');
    }
}
?>
