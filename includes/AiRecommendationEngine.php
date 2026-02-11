<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

class AiRecommendationEngine {
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
     * Track user behavior for AI learning
     */
    public function trackBehavior($productId, $actionType, $categoryId = null, $timeSpent = 0, $searchQuery = null) {
        $stmt = mysqli_prepare($this->conn, "
            INSERT INTO user_behavior (user_id, session_id, product_id, action_type, category_id, time_spent, search_query)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        mysqli_stmt_bind_param($stmt, "iisisis", $this->userId, $this->sessionId, $productId, $actionType, $categoryId, $timeSpent, $searchQuery);
        mysqli_stmt_execute($stmt);
        
        // Update recommendations after significant actions
        if (in_array($actionType, ['purchase', 'add_to_cart'])) {
            $this->updateRecommendations();
        }
    }
    
    /**
     * Get personalized product recommendations
     */
    public function getRecommendations($limit = 10, $type = 'all') {
        $recommendations = [];
        
        // Get collaborative filtering recommendations
        if ($type === 'all' || $type === 'collaborative') {
            $collaborative = $this->getCollaborativeRecommendations($limit / 3);
            $recommendations = array_merge($recommendations, $collaborative);
        }
        
        // Get content-based recommendations
        if ($type === 'all' || $type === 'content_based') {
            $content = $this->getContentBasedRecommendations($limit / 3);
            $recommendations = array_merge($recommendations, $content);
        }
        
        // Get trending products
        if ($type === 'all' || $type === 'trending') {
            $trending = $this->getTrendingRecommendations($limit / 3);
            $recommendations = array_merge($recommendations, $trending);
        }
        
        // Remove duplicates and sort by score
        $recommendations = $this->deduplicateAndSort($recommendations, $limit);
        
        return $recommendations;
    }
    
    /**
     * Collaborative filtering recommendations
     */
    private function getCollaborativeRecommendations($limit) {
        $recommendations = [];
        
        if (!$this->userId) return $recommendations;
        
        // Find users with similar behavior
        $stmt = mysqli_prepare($this->conn, "
            SELECT DISTINCT ub2.user_id, COUNT(*) as common_actions
            FROM user_behavior ub1
            JOIN user_behavior ub2 ON ub1.product_id = ub2.product_id 
                AND ub1.action_type = ub2.action_type 
                AND ub1.user_id != ub2.user_id
            WHERE ub1.user_id = ? 
                AND ub2.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY ub2.user_id
            HAVING common_actions >= 3
            ORDER BY common_actions DESC
            LIMIT 10
        ");
        mysqli_stmt_bind_param($stmt, "i", $this->userId);
        mysqli_stmt_execute($stmt);
        $similarUsers = mysqli_stmt_get_result($stmt);
        
        $similarUserIds = [];
        while ($row = mysqli_fetch_assoc($similarUsers)) {
            $similarUserIds[] = $row['user_id'];
        }
        
        if (!empty($similarUserIds)) {
            $placeholders = str_repeat('?,', count($similarUserIds) - 1) . '?';
            $types = str_repeat('i', count($similarUserIds));
            
            // Get products liked by similar users but not by current user
            $stmt = mysqli_prepare($this->conn, "
                SELECT DISTINCT p.id, p.title, p.price, p.image, COUNT(*) as recommendation_count
                FROM products p
                JOIN user_behavior ub ON p.id = ub.product_id
                WHERE ub.user_id IN ($placeholders)
                    AND ub.action_type IN ('purchase', 'add_to_cart')
                    AND p.id NOT IN (
                        SELECT product_id FROM user_behavior 
                        WHERE user_id = ? AND action_type IN ('purchase', 'add_to_cart')
                    )
                    AND p.status = 'active'
                GROUP BY p.id
                ORDER BY recommendation_count DESC, p.created_at DESC
                LIMIT ?
            ");
            
            $params = array_merge($similarUserIds, [$this->userId, $limit]);
            mysqli_stmt_bind_param($stmt, $types . 'ii', ...$params);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            while ($row = mysqli_fetch_assoc($result)) {
                $recommendations[] = [
                    'product' => $row,
                    'type' => 'collaborative',
                    'score' => $row['recommendation_count'] / 10,
                    'reason' => 'Users with similar taste also liked this'
                ];
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Content-based recommendations
     */
    private function getContentBasedRecommendations($limit) {
        $recommendations = [];
        
        // Get user's preferred categories from behavior
        $stmt = mysqli_prepare($this->conn, "
            SELECT c.id, c.name, COUNT(*) as interaction_count
            FROM categories c
            JOIN user_behavior ub ON c.id = ub.category_id
            WHERE ub.user_id = ? AND ub.category_id IS NOT NULL
            GROUP BY c.id
            ORDER BY interaction_count DESC
            LIMIT 5
        ");
        mysqli_stmt_bind_param($stmt, "i", $this->userId);
        mysqli_stmt_execute($stmt);
        $categories = mysqli_stmt_get_result($stmt);
        
        $categoryIds = [];
        while ($row = mysqli_fetch_assoc($categories)) {
            $categoryIds[] = $row['id'];
        }
        
        if (!empty($categoryIds)) {
            $placeholders = str_repeat('?,', count($categoryIds) - 1) . '?';
            $types = str_repeat('i', count($categoryIds));
            
            // Get products from preferred categories
            $stmt = mysqli_prepare($this->conn, "
                SELECT p.id, p.title, p.price, p.image, c.name as category_name
                FROM products p
                JOIN categories c ON p.category_id = c.id
                WHERE p.category_id IN ($placeholders)
                    AND p.status = 'active'
                    AND p.id NOT IN (
                        SELECT product_id FROM user_behavior 
                        WHERE user_id = ? AND action_type = 'purchase'
                    )
                ORDER BY p.created_at DESC
                LIMIT ?
            ");
            
            $params = array_merge($categoryIds, [$this->userId, $limit]);
            mysqli_stmt_bind_param($stmt, $types . 'ii', ...$params);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            while ($row = mysqli_fetch_assoc($result)) {
                $recommendations[] = [
                    'product' => $row,
                    'type' => 'content_based',
                    'score' => 0.7,
                    'reason' => 'Based on your interest in ' . $row['category_name']
                ];
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Trending products recommendations
     */
    private function getTrendingRecommendations($limit) {
        $recommendations = [];
        
        $stmt = mysqli_prepare($this->conn, "
            SELECT p.id, p.title, p.price, p.image, 
                   COUNT(ub.id) as view_count,
                   COUNT(CASE WHEN ub.action_type = 'add_to_cart' THEN 1 END) as cart_count,
                   COUNT(CASE WHEN ub.action_type = 'purchase' THEN 1 END) as purchase_count
            FROM products p
            LEFT JOIN user_behavior ub ON p.id = ub.product_id 
                AND ub.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            WHERE p.status = 'active'
            GROUP BY p.id
            HAVING view_count > 0
            ORDER BY (cart_count * 3 + purchase_count * 5 + view_count) DESC
            LIMIT ?
        ");
        mysqli_stmt_bind_param($stmt, "i", $limit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $recommendations[] = [
                'product' => $row,
                'type' => 'trending',
                'score' => ($row['cart_count'] * 3 + $row['purchase_count'] * 5 + $row['view_count']) / 100,
                'reason' => 'Trending product - ' . $row['view_count'] . ' views this week'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Get similar products for a given product
     */
    public function getSimilarProducts($productId, $limit = 5) {
        $recommendations = [];
        
        // Get product details
        $stmt = mysqli_prepare($this->conn, "
            SELECT p.*, c.name as category_name
            FROM products p
            JOIN categories c ON p.category_id = c.id
            WHERE p.id = ? AND p.status = 'active'
        ");
        mysqli_stmt_bind_param($stmt, "i", $productId);
        mysqli_stmt_execute($stmt);
        $product = mysqli_stmt_get_result($stmt)->fetch_assoc();
        
        if (!$product) return $recommendations;
        
        // Get products from same category
        $stmt = mysqli_prepare($this->conn, "
            SELECT p.*, c.name as category_name
            FROM products p
            JOIN categories c ON p.category_id = c.id
            WHERE p.category_id = ? 
                AND p.id != ? 
                AND p.status = 'active'
            ORDER BY 
                ABS(p.price - ?) ASC,
                p.created_at DESC
            LIMIT ?
        ");
        mysqli_stmt_bind_param($stmt, "iiid", $product['category_id'], $productId, $product['price'], $limit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $recommendations[] = [
                'product' => $row,
                'type' => 'similar',
                'score' => 0.8,
                'reason' => 'Similar to ' . $product['title']
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Update recommendation cache
     */
    private function updateRecommendations() {
        if (!$this->userId) return;
        
        // Clear old recommendations
        $stmt = mysqli_prepare($this->conn, "
            DELETE FROM product_recommendations 
            WHERE user_id = ? OR created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        mysqli_stmt_bind_param($stmt, "i", $this->userId);
        mysqli_stmt_execute($stmt);
        
        // Generate new recommendations
        $recommendations = $this->getRecommendations(20);
        
        foreach ($recommendations as $rec) {
            $stmt = mysqli_prepare($this->conn, "
                INSERT INTO product_recommendations (user_id, product_id, recommendation_type, score, expires_at)
                VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
            ");
            mysqli_stmt_bind_param($stmt, "iisd", $this->userId, $rec['product']['id'], $rec['type'], $rec['score']);
            mysqli_stmt_execute($stmt);
        }
    }
    
    /**
     * Remove duplicates and sort recommendations
     */
    private function deduplicateAndSort($recommendations, $limit) {
        $seen = [];
        $unique = [];
        
        foreach ($recommendations as $rec) {
            $productId = $rec['product']['id'];
            if (!isset($seen[$productId])) {
                $seen[$productId] = true;
                $unique[] = $rec;
            }
        }
        
        // Sort by score
        usort($unique, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        return array_slice($unique, 0, $limit);
    }
    
    /**
     * Get recommendation reasons for display
     */
    public function getRecommendationReasons($recommendations) {
        $reasons = [];
        foreach ($recommendations as $rec) {
            $reasons[] = $rec['reason'] ?? 'Recommended for you';
        }
        return $reasons;
    }
}
?>
