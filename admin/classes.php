<?php
require_once '../config/db.php';
require_once '../includes/header.php';

if ($_SESSION['role'] !== 'admin') die("<div class='alert alert-error'>Access Denied</div>");

if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=timetable_export_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Class Name', 'Assigned Teacher', 'Scheduled Day', 'Start Time', 'End Time']);
    
    $stmt = $pdo->prepare("SELECT c.*, f.first_name, f.last_name FROM classes c LEFT JOIN faculty f ON c.faculty_id = f.id WHERE c.center_id = ? ORDER BY c.name ASC");
    $stmt->execute([$_SESSION['center_id']]);
    $clsData = $stmt->fetchAll();
    foreach ($clsData as $row) {
        $teacher = $row['first_name'] ? ($row['first_name'] . ' ' . $row['last_name']) : 'Unassigned';
        $st = $row['start_time'] ? date('g:i A', strtotime($row['start_time'])) : 'N/A';
        $et = $row['end_time'] ? date('g:i A', strtotime($row['end_time'])) : 'N/A';
        fputcsv($output, [$row['name'], $teacher, $row['day_of_week'] ?? 'Unscheduled', $st, $et]);
    }
    fclose($output);
    exit;
}

$message = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['add', 'edit'])) {
    $name = trim($_POST['name']);
    $faculty_id = $_POST['faculty_id'] ? $_POST['faculty_id'] : null;
    $day_of_week = $_POST['day_of_week'] ?: null;
    $start_time = $_POST['start_time'] ?: null;
    $end_time = $_POST['end_time'] ?: null;
    $action = $_POST['action'];

    $clash = false;
    
    // Clash Detection Algorithm
    if ($faculty_id && $day_of_week && $start_time && $end_time) {
        $check_stmt = $pdo->prepare("SELECT id, name, start_time, end_time FROM classes WHERE faculty_id = ? AND day_of_week = ? AND center_id = ?");
        $check_stmt->execute([$faculty_id, $day_of_week, $_SESSION['center_id']]);
        $existing_classes = $check_stmt->fetchAll();
        
        $new_st = strtotime($start_time);
        $new_et = strtotime($end_time);
        
        foreach ($existing_classes as $ec) {
            // Ignore self-clash during editing phase
            if ($action == 'edit' && isset($_POST['class_id']) && $_POST['class_id'] == $ec['id']) continue;
            
            $ex_st = strtotime($ec['start_time']);
            $ex_et = strtotime($ec['end_time']);
            
            // Overlap Math Logic: (StartA < EndB) and (EndA > StartB)
            if ($new_st < $ex_et && $new_et > $ex_st) {
                $clash = true;
                $error = "Assignment Failed: This teacher is already scheduled for '" . htmlspecialchars($ec['name']) . "' from " . date('g:i A', $ex_st) . " to " . date('g:i A', $ex_et) . " on " . htmlspecialchars($day_of_week) . ".";
                break;
            }
        }
    }

    if (!$clash) {
        try {
            if ($action == 'add') {
                $stmt = $pdo->prepare("INSERT INTO classes (name, faculty_id, center_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $faculty_id, $_SESSION['center_id'], $day_of_week, $start_time, $end_time]);
                $message = "Class added successfully!";
            } else {
                $class_id = $_POST['class_id'];
                $stmt = $pdo->prepare("UPDATE classes SET name = ?, faculty_id = ?, day_of_week = ?, start_time = ?, end_time = ? WHERE id = ? AND center_id = ?");
                $stmt->execute([$name, $faculty_id, $day_of_week, $start_time, $end_time, $class_id, $_SESSION['center_id']]);
                $message = "Class schedule safely updated and verified!";
            }
        } catch (Exception $e) { $error = "Failed to process class: " . $e->getMessage(); }
    }
}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $pdo->prepare("DELETE FROM classes WHERE id = ? AND center_id = ?")->execute([$id, $_SESSION['center_id']]);
        $message = "Class deleted successfully.";
    } catch (Exception $e) { $error = "Failed to delete: " . $e->getMessage(); }
}

$c_id = $_SESSION['center_id'];

// Check if UI is in Edit mode
$edit_class = null;
if (isset($_GET['edit'])) {
    $e_stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ? AND center_id = ?");
    $e_stmt->execute([$_GET['edit'], $c_id]);
    $edit_class = $e_stmt->fetch();
}

