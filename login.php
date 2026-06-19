<?php
require_once 'config.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $error_msg = "Please fill in all fields.";
    } else {
        // Sanitize and fetch user
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();
            // Verify password hash
            if (password_verify($password, $user['password'])) {
                // Set session data
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['branch_id'] = $user['branch_id'];

                header("Location: index.php");
                exit();
            } else {
                $error_msg = "Invalid password.";
            }
        } else {
            $error_msg = "User not found with this email.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - School Management System</title>
    <link rel="stylesheet" href="style.css">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="login-container">
    <div class="login-card">
        <div class="brand">
            <div class="brand-logo">
                <i class="fa-solid fa-graduation-cap" style="color: white;"></i>
            </div>
            <h2>School Management System</h2>
            <p>Welcome back! Please login to continue.</p>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger" style="margin-bottom: 20px;">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div><?php echo htmlspecialchars($error_msg); ?></div>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="email"><i class="fa-solid fa-envelope"></i> Email Address</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="admin@school.com" required autocomplete="email">
            </div>

            <div class="form-group">
                <label for="password"><i class="fa-solid fa-lock"></i> Password</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn-submit">
                Sign In
            </button>
        </form>
        
        <div style="margin-top: 25px; text-align: center; font-size: 0.8rem; color: #94a3b8;">
            <p>Demo Admin: <strong>admin@school.com</strong> / <strong>admin123</strong></p>
            <p style="margin-top: 5px;">Demo Teacher: <strong>dara@school.com</strong> / <strong>teacher123</strong></p>
        </div>
    </div>
</body>
</html>
