<?php
require_once '../config/db.php';
require_once '../includes/header.php';

if ($_SESSION['role'] !== 'faculty') die("<div class='alert alert-error'>Access Denied</div>");

$message = ''; $error = '';
$user_id = $_SESSION['user_id'];
$faculty_id = $pdo->query("SELECT id FROM faculty WHERE user_id = $user_id")->fetchColumn();
$classes = $pdo->query("SELECT * FROM classes WHERE faculty_id = $faculty_id")->fetchAll();

$selected_class = $_GET['class_id'] ?? '';
$subject = $_GET['subject'] ?? '';
$term = $_GET['term'] ?? 'Mid-Term';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_grades') {
    $class_id = $_POST['class_id'];
    $sub = $_POST['subject'];
    $trm = $_POST['term'];
    $grades_data = $_POST['marks'] ?? [];

    try {
        $pdo->beginTransaction();
        foreach ($grades_data as $student_id => $marks) {
            if ($marks === '') continue; 
            
            $stmt = $pdo->prepare("SELECT id FROM grades WHERE student_id = ? AND class_id = ? AND subject = ? AND term = ?");
            $stmt->execute([$student_id, $class_id, $sub, $trm]);
            if ($stmt->rowCount() > 0) {
                $stmt2 = $pdo->prepare("UPDATE grades SET marks = ? WHERE student_id = ? AND class_id = ? AND subject = ? AND term = ?");
                $stmt2->execute([$marks, $student_id, $class_id, $sub, $trm]);
            } else {
                $stmt2 = $pdo->prepare("INSERT INTO grades (student_id, class_id, subject, marks, term) VALUES (?, ?, ?, ?, ?)");
                $stmt2->execute([$student_id, $class_id, $sub, $marks, $trm]);
            }
        }
        $pdo->commit();
        $message = "Grades saved successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to save: " . $e->getMessage();
    }
}

$students = [];
if ($selected_class && $subject && $term) {
    $stmt = $pdo->prepare("SELECT s.id, s.first_name, s.last_name, s.roll_no, g.marks 
                           FROM students s 
                           LEFT JOIN grades g ON s.id = g.student_id AND g.subject = ? AND g.term = ? 
                           WHERE s.class_id = ? ORDER BY s.roll_no ASC");
    $stmt->execute([$subject, $term, $selected_class]);
    $students = $stmt->fetchAll();
}
?>

<div class="card">
    <div class="card-header">Manage Grades</div>
    <?php if ($message): ?> <div class="alert alert-success"><?php echo $message; ?></div> <?php endif; ?>
    <?php if ($error): ?> <div class="alert alert-error"><?php echo $error; ?></div> <?php endif; ?>
    
    <form method="GET" action="" class="flex gap-2 items-center mb-4" style="gap: 15px; margin-bottom: 20px; flex-wrap: wrap;">
        <div>
            <label style="font-size: 14px; font-weight: 500;">Class:</label>
            <select name="class_id" class="form-control" required>
                <option value="">-- Select Class --</option>
                <?php foreach($classes as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo $selected_class == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size: 14px; font-weight: 500;">Subject:</label>
            <input type="text" name="subject" class="form-control" value="<?php echo htmlspecialchars($subject); ?>" required placeholder="e.g. Mathematics">
        </div>
        <div>
            <label style="font-size: 14px; font-weight: 500;">Term:</label>
            <input type="text" name="term" class="form-control" value="<?php echo htmlspecialchars($term); ?>" required placeholder="e.g. Final">
        </div>
        <div style="align-self: flex-end;">
            <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">Load Students</button>
        </div>
    </form>

    <?php if ($selected_class && $subject && $term && $students): ?>
        <form method="POST" action="">
            <input type="hidden" name="action" value="save_grades">
            <input type="hidden" name="class_id" value="<?php echo htmlspecialchars($selected_class); ?>">
            <input type="hidden" name="subject" value="<?php echo htmlspecialchars($subject); ?>">
            <input type="hidden" name="term" value="<?php echo htmlspecialchars($term); ?>">
            
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Roll No</th><th>Name</th><th>Marks (out of 100)</th></tr></thead>
                    <tbody>
                        <?php foreach($students as $s): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($s['roll_no']); ?></td>
                            <td><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?></td>
                            <td>
                                <input type="number" step="0.01" max="100" min="0" name="marks[<?php echo $s['id']; ?>]" class="form-control" value="<?php echo htmlspecialchars($s['marks'] ?? ''); ?>">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="submit" class="btn btn-primary mt-4">Save Grades</button>
        </form>
    <?php elseif ($selected_class && $subject && $term): ?>
        <div class="alert alert-success">No students found in this class.</div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
