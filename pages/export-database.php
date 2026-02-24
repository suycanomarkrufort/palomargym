<?php
require_once '../config/database.php';
require_once '../config/encryption.php';

if (!is_logged_in()) {
    die('Unauthorized');
}

// Get all tables from database dynamically
$tables_query = $conn->query("SHOW TABLES");
$tables = [];

while ($row = $tables_query->fetch_array()) {
    $tables[] = $row[0];
}

$backup = [];

// Export each table
foreach ($tables as $table) {
    try {
        $query = "SELECT * FROM `$table`";
        $result = $conn->query($query);
        
        if ($result) {
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $backup[$table] = $data;
        }
    } catch (Exception $e) {
        // Skip table if error occurs
        continue;
    }
}

// Add metadata
$backup['_metadata'] = [
    'export_date' => date('Y-m-d H:i:s'),
    'system_version' => 'v2.5.6-STABLE',
    'exported_by' => get_user_id(),
    'exported_by_name' => get_current_user_name(),
    'table_count' => count($tables),
    'database_name' => 'wolf_palomar_gym'
];

// Convert to JSON
$json_data = json_encode($backup, JSON_PRETTY_PRINT);

// Encrypt the data
$encrypted_data = encrypt_data($json_data);

// Create encrypted backup structure
$encrypted_backup = [
    'signature' => 'WOLF_PALOMAR_ENCRYPTED_BACKUP',
    'version' => '1.0',
    'timestamp' => time(),
    'encrypted' => true,
    'data' => $encrypted_data
];

// Log activity
log_activity('Export Database', 'Encrypted database backup exported (' . count($tables) . ' tables)', get_user_id());

// Return as encrypted JSON with .wpgb extension
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="wolf_palomar_backup_' . date('Ymd_His') . '.wpgb"');
echo json_encode($encrypted_backup);
?>