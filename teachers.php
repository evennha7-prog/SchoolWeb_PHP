<?php
require_once 'header.php';

// Only Super Admin can access this page
if ($user_role !== 'super_admin') {
    header("Location: index.php");
    exit();
}

$msg = "";
$err = "";

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_teacher'])) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $branch_id = intval($_POST['branch_id']);

        if (empty($name) || empty($email) || empty($password) || empty($branch_id)) {
            $err = "All fields marked with * are required.";
        } else {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $err = "Email address is already in use.";
            } else {
                // Insert new teacher
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $role = 'teacher';
                $insert_stmt = $conn->prepare("INSERT INTO users (name, email, password, role, branch_id) VALUES (?, ?, ?, ?, ?)");
                $insert_stmt->bind_param("ssssi", $name, $email, $hashed_password, $role, $branch_id);
                if ($insert_stmt->execute()) {
                    $msg = "Teacher account created successfully.";
                } else {
                    $err = "Failed to create teacher account: " . $conn->error;
                }
                $insert_stmt->close();
            }
            $stmt->close();
        }
    } elseif (isset($_POST['edit_teacher'])) {
        $id = intval($_POST['id']);
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $branch_id = intval($_POST['branch_id']);

        if (empty($name) || empty($email) || empty($branch_id)) {
            $err = "Name, Email, and Branch are required.";
        } else {
            // Check if email already exists for a different user
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->bind_param("si", $email, $id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $err = "Email address is already in use by another user.";
            } else {
                // Update basic info
                if (!empty($password)) {
                    // Update with password change
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $update_stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, password = ?, branch_id = ? WHERE id = ?");
                    $update_stmt->bind_param("sssii", $name, $email, $hashed_password, $branch_id, $id);
                } else {
                    // Update without password change
                    $update_stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, branch_id = ? WHERE id = ?");
                    $update_stmt->bind_param("ssii", $name, $email, $branch_id, $id);
                }

                if ($update_stmt->execute()) {
                    $msg = "Teacher details updated successfully.";
                } else {
                    $err = "Failed to update teacher: " . $conn->error;
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
    // Prevent deleting self
    if ($id === intval($_SESSION['user_id'])) {
        $err = "You cannot delete your own account.";
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'teacher'");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $msg = "Teacher account deleted successfully.";
        } else {
            $err = "Failed to delete account: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch edit target
$edit_teacher = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'teacher'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_teacher = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Fetch all teachers with branch details
$teachers = $conn->query("SELECT u.*, b.name as branch_name 
                          FROM users u 
                          LEFT JOIN branches b ON u.branch_id = b.id 
                          WHERE u.role = 'teacher' 
                          ORDER BY u.id DESC");

// Fetch branches for drop-down list
$branches = $conn->query("SELECT id, name FROM branches ORDER BY name ASC");
$branches_list = [];
if ($branches) {
    while ($b = $branches->fetch_assoc()) {
        $branches_list[] = $b;
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

<!-- Form Section -->
<div style="margin-bottom: 24px;">
    <button class="btn btn-primary" onclick="toggleForm('teacherForm')">
        <i class="fa-solid <?php echo $edit_teacher ? 'fa-user-pen' : 'fa-user-plus'; ?>"></i> 
        <?php echo $edit_teacher ? 'Edit Teacher: ' . htmlspecialchars($edit_teacher['name']) : 'Add New Teacher / Staff'; ?>
    </button>
</div>

<div id="teacherForm" class="card form-collapse <?php echo ($edit_teacher || !empty($err)) ? 'show' : ''; ?>">
    <div class="card-header">
        <h2 class="card-title"><?php echo $edit_teacher ? 'Edit Teacher Account' : 'Register New Teacher / Staff'; ?></h2>
    </div>
    <div class="card-body">
        <form action="teachers.php" method="POST">
            <?php if ($edit_teacher): ?>
                <input type="hidden" name="id" value="<?php echo $edit_teacher['id']; ?>">
            <?php endif; ?>

            <div class="form-grid">
                <div class="form-group">
                    <label for="name">Full Name *</label>
                    <input type="text" id="name" name="name" class="form-control" placeholder="e.g. Sok Dara" value="<?php echo $edit_teacher ? htmlspecialchars($edit_teacher['name']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="dara@school.com" value="<?php echo $edit_teacher ? htmlspecialchars($edit_teacher['email']) : ''; ?>" required autocomplete="email">
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="password">Password <?php echo $edit_teacher ? '(Leave empty to keep current)' : '*'; ?></label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" <?php echo $edit_teacher ? '' : 'required'; ?> autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label for="branch_id">Assigned Branch *</label>
                    <select id="branch_id" name="branch_id" class="form-control" required>
                        <option value="">-- Select Branch --</option>
                        <?php foreach($branches_list as $br): ?>
                            <option value="<?php echo $br['id']; ?>" <?php echo ($edit_teacher && intval($edit_teacher['branch_id']) === intval($br['id'])) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($br['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" name="<?php echo $edit_teacher ? 'edit_teacher' : 'add_teacher'; ?>" class="btn btn-success">
                    <i class="fa-solid fa-floppy-disk"></i> Save Account
                </button>
                <?php if ($edit_teacher): ?>
                    <a href="teachers.php" class="btn btn-secondary">Cancel</a>
                <?php else: ?>
                    <button type="button" class="btn btn-secondary" onclick="toggleForm('teacherForm')">Cancel</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Data Table Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fa-solid fa-chalkboard-user" style="color:var(--primary); margin-right:8px;"></i> Registered Teacher Registry</h2>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Assigned Branch</th>
                        <th>Date Created</th>
                        <th style="width: 150px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($teachers && $teachers->num_rows > 0): ?>
                        <?php while($row = $teachers->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><span class="badge badge-info"><?php echo htmlspecialchars($row['branch_name'] ?? 'Unassigned'); ?></span></td>
                                <td><?php echo date('Y-m-d', strtotime($row['created_at'])); ?></td>
                                <td>
                                    <div class="action-links">
                                        <a href="teachers.php?action=edit&id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm" title="Edit">
                                            <i class="fa-solid fa-pen"></i>
                                        </a>
                                        <a href="teachers.php?action=delete&id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirmDelete('Are you sure you want to delete this teacher account?')" title="Delete">
                                            <i class="fa-solid fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 24px; color: #64748b;">No teacher accounts registered.</td>
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
