<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo "No schedules available.";
    exit;
}

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'];
$employeeId = $_GET['employee_id'] ?? null;

$scheduleQuery = "SELECT s.id AS schedule_id, s.label, s.start_time, s.end_time, s.user_id, e.emid
                    FROM schedules s
                    JOIN employee e ON s.user_id = e.id
                    LEFT JOIN user_attributes ua ON e.id = ua.user_id AND ua.attribute_name = 'team'
                    WHERE 1=1";

$params = [];
$types = "";

if ($role === 'agent') {
    $scheduleQuery .= " AND s.user_id = ?";
    $params[] = $userId;
    $types .= "i";
} elseif ($role === 'team_leader') {
    if ($employeeId) {
        $scheduleQuery .= " AND s.user_id = ?";
        $params[] = $employeeId;
        $types .= "i";
    } else {
        $scheduleQuery .= " AND ua.attribute_value = (SELECT ua.attribute_value FROM user_attributes WHERE user_id = ? AND attribute_name = 'team')";
        $params[] = $userId;
        $types .= "i";
    }
} elseif ($role === 'operation_manager') {
    if ($employeeId) {
        $scheduleQuery .= " AND s.user_id = ?";
        $params[] = $employeeId;
        $types .= "i";
    }
}

$stmt = $conn->prepare($scheduleQuery);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$scheduleResult = $stmt->get_result();

if ($scheduleResult && $scheduleResult->num_rows > 0) {
    while ($schedule = $scheduleResult->fetch_assoc()) {
        $startTime = date('h:i A', strtotime($schedule['start_time']));
        $endTime = date('h:i A', strtotime($schedule['end_time']));
        $scheduleTimeRange = $startTime . ' - ' . $endTime;

        echo '<div class="schedule-item" data-user-id="' . htmlspecialchars($schedule['user_id']) . '" data-schedule-id="' . htmlspecialchars($schedule['schedule_id']) . '">';
        echo '<span class="label">' . htmlspecialchars($schedule['label']) . '</span>';
        echo '<span class="time">' . $scheduleTimeRange . '</span>';

        if ($role === 'team_leader') {
            echo '<button class="delete-schedule-btn" data-schedule-id="' . htmlspecialchars($schedule['schedule_id']) . '">Delete</button>';
        }
        echo '</div>';
    }
} else {
    echo '<p>No schedules available.</p>';
}

if (isset($stmt)) {
    $stmt->close();
}
$conn->close();
?>