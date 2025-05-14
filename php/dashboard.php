<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Fetch user data
$query = "SELECT u.id AS user_id, u.emid, u.role, a.attribute_value AS team
            FROM employee u
            LEFT JOIN user_attributes a ON u.id = a.user_id AND a.attribute_name = 'team'
            WHERE u.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
$userTeam = $user['team'];

// Fetch team members (for dropdowns)
$teamMembers = [];
$teams = [];

if ($role === 'team_leader' || $role === 'operation_manager') {
    $teamMemberQuery = "SELECT e.id AS employee_id, e.emid, e.role, ua.attribute_value AS team FROM employee e
                            LEFT JOIN user_attributes ua ON e.id = ua.user_id AND ua.attribute_name = 'team'";

    if ($role === 'team_leader') {
        $teamMemberQuery .= " WHERE ua.attribute_value = ? OR e.role = 'operation_manager'";
        $teamMemberStmt = $conn->prepare($teamMemberQuery);
        $teamMemberStmt->bind_param("s", $userTeam);
    } elseif ($role === 'operation_manager') {
        $teamMemberQuery .= " ORDER BY ua.attribute_value, e.emid";
        $teamMemberStmt = $conn->prepare($teamMemberQuery);
    }

    if (isset($teamMemberStmt)) {
        $teamMemberStmt->execute();
        $teamMemberResult = $teamMemberStmt->get_result();
        if ($teamMemberResult) {
            while ($row = $teamMemberResult->fetch_assoc()) {
                $teamMembers[] = $row;
            }
            $teamMemberResult->free();
        }
        $teamMemberStmt->close();
    }

    if ($role === 'operation_manager') {
        $teamsQuery = "SELECT DISTINCT attribute_value FROM user_attributes WHERE attribute_name = 'team'";
        $teamsResult = $conn->query($teamsQuery);
        if ($teamsResult) {
            while ($row = $teamsResult->fetch_assoc()) {
                if (!empty($row['attribute_value']) && $row['attribute_value'] !== 'N/A') {
                    $teams[] = $row['attribute_value'];
                }
            }
            $teamsResult->free();
        }
    }
}

// Initial request query (will be updated by JavaScript AJAX)
$requestQuery = "SELECT r.id AS request_id, r.date, r.request, r.remarks, r.status, e.emid, e.role FROM requests r
                        JOIN employee e ON r.user_id = e.id
                        LEFT JOIN user_attributes ua ON e.id = ua.user_id AND ua.attribute_name = 'team'

                        WHERE 1=1";

$requestStmt = $conn->prepare($requestQuery);
$requestStmt->execute();
$requestResult = $requestStmt->get_result();
$requestStmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <link rel="stylesheet" href="../style/dashboard.css">
    <link rel="stylesheet" href="../style/schedule.css">
    <link rel="stylesheet" href="../style/request.css">
    <link rel="stylesheet" href="../style/ongoing.css">


