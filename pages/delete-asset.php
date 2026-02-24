<?php
require_once '../config/database.php';
header('Content-Type: application/json');

if (!is_logged_in() || !is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$id = intval($_POST['id']);

// Ang gagawin lang natin ay gagawing '0' ang asset_saved para mawala sa Vault view
$query = $conn->prepare("UPDATE member SET asset_saved = 0 WHERE id = ?");
$query->bind_param("i", $id);

if ($query->execute()) {
    echo json_encode(['success' => true, 'message' => 'Asset removed from Vault. Member is safe.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
?>