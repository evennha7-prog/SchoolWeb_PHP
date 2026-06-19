<?php
require_once 'header.php';

$msg = "";
$err = "";

// Fetch levels and branches for forms & filters
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

// Handle Form Submissions (Add / Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_student'])) {
        $student_code = trim($_POST['student_code']);
        $name = trim($_POST['name']);
        $gender = trim($_POST['gender']);
        $dob = trim($_POST['dob']);
        $phone = trim($_POST['phone']);
        $branch_id = intval($_POST['branch_id']);
        $level_id = intval($_POST['level_id']);

        // Check teacher branch restriction
        if ($user_role !== 'super_admin' && $branch_id !== intval($_SESSION['branch_id'])) {
            $err = "Unauthorized branch assignment.";
        } elseif (empty($student_code) || empty($name) || empty($gender) || empty($branch_id) || empty($level_id)) {
            $err = "All fields marked with * are required.";
        } else {
            // Check unique student code
            $stmt = $conn->prepare("SELECT id FROM students WHERE student_code = ?");
            $stmt->bind_param("s", $student_code);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $err = "Student code is already in use.";
            } else {
                $insert_stmt = $conn->prepare("INSERT INTO students (student_code, name, gender, dob, phone, branch_id, level_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $insert_stmt->bind_param("sssssii", $student_code, $name, $gender, $dob, $phone, $branch_id, $level_id);
                if ($insert_stmt->execute()) {
                    $msg = "Student registered successfully.";
                } else {
                    $err = "Failed to register student: " . $conn->error;
                }
                $insert_stmt->close();
            }
            $stmt->close();
        }
    } elseif (isset($_POST['edit_student'])) {
        $id = intval($_POST['id']);
        $student_code = trim($_POST['student_code']);
        $name = trim($_POST['name']);
        $gender = trim($_POST['gender']);
        $dob = trim($_POST['dob']);
        $phone = trim($_POST['phone']);
        $branch_id = intval($_POST['branch_id']);
        $level_id = intval($_POST['level_id']);

        // Check if teacher is trying to modify a student in another branch
        $verify_stmt = $conn->prepare("SELECT branch_id FROM students WHERE id = ?");
        $verify_stmt->bind_param("i", $id);
        $verify_stmt->execute();
        $existing_student = $verify_stmt->get_result()->fetch_assoc();
        $verify_stmt->close();

        if (!$existing_student) {
            $err = "Student not found.";
        } elseif ($user_role !== 'super_admin' && (intval($existing_student['branch_id']) !== intval($_SESSION['branch_id']) || $branch_id !== intval($_SESSION['branch_id']))) {
            $err = "Unauthorized access to this student.";
        } elseif (empty($student_code) || empty($name) || empty($gender) || empty($branch_id) || empty($level_id)) {
            $err = "All fields marked with * are required.";
        } else {
            // Check student code uniqueness for other students
            $stmt = $conn->prepare("SELECT id FROM students WHERE student_code = ? AND id != ?");
            $stmt->bind_param("si", $student_code, $id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $err = "Student code is already in use by another student.";
            } else {
                $update_stmt = $conn->prepare("UPDATE students SET student_code = ?, name = ?, gender = ?, dob = ?, phone = ?, branch_id = ?, level_id = ? WHERE id = ?");
                $update_stmt->bind_param("sssssiii", $student_code, $name, $gender, $dob, $phone, $branch_id, $level_id, $id);
                if ($update_stmt->execute()) {
                    $msg = "Student details updated successfully.";
                } else {
                    $err = "Failed to update student: " . $conn->error;
                }
                $update_stmt->close();
            }
            $stmt->close();
        }
    }
}

