<?php
require_once '../config/db.php';
require_once '../includes/header.php';

if ($_SESSION['role'] !== 'student') die("<div class='alert alert-error'>Access Denied</div>");

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
$stmt->execute([$user_id]);
$student_id = $stmt->fetchColumn();

$grades = $pdo->query("SELECT g.subject, g.marks, g.term, c.name as class_name 
                       FROM grades g 
                       JOIN classes c ON g.class_id = c.id 
                       WHERE g.student_id = $student_id 
                       ORDER BY g.term ASC, g.subject ASC")->fetchAll();
?>

<div class="card">
    <div class="card-header">My Grades</div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Term</th><th>Class</th><th>Subject</th><th>Marks</th></tr></thead>
            <tbody>
                <?php foreach($grades as $g): ?>
                <tr>
                    <td><?php echo htmlspecialchars($g['term']); ?></td>
                    <td><?php echo htmlspecialchars($g['class_name']); ?></td>
                    <td><?php echo htmlspecialchars($g['subject']); ?></td>
                    <td><strong><?php echo htmlspecialchars($g['marks']); ?></strong> / 100</td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($grades)): ?><tr><td colspan="4">No grade records found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
