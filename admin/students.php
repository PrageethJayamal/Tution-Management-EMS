<?php
require_once '../config/db.php';
require_once '../includes/header.php';

if ($_SESSION['role'] !== 'admin') die("<div class='alert alert-error'>Access Denied</div>");

if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=students_export_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Roll Number', 'First Name', 'Last Name', 'System Username', 'Class', 'Parent']);
    $stmt = $pdo->prepare("SELECT s.*, u.username, c.name as class_name, p.first_name as p_fname, p.last_name as p_lname FROM students s JOIN users u ON s.user_id = u.id LEFT JOIN classes c ON s.class_id = c.id LEFT JOIN parents p ON s.parent_id = p.id WHERE u.center_id = ? ORDER BY s.roll_no ASC");
    $stmt->execute([$_SESSION['center_id']]);
    $stData = $stmt->fetchAll();
    foreach ($stData as $row) {
        fputcsv($output, [$row['id'],$row['roll_no'],$row['first_name'],$row['last_name'],$row['username'],$row['class_name'] ?? 'Unassigned',$row['p_fname'] ? ($row['p_fname'] . ' ' . $row['p_lname']) : 'N/A']);
    }
    fclose($output);
    log_activity($pdo, $_SESSION['user_id'], 'Exported Students Data', "Exported " . count($stData) . " student records to CSV.");
    exit;
}

$message = ''; $error = '';

// Handle Update Student
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
    $id = $_POST['id'];
    $fname = trim($_POST['first_name']);
    $lname = trim($_POST['last_name']);
    $roll_no = strtoupper(trim($_POST['roll_no']));
    $class_id = $_POST['class_id'] ? $_POST['class_id'] : null;
    $parent_id = $_POST['parent_id'] ? $_POST['parent_id'] : null;
    $payment_status = $_POST['payment_status'] ?? 'unpaid';
    $new_password = $_POST['new_password'] ?? '';

    // IDOR Mitigation Pipeline
    $auth_check = $pdo->prepare("SELECT u.center_id FROM students s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
    $auth_check->execute([$id]);
    if ($auth_check->fetchColumn() != $_SESSION['center_id']) {
        die("<div style='font-family: sans-serif; padding: 20px; background: #fef2f2; color: #991b1b; border: 1px solid #f87171; border-radius: 8px;'><strong>Security Fault (IDOR):</strong> Unauthorized Data Manipulation Blocked. Cross-tenant destruction array terminated.</div>");
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE students SET first_name = ?, last_name = ?, roll_no = ?, class_id = ?, parent_id = ?, payment_status = ? WHERE id = ?")
            ->execute([$fname, $lname, $roll_no, $class_id, $parent_id, $payment_status, $id]);
            
        if (!empty($new_password)) {
            $stmt = $pdo->prepare("SELECT user_id FROM students WHERE id = ?");
            $stmt->execute([$id]);
            $uid = $stmt->fetchColumn();
            
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $uid]);
        }
        
        log_activity($pdo, $_SESSION['user_id'], 'Edited Student', "Updated profile for Roll No: $roll_no");
        $pdo->commit();
        $message = "Student updated successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Update failed: " . $e->getMessage();
    }
}

