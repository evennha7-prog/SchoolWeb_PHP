<?php
require_once 'header.php';

$selected_branch = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : 0;

// Enforce teacher branch restriction
if ($user_role !== 'super_admin') {
    $selected_branch = intval($_SESSION['branch_id']);
}

// Build SQL where clauses
$where_student = "";
$where_exam = "";
$where_attendance = "";

if ($selected_branch > 0) {
    $where_student = "WHERE s.branch_id = $selected_branch";
    $where_exam = "WHERE s.branch_id = $selected_branch";
    $where_attendance = "WHERE s.branch_id = $selected_branch";
}

// Fetch branches for dropdown filter
if ($user_role === 'super_admin') {
    $branches = $conn->query("SELECT id, name FROM branches ORDER BY name ASC");
} else {
    $teacher_branch_id = $_SESSION['branch_id'] ?? 0;
    $branches = $conn->query("SELECT id, name FROM branches WHERE id = $teacher_branch_id");
}

// 1. Fetch Student Count per Academic Level
$level_dist = [];
$level_q = $conn->query("SELECT l.name as level_name, COUNT(*) as student_count 
                         FROM students s
                         JOIN levels l ON s.level_id = l.id
                         $where_student
                         GROUP BY l.id, l.name");
if ($level_q) {
    while ($row = $level_q->fetch_assoc()) {
        $level_dist[] = $row;
    }
}

// 2. Fetch Student Count per Branch (Only relevant/shown for Super Admin)
$branch_dist = [];
if ($user_role === 'super_admin') {
    $branch_q = $conn->query("SELECT b.name as branch_name, COUNT(*) as student_count 
                             FROM students s
                             JOIN branches b ON s.branch_id = b.id
                             GROUP BY b.id, b.name");
    if ($branch_q) {
        while ($row = $branch_q->fetch_assoc()) {
            $branch_dist[] = $row;
        }
    }
}

// 3. Fetch Attendance Stats
$attendance_stats = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Excused' => 0];
$att_q = $conn->query("SELECT a.status, COUNT(*) as count 
                       FROM attendance a
                       JOIN students s ON a.student_id = s.id
                       $where_attendance
                       GROUP BY a.status");
$total_att_records = 0;
if ($att_q) {
    while ($row = $att_q->fetch_assoc()) {
        $attendance_stats[$row['status']] = intval($row['count']);
        $total_att_records += intval($row['count']);
    }
}

// 4. Fetch Exam Subject Averages
$subject_stats = [];
$exam_q = $conn->query("SELECT sub.name as subject, AVG(e.score) as avg_score, MAX(e.score) as max_score, MIN(e.score) as min_score, COUNT(*) as count 
                        FROM exams e
                        JOIN students s ON e.student_id = s.id
                        JOIN subjects sub ON e.subject_id = sub.id
                        $where_exam
                        GROUP BY e.subject_id, sub.name
                        ORDER BY avg_score DESC");
if ($exam_q) {
    while ($row = $exam_q->fetch_assoc()) {
        $subject_stats[] = $row;
    }
}
?>

<!-- Print-only Report Header -->
<div style="display:none;" class="print-header">
    <h1 style="text-align:center; margin-bottom:10px;">School Management System - Performance Report</h1>
    <h3 style="text-align:center; margin-bottom:30px; font-weight:normal; color:#475569;">
        Scope: <?php echo $selected_branch > 0 ? 'Selected Branch' : 'All Branches'; ?> | Date: <?php echo date('Y-m-d H:i'); ?>
    </h3>
</div>

<!-- Filters & Actions (Hidden in Print) -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <h2 class="card-title"><i class="fa-solid fa-chart-line"></i> Report Scope Filters</h2>
        <button class="btn btn-primary" onclick="window.print();">
            <i class="fa-solid fa-print"></i> Print / Export PDF
        </button>
    </div>
    <div class="card-body">
        <form action="reports.php" method="GET">
            <div class="form-grid">
                <?php if ($user_role === 'super_admin'): ?>
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="branch_id">Select Branch</label>
                        <select id="branch_id" name="branch_id" class="form-control" onchange="this.form.submit()">
                            <option value="0">All Branches (Global stats)</option>
                            <?php if ($branches): ?>
                                <?php while($b = $branches->fetch_assoc()): ?>
                                    <option value="<?php echo $b['id']; ?>" <?php echo ($selected_branch === intval($b['id'])) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($b['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Assigned Branch</label>
                        <input type="text" class="form-control" value="<?php 
                            $b = $branches->fetch_assoc(); 
                            echo htmlspecialchars($b['name'] ?? 'N/A'); 
                        ?>" readonly>
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Layout Dashboard Summary Cards -->
<div class="dashboard-grid">
    <!-- Left Column: Attendance and Academic averages -->
    <div>
        <!-- Attendance Performance -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><i class="fa-solid fa-clipboard-user" style="color:var(--primary); margin-right:8px;"></i> Overall Attendance Rate</h2>
            </div>
            <div class="card-body">
                <?php if ($total_att_records > 0): ?>
                    <?php 
                    $present_pct = round(($attendance_stats['Present'] / $total_att_records) * 100, 1);
                    $absent_pct = round(($attendance_stats['Absent'] / $total_att_records) * 100, 1);
                    $late_pct = round(($attendance_stats['Late'] / $total_att_records) * 100, 1);
                    $excused_pct = round(($attendance_stats['Excused'] / $total_att_records) * 100, 1);
                    ?>
                    
                    <div style="margin-bottom: 20px;">
                        <div style="display:flex; justify-content:space-between; font-weight:600; margin-bottom:5px;">
                            <span>Present</span>
                            <span><?php echo $present_pct; ?>% (<?php echo $attendance_stats['Present']; ?>)</span>
                        </div>
                        <div style="background-color:#e2e8f0; border-radius:9999px; height:10px; overflow:hidden;">
                            <div style="background-color:var(--secondary); height:100%; width:<?php echo $present_pct; ?>%;"></div>
                        </div>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <div style="display:flex; justify-content:space-between; font-weight:600; margin-bottom:5px;">
                            <span>Absent</span>
                            <span><?php echo $absent_pct; ?>% (<?php echo $attendance_stats['Absent']; ?>)</span>
                        </div>
                        <div style="background-color:#e2e8f0; border-radius:9999px; height:10px; overflow:hidden;">
                            <div style="background-color:var(--danger); height:100%; width:<?php echo $absent_pct; ?>%;"></div>
                        </div>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <div style="display:flex; justify-content:space-between; font-weight:600; margin-bottom:5px;">
                            <span>Late</span>
                            <span><?php echo $late_pct; ?>% (<?php echo $attendance_stats['Late']; ?>)</span>
                        </div>
                        <div style="background-color:#e2e8f0; border-radius:9999px; height:10px; overflow:hidden;">
                            <div style="background-color:var(--warning); height:100%; width:<?php echo $late_pct; ?>%;"></div>
                        </div>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <div style="display:flex; justify-content:space-between; font-weight:600; margin-bottom:5px;">
                            <span>Excused</span>
                            <span><?php echo $excused_pct; ?>% (<?php echo $attendance_stats['Excused']; ?>)</span>
                        </div>
                        <div style="background-color:#e2e8f0; border-radius:9999px; height:10px; overflow:hidden;">
                            <div style="background-color:var(--info); height:100%; width:<?php echo $excused_pct; ?>%;"></div>
                        </div>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: #64748b; padding: 20px;">No attendance records found for the selected scope.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Academic performance -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><i class="fa-solid fa-award" style="color:var(--warning); margin-right:8px;"></i> Academic Performance by Subject</h2>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Average Score</th>
                                <th>Highest Score</th>
                                <th>Lowest Score</th>
                                <th>Grades Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($subject_stats) > 0): ?>
                                <?php foreach($subject_stats as $stat): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($stat['subject']); ?></strong></td>
                                        <td>
                                            <span class="badge <?php echo $stat['avg_score'] >= 80 ? 'badge-success' : ($stat['avg_score'] >= 50 ? 'badge-warning' : 'badge-danger'); ?>" style="font-size:0.85rem; padding: 5px 10px;">
                                                <?php echo number_format($stat['avg_score'], 2); ?>
                                            </span>
                                        </td>
                                        <td><span class="badge badge-success"><?php echo number_format($stat['max_score'], 1); ?></span></td>
                                        <td><span class="badge badge-danger"><?php echo number_format($stat['min_score'], 1); ?></span></td>
                                        <td><?php echo $stat['count']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 24px; color: #64748b;">No exam grades recorded for the selected scope.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Student distributions -->
    <div>
        <!-- Student levels distribution -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><i class="fa-solid fa-graduation-cap" style="color:var(--secondary); margin-right:8px;"></i> Distribution by Level</h2>
            </div>
            <div class="card-body" style="padding:0;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Level</th>
                            <th>Student Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($level_dist) > 0): ?>
                            <?php foreach($level_dist as $lvl): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($lvl['level_name']); ?></strong></td>
                                    <td><?php echo $lvl['student_count']; ?> students</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="2" style="text-align: center; padding: 20px; color: #64748b;">No student records found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Student branches distribution (Super Admin only) -->
        <?php if ($user_role === 'super_admin'): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fa-solid fa-code-branch" style="color:var(--info); margin-right:8px;"></i> Distribution by Branch</h2>
                </div>
                <div class="card-body" style="padding:0;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Branch</th>
                                <th>Student Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($branch_dist) > 0): ?>
                                <?php foreach($branch_dist as $br): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($br['branch_name']); ?></strong></td>
                                        <td><?php echo $br['student_count']; ?> students</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" style="text-align: center; padding: 20px; color: #64748b;">No student records found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Print Styles -->
<style>
@media print {
    .print-header {
        display: block !important;
    }
    .main-wrapper {
        margin-left: 0 !important;
    }
    .content-container {
        padding: 0 !important;
    }
}
</style>

<?php
require_once 'footer.php';
?>
