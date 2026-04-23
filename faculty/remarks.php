<?php
require_once '../config/db.php';
require_once '../includes/header.php';

if ($_SESSION['role'] !== 'faculty') die("<div class='alert alert-error'>Access Denied</div>");

$message_success = ''; $error = '';
$user_id = $_SESSION['user_id'];

// Get faculty identity
$stmt = $pdo->prepare("SELECT id FROM faculty WHERE user_id = ?");
$stmt->execute([$user_id]);
$faculty_id = $stmt->fetchColumn();

// Get assigned classes
$stmt = $pdo->prepare("SELECT * FROM classes WHERE faculty_id = ?");
$stmt->execute([$faculty_id]);
$classes = $stmt->fetchAll();

$selected_class = $_GET['class_id'] ?? '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_remark') {
    $student_id = $_POST['student_id'];
    $remark = trim($_POST['remark']);
    $severity = $_POST['severity'];
    
    // Ensure the mapped class actually passed via session/GET remains valid
    if ($student_id && $remark && $severity) {
        try {
            $insert_stmt = $pdo->prepare("INSERT INTO remarks (student_id, faculty_id, remark, severity) VALUES (?, ?, ?, ?)");
            $insert_stmt->execute([$student_id, $faculty_id, $remark, $severity]);
            $message_success = "Disciplinary/Behavioral remark successfully submitted to parent dashboard.";
        } catch (Exception $e) {
            $error = "Failed to add remark: " . $e->getMessage();
        }
    } else {
        $error = "Please fill out all fields.";
    }
}

// Fetch students if class is actively targeted
$students = [];
$past_remarks = [];
if ($selected_class) {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, roll_no FROM students WHERE class_id = ? ORDER BY roll_no ASC");
    $stmt->execute([$selected_class]);
    $students = $stmt->fetchAll();
    
    // Fetch remark history regarding these specific students
    if (!empty($students)) {
        $student_ids = array_map(function($s) { return $s['id']; }, $students);
        $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
        
        // Push faculty_id as well so we only see remarks the teacher explicitly wrote
        $params = array_merge([$faculty_id], $student_ids);
        
        $hist_stmt = $pdo->prepare("SELECT r.*, s.first_name, s.last_name, s.roll_no 
                                    FROM remarks r 
                                    JOIN students s ON r.student_id = s.id 
                                    WHERE r.faculty_id = ? AND r.student_id IN ($placeholders) 
                                    ORDER BY r.created_at DESC");
        $hist_stmt->execute($params);
        $past_remarks = $hist_stmt->fetchAll();
    }
}
?>

<div class="card">
    <div class="card-header">Disciplinary & Behavioral Remarks</div>
    <div style="margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid var(--border);">
        <form method="GET" action="" style="display: flex; gap: 15px; align-items: flex-end;">
            <div style="flex: 1;">
                <label style="font-weight: 500; font-size: 14px; margin-bottom: 5px; display: block;">Step 1: Select Active Class</label>
                <select name="class_id" class="form-control" required onchange="this.form.submit()">
                    <option value="">-- Choose Assigned Class --</option>
                    <?php foreach($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $selected_class == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <noscript><button type="submit" class="btn btn-primary">Load</button></noscript>
            </div>
        </form>
    </div>

    <?php if ($message_success): ?> <div class="alert alert-success"><?php echo $message_success; ?></div> <?php endif; ?>
    <?php if ($error): ?> <div class="alert alert-error"><?php echo $error; ?></div> <?php endif; ?>

    <?php if ($selected_class && empty($students)): ?>
        <div class="alert alert-error">There are no students enrolled in this targeted class yet.</div>
    <?php elseif ($selected_class && !empty($students)): ?>
        
        <div style="display: flex; gap: 30px; flex-wrap: wrap;">
            <!-- Insertion Form -->
            <div style="flex: 1; min-width: 300px; background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid var(--border);">
                <h3 style="margin-top:0; font-size: 16px; color: var(--text-main);">Pin New Remark To Profile</h3>
                <form method="POST" action="remarks.php?class_id=<?php echo $selected_class; ?>">
                    <input type="hidden" name="action" value="add_remark">
                    
                    <div class="form-group">
                        <label>Student Profile</label>
                        <select name="student_id" class="form-control" required>
                            <option value="">-- Select Student --</option>
                            <?php foreach($students as $s): ?>
                                <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name'] . ' (Roll: ' . $s['roll_no'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Sentiment / Severity Tag</label>
                        <div style="display: flex; gap: 15px; margin-top: 5px;">
                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer; color: #10B981; font-weight: 500;">
                                <input type="radio" name="severity" value="positive" required> 🌟 Positive
                            </label>
                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer; color: #64748B; font-weight: 500;">
                                <input type="radio" name="severity" value="neutral" checked> 📝 Neutral
                            </label>
                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer; color: #EF4444; font-weight: 500;">
                                <input type="radio" name="severity" value="negative"> ⚠️ Negative
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Official Remark (Visible to Guardian)</label>
                        <textarea name="remark" class="form-control" rows="4" style="resize: vertical;" required placeholder="Type qualitative assessment here..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%;">Dispatch Note to Guardian Dashboard</button>
                </form>
            </div>

            <!-- History Log -->
            <div style="flex: 2; min-width: 400px;">
                <h3 style="margin-top:0; font-size: 16px; color: var(--text-main); border-bottom: 2px solid var(--border); padding-bottom: 10px;">Recent Remarks Log (This Class)</h3>
                <div style="max-height: 400px; overflow-y: auto; padding-right: 10px;">
                    <?php if (empty($past_remarks)): ?>
                        <p style="color: var(--text-muted); font-style: italic;">No specific remarks dispatched for any enrolled students in this class yet.</p>
                    <?php else: ?>
                        <?php foreach($past_remarks as $r): ?>
                            <?php 
                            // Determine boundary styling based off severity ENUM
                            $bg_col = ''; $bord_col = ''; $tag = '';
                            if ($r['severity'] == 'positive') { $bg_col = '#ecfdf5'; $bord_col = '#34d399'; $tag = '🌟 Positive'; }
                            if ($r['severity'] == 'neutral') { $bg_col = '#f8fafc'; $bord_col = '#cbd5e1'; $tag = '📝 Neutral'; }
                            if ($r['severity'] == 'negative') { $bg_col = '#fef2f2'; $bord_col = '#f87171'; $tag = '⚠️ Warning'; }
                            ?>
                            <div style="background: <?php echo $bg_col; ?>; border-left: 4px solid <?php echo $bord_col; ?>; padding: 12px 15px; border-radius: 4px; margin-bottom: 15px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                    <strong><?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name'] . ' (' . $r['roll_no'] . ')'); ?></strong>
                                    <span style="font-size: 12px; font-weight: bold; color: <?php echo $bord_col; ?>;"><?php echo $tag; ?></span>
                                </div>
                                <div style="font-size: 14px; color: var(--text-secondary); white-space: pre-wrap; margin-bottom: 8px;"><?php echo htmlspecialchars($r['remark']); ?></div>
                                <div style="font-size: 11px; color: var(--text-muted);">Dispatched: <?php echo date('F j, Y, g:i a', strtotime($r['created_at'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
