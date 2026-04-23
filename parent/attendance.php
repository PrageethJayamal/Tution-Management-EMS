<?php
require_once '../config/db.php';
require_once '../includes/header.php';

if ($_SESSION['role'] !== 'parent') die("<div class='alert alert-error'>Access Denied</div>");

$user_id = $_SESSION['user_id'];
$parent_id = $pdo->query("SELECT id FROM parents WHERE user_id = $user_id")->fetchColumn();
$children = [];
if ($parent_id) {
    $children = $pdo->query("SELECT id, first_name, last_name FROM students WHERE parent_id = $parent_id")->fetchAll();
}

$selected_child = $_GET['student_id'] ?? ($children[0]['id'] ?? '');

$attendance = [];
if ($selected_child) {
    $stmt = $pdo->prepare("SELECT a.attendance_date, a.status, c.name as class_name 
                           FROM attendance a 
                           JOIN classes c ON a.class_id = c.id 
                           WHERE a.student_id = ? 
                           ORDER BY a.attendance_date DESC");
    $stmt->execute([$selected_child]);
    $attendance = $stmt->fetchAll();
}
?>

<div class="card">
    <div class="card-header">Child Attendance</div>
    
    <?php if ($children): ?>
        <form method="GET" action="" class="mb-4">
            <label style="font-size: 14px; font-weight: 500;">Select Child:</label>
            <select name="student_id" class="form-control" style="width: auto; display: inline-block; margin-left: 10px;" onchange="this.form.submit()">
                <?php foreach($children as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo $selected_child == $c['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <div class="table-responsive">
            <table>
                <thead><tr><th>Date</th><th>Class</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach($attendance as $a): ?>
                    <tr>
                        <td><?php echo date('d M Y', strtotime($a['attendance_date'])); ?></td>
                        <td><?php echo htmlspecialchars($a['class_name']); ?></td>
                        <td>
                            <?php 
                            $badge = $a['status'] == 'present' ? 'background: #10B981; color: white;' : ($a['status'] == 'absent' ? 'background: #EF4444; color: white;' : 'background: #F59E0B; color: white;');
                            echo "<span style='padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; $badge'>" . ucfirst($a['status']) . "</span>";
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($attendance)): ?><tr><td colspan="3">No attendance records found.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-error">No children linked to your account.</div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
