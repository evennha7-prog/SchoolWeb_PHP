<?php
require_once 'header.php';

$msg = "";
$err = "";

// Fetch levels and branches for dropdown filters
$levels_res = $conn->query("SELECT id, name FROM levels ORDER BY name ASC");
$levels_list = [];
while ($lv = $levels_res->fetch_assoc()) {
    $levels_list[] = $lv;
}

if ($user_role === 'super_admin') {
    $branches_res = $conn->query("SELECT id, name FROM branches ORDER BY name ASC");
} else {
    $teacher_branch_id = $_SESSION['branch_id'] ?? 0;
    $branches_res = $conn->query("SELECT id, name FROM branches WHERE id = $teacher_branch_id");
}
$branches_list = [];
while ($br = $branches_res->fetch_assoc()) {
    $branches_list[] = $br;
}

// Current selection parameters
$selected_date = isset($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
$selected_branch = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : 0;
$selected_level = isset($_GET['level_id']) ? intval($_GET['level_id']) : 0;

// Enforce teacher branch restriction
if ($user_role !== 'super_admin') {
    $selected_branch = intval($_SESSION['branch_id']);
}

// Handle Form Submission (Save Attendance)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $post_date = trim($_POST['date']);
    $post_branch = intval($_POST['branch_id']);
    $post_level = intval($_POST['level_id']);
    $attendance_data = $_POST['status'] ?? []; // Array of student_id => status

    // Validate inputs
    if ($user_role !== 'super_admin' && $post_branch !== intval($_SESSION['branch_id'])) {
        $err = "Unauthorized branch access.";
    } elseif (empty($post_date) || $post_branch === 0 || $post_level === 0) {
        $err = "Invalid parameters for saving attendance.";
    } else {
        $marked_by = $_SESSION['user_id'];
        $success_count = 0;
        $error_count = 0;

        // Perform transactional update/insert
        $conn->begin_transaction();
        
        foreach ($attendance_data as $student_id => $status) {
            $student_id = intval($student_id);
            // Verify student is actually in the selected branch/level (security check)
            $check_stmt = $conn->prepare("SELECT id FROM students WHERE id = ? AND branch_id = ? AND level_id = ?");
            $check_stmt->bind_param("iii", $student_id, $post_branch, $post_level);
            $check_stmt->execute();
            $student_exists = $check_stmt->get_result()->num_rows > 0;
            $check_stmt->close();

            if ($student_exists) {
                // Upsert query using ON DUPLICATE KEY UPDATE
                $stmt = $conn->prepare("INSERT INTO attendance (student_id, date, status, marked_by) 
                                        VALUES (?, ?, ?, ?) 
                                        ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by)");
                $stmt->bind_param("isss", $student_id, $post_date, $status, $marked_by);
                if ($stmt->execute()) {
                    $success_count++;
                } else {
                    $error_count++;
                }
                $stmt->close();
            } else {
                $error_count++;
            }
        }

        if ($error_count === 0) {
            $conn->commit();
            $msg = "Attendance saved successfully for $success_count students.";
        } else {
            $conn->rollback();
            $err = "Failed to save attendance for some students. Process rolled back.";
        }
    }
}

// Load students list for selected Branch & Level
$students_list = [];
$attendance_records = [];

if ($selected_branch > 0 && $selected_level > 0) {
    // 1. Fetch Students
    $stmt = $conn->prepare("SELECT id, student_code, name, gender FROM students WHERE branch_id = ? AND level_id = ? ORDER BY name ASC");
    $stmt->bind_param("ii", $selected_branch, $selected_level);
    $stmt->execute();
    $students_res = $stmt->get_result();
    while ($student = $students_res->fetch_assoc()) {
        $students_list[] = $student;
    }
    $stmt->close();

    // 2. Fetch existing attendance for this date & class
    if (count($students_list) > 0) {
        $student_ids = array_column($students_list, 'id');
        $id_placeholders = implode(',', array_fill(0, count($student_ids), '?'));
        
        $att_query = "SELECT student_id, status FROM attendance WHERE date = ? AND student_id IN ($id_placeholders)";
        $stmt_att = $conn->prepare($att_query);
        
        // Dynamic binding: date first, then student ids
        $types = "s" . str_repeat("i", count($student_ids));
        $bind_params = array_merge([$selected_date], $student_ids);
        $stmt_att->bind_param($types, ...$bind_params);
        
        $stmt_att->execute();
        $att_res = $stmt_att->get_result();
        while ($att_row = $att_res->fetch_assoc()) {
            $attendance_records[$att_row['student_id']] = $att_row['status'];
        }
        $stmt_att->close();
    }
}
?>

<?php if (!empty($msg)): ?>
    <div class="alert alert-success">
        <i class="fa-solid fa-circle-check"></i>
        <div><?php echo htmlspecialchars($msg); ?></div>
    </div>
<?php endif; ?>

<?php if (!empty($err)): ?>
    <div class="alert alert-danger">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <div><?php echo htmlspecialchars($err); ?></div>
    </div>
<?php endif; ?>

