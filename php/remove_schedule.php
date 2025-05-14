<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['schedule_id'])) {
    $scheduleId = $_POST['schedule_id'];

    // Sanitize the input to prevent SQL injection
    $scheduleId = mysqli_real_escape_string($conn, $scheduleId);

    $query = "DELETE FROM schedules WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $scheduleId);

    if ($stmt->execute()) {
        echo json_encode(array("success" => true, "message" => "Schedule deleted successfully."));
    } else {
        echo json_encode(array("success" => false, "message" => "Failed to delete schedule."));
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(array("success" => false, "message" => "Invalid request."));
}
?>