// Handle Delete Request
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // Check ownership/branch constraint
    $verify_stmt = $conn->prepare("SELECT branch_id FROM students WHERE id = ?");
    $verify_stmt->bind_param("i", $id);
    $verify_stmt->execute();
    $existing_student = $verify_stmt->get_result()->fetch_assoc();
    $verify_stmt->close();

    if (!$existing_student) {
        $err = "Student not found.";
    } elseif ($user_role !== 'super_admin' && intval($existing_student['branch_id']) !== intval($_SESSION['branch_id'])) {
        $err = "Unauthorized deletion attempt.";
    } else {
        $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $msg = "Student deleted successfully.";
        } else {
            $err = "Failed to delete student: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch edit target
$edit_student = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_student = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Check teacher access to edit target
    if ($edit_student && $user_role !== 'super_admin' && intval($edit_student['branch_id']) !== intval($_SESSION['branch_id'])) {
        $err = "Unauthorized access to this student profile.";
        $edit_student = null;
    }
}

// Filtering setup
$filter_branch = isset($_GET['filter_branch']) ? intval($_GET['filter_branch']) : 0;
$filter_level = isset($_GET['filter_level']) ? intval($_GET['filter_level']) : 0;

// Enforce teacher branch filter
if ($user_role !== 'super_admin') {
    $filter_branch = intval($_SESSION['branch_id']);
}

