<?php
session_start();
require 'db.php';

// Initialize variables
$emid = $password = $role = $team = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input
    $emid = htmlspecialchars($_POST['emid'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = htmlspecialchars($_POST['role'] ?? '');
    $team = htmlspecialchars($_POST['team'] ?? '');

    // Validation
    if (empty($emid)) {
        $errors['emid'] = "EMID is required";
    }
    if (empty($password)) {
        $errors['password'] = "Password is required";
    } elseif (strlen($password) < 8) {
        $errors['password'] = "Password must be at least 8 characters long";
    }
    if (empty($role)) {
        $errors['role'] = "Role is required";
    }

    // Only validate team if NOT operation_manager
    if ($role !== 'operation_manager' && empty($team)) {
        $errors['team'] = "Team is required";
    }

    // Check if EMID already exists
    $stmt = $conn->prepare("SELECT emid FROM employee WHERE emid = ?");
    $stmt->bind_param("s", $emid);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $errors['emid'] = "EMID already exists";
    }
    $stmt->close();

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Default team for operation manager
        if ($role === 'operation_manager') {
            $team = 'N/A'; // You can change "N/A" if you want
        }

        $conn->begin_transaction();

        // Insert employee
        $stmt = $conn->prepare("INSERT INTO employee (emid, password, role) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $emid, $hashed_password, $role);
        $result = $stmt->execute();
        $user_id = $conn->insert_id;

        // Insert team attribute
        $stmt = $conn->prepare("INSERT INTO user_attributes (user_id, attribute_name, attribute_value) VALUES (?, 'team', ?)");
        $stmt->bind_param("is", $user_id, $team);
        $result2 = $stmt->execute();

        if ($result && $result2) {
            $conn->commit();
            $_SESSION['message'] = "Registration successful!";
            header("Location: login.php");
            exit;
        } else {
            $conn->rollback();
            $errors['global'] = "Registration failed. Please try again.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Registration</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../style/registration.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        function checkRole() {
            const roleSelect = document.getElementById('role');
            const teamGroup = document.getElementById('team-group');
            const teamSelect = document.getElementById('team');

            if (roleSelect.value === 'operation_manager') {
                teamGroup.style.display = 'none';
                teamSelect.required = false;
                teamSelect.value = '';
            } else {
                teamGroup.style.display = 'block';
                teamSelect.required = true;
            }
        }

        window.onload = checkRole; // Automatically call on page load
    </script>
</head>
<body>
    <div class="registration-container">
        <div class="registration-box">
            <h1>Register</h1>
            <?php if (!empty($errors['global'])): ?>
                <p class="error-message"><?php echo $errors['global']; ?></p>
            <?php endif; ?>
            <form method="POST" action="">
                <div class="input-group">
                    <label for="emid">EMID</label>
                    <input type="text" id="emid" name="emid" value="<?php echo htmlspecialchars($emid); ?>" required>
                    <?php if (!empty($errors['emid'])): ?>
                        <p class="error-message"><?php echo $errors['emid']; ?></p>
                    <?php endif; ?>
                </div>

                <div class="input-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                    <?php if (!empty($errors['password'])): ?>
                        <p class="error-message"><?php echo $errors['password']; ?></p>
                    <?php endif; ?>
                </div>

                <div class="input-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" onchange="checkRole()" required>
                        <option value="">-- Select Role --</option>
                        <option value="agent" <?php if ($role === 'agent') echo 'selected'; ?>>Agent</option>
                        <option value="team_leader" <?php if ($role === 'team_leader') echo 'selected'; ?>>Team Leader</option>
                        <option value="operation_manager" <?php if ($role === 'operation_manager') echo 'selected'; ?>>Operation Manager</option>
                    </select>
                    <?php if (!empty($errors['role'])): ?>
                        <p class="error-message"><?php echo $errors['role']; ?></p>
                    <?php endif; ?>
                </div>

                <div class="input-group" id="team-group">
                    <label for="team">Team</label>
                    <select id="team" name="team">
                        <option value="">-- Select Team --</option>
                        <option value="Team 1" <?php if ($team === 'Team 1') echo 'selected'; ?>>Team 1</option>
                        <option value="Team 2" <?php if ($team === 'Team 2') echo 'selected'; ?>>Team 2</option>
                        <option value="Team 3" <?php if ($team === 'Team 3') echo 'selected'; ?>>Team 3</option>
                    </select>
                    <?php if (!empty($errors['team'])): ?>
                        <p class="error-message"><?php echo $errors['team']; ?></p>
                    <?php endif; ?>
                </div>

                <button type="submit" class="registration-button">Register</button>
            </form>
        </div>
    </div>
</body>
</html>
