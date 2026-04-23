<?php
require_once '../config/db.php';
require_once '../includes/header.php';

if ($_SESSION['role'] !== 'admin') {
    die("<div class='alert alert-error'>Access Denied</div>");
}

$message = ''; $error = '';

// Handle Update Parent
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
    $id = $_POST['id'];
    $fname = trim($_POST['first_name']);
    $lname = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $new_password = $_POST['new_password'] ?? '';

    try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE parents SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?")
            ->execute([$fname, $lname, $email, $phone, $id]);
            
        if (!empty($new_password)) {
            $stmt = $pdo->prepare("SELECT user_id FROM parents WHERE id = ?");
            $stmt->execute([$id]);
            $uid = $stmt->fetchColumn();
            
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $uid]);
        }
        
        log_activity($pdo, $_SESSION['user_id'], 'Edited Parent', "Updated profile for Parent: $email");
        $pdo->commit();
        $message = "Parent updated successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Update failed: " . $e->getMessage();
    }
}

// Handle Create Parent
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $fname = trim($_POST['first_name']);
    $lname = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) throw new Exception("Username already exists.");

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, center_id) VALUES (?, ?, 'parent', ?)");
        $stmt->execute([$username, $hash, $_SESSION['center_id']]);
        $user_id = $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO parents (user_id, first_name, last_name, email, phone) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $fname, $lname, $email, $phone]);
        $parent_id = $pdo->lastInsertId();

        if (!empty($_POST['student_rolls'])) {
            $rolls = array_map('trim', explode(',', $_POST['student_rolls']));
            foreach ($rolls as $r) {
                if (!empty($r)) {
                    $upd = $pdo->prepare("UPDATE students SET parent_id = ? WHERE roll_no = ?");
                    $upd->execute([$parent_id, $r]);
                }
            }
        }

        $pdo->commit();
        $message = "Parent & children links added successfully!";
        log_activity($pdo, $_SESSION['user_id'], 'Added Parent', "Created parent account for $email");
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to add parent: " . $e->getMessage();
    }
}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("SELECT user_id FROM parents WHERE id = ?");
        $stmt->execute([$id]);
        $uid = $stmt->fetchColumn();
        if ($uid) {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
            $message = "Parent deleted successfully.";
            log_activity($pdo, $_SESSION['user_id'], 'Deleted Parent', "Deleted parent record ID: $id");
        }
    } catch (Exception $e) { $error = "Failed to delete: " . $e->getMessage(); }
}

$edit_data = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT p.*, u.username FROM parents p JOIN users u ON p.user_id = u.id WHERE p.id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_data = $stmt->fetch();
}

$c_id = $_SESSION['center_id'];
$stmt = $pdo->prepare("SELECT p.*, u.username FROM parents p JOIN users u ON p.user_id = u.id WHERE u.center_id = ? ORDER BY p.id DESC");
$stmt->execute([$c_id]); $parents = $stmt->fetchAll();
?>

<?php if ($message): ?> <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div> <?php endif; ?>
<?php if ($error): ?> <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div> <?php endif; ?>

<div class="flex flex-wrap gap-24" style="gap: 24px; flex-wrap: wrap;">
    <div style="flex: 1; min-width: 300px;">
        <div class="card">
            <div class="card-header"><?php echo $edit_data ? 'Edit Parent' : 'Add New Parent'; ?></div>
            <form method="POST" action="parents.php">
                <?php if ($edit_data): ?>
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
                    <div class="form-group">
                        <label>System Username (Read Only)</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($edit_data['username']); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Reset Password (leave blank to keep current)</label>
                        <input type="text" name="new_password" class="form-control" placeholder="Type new password">
                    </div>
                <?php else: ?>
                    <input type="hidden" name="action" value="add">
                    <div class="form-group"><label>Username</label><input type="text" name="username" class="form-control" required></div>
                    <div class="form-group"><label>Password</label><input type="password" name="password" class="form-control" required></div>
                <?php endif; ?>

                <div class="flex gap-2" style="gap: 15px;">
                    <div class="form-group" style="flex: 1;"><label>First Name</label><input type="text" name="first_name" class="form-control" value="<?php echo $edit_data ? htmlspecialchars($edit_data['first_name']) : ''; ?>" required></div>
                    <div class="form-group" style="flex: 1;"><label>Last Name</label><input type="text" name="last_name" class="form-control" value="<?php echo $edit_data ? htmlspecialchars($edit_data['last_name']) : ''; ?>" required></div>
                </div>
                <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" value="<?php echo $edit_data ? htmlspecialchars($edit_data['email']) : ''; ?>" required></div>
                <div class="form-group"><label>Phone</label><input type="text" name="phone" class="form-control" value="<?php echo $edit_data ? htmlspecialchars($edit_data['phone']) : ''; ?>"></div>
                
                <?php if (!$edit_data): ?>
                <div class="form-group">
                    <label>Link Children <small style="color:var(--text-muted);font-weight:normal;">(Roll Numbers starting with STU, comma-separated)</small></label>
                    <input type="text" name="student_rolls" class="form-control" placeholder="e.g. STU101, STU102">
                </div>
                <?php endif; ?>
                
                <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn btn-primary mt-4"><?php echo $edit_data ? 'Save Changes' : 'Add Parent'; ?></button>
                    <?php if ($edit_data): ?>
                        <a href="parents.php" class="btn btn-outline mt-4" style="border:1px solid var(--border); padding: 12px 20px; text-decoration: none; border-radius: 8px; font-weight: 600; color: var(--text-main);">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <div style="flex: 2; min-width: 400px;">
        <div class="card">
            <div class="card-header">Manage Parents</div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Phone</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach($parents as $p): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($p['username']); ?></td>
                            <td><?php echo htmlspecialchars($p['email']); ?></td>
                            <td><?php echo htmlspecialchars($p['phone']); ?></td>
                            <td>
                                <a href="parents.php?edit=<?php echo $p['id']; ?>" class="btn btn-outline btn-sm" style="border:1px solid var(--border);color:var(--text-main);text-decoration:none;">Edit</a>
                                <a href="?delete=<?php echo $p['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($parents)): ?><tr><td colspan="5">No parents found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
