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

$grades = [];
if ($selected_child) {
    $stmt = $pdo->prepare("SELECT g.subject, g.marks, g.term, c.name as class_name 
                           FROM grades g 
                           JOIN classes c ON g.class_id = c.id 
                           WHERE g.student_id = ? 
                           ORDER BY g.term ASC, g.subject ASC");
    $stmt->execute([$selected_child]);
    $grades = $stmt->fetchAll();
}
?>

<div class="card">
    <div class="card-header">Child Grades</div>
    
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
    <?php else: ?>
        <div class="alert alert-error">No children linked to your account.</div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
