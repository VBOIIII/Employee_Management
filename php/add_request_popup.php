<?php
session_start();
require 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get the employee_id from the form submission
$employee_id = isset($_POST['employee_id']) ? (int)$_POST['employee_id'] : null;
$request = $_POST['request'] ?? '';
$remarks = $_POST['remarks'] ?? '';

// Validate the input
if ($employee_id === null || empty($request)) {
    echo json_encode(['success' => false, 'message' => 'Employee and Request fields cannot be empty.']);
    exit;
}

// You might want to add a check here to ensure the logged-in user (team_leader)
// has the permission to create a request for the selected $employee_id.

$stmt = $conn->prepare("INSERT INTO requests (user_id, date, request, remarks, status) VALUES (?, NOW(), ?, ?, 'Pending')");
// **IMPORTANT:** Use $employee_id here for the user_id column
$stmt->bind_param("iss", $employee_id, $request, $remarks);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Request added successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to add request: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>