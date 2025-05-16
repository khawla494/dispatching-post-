<?php
$host = "sql103.infinityfree.com"; 
$dbname = "if0_38668211_dispating_post_app"; 
$username = "if0_38668211"; 
$password = "8GSos6jsJJZ"; 

// Function to fix column name issues
function fixColumnNames($query) {
    // Map of column names that need fixing (code column name => database column name)
    $replacements = [
        'mediaPath' => 'media_path',
        'scheduled_time' => 'scheduled_at'
        
    ];
    
    // Debug - log the original query for troubleshooting
    error_log("Original query: " . $query);
    
    // Apply all replacements
    foreach ($replacements as $wrong => $correct) {
        $query = str_replace($wrong, $correct, $query);
    }
    
    // Debug - log the modified query
    error_log("Modified query: " . $query);
    
    return $query;
}

try {
    // Create a standard PDO connection
    $dsn = "mysql:host=$host;port=3306;dbname=$dbname;charset=utf8mb4";
    $originalPdo = new PDO($dsn, $username, $password);
    $originalPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create our PDO wrapper object that will intercept and fix all queries
    $pdo = new class($originalPdo) {
        private $pdo;
        
        public function __construct($pdo) {
            $this->pdo = $pdo;
        }
        
        // Intercept prepare calls
        public function prepare($query, $options = []) {
            // First fix column names
            $fixedQuery = fixColumnNames($query);
            
            // Check if this is an INSERT query with scheduled_at
            if (stripos($fixedQuery, 'INSERT INTO') !== false && 
                stripos($fixedQuery, 'scheduled_at') !== false) {
                
                // If VALUES contains NULL for scheduled_at or doesn't specify it
                if (stripos($fixedQuery, 'scheduled_at') !== false && 
                   (stripos($fixedQuery, 'scheduled_at, NULL') !== false || 
                    stripos($fixedQuery, 'scheduled_at = NULL') !== false ||
                    stripos($fixedQuery, 'scheduled_at=NULL') !== false)) {
                    
                    // Replace NULL with current date-time
                    $fixedQuery = str_replace(
                        ['scheduled_at, NULL', 'scheduled_at = NULL', 'scheduled_at=NULL'], 
                        ['scheduled_at, NOW()', 'scheduled_at = NOW()', 'scheduled_at=NOW()'], 
                        $fixedQuery
                    );
                }
                
                // If it's a prepared statement with placeholders
                if (preg_match('/INSERT INTO.*\((.*?)\).*VALUES.*\((.*?)\)/is', $fixedQuery, $matches)) {
                    $columns = $matches[1];
                    $values = $matches[2];
                    
                    // If scheduled_at isn't in the column list, add it
                    if (stripos($columns, 'scheduled_at') === false) {
                        $columns .= ', scheduled_at';
                        $values .= ', NOW()';
                        $fixedQuery = preg_replace(
                            '/INSERT INTO (.*?) \((.*?)\) VALUES \((.*?)\)/is', 
                            'INSERT INTO $1 (' . $columns . ') VALUES (' . $values . ')', 
                            $fixedQuery
                        );
                    }
                }
            }
            
            // Log the final query for debugging
            error_log("Final query: " . $fixedQuery);
            
            return $this->pdo->prepare($fixedQuery, $options);
        }
        
        // Pass all other method calls to the original PDO
        public function __call($name, $arguments) {
            return call_user_func_array([$this->pdo, $name], $arguments);
        }
        
        // Make sure setAttribute and other PDO methods work
        public function setAttribute($attribute, $value) {
            return $this->pdo->setAttribute($attribute, $value);
        }
        
        public function getAttribute($attribute) {
            return $this->pdo->getAttribute($attribute);
        }
    };
    
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
?>