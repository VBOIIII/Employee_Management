<?php
session_start();
require 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Ensure only team_leaders can use this functionality (as per your dashboard code)
if ($_SESSION['role'] !== 'team_leader') {
    echo json_encode(['success' => false, 'message' => 'Forbidden: Only team leaders can update request status.']);
    exit;
}

// Check if the request ID and status are present in the POST data
$requestId = isset($_POST['id']) ? (int)$_POST['id'] : null;
$status = isset($_POST['status']) ? $_POST['status'] : null;

if ($requestId === null || !in_array($status, ['Accepted', 'Declined'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
    exit;
}

// Update the request status in the database
$updateQuery = "UPDATE requests SET status = ? WHERE id = ?";
$stmt = $conn->prepare($updateQuery);
$stmt->bind_param("si", $status, $requestId);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Request status updated successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update request status: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>