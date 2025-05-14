<?php
require 'db.php';

$userId = $_GET['employee_id'] ?? null;
$team = $_GET['team'] ?? null;

// Get the current time in a comparable format (e.g., HH:MM:SS)
$currentTime = date('H:i:s');

$query = "SELECT s.label, s.time, e.emid
          FROM schedules s
          JOIN employee e ON s.user_id = e.id
          LEFT JOIN user_attributes ua ON e.id = ua.user_id AND ua.attribute_name = 'team'";

$params = [];
$types = "";

$whereClauses = [];

if ($userId) {
    $whereClauses[] = "s.user_id = ?";
    $params[] = $userId;
    $types .= "i";
} elseif ($team) {
    $whereClauses[] = "ua.attribute_value = ?";
    $params[] = $team;
    $types .= "s";
} else {
    // Default loading based on the logged-in user's role, similar to your existing logic
    session_start();
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'agent') {
        $whereClauses[] = "s.user_id = ?";
        $params[] = $_SESSION['user_id'];
        $types .= "i";
    } elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'team_leader') {
        // Assuming you have a way to get the team of the team leader
        $teamLeaderTeamQuery = "SELECT attribute_value FROM user_attributes WHERE user_id = ? AND attribute_name = 'team'";
        $teamLeaderTeamStmt = $conn->prepare($teamLeaderTeamQuery);
        $teamLeaderTeamStmt->bind_param("i", $_SESSION['user_id']);
        $teamLeaderTeamStmt->execute();
        $teamLeaderTeamResult = $teamLeaderTeamStmt->get_result();
        if ($teamLeaderTeamResult && $row = $teamLeaderTeamResult->fetch_assoc()) {
            $whereClauses[] = "ua.attribute_value = ?";
            $params[] = $row['attribute_value'];
            $types .= "s";
        }
        $teamLeaderTeamStmt->close();
    }
}

// Add the condition to filter by the current ongoing schedule
// This part assumes your 's.time' can be used to determine a start and end time
// You might need to adjust this based on how your 's.time' is structured
$whereClauses[] = "TIME_FORMAT(s.time, '%H:%i:%s') <= CURTIME() AND TIME_FORMAT(DATE_ADD(s.time, INTERVAL 1 HOUR), '%H:%i:%s') > CURTIME()";
 // **IMPORTANT:** The `INTERVAL 1 HOUR` is an assumption. You should replace this with your actual schedule duration or a proper end time from your database if available.
// A more accurate approach would be to have a separate 'end_time' column in your 'schedules' table.
// If you have 'start_time' and 'end_time' columns, the condition would be:
// "$currentTime BETWEEN s.start_time AND s.end_time"
// Make sure the time formats match for comparison.

if (!empty($whereClauses)) {
    $query .= " WHERE " . implode(" AND ", $whereClauses);
}

$query .= " ORDER BY s.time";

$stmt = $conn->prepare($query);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    while ($schedule = $result->fetch_assoc()) {
        $scheduleTime = date('h:i A', strtotime($schedule['time']));
        echo "<div class=\"ongoing-item highlighted\">"; // Always highlighted as it's the current one
        echo "<span class=\"ongoing-label\">" . htmlspecialchars($schedule['label']) . "</span>";
        echo "<span class=\"ongoing-time\">" . $scheduleTime . "</span>";
        if (isset($schedule['emid'])) {
            echo "<div class=\"employee-info\">Employee ID: " . htmlspecialchars($schedule['emid']) . "</div>";
        }
        echo "</div>";
    }
    $result->free();
} else {
    echo "<p>No ongoing tasks at this time.</p>";
}

$stmt->close();
$conn->close();
?>