// Handle Create Student
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $fname = trim($_POST['first_name']);
    $lname = trim($_POST['last_name']);
    $roll_no = strtoupper(trim($_POST['roll_no']));
    $class_id = $_POST['class_id'] ? $_POST['class_id'] : null;
    $parent_id = $_POST['parent_id'] ? $_POST['parent_id'] : null;
    $payment_status = $_POST['payment_status'] ?? 'unpaid';

    try {
        $pdo->beginTransaction();
        if (strpos($roll_no, 'STU') !== 0) throw new Exception("Roll number must start with 'STU'.");
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) throw new Exception("Username already exists.");

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, center_id) VALUES (?, ?, 'student', ?)");
        $stmt->execute([$username, $hash, $_SESSION['center_id']]);
        $user_id = $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO students (user_id, first_name, last_name, roll_no, class_id, parent_id, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $fname, $lname, $roll_no, $class_id, $parent_id, $payment_status]);

        $pdo->commit();
        $message = "Student added successfully!";
        log_activity($pdo, $_SESSION['user_id'], 'Added Student', "Created student record for Roll No: $roll_no");
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to add student: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'import_csv') {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, "r");
        $count = 0;
        
        $first_row = fgetcsv($handle);
        if ($first_row && strtolower($first_row[0]) !== 'first name') {
            rewind($handle);
        }
        
        try {
            $pdo->beginTransaction();
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (count($data) < 3) continue;
                $fname = trim($data[0]);
                $lname = trim($data[1]);
                $roll_no = strtoupper(trim($data[2]));
                $class_id = (isset($data[3]) && is_numeric($data[3])) ? $data[3] : null;
                $parent_id = (isset($data[4]) && is_numeric($data[4])) ? $data[4] : null;
                
                if (strpos($roll_no, 'STU') !== 0) continue;
                
                $username = strtolower($fname . '.' . $lname . rand(10,99));
                $hash = password_hash('welcome123', PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("INSERT INTO users (username, password, role, center_id) VALUES (?, ?, 'student', ?)");
                $stmt->execute([$username, $hash, $_SESSION['center_id']]);
                $user_id = $pdo->lastInsertId();
                
                $stmt = $pdo->prepare("INSERT INTO students (user_id, first_name, last_name, roll_no, class_id, parent_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $fname, $lname, $roll_no, $class_id, $parent_id]);
                
                $count++;
            }
            $pdo->commit();
            $message = "Successfully imported $count students via CSV.";
            log_activity($pdo, $_SESSION['user_id'], 'Imported Students', "Bulk imported $count student records via CSV.");
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "CSV Import failed: " . $e->getMessage();
        }
        fclose($handle);
    } else {
        $error = "Error uploading CSV file.";
    }
}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("SELECT user_id FROM students WHERE id = ?");
        $stmt->execute([$id]);
        $uid = $stmt->fetchColumn();
        if ($uid) {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
            $message = "Student deleted successfully.";
            log_activity($pdo, $_SESSION['user_id'], 'Deleted Student', "Deleted student record ID: $id");
        }
    } catch (Exception $e) { $error = "Failed to delete: " . $e->getMessage(); }
}

$edit_data = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT s.*, u.username FROM students s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_data = $stmt->fetch();
}

$c_id = $_SESSION['center_id'];
$stmt = $pdo->prepare("SELECT s.*, u.username, c.name as class_name, p.first_name as p_fname, p.last_name as p_lname FROM students s JOIN users u ON s.user_id = u.id LEFT JOIN classes c ON s.class_id = c.id LEFT JOIN parents p ON s.parent_id = p.id WHERE u.center_id = ? ORDER BY s.id DESC");
$stmt->execute([$c_id]); $students = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT id, name FROM classes WHERE center_id = ? ORDER BY name ASC");
$stmt->execute([$c_id]); $classes = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT p.id, p.first_name, p.last_name FROM parents p JOIN users u ON p.user_id = u.id WHERE u.center_id = ? ORDER BY p.first_name ASC");
$stmt->execute([$c_id]); $parents = $stmt->fetchAll();
?>

<?php if ($message): ?> <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div> <?php endif; ?>
<?php if ($error): ?> <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div> <?php endif; ?>

