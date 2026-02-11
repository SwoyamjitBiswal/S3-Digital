<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

class IntelligentSearch {
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
     * Get search suggestions based on query
     */
    public function getSearchSuggestions($query, $limit = 10) {
        $suggestions = [];
        $query = strtolower(trim($query));
        
        if (strlen($query) < 2) return $suggestions;
        
        // Get product suggestions
        $productSuggestions = $this->getProductSuggestions($query, $limit / 2);
        $suggestions = array_merge($suggestions, $productSuggestions);
        
        // Get category suggestions
        $categorySuggestions = $this->getCategorySuggestions($query, $limit / 4);
        $suggestions = array_merge($suggestions, $categorySuggestions);
        
        // Get popular/trending suggestions
        $popularSuggestions = $this->getPopularSuggestions($query, $limit / 4);
        $suggestions = array_merge($suggestions, $popularSuggestions);
        
        // Remove duplicates and sort by relevance
        $suggestions = $this->deduplicateAndSortSuggestions($suggestions, $limit);
        
        return $suggestions;
    }
    
    /**
     * Perform intelligent search
     */
    public function search($query, $filters = [], $page = 1, $limit = 12) {
        $query = trim($query);
        $offset = ($page - 1) * $limit;
        
        // Log search query
        $this->logSearchQuery($query);
        
        // Build search query
        $searchResults = $this->buildSearchQuery($query, $filters, $offset, $limit);
        
        // Get total count for pagination
        $totalCount = $this->getSearchCount($query, $filters);
        
        return [
            'products' => $searchResults,
            'total' => $totalCount,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($totalCount / $limit),
            'query' => $query,
            'filters' => $filters,
            'suggestions' => $this->getRelatedSearches($query)
        ];
    }
    
