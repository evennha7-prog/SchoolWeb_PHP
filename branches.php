<?php
require_once 'header.php';

$msg = "";
$err = "";

// Only Super Admin can perform CRUD actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_role === 'super_admin') {
    if (isset($_POST['add_branch'])) {
        $name = trim($_POST['name']);
        $address = trim($_POST['address']);
        $phone = trim($_POST['phone']);

        if (empty($name)) {
            $err = "Branch name is required.";
        } else {
            $stmt = $conn->prepare("INSERT INTO branches (name, address, phone) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $address, $phone);
            if ($stmt->execute()) {
                $msg = "Branch added successfully.";
            } else {
                $err = "Failed to add branch: " . $conn->error;
            }
            $stmt->close();
        }
    } elseif (isset($_POST['edit_branch'])) {
        $id = intval($_POST['id']);
        $name = trim($_POST['name']);
        $address = trim($_POST['address']);
        $phone = trim($_POST['phone']);

        if (empty($name)) {
            $err = "Branch name is required.";
        } else {
            $stmt = $conn->prepare("UPDATE branches SET name = ?, address = ?, phone = ? WHERE id = ?");
            $stmt->bind_param("sssi", $name, $address, $phone, $id);
            if ($stmt->execute()) {
                $msg = "Branch updated successfully.";
            } else {
                $err = "Failed to update branch: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Handle Delete Request
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if ($user_role !== 'super_admin') {
        $err = "Unauthorized access.";
    } else {
        $id = intval($_GET['id']);
        $stmt = $conn->prepare("DELETE FROM branches WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $msg = "Branch deleted successfully.";
        } else {
            $err = "Failed to delete branch: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch edit target
$edit_branch = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id']) && $user_role === 'super_admin') {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM branches WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_branch = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Fetch all branches
$branches = $conn->query("SELECT * FROM branches ORDER BY id DESC");
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

<!-- Form Section (Collapse-style toggler for clean interface) -->
<?php if ($user_role === 'super_admin'): ?>
    <div style="margin-bottom: 24px;">
        <button class="btn btn-primary" onclick="toggleForm('branchForm')">
            <i class="fa-solid <?php echo $edit_branch ? 'fa-pen-to-square' : 'fa-plus'; ?>"></i> 
            <?php echo $edit_branch ? 'Edit Branch: ' . htmlspecialchars($edit_branch['name']) : 'Add New Branch'; ?>
        </button>
    </div>

    <div id="branchForm" class="card form-collapse <?php echo ($edit_branch || !empty($err)) ? 'show' : ''; ?>">
        <div class="card-header">
            <h2 class="card-title"><?php echo $edit_branch ? 'Edit Branch Details' : 'Enter Branch Details'; ?></h2>
        </div>
        <div class="card-body">
            <form action="branches.php" method="POST">
                <?php if ($edit_branch): ?>
                    <input type="hidden" name="id" value="<?php echo $edit_branch['id']; ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="name">Branch Name *</label>
                        <input type="text" id="name" name="name" class="form-control" placeholder="e.g. Phnom Penh Campus" value="<?php echo $edit_branch ? htmlspecialchars($edit_branch['name']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="text" id="phone" name="phone" class="form-control" placeholder="e.g. +855 23 123 456" value="<?php echo $edit_branch ? htmlspecialchars($edit_branch['phone']) : ''; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" class="form-control" placeholder="Full address details..."><?php echo $edit_branch ? htmlspecialchars($edit_branch['address']) : ''; ?></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" name="<?php echo $edit_branch ? 'edit_branch' : 'add_branch'; ?>" class="btn btn-success">
                        <i class="fa-solid fa-floppy-disk"></i> Save Branch
                    </button>
                    <?php if ($edit_branch): ?>
                        <a href="branches.php" class="btn btn-secondary">Cancel</a>
                    <?php else: ?>
                        <button type="button" class="btn btn-secondary" onclick="toggleForm('branchForm')">Cancel</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Data Table Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fa-solid fa-code-branch" style="color:var(--primary); margin-right:8px;"></i> Registered School Branches</h2>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Branch Name</th>
                        <th>Address</th>
                        <th>Phone</th>
                        <?php if ($user_role === 'super_admin'): ?>
                            <th style="width: 150px;">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($branches && $branches->num_rows > 0): ?>
                        <?php while($row = $branches->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['address'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($row['phone'] ?? 'N/A'); ?></td>
                                <?php if ($user_role === 'super_admin'): ?>
                                    <td>
                                        <div class="action-links">
                                            <a href="branches.php?action=edit&id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm" title="Edit">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>
                                            <a href="branches.php?action=delete&id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirmDelete('Are you sure you want to delete this branch?')" title="Delete">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo $user_role === 'super_admin' ? '5' : '4'; ?>" style="text-align: center; padding: 24px; color: #64748b;">No branches found.</td>
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