</head>
<body>
    <div class="header">
        <h1>Employee Management System</h1>
    </div>
    <div class="dashboard-container">
        <div class="left-panel">
            <div class="employee-box">
                <div class="employee-id-role-team">
                    <div class="employee-detail-box">
                        <p class="employee-detail">Employee ID: <?php echo htmlspecialchars($user['emid']); ?></p>
                    </div>
                    <div class="employee-detail-box">
                        <p class="employee-detail">Role: <?php echo htmlspecialchars($user['role']); ?></p>
                    </div>
                    <div class="employee-detail-box">
                        <p class="employee-detail">Team: <?php echo htmlspecialchars($user['team'] ?? 'Not Assigned'); ?></p>
                    </div>
                </div>

                <h3>Schedule</h3>
                <div class="schedule-list" id="scheduleList">
                    <?php if ($role !== 'operation_manager'): ?>
                        <?php
                        $initialScheduleQuery = "SELECT s.id AS schedule_id, s.label, s.start_time, s.end_time, s.user_id, e.emid
                                                FROM schedules s
                                                    JOIN employee e ON s.user_id = e.id
                                                    LEFT JOIN user_attributes ua ON e.id = ua.user_id AND ua.attribute_name = 'team'";
                        $initialScheduleParams = [];
                        $initialScheduleTypes = "";

                        if ($role === 'agent') {
                            $initialScheduleQuery .= " WHERE s.user_id = ?";
                            $initialScheduleParams[] = $userId;
                            $initialScheduleTypes = "i";
                        } elseif ($role === 'team_leader') {
                            $initialScheduleQuery .= " WHERE ua.attribute_value = ?";
                            $initialScheduleParams[] = $userTeam;
                            $initialScheduleTypes = "s";
                        }
                        $initialScheduleQuery .= " ORDER BY s.start_time";
                        $initialScheduleStmt = $conn->prepare($initialScheduleQuery);
                        if (!empty($initialScheduleTypes)) {
                            $initialScheduleStmt->bind_param($initialScheduleTypes, ...$initialScheduleParams);
                        }
                        $initialScheduleStmt->execute();
                        $initialScheduleResult = $initialScheduleStmt->get_result();
                        ?>
                        <?php if ($initialScheduleResult && $initialScheduleResult->num_rows > 0): ?>
                            <?php while ($schedule = $initialScheduleResult->fetch_assoc()):
                                $startTime = date('h:i A', strtotime($schedule['start_time']));
                                $endTime = date('h:i A', strtotime($schedule['end_time']));
                                $scheduleTimeRange = $startTime . ' - ' . $endTime;
                                ?>
                                <div class="schedule-item <?php echo ($schedule['label'] === 'Break') ? 'break' : ''; ?>" data-user-id="<?php echo $schedule['user_id']; ?>" data-schedule-id="<?php echo $schedule['schedule_id']; ?>">
                                    <span class="label"><?php echo htmlspecialchars($schedule['label']); ?></span>
                                    <span class="time"><?php echo $scheduleTimeRange; ?></span>
                                    <?php if ($role === 'team_leader'): ?>
                                        <button class="delete-schedule-btn" data-schedule-id="<?php echo $schedule['schedule_id']; ?>">Delete</button>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                            <?php $initialScheduleResult->free(); ?>
                            <?php $initialScheduleStmt->close(); ?>
                        <?php else: ?>
                            <p>No schedules available.</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php if ($role === 'team_leader' || $role === 'operation_manager'): ?>
                    <div class="team-schedule-management">
                        <h3>Manage Schedules</h3>
                        <?php if ($role === 'operation_manager'): ?>
                            <label for="team_select">Select Team:</label>
                            <select id="team_select" onchange="updateEmployeeDropdown()">
                                <option value="">-- Select a Team --</option>
                                <?php foreach ($teams as $team): ?>
                                    <option value="<?php echo htmlspecialchars($team); ?>"><?php echo htmlspecialchars($team); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>

                        <label for="employee_id">Select Employee:</label>
                        <select id="employee_id" name="employee_id" onchange="updateSchedule()">
                            <option value="">-- Select an Employee --</option>
                            <?php if (!empty($teamMembers)): ?>
                                <?php foreach ($teamMembers as $member): ?>
                                    <option value="<?php echo htmlspecialchars($member['employee_id']); ?>" <?php echo ($role === 'operation_manager' && isset($member['team'])) ? 'data-team="' . htmlspecialchars($member['team']) . '"' : ''; ?>>
                                        <?php echo htmlspecialchars($member['emid']) . " (" . htmlspecialchars($member['role']) . ")"; ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <?php if ($role === 'team_leader'): ?>
                            <button onclick="showModal()">Add Schedule</button>
                        <?php endif; ?>

                    </div>

                    <div id="scheduleModal" style="display:none;">
                        <div class="modal-content">
                            <span id="closeModalBtn" class="close-btn">&times;</span>
                            <h3 class="modal-title">Add Schedule</h3>
                            <form method="POST" action="" id="add_schedule_form" class="add-schedule-form">
                                <input type="hidden" name="emid" id="selected_emid" value="">
                                <input type="hidden" name="schedule_id" id="schedule_id_input" value="">
                                <label for="schedule_label">Schedule Label:</label>
                                <select name="schedule_label" id="schedule_label" required>
                                    <option value="">-- Select --</option>
                                    <option value="Meeting">Meeting</option>
                                    <option value="Training">Training</option>
                                    <option value="Break">Break</option>
                                </select>
                                <label for="start_time">Start Time:</label>
                                <input type="time" name="start_time" id="start_time" required>
                                <label for="end_time">End Time:</label>
                                <input type="time" name="end_time" id="end_time" required>
                                <button type="submit" class="submit-btn">Add Schedule</button>
                            </form>
                        </div>
                    </div>

                    <script>
                        let currentEmployeeId = null;
                        function updateSchedule() {
                            const employeeId = document.getElementById('employee_id').value;
                            if (employeeId) {
                                const selectedEmployeeInput = document.getElementById('selected_emid');
                                selectedEmployeeInput.value = employeeId;
                                currentEmployeeId = employeeId;
                                loadSchedules(employeeId);
                                loadOngoingTasks(employeeId);
                            } else {
                                $('#scheduleList').html('<p>No schedules available.</p>');
                                currentEmployeeId = null;
                                loadOngoingTasks();
                            }
                        }

                        function loadSchedules(employeeId = null) {
                            $.ajax({
                                url: 'get_schedules.php',
                                type: 'GET',
                                data: { employee_id: employeeId },
                                success: function(data) {
                                    $('#scheduleList').html(data);
                                    bindDeleteButtons();
                                },
                                error: function(xhr, status, error) {
                                    console.error("Error fetching schedules:", error);
                                }
                            });
                        }

                        function bindDeleteButtons() {
                            $('.delete-schedule-btn').off('click').on('click', function() {
                                const scheduleId = $(this).data('schedule-id');
                                if (confirm("Are you sure you want to delete this schedule?")) {
                                    $.ajax({
                                        url: 'remove_schedule.php',
                                        type: 'POST',
                                        data: { schedule_id: scheduleId },
                                        success: function(response) {
                                            const data = JSON.parse(response);
                                            const messageDiv = $("#message");
                                            messageDiv.text(data.message);
                                            if (data.success) {
                                                messageDiv.css({ backgroundColor: "#d4edda", color: "#155724" });
                                                loadSchedules(currentEmployeeId);
                                                loadOngoingTasks(currentEmployeeId);
                                            } else {
                                                messageDiv.css({ backgroundColor: "#f8d7da", color: "#721c24" });
                                            }
                                            setTimeout(() => messageDiv.text(''), 2000);
                                        },
                                        error: function(xhr, status, error) {
                                            console.error("Error deleting schedule:", error);
                                        }
                                    });
                                }
                            });
                        }

                        function showModal() {
                            const employeeId = document.getElementById('employee_id').value;
                            if (employeeId) {
                                $("#selected_emid").val(employeeId);
                                $("#schedule_id_input").val("");
                                $("#schedule_label").val("");
                                $("#start_time").val("");
                                $("#end_time").val("");
                                $(".modal-title").text("Add Schedule");
                                $("#add_schedule_form button[type='submit']").text("Add Schedule");
                                $("#scheduleModal").show();
                            } else {
                                alert("Please select an employee first.");
                            }
                        }

                        function updateEmployeeDropdown() {
                            const selectedTeam = $('#team_select').val();
                            const employeeDropdown = $('#employee_id');
                            employeeDropdown.find('option').hide();
                            employeeDropdown.find('option[value=""]').show();

                            if (selectedTeam) {
                                employeeDropdown.find('option[data-team="' + selectedTeam + '"]').show();
                            } else {
                                employeeDropdown.find('option').show();
                            }

                            employeeDropdown.val("");
                            currentEmployeeId = null;
                            $('#scheduleList').html('<p>No schedules available.</p>');
                            loadOngoingTasks();
                        }

                        function loadOngoingTasks(employeeId = null, team = null) {
                            let url = 'get_ongoing.php';
                            let data = {};

                            <?php if ($role === 'agent'): ?>
                                data = { employee_id: <?php echo json_encode($userId); ?> };
                            <?php elseif ($role === 'team_leader' || $role === 'operation_manager'): ?>
                                const selectedEmpId = $('#employee_id').val();
                                if (selectedEmpId) {
                                    data = { employee_id: selectedEmpId };
                                } else if ($role === 'team_leader' && currentEmployeeId) {
                                    data = { employee_id: currentEmployeeId };
                                } else if ($role === 'operation_manager' && team) {
                                    data = { team: team };
                                }
                            <?php endif; ?>

                            $.ajax({
                                url: url,
                                type: 'GET',
                                data: data,
                                success: function(data) {
                                    $('#ongoingTaskList').html(data);
                                },
                                error: function(xhr, status, error) {
                                    console.error("Error fetching ongoing tasks:", error);
                                }
                            });
                        }

                        $(document).ready(function() {
                            $("#closeModalBtn").click(function() {
                                $("#scheduleModal").hide();
                            });

                            $("#add_schedule_form").submit(function(e) {
                                e.preventDefault();
                                const formData = new FormData(this);
                                const scheduleId = $("#schedule_id_input").val();
                                let url = 'add_schedule.php';
                                if (scheduleId) {
                                    url = 'edit_schedule.php';
                                }
                                fetch(url, {
                                    method: 'POST',
                                    body: formData
                                })
                                .then(response => response.json())
                                .then(data => {
                                    console.log("Add Schedule Response Data:", data);
                                    const messageDiv = $("#message");
                                    messageDiv.text(data.message);
                                    if (data.success) {
                                        messageDiv.css({ backgroundColor: "#d4edda", color: "#155724" });
                                        loadSchedules(currentEmployeeId);
                                        loadOngoingTasks(currentEmployeeId);
                                        $("#scheduleModal").hide();
                                        $("#add_schedule_form")[0].reset();
                                        setTimeout(() => {
                                            messageDiv.text('');
                                            console.log("Message cleared.");
                                        }, 3000);
                                    } else {
                                        messageDiv.css({ backgroundColor: "#f8d7da", color: "#721c24" });
                                    }
                                })
                                .catch(error => {
                                    console.error('Error:', error);
                                });
                            });

                            <?php if ($role === "team_leader" || $role === "operation_manager"): ?>
                                <?php $currentEmployeeId = $user['user_id']; ?>
                                currentEmployeeId = "<?php echo $currentEmployeeId; ?>";
                                $("#employee_id").val(currentEmployeeId).trigger('change');
                            <?php else: ?>
                                <?php $currentEmployeeId = $userId; ?>
                                currentEmployeeId = "<?php echo $currentEmployeeId; ?>";
                                $("#employee_id").val(currentEmployeeId).trigger('change');
                            <?php endif; ?>

                            $("#employee_id").on("change", function() {
                                const selectedEmpId = $(this).val();
                                if (selectedEmpId) {
                                    currentEmployeeId = selectedEmpId;
                                    loadSchedules(currentEmployeeId);
                                    loadOngoingTasks(currentEmployeeId);
                                } else {
                                    loadOngoingTasks();
                                }
                            });
                        });
                    </script>

                    <div id="message"></div>
                <?php endif; ?>
            </div>

            <div class="request-box">
                <?php if ($role === 'team_leader'): ?>
                    <button id="addRequestBtn" class="add-request-button">Add Request</button>
                <?php endif; ?>

                <h3>Requests</h3>

                <?php if ($role === 'team_leader' || $role === 'operation_manager'): ?>
                <label for="employee_id_request_filter">Select Employee:</label>
                <select id="employee_id_request_filter" name="employee_id_request_filter">
                    <option value="">-- Show All --</option>
                    <?php if (!empty($teamMembers)): ?>
                        <?php foreach ($teamMembers as $member): ?>
                            <option value="<?php echo htmlspecialchars($member['employee_id']); ?>">
                                <?php echo htmlspecialchars($member['emid']) . " (" . htmlspecialchars($member['role']) . ")"; ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <?php endif; ?>

                <div class="request-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee ID</th>
                                <th>Role</th>
                                <th>Date</th>
                                <th>Request</th>
                                <th>Remarks</th>
                                <th>Status</th>
                                <?php if ($role === 'team_leader'): ?>
                                    <th>Action</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="requestTableBody">
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($role === 'team_leader'): ?>
                <div id="requestModal" class="modal" style="display:none;">
                    <div class="modal-content">
                        <span id="closeRequestModal" class="close-btn">&times;</span>
                        <h3>Add New Request</h3>
                        <form id="addRequestForm">
                            <label for="employee_id_request">Employee:</label>
                            <select id="employee_id_request" name="employee_id" required>
                                <option value="">-- Select an Employee --</option>
                                <?php if (!empty($teamMembers)): ?>
                                    <?php foreach ($teamMembers as $member): ?>
                                        <option value="<?php echo htmlspecialchars($member['employee_id']); ?>">
                                            <?php echo htmlspecialchars($member['emid']) . " (" . htmlspecialchars($member['role']) . ")"; ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>

                            <label for="request">Request:</label>
                            <textarea name="request" id="request" required></textarea>

                            <label for="remarks">Remarks:</label>
                            <textarea name="remarks" id="remarks"></textarea>

                            <button type="submit">Submit Request</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <script>
                document.getElementById('addRequestBtn')?.addEventListener('click', function() {
                    document.getElementById('requestModal').style.display = 'block';
                });
                document.getElementById('closeRequestModal')?.addEventListener('click', function() {
                    document.getElementById('requestModal').style.display = 'none';
                });
                document.getElementById('addRequestForm')?.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);

                    fetch('add_request_popup.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        alert(data.message);
                        if (data.success) {
                            document.getElementById('requestModal').style.display = 'none';
                            document.getElementById('addRequestForm').reset();
                            location.reload();
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
                });
                document.addEventListener('DOMContentLoaded', function() {
                    // Function to update request status (for accept/decline buttons)
                    function updateRequestStatus(id, status) {
                        fetch('update_request_status.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `id=${id}&status=${status}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // If successful, update the status directly in the table (without a full reload)
                                const statusCell = document.getElementById(`status-${id}`);
                                if (statusCell) {
                                    statusCell.innerText = status;
                                    // Update the class for styling if needed
                                    statusCell.className = 'status-' + status.toLowerCase();
                                }
                                // Find the buttons within the same row and remove them
                                const row = $(`#status-${id}`).closest('tr');
                                if (row.length) {
                                    row.find('.accept-btn, .decline-btn').remove();
                                    // Add "Action Done" text in the same cell where buttons were
                                    const actionCell = row.find('td:last');
                                    if (actionCell.length) {
                                        actionCell.text('Action Done');
                                    }
                                }

                            } else {
                                alert(data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error updating request status:', error);
                        });
                    }

                    // Event listeners for accept/decline buttons (delegated for dynamically loaded content)
                    $(document).on('click', '.accept-btn', function() {
                        const requestId = $(this).data('id');
                        updateRequestStatus(requestId, 'Accepted');
                    });
                    $(document).on('click', '.decline-btn', function() {
                        const requestId = $(this).data('id');
                        updateRequestStatus(requestId, 'Declined');
                    });
                    // JavaScript for request filtering
                    $('#employee_id_request_filter').on('change', function() {
                        const selectedEmployeeId = $(this).val();
                        console.log("Selected Employee ID for Requests:", selectedEmployeeId); // For debugging
                        loadRequests(selectedEmployeeId);
                    });
                    // Function to load requests via AJAX
                    function loadRequests(employeeId = null) {
                        console.log("Loading requests for employeeId:", employeeId); // For debugging
                        $.ajax({
                            url: 'get_requests.php', // Ensure this path is correct
                            type: 'GET',
                            data: { employee_id: employeeId },
                            success: function(data) {
                                $('#requestTableBody').html(data); // Update the table body
                            },
                            error: function(xhr, status, error) {
                                console.error("Error fetching requests:", error);
                            }
                        });
                    }

                    // Initial load of requests when the page loads
                    loadRequests();
                    // --- ONGOING TASK JAVASCRIPT ---
                    // Function to load all schedules (ongoing and not)
                    function loadOngoingTasks(employeeId = null, team = null) {
    let url = 'get_ongoing.php';
    let data = {};

    const role = <?php echo json_encode($role); ?>;
    const userId = <?php echo json_encode($userId); ?>;
    const userTeam = <?php echo json_encode($userTeam); ?>;
    const selectedEmpId = $('#employee_id').val();
    const selectedTeam = $('#team_select').val();

    if (role === 'agent') {
        data = { employee_id: userId };
    } else if (role === 'team_leader') {
        if (selectedEmpId) {
            data = { employee_id: selectedEmpId };
        }
        // For team leaders, if no employee is selected in the dropdown, no employee_id is sent,
        // which means the get_ongoing.php for team leaders would not return any specific employee's ongoing tasks by design.
        // The PHP logic in get_ongoing.php for team_leader already handles the employeeId if provided.
    } else if (role === 'operation_manager') {
        if (selectedEmpId) {
            data = { employee_id: selectedEmpId };
        } else if (selectedTeam) {
            data = { team: selectedTeam };
        }
    }

    $.ajax({
        url: url,
        type: 'GET',
        data: data,
        success: function(data) {
            $('#ongoingTaskList').html(data);
        },
        error: function(xhr, status, error) {
            console.error("Error fetching ongoing tasks:", error);
        }
    });
}

$(document).ready(function() {
    // Initial load of ongoing tasks based on the initial state of dropdowns and the user's role
    loadOngoingTasks();

    // Event listener for the 'Manage Schedules' employee dropdown
    $('#employee_id').on("change", function() {
        const selectedEmpId = $(this).val();
        loadOngoingTasks(selectedEmpId);
    });

    // Event listener for the 'Manage Schedules' team dropdown (for Operation Manager)
    $('#team_select').on('change', function() {
        loadOngoingTasks();
    });
});
                });
                
            </script>


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
                    <div class="ongoing-task-list" id="ongoingTaskList">
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
                const timezoneShort = Intl.DateTimeFormat(undefined, {
                    timeZoneName: 'short'
                }).format(now).split(' ')[2] || '';

                document.getElementById('datetime').textContent = `${month} ${now.getDate()}, ${year} ${String(hours).padStart(2, '0')}:${minutes}:${seconds} ${ampm} ${timezoneShort}`;
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
                const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
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
            let currentSlide = 0;
            const slideIntervalTime = 3000;
            let slides = document.querySelectorAll('.ongoing-item');
            function showSlide(index) {
                const taskList = document.querySelector('.ongoing-task-list');
                const width = document.querySelector('#ongoingTaskList').offsetWidth;
                taskList.style.transform = `translateX(-${index * width}px)`;
            }

            function nextSlide() {
                slides = document.querySelectorAll('.ongoing-item');
                currentSlide++;
                if (currentSlide >= slides.length) currentSlide = 0;
            }

        </script>
    </body>
    </html>