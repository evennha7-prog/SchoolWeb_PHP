<?php
require_once 'header.php';

// Prepare metrics queries
$total_branches = 0;
$total_levels = 0;
$total_teachers = 0;
$total_subjects = 0;
$total_students = 0;
$attendance_rate = 0;

if ($user_role === 'super_admin') {
    // Branches
    $res = $conn->query("SELECT COUNT(*) as count FROM branches");
    if ($res) $total_branches = $res->fetch_assoc()['count'];

    // Levels
    $res = $conn->query("SELECT COUNT(*) as count FROM levels");
    if ($res) $total_levels = $res->fetch_assoc()['count'];

    // Teachers
    $res = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'teacher'");
    if ($res) $total_teachers = $res->fetch_assoc()['count'];

    // Subjects
    $res = $conn->query("SELECT COUNT(*) as count FROM subjects");
    if ($res) $total_subjects = $res->fetch_assoc()['count'];

    // Students
    $res = $conn->query("SELECT COUNT(*) as count FROM students");
    if ($res) $total_students = $res->fetch_assoc()['count'];

    // Today's Attendance Rate
    $today = date('Y-m-d');
    $res_total_att = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE date = '$today'");
    $res_present_att = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE date = '$today' AND status IN ('Present', 'Late', 'Excused')");
    
    $total_att = $res_total_att ? $res_total_att->fetch_assoc()['count'] : 0;
    $present_att = $res_present_att ? $res_present_att->fetch_assoc()['count'] : 0;
    
    if ($total_att > 0) {
        $attendance_rate = round(($present_att / $total_att) * 100);
    } else {
        $attendance_rate = 0;
    }
} else {
    // Teacher specific metrics (Filtered by Branch)
    $branch_id = $_SESSION['branch_id'] ?? 0;

    // Students in teacher's branch
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM students WHERE branch_id = ?");
    $stmt->bind_param("i", $branch_id);
    $stmt->execute();
    $total_students = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();

    // Exams marked by this teacher
    $teacher_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM exams WHERE teacher_id = ?");
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $total_teachers = $stmt->get_result()->fetch_assoc()['count']; // Reusing teacher card for total exams
    $stmt->close();

    // Subjects (global count)
    $res = $conn->query("SELECT COUNT(*) as count FROM subjects");
    if ($res) $total_subjects = $res->fetch_assoc()['count'];

    // Today's Attendance in teacher's branch
    $today = date('Y-m-d');
    $stmt_tot = $conn->prepare("SELECT COUNT(*) as count FROM attendance a JOIN students s ON a.student_id = s.id WHERE s.branch_id = ? AND a.date = ?");
    $stmt_tot->bind_param("is", $branch_id, $today);
    $stmt_tot->execute();
    $total_att = $stmt_tot->get_result()->fetch_assoc()['count'];
    $stmt_tot->close();

    $stmt_pres = $conn->prepare("SELECT COUNT(*) as count FROM attendance a JOIN students s ON a.student_id = s.id WHERE s.branch_id = ? AND a.date = ? AND a.status IN ('Present', 'Late', 'Excused')");
    $stmt_pres->bind_param("is", $branch_id, $today);
    $stmt_pres->execute();
    $present_att = $stmt_pres->get_result()->fetch_assoc()['count'];
    $stmt_pres->close();

    if ($total_att > 0) {
        $attendance_rate = round(($present_att / $total_att) * 100);
    } else {
        $attendance_rate = 0;
    }
}

