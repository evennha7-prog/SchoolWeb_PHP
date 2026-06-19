<?php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_role = $_SESSION['role'] ?? 'teacher';
$user_name = $_SESSION['name'] ?? 'Staff';
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Management System</title>
    <link rel="stylesheet" href="style.css">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <aside id="sidebar">
            <div class="brand">
                <div class="brand-icon">S</div>
                <span>School System</span>
            </div>
            <ul class="nav-menu">
                <li class="nav-item <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                    <a href="index.php"><i class="fa-solid fa-gauge"></i> Dashboard</a>
                </li>
                <?php if ($user_role === 'super_admin'): ?>
                <li class="nav-item <?php echo $current_page == 'branches.php' ? 'active' : ''; ?>">
                    <a href="branches.php"><i class="fa-solid fa-code-branch"></i> Branches</a>
                </li>
                <li class="nav-item <?php echo $current_page == 'levels.php' ? 'active' : ''; ?>">
                    <a href="levels.php"><i class="fa-solid fa-graduation-cap"></i> Levels</a>
                </li>
                <li class="nav-item <?php echo $current_page == 'teachers.php' ? 'active' : ''; ?>">
                    <a href="teachers.php"><i class="fa-solid fa-chalkboard-user"></i> Teachers / Staff</a>
                </li>
                <?php endif; ?>
                <li class="nav-item <?php echo $current_page == 'students.php' ? 'active' : ''; ?>">
                    <a href="students.php"><i class="fa-solid fa-users"></i> Students</a>
                </li>
                <li class="nav-item <?php echo $current_page == 'subjects.php' ? 'active' : ''; ?>">
                    <a href="subjects.php"><i class="fa-solid fa-book"></i> Subjects</a>
                </li>
                <li class="nav-item <?php echo $current_page == 'attendance.php' ? 'active' : ''; ?>">
                    <a href="attendance.php"><i class="fa-solid fa-calendar-check"></i> Attendance</a>
                </li>
                <li class="nav-item <?php echo $current_page == 'marks.php' ? 'active' : ''; ?>">
                    <a href="marks.php"><i class="fa-solid fa-square-poll-vertical"></i> Exam Results</a>
                </li>
                <li class="nav-item <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                    <a href="reports.php"><i class="fa-solid fa-chart-line"></i> Reports</a>
                </li>
            </ul>
            <div class="sidebar-footer">
                &copy; 2026 School System
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="main-wrapper">
            <!-- Top Navbar -->
            <header class="top-navbar">
                <div class="page-title">
                    <?php
                    switch($current_page) {
                        case 'index.php': echo 'Dashboard'; break;
                        case 'branches.php': echo 'Branches Management'; break;
                        case 'levels.php': echo 'Academic Levels'; break;
                        case 'teachers.php': echo 'Teacher & Staff Management'; break;
                        case 'students.php': echo 'Student registry'; break;
                        case 'subjects.php': echo 'Subject Management'; break;
                        case 'attendance.php': echo 'Attendance registry'; break;
                        case 'marks.php': echo 'Exam Results & Marks'; break;
                        case 'reports.php': echo 'Reports Summary'; break;
                        default: echo 'School Admin';
                    }
                    ?>
                </div>
                <div class="user-profile">
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
                        <div class="user-role"><?php echo $user_role === 'super_admin' ? 'Super Admin' : 'Teacher / Staff'; ?></div>
                    </div>
                    <div class="avatar">
                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                    </div>
                    <a href="logout.php" class="btn-logout">
                        <i class="fa-solid fa-right-from-bracket"></i> Logout
                    </a>
                </div>
            </header>
            
            <main class="content-container">