$stmt = $pdo->prepare("SELECT c.*, f.first_name, f.last_name FROM classes c LEFT JOIN faculty f ON c.faculty_id = f.id WHERE c.center_id = ? ORDER BY c.name ASC");
$stmt->execute([$c_id]); $classes = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT f.id, f.first_name, f.last_name FROM faculty f JOIN users u ON f.user_id = u.id WHERE u.center_id = ? ORDER BY f.first_name ASC");
$stmt->execute([$c_id]); $faculties = $stmt->fetchAll();
?>

<?php if ($message): ?> <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div> <?php endif; ?>
<?php if ($error): ?> <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div> <?php endif; ?>

<div class="flex flex-wrap gap-24" style="gap: 24px; flex-wrap: wrap;">
    <div style="flex: 1; min-width: 300px;">
        <div class="card">
            <div class="card-header"><?php echo $edit_class ? 'Edit Class Details' : 'Add New Class'; ?></div>
            <form method="POST" action="classes.php">
                <input type="hidden" name="action" value="<?php echo $edit_class ? 'edit' : 'add'; ?>">
                <?php if ($edit_class): ?>
                    <input type="hidden" name="class_id" value="<?php echo $edit_class['id']; ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label>Class Name</label>
                    <input type="text" name="name" class="form-control" value="<?php echo $edit_class ? htmlspecialchars($edit_class['name']) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Assigned Day</label>
                    <select name="day_of_week" class="form-control" required>
                        <option value="">-- Select Day --</option>
                        <?php 
                        $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
                        foreach($days as $d):
                            $sel = ($edit_class && $edit_class['day_of_week'] == $d) ? 'selected' : '';
                        ?>
                            <option value="<?php echo $d; ?>" <?php echo $sel; ?>><?php echo $d; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="display:flex; gap:15px; margin-bottom:15px;">
                    <div style="flex:1;">
                        <label style="display:block; margin-bottom:5px; font-weight:500; color:var(--text-secondary); font-size:14px;">Start Time</label>
                        <input type="time" name="start_time" class="form-control" value="<?php echo $edit_class ? substr($edit_class['start_time'], 0, 5) : ''; ?>" required>
                    </div>
                    <div style="flex:1;">
                        <label style="display:block; margin-bottom:5px; font-weight:500; color:var(--text-secondary); font-size:14px;">End Time</label>
                        <input type="time" name="end_time" class="form-control" value="<?php echo $edit_class ? substr($edit_class['end_time'], 0, 5) : ''; ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Assign Class Teacher</label>
                    <select name="faculty_id" class="form-control">
                        <option value="">-- None --</option>
                        <?php foreach($faculties as $fac): ?>
                            <option value="<?php echo $fac['id']; ?>" <?php echo ($edit_class && $edit_class['faculty_id'] == $fac['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($fac['first_name'] . ' ' . $fac['last_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary mt-4" style="width:100%;">
                    <?php echo $edit_class ? 'Save Updated Schedule' : 'Create Class & Assign Timeslot'; ?>
                </button>
                <?php if ($edit_class): ?>
                    <a href="classes.php" class="btn mt-2" style="display:block; text-align:center; background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; text-decoration:none;">Cancel Edit Mode</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <div style="flex: 2; min-width: 400px;">
        <div class="card">
            <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                Manage Classes
                <a href="?export=csv" class="btn btn-outline" style="font-size:12px;padding:6px 12px;border:1px solid var(--border);color:var(--text-main);text-decoration:none;">Export CSV</a>
            </div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Class Name</th><th>Schedule</th><th>Class Teacher</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach($classes as $c): ?>
                        <tr>
                            <td style="font-weight:bold;"><?php echo htmlspecialchars($c['name']); ?></td>
                            <td>
                                <?php if($c['day_of_week']): ?>
                                    <span style="background:var(--bg); padding:4px 8px; border-radius:4px; font-size:12px; border:1px solid var(--border); display:inline-block; margin-bottom:4px;">
                                        🗓️ <?php echo htmlspecialchars($c['day_of_week']); ?>
                                    </span>
                                    <br>
                                    <span style="font-size:12px; color:var(--text-muted);">
                                        <?php echo date('g:i A', strtotime($c['start_time'])) . ' - ' . date('g:i A', strtotime($c['end_time'])); ?>
                                    </span>
                                <?php else: ?>
                                    <i style="color:var(--text-muted); font-size:12px;">Unscheduled</i>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $c['first_name'] ? htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) : '<i style="color:var(--text-muted);">Unassigned</i>'; ?></td>
                            <td><a href="?edit=<?php echo $c['id']; ?>" class="btn btn-sm" style="background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; text-decoration:none;">✏️ Edit</a>
                            <a href="?delete=<?php echo $c['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this class entirely?')">Delete</a></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($classes)): ?><tr><td colspan="4">No classes found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
