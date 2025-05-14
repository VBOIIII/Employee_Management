<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$role = $_SESSION['role'];
$userId = $_SESSION['user_id'];

// Get the employee_id from the GET request
$employeeIdToFilter = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : null;

$requestQuery = "SELECT r.id AS request_id, r.date, r.request, r.remarks, r.status, e.emid, e.role
                    FROM requests r
                    JOIN employee e ON r.user_id = e.id
                    LEFT JOIN user_attributes ua ON e.id = ua.user_id AND ua.attribute_name = 'team'";

$params = [];
$types = "";
$whereClauses = [];

if ($role === 'agent') {
    $whereClauses[] = "r.user_id = ?";
    $params[] = $userId;
    $types .= "i";
} elseif ($role === 'team_leader') {
    // Team leader sees their team's requests by default, or a specific employee's if selected
    if (empty($employeeIdToFilter)) {
        $queryUserTeam = "SELECT a.attribute_value AS team FROM user_attributes a WHERE a.user_id = ? AND a.attribute_name = 'team'";
        $stmtUserTeam = $conn->prepare($queryUserTeam);
        $stmtUserTeam->bind_param("i", $userId);
        $stmtUserTeam->execute();
        $resultUserTeam = $stmtUserTeam->get_result();
        if ($row = $resultUserTeam->fetch_assoc()) {
            $userTeam = $row['team'];
            $whereClauses[] = "ua.attribute_value = ?";
            $params[] = $userTeam;
            $types .= "s";
        }
        $stmtUserTeam->close();
    } else {
        $whereClauses[] = "r.user_id = ?";
        $params[] = $employeeIdToFilter;
        $types .= "i";
    }
} elseif ($role === 'operation_manager') {
    // Operation manager can filter by any employee if selected, otherwise sees all (no initial filter here)
    if (!empty($employeeIdToFilter)) {
        $whereClauses[] = "r.user_id = ?";
        $params[] = $employeeIdToFilter;
        $types .= "i";
    }
}

// Add WHERE clause if there are any conditions
if (!empty($whereClauses)) {
    $requestQuery .= " WHERE " . implode(' AND ', $whereClauses);
}

$requestQuery .= " ORDER BY r.date DESC"; // Optional: Order the requests

$requestStmt = $conn->prepare($requestQuery);

if (!empty($types)) {
    $requestStmt->bind_param($types, ...$params);
}

$requestStmt->execute();
$requestResult = $requestStmt->get_result();
$requestStmt->close();

// Generate the HTML table rows
if ($requestResult && $requestResult->num_rows > 0) {
    while ($request = $requestResult->fetch_assoc()) {
        ?>
        <tr>
            <td><?php echo htmlspecialchars($request['emid']); ?></td>
            <td><?php echo htmlspecialchars($request['role']); ?></td>
            <td><?php echo date('Y-m-d', strtotime($request['date'])); ?></td>
            <td><?php echo htmlspecialchars($request['request']); ?></td>
            <td><?php echo htmlspecialchars($request['remarks']); ?></td>
            <td id="status-<?php echo $request['request_id']; ?>">
                <?php echo htmlspecialchars($request['status']); ?>
            </td>
            <?php if ($role === 'team_leader'): ?>
                <td>
                    <?php if ($request['status'] === 'Pending'): ?>
                        <button class="accept-btn" data-id="<?php echo $request['request_id']; ?>">Accept</button>
                        <button class="decline-btn" data-id="<?php echo $request['request_id']; ?>">Decline</button>
                    <?php else: ?>
                        <span>Action Done</span>
                    <?php endif; ?>
                </td>
            <?php endif; ?>
        </tr>
        <?php
    }
    $requestResult->free();
} else {
    ?>
    <tr>
        <td colspan="<?php echo ($role === 'team_leader') ? 7 : 6; ?>">No requests found.</td>
    </tr>
    <?php
}

$conn->close();
?>