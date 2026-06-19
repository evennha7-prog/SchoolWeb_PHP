<?php
require_once 'header.php';

$msg = "";
$err = "";

// Fetch students for the dropdown selection
if ($user_role === 'super_admin') {
    // Super admins can grade any student
    $students_res = $conn->query("SELECT s.id, s.name, s.student_code, l.name as level_name 
                                  FROM students s 
                                  JOIN levels l ON s.level_id = l.id 
                                  ORDER BY s.name ASC");
} else {
    // Teachers can only grade students in their branch
    $teacher_branch_id = $_SESSION['branch_id'] ?? 0;
    $students_res = $conn->query("SELECT s.id, s.name, s.student_code, l.name as level_name 
                                  FROM students s 
                                  JOIN levels l ON s.level_id = l.id 
                                  WHERE s.branch_id = $teacher_branch_id 
                                  ORDER BY s.name ASC");
}

$students_list = [];
while ($st = $students_res->fetch_assoc()) {
    $students_list[] = $st;
}

// Fetch subjects list
$subjects_res = $conn->query("SELECT * FROM subjects ORDER BY name ASC");
$subjects_list = [];
if ($subjects_res) {
    while ($sub = $subjects_res->fetch_assoc()) {
        $subjects_list[] = $sub;
    }
}

// Handle Form Submissions (Add / Edit Exam Mark)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_mark'])) {
        $student_id = intval($_POST['student_id']);
        $subject_id = intval($_POST['subject_id']);
        $score = floatval($_POST['score']);
        $exam_date = trim($_POST['exam_date']);
        $remarks = trim($_POST['remarks']);

        // Check if student belongs to teacher's branch
        $student_check = false;
        foreach ($students_list as $st) {
            if (intval($st['id']) === $student_id) {
                $student_check = true;
                break;
            }
        }

        // Check if subject exists
        $subject_check = false;
        foreach ($subjects_list as $sub) {
            if (intval($sub['id']) === $subject_id) {
                $subject_check = true;
                break;
            }
        }

        if (!$student_check) {
            $err = "Unauthorized student selection.";
        } elseif (!$subject_check) {
            $err = "Invalid subject selection.";
        } elseif (empty($student_id) || empty($subject_id) || !isset($_POST['score']) || empty($exam_date)) {
            $err = "All fields marked with * are required.";
        } elseif ($score < 0 || $score > 100) {
            $err = "Score must be between 0 and 100.";
        } else {
            $teacher_id = $_SESSION['user_id'];
            $stmt = $conn->prepare("INSERT INTO exams (student_id, subject_id, score, exam_date, teacher_id, remarks) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iidsis", $student_id, $subject_id, $score, $exam_date, $teacher_id, $remarks);
            if ($stmt->execute()) {
                $msg = "Exam mark added successfully.";
            } else {
                $err = "Failed to add mark: " . $conn->error;
            }
            $stmt->close();
        }
    } elseif (isset($_POST['edit_mark'])) {
        $id = intval($_POST['id']);
        $student_id = intval($_POST['student_id']);
        $subject_id = intval($_POST['subject_id']);
        $score = floatval($_POST['score']);
        $exam_date = trim($_POST['exam_date']);
        $remarks = trim($_POST['remarks']);

        // Verify exam edit access (Teacher can only edit marks in their branch)
        $verify_stmt = $conn->prepare("SELECT s.branch_id FROM exams e JOIN students s ON e.student_id = s.id WHERE e.id = ?");
        $verify_stmt->bind_param("i", $id);
        $verify_stmt->execute();
        $exam_branch = $verify_stmt->get_result()->fetch_assoc();
        $verify_stmt->close();

        $student_check = false;
        foreach ($students_list as $st) {
            if (intval($st['id']) === $student_id) {
                $student_check = true;
                break;
            }
        }

        // Check if subject exists
        $subject_check = false;
        foreach ($subjects_list as $sub) {
            if (intval($sub['id']) === $subject_id) {
                $subject_check = true;
                break;
            }
        }

        if (!$exam_branch) {
            $err = "Exam record not found.";
        } elseif ($user_role !== 'super_admin' && intval($exam_branch['branch_id']) !== intval($_SESSION['branch_id'])) {
            $err = "Unauthorized modification attempt.";
        } elseif (!$student_check) {
            $err = "Unauthorized student assignment.";
        } elseif (!$subject_check) {
            $err = "Invalid subject selection.";
        } elseif (empty($student_id) || empty($subject_id) || !isset($_POST['score']) || empty($exam_date)) {
            $err = "All fields marked with * are required.";
        } elseif ($score < 0 || $score > 100) {
            $err = "Score must be between 0 and 100.";
        } else {
            $stmt = $conn->prepare("UPDATE exams SET student_id = ?, subject_id = ?, score = ?, exam_date = ?, remarks = ? WHERE id = ?");
            $stmt->bind_param("iidssi", $student_id, $subject_id, $score, $exam_date, $remarks, $id);
            if ($stmt->execute()) {
                $msg = "Exam mark updated successfully.";
            } else {
                $err = "Failed to update mark: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Handle Delete Request
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Check branch permissions before deleting
    $verify_stmt = $conn->prepare("SELECT s.branch_id FROM exams e JOIN students s ON e.student_id = s.id WHERE e.id = ?");
    $verify_stmt->bind_param("i", $id);
    $verify_stmt->execute();
    $exam_branch = $verify_stmt->get_result()->fetch_assoc();
    $verify_stmt->close();

    if (!$exam_branch) {
        $err = "Exam record not found.";
    } elseif ($user_role !== 'super_admin' && intval($exam_branch['branch_id']) !== intval($_SESSION['branch_id'])) {
        $err = "Unauthorized deletion attempt.";
    } else {
        $stmt = $conn->prepare("DELETE FROM exams WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $msg = "Exam mark deleted successfully.";
        } else {
            $err = "Failed to delete mark: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch edit target
$edit_exam = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM exams WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_exam = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Verify branch constraints for editing
    if ($edit_exam && $user_role !== 'super_admin') {
        $verify_stmt = $conn->prepare("SELECT branch_id FROM students WHERE id = ?");
        $verify_stmt->bind_param("i", $edit_exam['student_id']);
        $verify_stmt->execute();
        $student_branch = $verify_stmt->get_result()->fetch_assoc();
        $verify_stmt->close();

        if ($student_branch && intval($student_branch['branch_id']) !== intval($_SESSION['branch_id'])) {
            $err = "Unauthorized access to this grading record.";
            $edit_exam = null;
        }
    }
}

// Fetch all relevant exam scores
if ($user_role === 'super_admin') {
    $exams_query = "SELECT e.*, s.name as student_name, s.student_code, l.name as level_name, u.name as teacher_name, sub.name as subject_name 
                    FROM exams e 
                    JOIN students s ON e.student_id = s.id 
                    JOIN levels l ON s.level_id = l.id
                    JOIN subjects sub ON e.subject_id = sub.id
                    LEFT JOIN users u ON e.teacher_id = u.id
                    ORDER BY e.exam_date DESC, e.id DESC";
    $exams = $conn->query($exams_query);
} else {
    $teacher_branch_id = $_SESSION['branch_id'] ?? 0;
    $stmt = $conn->prepare("SELECT e.*, s.name as student_name, s.student_code, l.name as level_name, u.name as teacher_name, sub.name as subject_name 
                            FROM exams e 
                            JOIN students s ON e.student_id = s.id 
                            JOIN levels l ON s.level_id = l.id
                            JOIN subjects sub ON e.subject_id = sub.id
                            LEFT JOIN users u ON e.teacher_id = u.id
                            WHERE s.branch_id = ?
                            ORDER BY e.exam_date DESC, e.id DESC");
    $stmt->bind_param("i", $teacher_branch_id);
    $stmt->execute();
    $exams = $stmt->get_result();
    $stmt->close();
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

<!-- Quick Actions -->
<div style="margin-bottom: 24px;">
    <button class="btn btn-primary" onclick="toggleForm('examForm')">
        <i class="fa-solid <?php echo $edit_exam ? 'fa-pen-to-square' : 'fa-plus'; ?>"></i> 
        <?php echo $edit_exam ? 'Edit Mark Details' : 'Input Student Exam Result'; ?>
    </button>
</div>

<!-- Add / Edit Form Card -->
<div id="examForm" class="card form-collapse <?php echo ($edit_exam || !empty($err)) ? 'show' : ''; ?>">
    <div class="card-header">
        <h2 class="card-title"><?php echo $edit_exam ? 'Edit Exam Mark Record' : 'Record New Exam Grade'; ?></h2>
    </div>
    <div class="card-body">
        <form action="marks.php" method="POST">
            <?php if ($edit_exam): ?>
                <input type="hidden" name="id" value="<?php echo $edit_exam['id']; ?>">
            <?php endif; ?>

            <div class="form-grid">
                <div class="form-group">
                    <label for="student_id">Select Student *</label>
                    <select id="student_id" name="student_id" class="form-control" required>
                        <option value="">-- Select Student --</option>
                        <?php foreach($students_list as $st): ?>
                            <option value="<?php echo $st['id']; ?>" <?php echo ($edit_exam && intval($edit_exam['student_id']) === intval($st['id'])) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($st['name']); ?> (<?php echo htmlspecialchars($st['student_code']); ?>) - <?php echo htmlspecialchars($st['level_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="subject_id">Subject *</label>
                    <select id="subject_id" name="subject_id" class="form-control" required>
                        <option value="">-- Select Subject --</option>
                        <?php foreach($subjects_list as $sub): ?>
                            <option value="<?php echo $sub['id']; ?>" <?php echo ($edit_exam && intval($edit_exam['subject_id']) === intval($sub['id'])) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sub['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="score">Obtained Score (0-100) *</label>
                    <input type="number" id="score" name="score" class="form-control" step="0.01" min="0" max="100" placeholder="e.g. 85.5" value="<?php echo $edit_exam ? htmlspecialchars($edit_exam['score']) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="exam_date">Exam Date *</label>
                    <input type="date" id="exam_date" name="exam_date" class="form-control" value="<?php echo $edit_exam ? htmlspecialchars($edit_exam['exam_date']) : date('Y-m-d'); ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="remarks">Teacher Remarks / Notes</label>
                <input type="text" id="remarks" name="remarks" class="form-control" placeholder="e.g. Needs work on arithmetic, excellent progress" value="<?php echo $edit_exam ? htmlspecialchars($edit_exam['remarks']) : ''; ?>">
            </div>

            <div class="form-actions">
                <button type="submit" name="<?php echo $edit_exam ? 'edit_mark' : 'add_mark'; ?>" class="btn btn-success">
                    <i class="fa-solid fa-floppy-disk"></i> Save Grade
                </button>
                <?php if ($edit_exam): ?>
                    <a href="marks.php" class="btn btn-secondary">Cancel</a>
                <?php else: ?>
                    <button type="button" class="btn btn-secondary" onclick="toggleForm('examForm')">Cancel</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Data Table Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fa-solid fa-square-poll-vertical" style="color:var(--primary); margin-right:8px;"></i> Recorded Exam Grades Registry</h2>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Student</th>
                        <th>Level</th>
                        <th>Subject</th>
                        <th>Score</th>
                        <th>Graded By</th>
                        <th>Remarks</th>
                        <th style="width: 150px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($exams && $exams->num_rows > 0): ?>
                        <?php while($row = $exams->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('Y-m-d', strtotime($row['exam_date'])); ?></td>
                                <td>
                                    <div style="font-weight:600; color:var(--dark);"><?php echo htmlspecialchars($row['student_name']); ?></div>
                                    <div style="font-size:0.75rem; color:#64748b;"><?php echo htmlspecialchars($row['student_code']); ?></div>
                                </td>
                                <td><span class="badge badge-success"><?php echo htmlspecialchars($row['level_name']); ?></span></td>
                                <td><strong><?php echo htmlspecialchars($row['subject_name']); ?></strong></td>
                                <td>
                                    <span class="badge <?php echo $row['score'] >= 85 ? 'badge-success' : ($row['score'] >= 50 ? 'badge-warning' : 'badge-danger'); ?>" style="font-size: 0.85rem; padding: 5px 10px;">
                                        <?php echo number_format($row['score'], 2); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($row['teacher_name'] ?? 'System'); ?></td>
                                <td><span style="font-style: italic; font-size: 0.85rem; color: #64748b;"><?php echo htmlspecialchars($row['remarks'] ?? '-'); ?></span></td>
                                <td>
                                    <div class="action-links">
                                        <a href="marks.php?action=edit&id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm" title="Edit">
                                            <i class="fa-solid fa-pen"></i>
                                        </a>
                                        <a href="marks.php?action=delete&id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirmDelete('Are you sure you want to delete this grade record?')" title="Delete">
                                            <i class="fa-solid fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 24px; color: #64748b;">No exam grades found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once 'footer.php';
?>
