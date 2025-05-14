<?php
session_start();
require 'db.php';

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['user_id']) || !isset($_POST['emid']) || !isset($_POST['schedule_label']) || !isset($_POST['start_time']) || !isset($_POST['end_time'])) {
    $response['message'] = 'Invalid request';
} else {
    $userId = $_SESSION['user_id'];
    $emid = $_POST['emid'];
    $scheduleLabel = $_POST['schedule_label'];
    $startTime = $_POST['start_time'];
    $endTime = $_POST['end_time'];

    // TLs can add schedules for anyone
    $insertQuery = "INSERT INTO schedules (user_id, label, start_time, end_time) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($insertQuery);
    $stmt->bind_param("isss", $emid, $scheduleLabel, $startTime, $endTime);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Schedule added successfully.';
    } else {
        $response['message'] = 'Failed to add schedule: ' . $stmt->error;
    }
    $stmt->close();

}

echo json_encode($response);
?>