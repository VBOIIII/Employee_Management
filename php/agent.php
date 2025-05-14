<?php
session_start();
require '../php/db.php'; 

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php"); 
    exit;
}

// Fetch employee data
$userId = $_SESSION['user_id'];
$query = "SELECT u.id, u.emid, u.name, a.attribute_value AS team
          FROM users u
          LEFT JOIN user_attributes a ON u.id = a.user_id AND a.attribute_name = 'team'
          WHERE u.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Fetch schedule data (replace with actual schedule data)
$scheduleQuery = "SELECT * FROM schedules WHERE user_id = ?";
$scheduleStmt = $conn->prepare($scheduleQuery);
$scheduleStmt->bind_param("i", $userId);
$scheduleStmt->execute();
$scheduleResult = $scheduleStmt->get_result();

// Fetch requests (replace with actual requests data)
$requestQuery = "SELECT * FROM requests WHERE user_id = ?";
$requestStmt = $conn->prepare($requestQuery);
$requestStmt->bind_param("i", $userId);
$requestStmt->execute();
$requestResult = $requestStmt->get_result();

// Fetch ongoing tasks (replace with actual ongoing tasks data)
$taskQuery = "SELECT * FROM tasks WHERE user_id = ? AND status = 'ongoing'";
$taskStmt = $conn->prepare($taskQuery);
$taskStmt->bind_param("i", $userId);
$taskStmt->execute();
$taskResult = $taskStmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Agent Dashboard</title>
    <link rel="stylesheet" href="../style/agent.css">
</head>
<body>
    <div class="header">
        <h1>Employee Management System</h1>
    </div>
    <div class="dashboard-container">
        <div class="left-panel">
            <div class="employee-box">
                <div class="employee-id-name">Employee ID & Name: <?php echo $user['emid'] . " - " . $user['name']; ?></div>
                <div class="schedule-list">
                    <?php while ($schedule = $scheduleResult->fetch_assoc()) { ?>
                        <div class="schedule-item">
                            <span class="label"><?php echo $schedule['label']; ?></span>
                            <span class="time"><?php echo $schedule['time']; ?></span>
                        </div>
                    <?php } ?>
                </div>
            </div>
            <div class="request-box">
                <h3>Requests</h3>
                <div class="request-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Request</th>
                                <th>Remarks</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($request = $requestResult->fetch_assoc()) { ?>
                                <tr>
                                    <td><?php echo $request['date']; ?></td>
                                    <td><?php echo $request['request']; ?></td>
                                    <td><?php echo $request['remarks']; ?></td>
                                    <td><?php echo $request['status']; ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="right-panel">
            <div class="calendar-box">
                <div class="calendar-header">
                    <h2 id="datetime"></h2>
                </div>
                <div class="calendar-grid" id="calendarGrid">
                </div>
            </div>
            <div class="ongoing-box">
                <h3>Ongoing Tasks</h3>
                <div class="ongoing-task-list">
                    <?php while ($task = $taskResult->fetch_assoc()) { ?>
                        <div class="current-task">
                            <div class="label"><?php echo $task['task_name']; ?></div>
                            <div class="time"><?php echo $task['start_time'] . ' - ' . $task['end_time']; ?></div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateDateTime() {
            const now = new Date();
            const monthNames = ["January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"
            ];
            const month = monthNames[now.getMonth()];
            const year = now.getFullYear();
            let hours = now.getHours();
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12;
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            const timezoneShort = Intl.DateTimeFormat(undefined, { timeZoneName: 'short' }).format(now).split(' ')[2] || '';

            document.getElementById('datetime').textContent = `${month} ${year} ${String(hours).padStart(2, '0')}:${minutes}:${seconds} ${ampm} ${timezoneShort}`;
        }
        setInterval(updateDateTime, 1000);
        updateDateTime();

        function generateCalendar() {
            const calendarGrid = document.getElementById("calendarGrid");
            calendarGrid.innerHTML = "";

            const now = new Date();
            const year = now.getFullYear();
            const month = now.getMonth();
            const today = now.getDate();

            const date = new Date(year, month, 1);
            const firstDayIndex = date.getDay();
            const lastDay = new Date(year, month + 1, 0).getDate();

            const weekdays = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
            weekdays.forEach(day => {
                const dayEl = document.createElement("div");
                dayEl.className = "day-name";
                dayEl.textContent = day;
                calendarGrid.appendChild(dayEl);
            });

            for (let i = 0; i < firstDayIndex; i++) {
                const empty = document.createElement("div");
                empty.className = "day empty";
                calendarGrid.appendChild(empty);
            }

            for (let day = 1; day <= lastDay; day++) {
                const dayEl = document.createElement("div");
                dayEl.className = "day";
                if (day === today) {
                    dayEl.classList.add("today");
                }
                dayEl.textContent = day;
                calendarGrid.appendChild(dayEl);
            }
        }

        generateCalendar();
    </script>
</body>
</html>