    /**
     * Get product suggestions
     */
    private function getProductSuggestions($query, $limit) {
        $suggestions = [];
        
        $stmt = mysqli_prepare($this->conn, "
            SELECT id, title, price, image, category_id
            FROM products 
            WHERE status = 'active' 
            AND (LOWER(title) LIKE ? OR LOWER(description) LIKE ?)
            ORDER BY 
                CASE 
                    WHEN LOWER(title) LIKE ? THEN 1
                    WHEN LOWER(title) LIKE ? THEN 2
                    ELSE 3
                END,
                created_at DESC
            LIMIT ?
        ");
        
        $exactMatch = "$query%";
        $startsWith = "$query%";
        $contains = "%$query%";
        
        mysqli_stmt_bind_param($stmt, "ssssi", $contains, $contains, $exactMatch, $startsWith, $limit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($product = mysqli_fetch_assoc($result)) {
            $suggestions[] = [
                'type' => 'product',
                'id' => $product['id'],
                'title' => $product['title'],
                'price' => $product['price'],
                'image' => $product['image'],
                'category_id' => $product['category_id'],
                'url' => "product.php?id={$product['id']}",
                'relevance' => $this->calculateRelevance($query, $product['title'])
            ];
        }
        
        return $suggestions;
    }
    
    /**
     * Get category suggestions
     */
    private function getCategorySuggestions($query, $limit) {
        $suggestions = [];
        
        $stmt = mysqli_prepare($this->conn, "
            SELECT id, name, description, image
            FROM categories 
            WHERE status = 'active' 
            AND (LOWER(name) LIKE ? OR LOWER(description) LIKE ?)
            ORDER BY 
                CASE 
                    WHEN LOWER(name) LIKE ? THEN 1
                    WHEN LOWER(name) LIKE ? THEN 2
                    ELSE 3
                END,
                name ASC
            LIMIT ?
        ");
        
        $exactMatch = "$query%";
        $startsWith = "$query%";
        $contains = "%$query%";
        
        mysqli_stmt_bind_param($stmt, "ssssi", $contains, $contains, $exactMatch, $startsWith, $limit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($category = mysqli_fetch_assoc($result)) {
            $suggestions[] = [
                'type' => 'category',
                'id' => $category['id'],
                'title' => $category['name'],
                'description' => $category['description'],
                'image' => $category['image'],
                'url' => "categories.php?id={$category['id']}",
                'relevance' => $this->calculateRelevance($query, $category['name'])
            ];
        }
        
        return $suggestions;
    }
    
    /**
     * Get popular/trending suggestions
     */
    private function getPopularSuggestions($query, $limit) {
        $suggestions = [];
        
        $stmt = mysqli_prepare($this->conn, "
            SELECT query, frequency, category_id, product_id
            FROM search_suggestions 
            WHERE is_active = TRUE 
            AND LOWER(query) LIKE ?
            ORDER BY frequency DESC, created_at DESC
            LIMIT ?
        ");
        
        $contains = "%$query%";
        mysqli_stmt_bind_param($stmt, "si", $contains, $limit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $suggestions[] = [
                'type' => 'popular',
                'query' => $row['query'],
                'frequency' => $row['frequency'],
                'category_id' => $row['category_id'],
                'product_id' => $row['product_id'],
                'relevance' => min(1.0, $row['frequency'] / 100)
            ];
        }
        
        return $suggestions;
    }
    
    /**
     * Build intelligent search query
     */
    private function buildSearchQuery($query, $filters, $offset, $limit) {
        $sql = "
            SELECT p.*, c.name as category_name,
                   MATCH(p.title, p.description) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance_score,
                   COUNT(ub.id) as view_count
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN user_behavior ub ON p.id = ub.product_id AND ub.action_type = 'view'
            WHERE p.status = 'active'
        ";
        
        $params = [];
        $types = '';
        
        // Add search conditions
        if (!empty($query)) {
            $sql .= " AND (LOWER(p.title) LIKE ? OR LOWER(p.description) LIKE ? OR MATCH(p.title, p.description) AGAINST(? IN NATURAL LANGUAGE MODE))";
            $searchTerm = "%$query%";
            $params = array_merge($params, [$query, $searchTerm, $searchTerm]);
            $types .= 'sss';
        }
        
        // Add filters
        if (!empty($filters['category_id'])) {
            $sql .= " AND p.category_id = ?";
            $params[] = $filters['category_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['min_price'])) {
            $sql .= " AND p.price >= ?";
            $params[] = $filters['min_price'];
            $types .= 'd';
        }
        
        if (!empty($filters['max_price'])) {
            $sql .= " AND p.price <= ?";
            $params[] = $filters['max_price'];
            $types .= 'd';
        }
        
        if (!empty($filters['brand'])) {
            $sql .= " AND LOWER(p.brand) = ?";
            $params[] = strtolower($filters['brand']);
            $types .= 's';
        }
        
        $sql .= " GROUP BY p.id";
        
        // Add ordering
        $sql .= " ORDER BY ";
        
        if (!empty($query)) {
            $sql .= "relevance_score DESC, ";
        }
        
        $sql .= "view_count DESC, p.created_at DESC";
        
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $products = [];
        while ($product = mysqli_fetch_assoc($result)) {
            $products[] = $product;
        }
        
        return $products;
    }
    
    /**
     * Get total search count
     */
    private function getSearchCount($query, $filters) {
        $sql = "
            SELECT COUNT(DISTINCT p.id) as total
            FROM products p
            WHERE p.status = 'active'
        ";
        
        $params = [];
        $types = '';
        
        if (!empty($query)) {
            $sql .= " AND (LOWER(p.title) LIKE ? OR LOWER(p.description) LIKE ?)";
            $searchTerm = "%$query%";
            $params = array_merge($params, [$searchTerm, $searchTerm]);
            $types .= 'ss';
        }
        
        if (!empty($filters['category_id'])) {
            $sql .= " AND p.category_id = ?";
            $params[] = $filters['category_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['min_price'])) {
            $sql .= " AND p.price >= ?";
            $params[] = $filters['min_price'];
            $types .= 'd';
        }
        
        if (!empty($filters['max_price'])) {
            $sql .= " AND p.price <= ?";
            $params[] = $filters['max_price'];
            $types .= 'd';
        }
        
        if (!empty($filters['brand'])) {
            $sql .= " AND LOWER(p.brand) = ?";
            $params[] = strtolower($filters['brand']);
            $types .= 's';
        }
        
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        return $result->fetch_assoc()['total'];
    }
    
    /**
     * Get related searches
     */
    private function getRelatedSearches($query, $limit = 5) {
        $related = [];
        
        // Get searches that led to the same products
        $stmt = mysqli_prepare($this->conn, "
            SELECT DISTINCT ss.query, ss.frequency
            FROM search_suggestions ss
            JOIN products p ON (
                ss.product_id = p.id OR 
                (ss.category_id IS NOT NULL AND ss.category_id = p.category_id)
            )
            WHERE p.id IN (
                SELECT p2.id FROM products p2
                WHERE (LOWER(p2.title) LIKE ? OR LOWER(p2.description) LIKE ?)
                AND p2.status = 'active'
                LIMIT 10
            )
            AND ss.query != ?
            AND ss.is_active = TRUE
            ORDER BY ss.frequency DESC
            LIMIT ?
        ");
        
        $searchTerm = "%$query%";
        mysqli_stmt_bind_param($stmt, "sssi", $searchTerm, $searchTerm, $query, $limit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $related[] = [
                'query' => $row['query'],
                'frequency' => $row['frequency']
            ];
        }
        
        return $related;
    }
    
    /**
     * Calculate relevance score
     */
    private function calculateRelevance($query, $text) {
        $query = strtolower($query);
        $text = strtolower($text);
        
        // Exact match gets highest score
        if ($query === $text) return 1.0;
        
        // Starts with gets high score
        if (strpos($text, $query) === 0) return 0.9;
        
        // Contains gets medium score
        if (strpos($text, $query) !== false) return 0.7;
        
        // Fuzzy matching (simple implementation)
        $similarity = 0;
        $queryWords = explode(' ', $query);
        $textWords = explode(' ', $text);
        
        foreach ($queryWords as $qWord) {
            foreach ($textWords as $tWord) {
                if (levenshtein($qWord, $tWord) <= 2) {
                    $similarity += 0.3;
                }
            }
        }
        
        return min(0.6, $similarity);
    }
    
    /**
     * Remove duplicates and sort suggestions
     */
    private function deduplicateAndSortSuggestions($suggestions, $limit) {
        $seen = [];
        $unique = [];
        
        foreach ($suggestions as $suggestion) {
            $key = $suggestion['type'] . '_' . ($suggestion['id'] ?? $suggestion['query'] ?? '');
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $suggestion;
            }
        }
        
        // Sort by relevance
        usort($unique, function($a, $b) {
            return $b['relevance'] <=> $a['relevance'];
        });
        
        return array_slice($unique, 0, $limit);
    }
    
    /**
     * Log search query for analytics
     */
    private function logSearchQuery($query) {
        if (empty($query)) return;
        
        // Update search suggestions frequency
        $stmt = mysqli_prepare($this->conn, "
            INSERT INTO search_suggestions (query, frequency, created_at)
            VALUES (?, 1, NOW())
            ON DUPLICATE KEY UPDATE 
                frequency = frequency + 1,
                updated_at = NOW()
        ");
        mysqli_stmt_bind_param($stmt, "s", $query);
        mysqli_stmt_execute($stmt);
        
        // Track user search behavior
        $this->trackSearchBehavior($query);
    }
    
    /**
     * Track search behavior for personalization
     */
    private function trackSearchBehavior($query) {
        $stmt = mysqli_prepare($this->conn, "
            INSERT INTO user_behavior (user_id, session_id, action_type, search_query, created_at)
            VALUES (?, ?, 'search', ?, NOW())
        ");
        mysqli_stmt_bind_param($stmt, "iss", $this->userId, $this->sessionId, $query);
        mysqli_stmt_execute($stmt);
    }
    
    /**
     * Get trending searches
     */
    public function getTrendingSearches($limit = 10) {
        $stmt = mysqli_prepare($this->conn, "
            SELECT query, frequency
            FROM search_suggestions 
            WHERE is_active = TRUE 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY frequency DESC
            LIMIT ?
        ");
        mysqli_stmt_bind_param($stmt, "i", $limit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $trending = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $trending[] = [
                'query' => $row['query'],
                'frequency' => $row['frequency']
            ];
        }
        
        return $trending;
    }
    
    /**
     * Get search analytics
     */
    public function getSearchAnalytics($days = 30) {
        $stmt = mysqli_prepare($this->conn, "
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as search_count,
                COUNT(DISTINCT user_id) as unique_users,
                AVG(frequency) as avg_frequency
            FROM search_suggestions ss
            LEFT JOIN user_behavior ub ON ss.query = ub.search_query 
                AND ub.action_type = 'search'
                AND ub.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            WHERE ss.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ");
        mysqli_stmt_bind_param($stmt, "ii", $days, $days);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $analytics = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $analytics[] = $row;
        }
        
        return $analytics;
    }
}
?>
