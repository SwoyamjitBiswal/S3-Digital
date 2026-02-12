<?php
/**
 * Database Migration System
 * Handles database schema migrations and version control
 */

class Migration {
    private $conn;
    private $migrationsPath;
    private $versionTable = 'migrations';
    
    public function __construct($conn, $migrationsPath = null) {
        $this->conn = $conn;
        $this->migrationsPath = $migrationsPath ?: __DIR__ . '/../database/migrations';
        
        // Create migrations table if it doesn't exist
        $this->createMigrationsTable();
    }
    
    /**
     * Create migrations tracking table
     */
    private function createMigrationsTable() {
        $sql = "
            CREATE TABLE IF NOT EXISTS `{$this->versionTable}` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `migration` varchar(255) NOT NULL,
                `batch` int(11) NOT NULL,
                `executed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `migration` (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        mysqli_query($this->conn, $sql);
    }
    
    /**
     * Run all pending migrations
     */
    public function migrate() {
        $files = $this->getMigrationFiles();
        $ran = $this->getRanMigrations();
        
        $pending = array_diff($files, $ran);
        
        if (empty($pending)) {
            echo "No pending migrations.\n";
            return true;
        }
        
        $batch = $this->getNextBatchNumber();
        
        foreach ($pending as $file) {
            $this->runMigration($file, $batch);
        }
        
        echo "Migration completed successfully.\n";
        return true;
    }
    
    /**
     * Run a single migration
     */
    public function runMigration($file, $batch = null) {
        $batch = $batch ?: $this->getNextBatchNumber();
        
        echo "Running migration: {$file}\n";
        
        // Read migration file
        $migrationPath = $this->migrationsPath . '/' . $file;
        
        if (!file_exists($migrationPath)) {
            throw new Exception("Migration file not found: {$migrationPath}");
        }
        
        $content = file_get_contents($migrationPath);
        
        // Extract up and down migrations
        preg_match('/-- UP\s*([\s\S]*?)\s*-- DOWN/s', $content, $matches);
        
        if (!isset($matches[1])) {
            throw new Exception("Invalid migration format in {$file}");
        }
        
        $upSql = trim($matches[1]);
        
        // Execute migration
        try {
            // Split into individual statements
            $statements = array_filter(array_map('trim', explode(';', $upSql)));
            
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    if (!mysqli_query($this->conn, $statement)) {
                        throw new Exception("Migration failed: " . mysqli_error($this->conn));
                    }
                }
            }
            
            // Record migration
            $this->logMigration($file, $batch);
            
            echo "Migration {$file} completed successfully.\n";
            
        } catch (Exception $e) {
            echo "Migration {$file} failed: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
    
    /**
     * Rollback last batch of migrations
     */
    public function rollback($steps = 1) {
        $batch = $this->getLastBatchNumber() - $steps + 1;
        
        if ($batch < 1) {
            echo "Nothing to rollback.\n";
            return true;
        }
        
        $migrations = $this->getMigrationsByBatch($batch);
        
        if (empty($migrations)) {
            echo "No migrations found for rollback.\n";
            return true;
        }
        
        foreach (array_reverse($migrations) as $migration) {
            $this->rollbackMigration($migration['migration']);
        }
        
        echo "Rollback completed successfully.\n";
        return true;
    }
    
    /**
     * Rollback a single migration
     */
    public function rollbackMigration($file) {
        echo "Rolling back migration: {$file}\n";
        
        // Read migration file
        $migrationPath = $this->migrationsPath . '/' . $file;
        
        if (!file_exists($migrationPath)) {
            throw new Exception("Migration file not found: {$migrationPath}");
        }
        
        $content = file_get_contents($migrationPath);
        
        // Extract down migration
        preg_match('/-- DOWN\s*([\s\S]*?)\s*-- UP/s', $content, $matches);
        
        if (!isset($matches[1])) {
            throw new Exception("No DOWN migration found in {$file}");
        }
        
        $downSql = trim($matches[1]);
        
        // Execute rollback
        try {
            // Split into individual statements
            $statements = array_filter(array_map('trim', explode(';', $downSql)));
            
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    if (!mysqli_query($this->conn, $statement)) {
                        throw new Exception("Rollback failed: " . mysqli_error($this->conn));
                    }
                }
            }
            
            // Remove migration record
            $this->removeMigration($file);
            
            echo "Rollback {$file} completed successfully.\n";
            
        } catch (Exception $e) {
            echo "Rollback {$file} failed: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
    
    /**
     * Get migration files
     */
    private function getMigrationFiles() {
        $files = [];
        
        if (is_dir($this->migrationsPath)) {
            $files = scandir($this->migrationsPath);
            $files = array_filter($files, function($file) {
                return preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_\w+\.sql$/', $file);
            });
            sort($files);
        }
        
        return $files;
    }
    
    /**
     * Get ran migrations
     */
    private function getRanMigrations() {
        $result = mysqli_query($this->conn, "SELECT migration FROM {$this->versionTable} ORDER BY migration");
        
        $migrations = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $migrations[] = $row['migration'];
        }
        
        return $migrations;
    }
    
    /**
     * Get migrations by batch
     */
    private function getMigrationsByBatch($batch) {
        $stmt = mysqli_prepare($this->conn, "SELECT migration FROM {$this->versionTable} WHERE batch = ? ORDER BY migration DESC");
        mysqli_stmt_bind_param($stmt, "i", $batch);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $migrations = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $migrations[] = $row;
        }
        
        return $migrations;
    }
    
    /**
     * Get next batch number
     */
    private function getNextBatchNumber() {
        $result = mysqli_query($this->conn, "SELECT MAX(batch) as batch FROM {$this->versionTable}");
        $row = mysqli_fetch_assoc($result);
        
        return ($row['batch'] ?? 0) + 1;
    }
    
    /**
     * Get last batch number
     */
    private function getLastBatchNumber() {
        $result = mysqli_query($this->conn, "SELECT MAX(batch) as batch FROM {$this->versionTable}");
        $row = mysqli_fetch_assoc($result);
        
        return $row['batch'] ?? 0;
    }
    
    /**
     * Log migration
     */
    private function logMigration($migration, $batch) {
        $stmt = mysqli_prepare($this->conn, "INSERT INTO {$this->versionTable} (migration, batch) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, "si", $migration, $batch);
        mysqli_stmt_execute($stmt);
    }
    
    /**
     * Remove migration record
     */
    private function removeMigration($migration) {
        $stmt = mysqli_prepare($this->conn, "DELETE FROM {$this->versionTable} WHERE migration = ?");
        mysqli_stmt_bind_param($stmt, "s", $migration);
        mysqli_stmt_execute($stmt);
    }
    
    /**
     * Get migration status
     */
    public function status() {
        $files = $this->getMigrationFiles();
        $ran = $this->getRanMigrations();
        
        echo "Migration Status:\n";
        echo "================\n";
        
        foreach ($files as $file) {
            $status = in_array($file, $ran) ? 'Ran' : 'Pending';
            echo "[{$status}] {$file}\n";
        }
        
        $pending = count(array_diff($files, $ran));
        echo "\nPending: {$pending}\n";
        echo "Ran: " . count($ran) . "\n";
    }
    
    /**
     * Create new migration file
     */
    public function create($name) {
        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_{$name}.sql";
        $filepath = $this->migrationsPath . '/' . $filename;
        
        // Create migrations directory if it doesn't exist
        if (!is_dir($this->migrationsPath)) {
            mkdir($this->migrationsPath, 0755, true);
        }
        
        $template = "-- Migration: {$name}\n";
        $template .= "-- Created: " . date('Y-m-d H:i:s') . "\n\n";
        $template .= "-- UP\n";
        $template .= "-- Add your migration SQL here\n\n";
        $template .= "-- DOWN\n";
        $template .= "-- Add your rollback SQL here\n";
        
        if (file_put_contents($filepath, $template)) {
            echo "Migration created: {$filename}\n";
            return $filename;
        } else {
            throw new Exception("Failed to create migration file: {$filepath}");
        }
    }
    
    /**
     * Reset all migrations
     */
    public function reset() {
        $migrations = $this->getRanMigrations();
        
        if (empty($migrations)) {
            echo "No migrations to reset.\n";
            return true;
        }
        
        foreach (array_reverse($migrations) as $migration) {
            $this->rollbackMigration($migration);
        }
        
        echo "All migrations have been reset.\n";
        return true;
    }
    
    /**
     * Refresh migrations (rollback and migrate)
     */
    public function refresh() {
        $this->reset();
        $this->migrate();
    }
}
?>