<div class="flex flex-wrap gap-24" style="gap: 24px; flex-wrap: wrap;">
    <div style="flex: 1; min-width: 300px;">
        <div class="card">
            <div class="card-header"><?php echo $edit_data ? 'Edit Student' : 'Add New Student'; ?></div>
            <form method="POST" action="students.php">
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
                <div class="form-group"><label>Roll Number <small style="color:var(--text-muted);font-weight:normal;">(Starts with STU)</small></label><input type="text" name="roll_no" class="form-control" placeholder="STU001" pattern="^STU.*" title="Must start with STU" value="<?php echo $edit_data ? htmlspecialchars($edit_data['roll_no']) : ''; ?>" required></div>
                
                <div class="form-group">
                    <label>Assign Class</label>
                    <select name="class_id" class="form-control" <?php echo !$edit_data ? 'required' : ''; ?>>
                        <option value="">-- Select Class --</option>
                        <?php foreach($classes as $cls): ?>
                            <option value="<?php echo $cls['id']; ?>" <?php echo ($edit_data && $edit_data['class_id'] == $cls['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cls['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Assign Parent</label>
                    <select name="parent_id" class="form-control">
                        <option value="">-- None --</option>
                        <?php foreach($parents as $par): ?>
                            <option value="<?php echo $par['id']; ?>" <?php echo ($edit_data && $edit_data['parent_id'] == $par['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($par['first_name'] . ' ' . $par['last_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Payment Status</label>
                    <select name="payment_status" class="form-control">
                        <option value="unpaid" <?php echo ($edit_data && isset($edit_data['payment_status']) && $edit_data['payment_status'] == 'unpaid') ? 'selected' : ''; ?>>Unpaid</option>
                        <option value="paid" <?php echo ($edit_data && isset($edit_data['payment_status']) && $edit_data['payment_status'] == 'paid') ? 'selected' : ''; ?>>Paid</option>
                    </select>
                </div>
                
                <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn btn-primary mt-4"><?php echo $edit_data ? 'Save Changes' : 'Add Student'; ?></button>
                    <?php if ($edit_data): ?>
                        <a href="students.php" class="btn btn-outline mt-4" style="border:1px solid var(--border); padding: 12px 20px; text-decoration: none; border-radius: 8px; font-weight: 600; color: var(--text-main);">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <div style="flex: 2; min-width: 400px;">
        <div class="card">
            <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                Manage Students
                <div style="display:flex;gap:10px;">
                    <a href="?export=csv" class="btn btn-outline" style="font-size:12px;padding:6px 12px;border:1px solid var(--border);color:var(--text-main);text-decoration:none;">Export CSV</a>
                    <button type="button" class="btn btn-outline" onclick="document.getElementById('importForm').classList.toggle('hidden')" style="font-size:12px;padding:6px 12px;border:1px solid var(--border);background:transparent;color:var(--text-main);cursor:pointer;">Import CSV</button>
                </div>
            </div>
            
            <div id="importForm" class="hidden" style="margin-bottom:20px; padding:15px; background:var(--bg); border-radius:8px; border: 1px solid var(--border);">
                <form method="POST" action="" enctype="multipart/form-data" class="flex gap-2 items-center">
                    <input type="hidden" name="action" value="import_csv">
                    <input type="file" name="csv_file" accept=".csv" required style="font-size:13px; flex-grow:1;">
                    <button type="submit" class="btn btn-primary btn-sm">Upload & Import</button>
                </form>
                <small style="color:var(--text-muted);display:block;margin-top:8px;">Expected CSV Format: First Name, Last Name, Roll No, Class ID (optional), Parent ID (optional)</small>
            </div>

            <div class="table-responsive">
                <table>
                    <thead><tr><th>Roll No</th><th>Name</th><th>Username</th><th>Class</th><th>Parent</th><th>Payment</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach($students as $s): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($s['roll_no']); ?></td>
                            <td><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($s['username']); ?></td>
                            <td><?php echo htmlspecialchars($s['class_name'] ?? ''); ?></td>
                            <td><?php echo $s['p_fname'] ? htmlspecialchars($s['p_fname'] . ' ' . $s['p_lname']) : '<i>N/A</i>'; ?></td>
                            <td>
                                <?php if (isset($s['payment_status']) && $s['payment_status'] == 'paid'): ?>
                                    <span style="color:var(--success);font-weight:bold;">Paid</span>
                                <?php else: ?>
                                    <span style="color:var(--error);font-weight:bold;">Unpaid</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="students.php?edit=<?php echo $s['id']; ?>" class="btn btn-outline btn-sm" style="border:1px solid var(--border);color:var(--text-main);text-decoration:none;">Edit</a>
                                <a href="?delete=<?php echo $s['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($students)): ?><tr><td colspan="6">No students found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
