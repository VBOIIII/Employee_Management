<?php
session_start();
require 'db.php';

$emid = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emid = htmlspecialchars($_POST['emid'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($emid)) {
        $error = "EMID is required";
    } elseif (empty($password)) {
        $error = "Password is required";
    }

    if (empty($error)) {
        $stmt = $conn->prepare("SELECT u.id, u.emid, u.password, u.role, a.attribute_value as team
                                FROM employee u
                                LEFT JOIN user_attributes a ON u.id = a.user_id AND a.attribute_name = 'team'
                                WHERE u.emid = ?");
        $stmt->bind_param("s", $emid);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['emid'] = $user['emid'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['team'] = $user['team'];      
                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Invalid credentials.";
            }
        } else {
            $error = "Invalid credentials.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Login</title>
    <link rel="stylesheet" href="../style/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="company-logo">
                <i class="fas fa-building fa-3x"></i>
            </div>
            <h1>Employee Login</h1>

            <?php if (!empty($error)): ?>
                <p class="error-message" style="color: red; font-weight: bold;"><?php echo $error; ?></p>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="input-group">
                    <label for="emid"><i class="fas fa-user"></i> EMID</label>
                    <input type="text" id="emid" name="emid" value="<?php echo htmlspecialchars($emid); ?>" required>
                </div>
                <div class="input-group">
                    <label for="password"><i class="fas fa-lock"></i> Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="login-button">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>
        </div>
    </div>
</body>
</html>
