<?php
require_once '../config/db.php';
require_once '../includes/header.php';

if ($_SESSION['role'] !== 'student') die("<div class='alert alert-error'>Access Denied</div>");

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
$stmt->execute([$user_id]);
$student_id = $stmt->fetchColumn();

$attendance = $pdo->query("SELECT a.attendance_date, a.status, c.name as class_name 
                           FROM attendance a 
                           JOIN classes c ON a.class_id = c.id 
                           WHERE a.student_id = $student_id 
                           ORDER BY a.attendance_date DESC")->fetchAll();
?>

<div class="card">
    <div class="card-header">My Attendance</div>
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
</div>

<?php require_once '../includes/footer.php'; ?>
