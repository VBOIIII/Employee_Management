<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'team_leader') {
    header('Location: code_employee.php'); // redirect back if not team_leader
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'];
    $request = $_POST['request'] ?? '';
    $remarks = $_POST['remarks'] ?? '';

    if (!empty($request)) {
        $stmt = $conn->prepare("INSERT INTO requests (user_id, date, request, remarks, status) VALUES (?, NOW(), ?, ?, 'Pending')");
        $stmt->bind_param("iss", $userId, $request, $remarks);
        if ($stmt->execute()) {
            header("Location: dashboard.php"); 
            exit;
        } else {
            $error = "Failed to add request.";
        }
        $stmt->close();
    } else {
        $error = "Request field cannot be empty.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Request</title>
    <link rel="stylesheet" href="../style/dashboard.css"> 
</head>
<body>
    <div class="form-container">
        <h2>Add New Request</h2>

        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <label for="request">Request:</label>
            <textarea name="request" id="request" required></textarea>

            <label for="remarks">Remarks:</label>
            <textarea name="remarks" id="remarks"></textarea>

            <button type="submit">Submit Request</button>
        </form>

        <a href="dashboard.php">Cancel</a>
    </div>
</body>
</html>
