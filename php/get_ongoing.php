<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$role = $_SESSION['role'];
$userId = $_SESSION['user_id'];
$employeeId = isset($_GET['employee_id']) ? $_GET['employee_id'] : null;
$team = isset($_GET['team']) ? $_GET['team'] : null;

// Debugging: Output the role and userId
echo "";

$scheduleQuery = "SELECT s.id AS schedule_id, s.label, s.start_time, s.end_time, e.emid, e.role, ua.attribute_value AS team
                  FROM schedules s
                  JOIN employee e ON s.user_id = e.id
                  LEFT JOIN user_attributes ua ON e.id = ua.user_id AND ua.attribute_name = 'team'
                  WHERE CURTIME() BETWEEN TIME(s.start_time) AND TIME(s.end_time)";


$params = [];
$types = "";

if ($role === 'agent') {
    $scheduleQuery .= " AND s.user_id = ?";
    $params[] = $userId;
    $types = "i";
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
    } elseif ($team) {
        $scheduleQuery .= " AND ua.attribute_value = ?";
        $params[] = $team;
        $types .= "s";
    }
}

// Debugging: Output the final SQL query (before parameters are bound)
echo "";
// Debugging: Output the parameters and their types
echo "";

$scheduleStmt = $conn->prepare($scheduleQuery);
if ($params) {
    $scheduleStmt->bind_param($types, ...$params);
}
$scheduleStmt->execute();
$scheduleResult = $scheduleStmt->get_result();

if ($scheduleResult && $scheduleResult->num_rows > 0) {
    // Debugging: Output the number of rows returned
    echo "";
    while ($schedule = $scheduleResult->fetch_assoc()) {
        ?>
        <div class="ongoing-item">
    <div class="ongoing-label"><?php echo htmlspecialchars($schedule['label']); ?></div>
    <div class="ongoing-time">
        <?php
            echo htmlspecialchars(date('h:i A', strtotime($schedule['start_time']))) . " - " . htmlspecialchars(date('h:i A', strtotime($schedule['end_time'])));
        ?>
    </div>
    <div class="employee-info">
        <?php echo htmlspecialchars($schedule['emid']) . " (" . htmlspecialchars($schedule['role']) . ")"; ?>
    </div>
</div>

        <?php
    }
} else {
    echo "";
    echo "<p style='color: white;'>No ongoing schedules at this time.</p>";
}

if (isset($scheduleStmt)) {
    $scheduleStmt->close();
}
$conn->close();
?>