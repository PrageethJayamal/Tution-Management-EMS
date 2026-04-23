<?php
require_once '../config/db.php';
require_once '../includes/header.php';

if ($_SESSION['role'] !== 'faculty') die("<div class='alert alert-error'>Access Denied</div>");

$message = ''; $error = '';
$user_id = $_SESSION['user_id'];
$faculty_id = $pdo->query("SELECT id FROM faculty WHERE user_id = $user_id")->fetchColumn();
$classes = $pdo->query("SELECT * FROM classes WHERE faculty_id = $faculty_id")->fetchAll();

$selected_class = $_GET['class_id'] ?? '';
$selected_date = $_GET['date'] ?? date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_attendance') {
    $class_id = $_POST['class_id'];
    $date = $_POST['date'];
    $attendance_data = $_POST['status'] ?? [];

    try {
        $pdo->beginTransaction();
        foreach ($attendance_data as $student_id => $status) {
            $stmt = $pdo->prepare("INSERT INTO attendance (student_id, class_id, attendance_date, status) VALUES (?, ?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE status = VALUES(status)");
            $stmt->execute([$student_id, $class_id, $date, $status]);
        }
        $pdo->commit();
        $message = "Attendance saved successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to save: " . $e->getMessage();
    }
}

$students = [];
if ($selected_class) {
    $stmt = $pdo->prepare("SELECT s.id, s.first_name, s.last_name, s.roll_no, a.status 
                           FROM students s 
                           LEFT JOIN attendance a ON s.id = a.student_id AND a.attendance_date = ? 
                           WHERE s.class_id = ? ORDER BY s.roll_no ASC");
    $stmt->execute([$selected_date, $selected_class]);
    $students = $stmt->fetchAll();
}
?>

<div class="card">
    <div class="card-header">Manage Attendance</div>
    <?php if ($message): ?> <div class="alert alert-success"><?php echo $message; ?></div> <?php endif; ?>
    <?php if ($error): ?> <div class="alert alert-error"><?php echo $error; ?></div> <?php endif; ?>
    
    <form method="GET" action="" class="flex gap-2 items-center mb-4" style="gap: 15px; margin-bottom: 20px;">
        <div>
            <label style="font-size: 14px; font-weight: 500;">Class:</label>
            <select name="class_id" class="form-control" onchange="this.form.submit()" required>
                <option value="">-- Select Class --</option>
                <?php foreach($classes as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo $selected_class == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size: 14px; font-weight: 500;">Date:</label>
            <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($selected_date); ?>" onchange="this.form.submit()" required>
        </div>
    </form>

    <?php if ($selected_class && $students): ?>
        <form method="POST" action="">
            <input type="hidden" name="action" value="save_attendance">
            <input type="hidden" name="class_id" value="<?php echo htmlspecialchars($selected_class); ?>">
            <input type="hidden" name="date" value="<?php echo htmlspecialchars($selected_date); ?>">
            
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Roll No</th><th>Name</th><th>Attendance Status</th></tr></thead>
                    <tbody>
                        <?php foreach($students as $s): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($s['roll_no']); ?></td>
                            <td><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?></td>
                            <td>
                                <select name="status[<?php echo $s['id']; ?>]" class="form-control">
                                    <option value="present" <?php echo $s['status'] == 'present' ? 'selected' : ''; ?>>Present</option>
                                    <option value="absent" <?php echo $s['status'] == 'absent' ? 'selected' : ''; ?>>Absent</option>
                                    <option value="late" <?php echo $s['status'] == 'late' ? 'selected' : ''; ?>>Late</option>
                                </select>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="submit" class="btn btn-primary mt-4">Save Attendance</button>
        </form>
    <?php elseif ($selected_class): ?>
        <div class="alert alert-success">No students found in this class.</div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