<!-- Class Selection Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fa-solid fa-filter"></i> Select Class & Date</h2>
    </div>
    <div class="card-body">
        <form action="attendance.php" method="GET">
            <div class="form-grid">
                <div class="form-group">
                    <label for="date">Date</label>
                    <input type="date" id="date" name="date" class="form-control" value="<?php echo htmlspecialchars($selected_date); ?>" required>
                </div>

                <?php if ($user_role === 'super_admin'): ?>
                    <div class="form-group">
                        <label for="branch_id">Branch</label>
                        <select id="branch_id" name="branch_id" class="form-control" required>
                            <option value="">-- Select Branch --</option>
                            <?php foreach($branches_list as $br): ?>
                                <option value="<?php echo $br['id']; ?>" <?php echo ($selected_branch === intval($br['id'])) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($br['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <div class="form-group">
                        <label>Branch</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($branches_list[0]['name']); ?>" readonly>
                        <input type="hidden" name="branch_id" value="<?php echo $branches_list[0]['id']; ?>">
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="level_id">Academic Level</label>
                    <select id="level_id" name="level_id" class="form-control" required>
                        <option value="">-- Select Level --</option>
                        <?php foreach($levels_list as $lv): ?>
                            <option value="<?php echo $lv['id']; ?>" <?php echo ($selected_level === intval($lv['id'])) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($lv['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="display: flex; align-items: flex-end;">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fa-solid fa-spinner"></i> Load Student List
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Attendance Form Sheet -->
<?php if ($selected_branch > 0 && $selected_level > 0): ?>
    <div class="card animate-fade-in">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
            <h2 class="card-title">
                <i class="fa-solid fa-clipboard-user" style="color:var(--primary); margin-right:8px;"></i>
                Mark Attendance: 
                <span class="badge badge-info"><?php echo date('d M Y', strtotime($selected_date)); ?></span>
            </h2>
            <div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="markAll('Present')">Select All Present</button>
            </div>
        </div>
        <form action="attendance.php?date=<?php echo urlencode($selected_date); ?>&branch_id=<?php echo $selected_branch; ?>&level_id=<?php echo $selected_level; ?>" method="POST">
            <input type="hidden" name="date" value="<?php echo htmlspecialchars($selected_date); ?>">
            <input type="hidden" name="branch_id" value="<?php echo $selected_branch; ?>">
            <input type="hidden" name="level_id" value="<?php echo $selected_level; ?>">
            
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Student Name</th>
                                <th>Gender</th>
                                <th style="text-align: center;">Present</th>
                                <th style="text-align: center;">Absent</th>
                                <th style="text-align: center;">Late</th>
                                <th style="text-align: center;">Excused</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($students_list) > 0): ?>
                                <?php foreach($students_list as $student): ?>
                                    <?php 
                                    $saved_status = $attendance_records[$student['id']] ?? 'Present'; // default to Present
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($student['student_code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $student['gender'] === 'Male' ? 'badge-info' : 'badge-warning'; ?>">
                                                <?php echo htmlspecialchars($student['gender']); ?>
                                            </span>
                                        </td>
                                        <td style="text-align: center;">
                                            <input type="radio" name="status[<?php echo $student['id']; ?>]" value="Present" class="att-radio radio-present" <?php echo $saved_status === 'Present' ? 'checked' : ''; ?>>
                                        </td>
                                        <td style="text-align: center;">
                                            <input type="radio" name="status[<?php echo $student['id']; ?>]" value="Absent" class="att-radio radio-absent" <?php echo $saved_status === 'Absent' ? 'checked' : ''; ?>>
                                        </td>
                                        <td style="text-align: center;">
                                            <input type="radio" name="status[<?php echo $student['id']; ?>]" value="Late" class="att-radio radio-late" <?php echo $saved_status === 'Late' ? 'checked' : ''; ?>>
                                        </td>
                                        <td style="text-align: center;">
                                            <input type="radio" name="status[<?php echo $student['id']; ?>]" value="Excused" class="att-radio radio-excused" <?php echo $saved_status === 'Excused' ? 'checked' : ''; ?>>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 24px; color: #64748b;">No students found in this class. Add students before marking attendance.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if (count($students_list) > 0): ?>
                <div class="card-body" style="border-top:1px solid var(--border); padding: 20px 24px;">
                    <button type="submit" name="save_attendance" class="btn btn-success">
                        <i class="fa-solid fa-floppy-disk"></i> Submit Attendance Sheet
                    </button>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <script>
    function markAll(status) {
        const radios = document.querySelectorAll('.att-radio');
        radios.forEach(radio => {
            if (radio.value === status) {
                radio.checked = true;
            }
        });
    }
    </script>
<?php else: ?>
    <div class="card">
        <div class="card-body" style="text-align: center; padding: 40px; color: #64748b;">
            <i class="fa-solid fa-circle-info" style="font-size: 3rem; color: var(--primary-hover); margin-bottom: 16px;"></i>
            <h3>Please select a branch and academic level to begin marking student attendance.</h3>
        </div>
    </div>
<?php endif; ?>

<?php
require_once 'footer.php';
?>
