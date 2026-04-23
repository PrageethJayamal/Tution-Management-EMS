<?php
require_once '../config/db.php';
require_once '../includes/header.php';

if ($_SESSION['role'] !== 'superadmin') {
    die("<div class='alert alert-error'>Access Denied</div>");
}

$message = ''; $error = '';

// Handle Create Center
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $name = trim($_POST['name']);
    $username = trim($_POST['admin_username']);
    $password = $_POST['admin_password'];
    $center_code = strtoupper(substr(md5(uniqid()), 0, 6));

    try {
        $pdo->beginTransaction();
        
        $chk = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $chk->execute([$username]);
        if($chk->rowCount() > 0) throw new Exception("Admin username '$username' already exists in the system!");

        $stmt = $pdo->prepare("INSERT INTO centers (name, center_code) VALUES (?, ?)");
        $stmt->execute([$name, $center_code]);
        $center_id = $pdo->lastInsertId();

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, center_id) VALUES (?, ?, 'admin', ?)");
        $stmt->execute([$username, $hash, $center_id]);

        $pdo->commit();
        $message = "Center '$name' created successfully! Center Code: $center_code";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to add center: " . $e->getMessage();
    }
}

// Handle Update Center
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
    $id = $_POST['id'];
    $name = trim($_POST['name']);
    
    try {
        $pdo->prepare("UPDATE centers SET name = ? WHERE id = ?")->execute([$name, $id]);
        $message = "Center updated successfully!";
    } catch (Exception $e) {
        $error = "Update failed: " . $e->getMessage();
    }
}

// Handle Delete Center
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $pdo->prepare("DELETE FROM centers WHERE id = ?")->execute([$id]);
        $message = "Center systematically deleted.";
    } catch (Exception $e) { $error = "Failed to delete: " . $e->getMessage(); }
}

$edit_data = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM centers WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_data = $stmt->fetch();
}

$centers = $pdo->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM users WHERE center_id = c.id AND role = 'admin') as admin_count,
           (SELECT COUNT(*) FROM students s JOIN users u ON s.user_id = u.id WHERE u.center_id = c.id) as student_count
    FROM centers c ORDER BY c.created_at DESC
")->fetchAll();
?>

<?php if ($message): ?> <div class="alert alert-success" style="margin-bottom:20px;"><?php echo htmlspecialchars($message); ?></div> <?php endif; ?>
<?php if ($error): ?> <div class="alert alert-error" style="margin-bottom:20px;"><?php echo htmlspecialchars($error); ?></div> <?php endif; ?>

<div class="flex flex-wrap gap-24" style="gap: 24px; flex-wrap: wrap;">
    <div style="flex: 1; min-width: 300px;">
        <div class="card">
            <div class="card-header"><?php echo $edit_data ? 'Edit Center Details' : 'Provision New Center'; ?></div>
            <form method="POST" action="centers.php">
                <?php if ($edit_data): ?>
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
                    <div class="form-group">
                        <label>Assigned Center Code (Read Only)</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($edit_data['center_code']); ?>" disabled style="background:#F3F4F6;">
                    </div>
                    <div class="form-group">
                        <label>Institute Name</label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($edit_data['name']); ?>" required>
                    </div>
                <?php else: ?>
                    <input type="hidden" name="action" value="add">
                    <div class="form-group">
                        <label>Institute Name</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Excellence Academy" required>
                    </div>
                    
                    <div style="background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:15px; margin-top:25px; margin-bottom:10px;">
                        <h4 style="margin:0 0 15px; font-size:14px; color:var(--text-primary);">Create Initial Admin Account</h4>
                        <div class="form-group">
                            <label style="font-size:13px;">Username</label>
                            <input type="text" name="admin_username" class="form-control" required>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:13px;">Password</label>
                            <input type="password" name="admin_password" class="form-control" required>
                        </div>
                    </div>
                <?php endif; ?>

                <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn btn-primary mt-4" style="flex:1;"><?php echo $edit_data ? 'Save Changes' : 'Create Center'; ?></button>
                    <?php if ($edit_data): ?>
                        <a href="centers.php" class="btn btn-outline mt-4" style="text-align:center; padding: 14px 20px; text-decoration: none; border-radius: 6px; font-weight: 500;">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <div style="flex: 2; min-width: 400px;">
        <div class="card">
            <div class="card-header">Managed Centers</div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Center Name</th>
                            <th>Admins</th>
                            <th>Students</th>
                            <th>Created On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($centers as $c): ?>
                        <tr>
                            <td><span style="font-family:monospace; background:var(--bg); padding:4px 8px; border-radius:4px; font-weight:600; border:1px solid var(--border);"><?php echo htmlspecialchars($c['center_code']); ?></span></td>
                            <td><strong style="color:var(--text-primary);"><?php echo htmlspecialchars($c['name']); ?></strong></td>
                            <td><?php echo $c['admin_count']; ?></td>
                            <td><?php echo $c['student_count']; ?></td>
                            <td><?php echo date('M d, Y', strtotime($c['created_at'])); ?></td>
                            <td>
                                <a href="centers.php?edit=<?php echo $c['id']; ?>" class="btn btn-outline btn-sm" style="border:1px solid var(--border);color:var(--text-main);text-decoration:none;">Edit</a>
                                <a href="?delete=<?php echo $c['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('WARNING: Deleting this center irreversibly deletes ALL associated students, faculty, classes, and logs. Proceed?')">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($centers)): ?><tr><td colspan="6" style="text-align:center; padding:30px; color:var(--text-muted);">No centers provisioned yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
