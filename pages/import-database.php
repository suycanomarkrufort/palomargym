<?php
require_once '../config/database.php';
require_once '../config/encryption.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_FILES['backup_file'])) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

try {
    $file = $_FILES['backup_file'];
    
    // Check file extension
    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    if ($file_ext !== 'wpgb') {
        throw new Exception('Invalid backup file. Only .wpgb files are allowed.');
    }
    
    // Read file content
    $file_content = file_get_contents($file['tmp_name']);
    $encrypted_backup = json_decode($file_content, true);
    
    if (!$encrypted_backup) {
        throw new Exception('Invalid backup file format');
    }
    
    // Verify signature
    if (!isset($encrypted_backup['signature']) || $encrypted_backup['signature'] !== 'WOLF_PALOMAR_ENCRYPTED_BACKUP') {
        throw new Exception('Invalid backup file signature. This is not a valid Wolf Palomar backup.');
    }
    
    // Check if encrypted
    if (!isset($encrypted_backup['encrypted']) || $encrypted_backup['encrypted'] !== true) {
        throw new Exception('Backup file is not encrypted properly.');
    }
    
    // Decrypt data
    $decrypted_data = decrypt_data($encrypted_backup['data']);
    
    if ($decrypted_data === false) {
        throw new Exception('Failed to decrypt backup. Invalid encryption key or corrupted file.');
    }
    
    $backup = json_decode($decrypted_data, true);
    
    if (!$backup) {
        throw new Exception('Invalid decrypted data structure');
    }

    // Start transaction
    $conn->begin_transaction();

    // Disable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");

    $imported_tables = 0;
    $skipped_tables = 0;

    // Get current user ID to preserve
    $current_user_id = get_user_id();

    // Process each table from backup
    foreach ($backup as $table => $data) {
        // Skip metadata
        if ($table === '_metadata') {
            continue;
        }

        // Check if table exists in current database
        $check_table = $conn->query("SHOW TABLES LIKE '$table'");
        if ($check_table->num_rows === 0) {
            $skipped_tables++;
            continue;
        }

        // Special handling for admin table - don't delete current user
        if ($table === 'admin') {
            $conn->query("DELETE FROM `$table` WHERE id != $current_user_id");
        } elseif ($table === 'staff') {
            // Also preserve current staff if logged in as staff
            if (is_staff()) {
                $conn->query("DELETE FROM `$table` WHERE id != $current_user_id");
            } else {
                $conn->query("TRUNCATE TABLE `$table`");
            }
        } else {
            // Truncate table
            $conn->query("TRUNCATE TABLE `$table`");
        }

        // Insert data
        if (is_array($data) && !empty($data)) {
            foreach ($data as $row) {
                // Skip current admin/staff user in backup to avoid conflict
                if (($table === 'admin' || $table === 'staff') && isset($row['id']) && $row['id'] == $current_user_id) {
                    continue;
                }

                $columns = array_keys($row);
                $values = array_values($row);

                // Escape column names
                $columns_str = implode(', ', array_map(function($col) {
                    return "`$col`";
                }, $columns));

                // Prepare placeholders
                $placeholders = implode(', ', array_fill(0, count($values), '?'));

                // Prepare statement
                $stmt = $conn->prepare("INSERT INTO `$table` ($columns_str) VALUES ($placeholders)");

                if ($stmt) {
                    // Create types string (s for all values to be safe)
                    $types = str_repeat('s', count($values));
                    
                    // Bind parameters
                    $stmt->bind_param($types, ...$values);
                    
                    // Execute
                    if (!$stmt->execute()) {
                        // Log error but continue with other records
                        error_log("Failed to insert row in $table: " . $stmt->error);
                    }
                    
                    $stmt->close();
                }
            }
        }

        $imported_tables++;
    }

    // Re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");

    // Commit transaction
    $conn->commit();

    // Get backup info
    $backup_info = isset($backup['_metadata']) ? $backup['_metadata'] : [];
    $export_date = isset($backup_info['export_date']) ? $backup_info['export_date'] : 'Unknown';
    $exported_by = isset($backup_info['exported_by_name']) ? $backup_info['exported_by_name'] : 'Unknown';

    // Log activity
    log_activity('Import Database', "Encrypted database restored ($imported_tables tables imported, $skipped_tables skipped) - Exported by: $exported_by on $export_date", get_user_id());

    echo json_encode([
        'success' => true, 
        'message' => 'Database restored successfully from encrypted backup',
        'imported_tables' => $imported_tables,
        'skipped_tables' => $skipped_tables,
        'export_date' => $export_date,
        'exported_by' => $exported_by
    ]);

} catch (Exception $e) {
    // Rollback on error
    if (isset($conn)) {
        $conn->rollback();
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    }

    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>