// Build query
$where_clauses = [];
if ($filter_branch > 0) {
    $where_clauses[] = "s.branch_id = $filter_branch";
}
if ($filter_level > 0) {
    $where_clauses[] = "s.level_id = $filter_level";
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

$students_query = "SELECT s.*, b.name as branch_name, l.name as level_name 
                   FROM students s 
                   LEFT JOIN branches b ON s.branch_id = b.id 
                   LEFT JOIN levels l ON s.level_id = l.id 
                   $where_sql 
                   ORDER BY s.id DESC";
$students = $conn->query($students_query);

// Generate Auto Student Code for convenience
$auto_code = "STU" . sprintf("%03d", rand(100, 999));
if ($edit_student) {
    $auto_code = $edit_student['student_code'];
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

<!-- Quick Action Buttons -->
<div style="display: flex; gap: 16px; margin-bottom: 24px;">
    <button class="btn btn-primary" onclick="toggleForm('studentForm')">
        <i class="fa-solid <?php echo $edit_student ? 'fa-user-pen' : 'fa-user-plus'; ?>"></i> 
        <?php echo $edit_student ? 'Edit Student: ' . htmlspecialchars($edit_student['name']) : 'Add New Student'; ?>
    </button>
</div>

<!-- Add / Edit Form Card -->
<div id="studentForm" class="card form-collapse <?php echo ($edit_student || !empty($err)) ? 'show' : ''; ?>">
    <div class="card-header">
        <h2 class="card-title"><?php echo $edit_student ? 'Edit Student Profile' : 'Register New Student'; ?></h2>
    </div>
    <div class="card-body">
        <form action="students.php" method="POST">
            <?php if ($edit_student): ?>
                <input type="hidden" name="id" value="<?php echo $edit_student['id']; ?>">
            <?php endif; ?>

            <div class="form-grid">
                <div class="form-group">
                    <label for="student_code">Student Code *</label>
                    <input type="text" id="student_code" name="student_code" class="form-control" value="<?php echo htmlspecialchars($auto_code); ?>" required>
                </div>
                <div class="form-group">
                    <label for="name">Full Name *</label>
                    <input type="text" id="name" name="name" class="form-control" placeholder="e.g. Chan Rotha" value="<?php echo $edit_student ? htmlspecialchars($edit_student['name']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label for="gender">Gender *</label>
                    <select id="gender" name="gender" class="form-control" required>
                        <option value="Male" <?php echo ($edit_student && $edit_student['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo ($edit_student && $edit_student['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                    </select>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="dob">Date of Birth</label>
                    <input type="date" id="dob" name="dob" class="form-control" value="<?php echo $edit_student ? htmlspecialchars($edit_student['dob']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="text" id="phone" name="phone" class="form-control" placeholder="e.g. 012 345 678" value="<?php echo $edit_student ? htmlspecialchars($edit_student['phone']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="level_id">Academic Level *</label>
                    <select id="level_id" name="level_id" class="form-control" required>
                        <option value="">-- Select Level --</option>
                        <?php foreach($levels_list as $lv): ?>
                            <option value="<?php echo $lv['id']; ?>" <?php echo ($edit_student && intval($edit_student['level_id']) === intval($lv['id'])) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($lv['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="branch_id">Branch *</label>
                    <?php if ($user_role === 'super_admin'): ?>
                        <select id="branch_id" name="branch_id" class="form-control" required>
                            <option value="">-- Select Branch --</option>
                            <?php foreach($branches_list as $br): ?>
                                <option value="<?php echo $br['id']; ?>" <?php echo ($edit_student && intval($edit_student['branch_id']) === intval($br['id'])) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($br['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <!-- If teacher, lock branch to teacher's branch -->
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($branches_list[0]['name']); ?>" readonly>
                        <input type="hidden" name="branch_id" value="<?php echo $branches_list[0]['id']; ?>">
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" name="<?php echo $edit_student ? 'edit_student' : 'add_student'; ?>" class="btn btn-success">
                    <i class="fa-solid fa-floppy-disk"></i> Save Student
                </button>
                <?php if ($edit_student): ?>
                    <a href="students.php" class="btn btn-secondary">Cancel</a>
                <?php else: ?>
                    <button type="button" class="btn btn-secondary" onclick="toggleForm('studentForm')">Cancel</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Filters Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fa-solid fa-filter"></i> Filter Results</h2>
    </div>
    <div class="card-body">
        <form action="students.php" method="GET">
            <div class="form-grid">
                <?php if ($user_role === 'super_admin'): ?>
                    <div class="form-group">
                        <label for="filter_branch">Branch</label>
                        <select id="filter_branch" name="filter_branch" class="form-control">
                            <option value="0">All Branches</option>
                            <?php foreach($branches_list as $br): ?>
                                <option value="<?php echo $br['id']; ?>" <?php echo ($filter_branch === intval($br['id'])) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($br['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="filter_level">Academic Level</label>
                    <select id="filter_level" name="filter_level" class="form-control">
                        <option value="0">All Levels</option>
                        <?php foreach($levels_list as $lv): ?>
                            <option value="<?php echo $lv['id']; ?>" <?php echo ($filter_level === intval($lv['id'])) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($lv['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" style="display: flex; align-items: flex-end;">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fa-solid fa-magnifying-glass"></i> Filter
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Data Table Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fa-solid fa-users" style="color:var(--primary); margin-right:8px;"></i> Registered Students Directory</h2>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Gender</th>
                        <th>DOB</th>
                        <th>Phone</th>
                        <th>Branch</th>
                        <th>Level</th>
                        <th style="width: 150px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($students && $students->num_rows > 0): ?>
                        <?php while($row = $students->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['student_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td>
                                    <span class="badge <?php echo $row['gender'] === 'Male' ? 'badge-info' : 'badge-warning'; ?>">
                                        <?php echo htmlspecialchars($row['gender']); ?>
                                    </span>
                                </td>
                                <td><?php echo $row['dob'] ? date('Y-m-d', strtotime($row['dob'])) : 'N/A'; ?></td>
                                <td><?php echo htmlspecialchars($row['phone'] ?? 'N/A'); ?></td>
                                <td><span class="badge badge-secondary"><?php echo htmlspecialchars($row['branch_name'] ?? 'N/A'); ?></span></td>
                                <td><span class="badge badge-success"><?php echo htmlspecialchars($row['level_name'] ?? 'N/A'); ?></span></td>
                                <td>
                                    <div class="action-links">
                                        <a href="students.php?action=edit&id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm" title="Edit">
                                            <i class="fa-solid fa-pen"></i>
                                        </a>
                                        <a href="students.php?action=delete&id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirmDelete('Are you sure you want to delete this student profile?')" title="Delete">
                                            <i class="fa-solid fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 24px; color: #64748b;">No students found matching current filters.</td>
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
