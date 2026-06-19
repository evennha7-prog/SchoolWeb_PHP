<?php
require_once 'header.php';

$msg = "";
$err = "";

// Only Super Admin can perform CRUD actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_role === 'super_admin') {
    if (isset($_POST['add_level'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);

        if (empty($name)) {
            $err = "Level name is required.";
        } else {
            $stmt = $conn->prepare("INSERT INTO levels (name, description) VALUES (?, ?)");
            $stmt->bind_param("ss", $name, $description);
            if ($stmt->execute()) {
                $msg = "Academic level added successfully.";
            } else {
                $err = "Failed to add level: " . $conn->error;
            }
            $stmt->close();
        }
    } elseif (isset($_POST['edit_level'])) {
        $id = intval($_POST['id']);
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);

        if (empty($name)) {
            $err = "Level name is required.";
        } else {
            $stmt = $conn->prepare("UPDATE levels SET name = ?, description = ? WHERE id = ?");
            $stmt->bind_param("ssi", $name, $description, $id);
            if ($stmt->execute()) {
                $msg = "Academic level updated successfully.";
            } else {
                $err = "Failed to update level: " . $conn->error;
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
        $stmt = $conn->prepare("DELETE FROM levels WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $msg = "Academic level deleted successfully.";
        } else {
            $err = "Failed to delete level: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch edit target
$edit_level = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id']) && $user_role === 'super_admin') {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM levels WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_level = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Fetch all levels
$levels = $conn->query("SELECT * FROM levels ORDER BY id DESC");
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
<?php if ($user_role === 'super_admin'): ?>
    <div style="margin-bottom: 24px;">
        <button class="btn btn-primary" onclick="toggleForm('levelForm')">
            <i class="fa-solid <?php echo $edit_level ? 'fa-pen-to-square' : 'fa-plus'; ?>"></i> 
            <?php echo $edit_level ? 'Edit Level: ' . htmlspecialchars($edit_level['name']) : 'Add New Level'; ?>
        </button>
    </div>

    <div id="levelForm" class="card form-collapse <?php echo ($edit_level || !empty($err)) ? 'show' : ''; ?>">
        <div class="card-header">
            <h2 class="card-title"><?php echo $edit_level ? 'Edit Level Details' : 'Enter Level Details'; ?></h2>
        </div>
        <div class="card-body">
            <form action="levels.php" method="POST">
                <?php if ($edit_level): ?>
                    <input type="hidden" name="id" value="<?php echo $edit_level['id']; ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="name">Level Name *</label>
                    <input type="text" id="name" name="name" class="form-control" placeholder="e.g. Grade 10" value="<?php echo $edit_level ? htmlspecialchars($edit_level['name']) : ''; ?>" required>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" placeholder="Short description of the level..."><?php echo $edit_level ? htmlspecialchars($edit_level['description']) : ''; ?></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" name="<?php echo $edit_level ? 'edit_level' : 'add_level'; ?>" class="btn btn-success">
                        <i class="fa-solid fa-floppy-disk"></i> Save Level
                    </button>
                    <?php if ($edit_level): ?>
                        <a href="levels.php" class="btn btn-secondary">Cancel</a>
                    <?php else: ?>
                        <button type="button" class="btn btn-secondary" onclick="toggleForm('levelForm')">Cancel</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Data Table Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fa-solid fa-graduation-cap" style="color:var(--primary); margin-right:8px;"></i> Registered Academic Levels</h2>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Level Name</th>
                        <th>Description</th>
                        <?php if ($user_role === 'super_admin'): ?>
                            <th style="width: 150px;">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($levels && $levels->num_rows > 0): ?>
                        <?php while($row = $levels->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><span class="badge badge-success" style="font-size:0.85rem; padding: 6px 12px;"><?php echo htmlspecialchars($row['name']); ?></span></td>
                                <td><?php echo htmlspecialchars($row['description'] ?? 'N/A'); ?></td>
                                <?php if ($user_role === 'super_admin'): ?>
                                    <td>
                                        <div class="action-links">
                                            <a href="levels.php?action=edit&id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm" title="Edit">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>
                                            <a href="levels.php?action=delete&id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirmDelete('Are you sure you want to delete this level?')" title="Delete">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo $user_role === 'super_admin' ? '4' : '3'; ?>" style="text-align: center; padding: 24px; color: #64748b;">No levels found.</td>
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