// Fetch recent students added
if ($user_role === 'super_admin') {
    $recent_students_query = "SELECT s.*, b.name as branch_name, l.name as level_name 
                              FROM students s 
                              LEFT JOIN branches b ON s.branch_id = b.id 
                              LEFT JOIN levels l ON s.level_id = l.id 
                              ORDER BY s.id DESC LIMIT 5";
    $recent_students = $conn->query($recent_students_query);
} else {
    $branch_id = $_SESSION['branch_id'] ?? 0;
    $stmt = $conn->prepare("SELECT s.*, b.name as branch_name, l.name as level_name 
                            FROM students s 
                            LEFT JOIN branches b ON s.branch_id = b.id 
                            LEFT JOIN levels l ON s.level_id = l.id 
                            WHERE s.branch_id = ?
                            ORDER BY s.id DESC LIMIT 5");
    $stmt->bind_param("i", $branch_id);
    $stmt->execute();
    $recent_students = $stmt->get_result();
    $stmt->close();
}

// Fetch recent exam scores
if ($user_role === 'super_admin') {
    $recent_exams_query = "SELECT e.*, s.name as student_name, s.student_code, sub.name as subject_name 
                           FROM exams e 
                           JOIN students s ON e.student_id = s.id 
                           JOIN subjects sub ON e.subject_id = sub.id
                           ORDER BY e.id DESC LIMIT 5";
    $recent_exams = $conn->query($recent_exams_query);
} else {
    $teacher_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT e.*, s.name as student_name, s.student_code, sub.name as subject_name 
                            FROM exams e 
                            JOIN students s ON e.student_id = s.id 
                            JOIN subjects sub ON e.subject_id = sub.id
                            WHERE e.teacher_id = ?
                            ORDER BY e.id DESC LIMIT 5");
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $recent_exams = $stmt->get_result();
    $stmt->close();
}
?>

<!-- Metrics Overview -->
<div class="metrics-grid">
    <?php if ($user_role === 'super_admin'): ?>
        <div class="metric-card">
            <div class="metric-info">
                <h3>Total Branches</h3>
                <div class="value"><?php echo $total_branches; ?></div>
            </div>
            <div class="metric-icon indigo">
                <i class="fa-solid fa-code-branch"></i>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-info">
                <h3>Total Levels</h3>
                <div class="value"><?php echo $total_levels; ?></div>
            </div>
            <div class="metric-icon amber">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-info">
                <h3>Total Teachers</h3>
                <div class="value"><?php echo $total_teachers; ?></div>
            </div>
            <div class="metric-icon cyan">
                <i class="fa-solid fa-chalkboard-user"></i>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-info">
                <h3>Total Subjects</h3>
                <div class="value"><?php echo $total_subjects; ?></div>
            </div>
            <div class="metric-icon rose">
                <i class="fa-solid fa-book"></i>
            </div>
        </div>
    <?php else: ?>
        <div class="metric-card">
            <div class="metric-info">
                <h3>My Branch</h3>
                <div class="value" style="font-size: 1.15rem; font-weight:600; color:var(--primary); margin-top:8px;">
                    <?php 
                    $br_id = $_SESSION['branch_id'] ?? 0;
                    $b_q = $conn->query("SELECT name FROM branches WHERE id = $br_id");
                    echo $b_q ? htmlspecialchars($b_q->fetch_assoc()['name']) : 'N/A';
                    ?>
                </div>
            </div>
            <div class="metric-icon indigo">
                <i class="fa-solid fa-school"></i>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-info">
                <h3>Exams Graded</h3>
                <div class="value"><?php echo $total_teachers; ?></div>
            </div>
            <div class="metric-icon cyan">
                <i class="fa-solid fa-square-poll-vertical"></i>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-info">
                <h3>Total Subjects</h3>
                <div class="value"><?php echo $total_subjects; ?></div>
            </div>
            <div class="metric-icon rose">
                <i class="fa-solid fa-book"></i>
            </div>
        </div>
    <?php endif; ?>

    <div class="metric-card">
        <div class="metric-info">
            <h3>Total Students</h3>
            <div class="value"><?php echo $total_students; ?></div>
        </div>
        <div class="metric-icon emerald">
            <i class="fa-solid fa-users"></i>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-info">
            <h3>Today's Attendance</h3>
            <div class="value"><?php echo $attendance_rate; ?>%</div>
        </div>
        <div class="metric-icon rose">
            <i class="fa-solid fa-calendar-check"></i>
        </div>
    </div>
</div>

<!-- Dashboard Grid (Recent Activities) -->
<div class="dashboard-grid">
    <!-- Recent Students Table -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-user-plus" style="color:var(--primary); margin-right:8px;"></i> Recently Registered Students</h2>
            <a href="students.php" class="btn btn-secondary btn-sm">View All</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Gender</th>
                            <th>Branch</th>
                            <th>Level</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_students && $recent_students->num_rows > 0): ?>
                            <?php while($row = $recent_students->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($row['student_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $row['gender'] === 'Male' ? 'badge-info' : 'badge-warning'; ?>">
                                            <?php echo htmlspecialchars($row['gender']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['branch_name'] ?? 'N/A'); ?></td>
                                    <td><span class="badge badge-success"><?php echo htmlspecialchars($row['level_name'] ?? 'N/A'); ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 20px; color: #94a3b8;">No students registered yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Exams Graded -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-pen-to-square" style="color:var(--secondary); margin-right:8px;"></i> Recent Exam Grades</h2>
            <a href="marks.php" class="btn btn-secondary btn-sm">Manage</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Subject</th>
                            <th>Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_exams && $recent_exams->num_rows > 0): ?>
                            <?php while($row = $recent_exams->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:600; color:var(--dark);"><?php echo htmlspecialchars($row['student_name']); ?></div>
                                        <div style="font-size:0.75rem; color:#64748b;"><?php echo htmlspecialchars($row['student_code']); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $row['score'] >= 80 ? 'badge-success' : ($row['score'] >= 50 ? 'badge-warning' : 'badge-danger'); ?>">
                                            <?php echo number_format($row['score'], 1); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" style="text-align: center; padding: 20px; color: #94a3b8;">No exams graded yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'footer.php';
?>
