<?php
require_once '../config/db.php';
require_once '../includes/header.php';

if ($_SESSION['role'] !== 'admin') {
    die("<div class='alert alert-error'>Access Denied</div>");
}

$c_id = $_SESSION['center_id'];

// Get stats
$facultyCount = $pdo->prepare("SELECT count(*) FROM faculty f JOIN users u ON f.user_id = u.id WHERE u.center_id = ?");
$facultyCount->execute([$c_id]); $facultyCount = $facultyCount->fetchColumn();

$studentCount = $pdo->prepare("SELECT count(*) FROM students s JOIN users u ON s.user_id = u.id WHERE u.center_id = ?");
$studentCount->execute([$c_id]); $studentCount = $studentCount->fetchColumn();

$parentCount = $pdo->prepare("SELECT count(*) FROM parents p JOIN users u ON p.user_id = u.id WHERE u.center_id = ?");
$parentCount->execute([$c_id]); $parentCount = $parentCount->fetchColumn();

$classCount = $pdo->prepare("SELECT count(*) FROM classes WHERE center_id = ?");
$classCount->execute([$c_id]); $classCount = $classCount->fetchColumn();

// Analytics
$today = date('Y-m-d');
$presentToday = $pdo->prepare("SELECT count(*) FROM attendance a JOIN students s ON a.student_id = s.id JOIN users u ON s.user_id = u.id WHERE a.attendance_date = ? AND a.status = 'present' AND u.center_id = ?");
$presentToday->execute([$today, $c_id]); $presentToday = $presentToday->fetchColumn();

$absentToday = $pdo->prepare("SELECT count(*) FROM attendance a JOIN students s ON a.student_id = s.id JOIN users u ON s.user_id = u.id WHERE a.attendance_date = ? AND a.status = 'absent' AND u.center_id = ?");
$absentToday->execute([$today, $c_id]); $absentToday = $absentToday->fetchColumn();

// Recent Enrollments
$stmt = $pdo->prepare("SELECT s.*, c.name as class_name FROM students s JOIN users u ON s.user_id = u.id LEFT JOIN classes c ON s.class_id = c.id WHERE u.center_id = ? ORDER BY s.id DESC LIMIT 5");
$stmt->execute([$c_id]);
$recentStudents = $stmt->fetchAll();

// Notices logic
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'post_notice') {
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    $audience = $_POST['audience'];
    $author_id = $_SESSION['user_id'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO notices (center_id, author_id, target_audience, title, message) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$c_id, $author_id, $audience, $title, $message]);
        
        require_once '../includes/mailer.php';
        send_notice_emails($pdo, $c_id, $audience, $_SESSION['username'], $title, $message);
        
        $notice_msg = "Announcement posted successfully!";
    } catch (Exception $e) { $notice_err = "Failed to post: " . $e->getMessage(); }
}

if (isset($_GET['delete_notice'])) {
    try {
        $pdo->prepare("DELETE FROM notices WHERE id = ? AND center_id = ?")->execute([$_GET['delete_notice'], $c_id]);
        $notice_msg = "Announcement deleted.";
    } catch(Exception $e) {}
}

$notices = $pdo->prepare("SELECT n.*, u.username as author_name FROM notices n JOIN users u ON n.author_id = u.id WHERE n.center_id = ? ORDER BY n.created_at DESC LIMIT 10");
$notices->execute([$c_id]);
$notices = $notices->fetchAll();
?>

<div class="card">
    <div class="card-header">Global Analytics</div>
    <div class="dashboard-grid">
        <div class="stat-card">
            <h3>Total Faculty</h3>
            <p><?php echo $facultyCount; ?></p>
        </div>
        <div class="stat-card">
            <h3>Total Students</h3>
            <p><?php echo $studentCount; ?></p>
        </div>
        <div class="stat-card">
            <h3>Total Parents</h3>
            <p><?php echo $parentCount; ?></p>
        </div>
        <div class="stat-card" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%);">
            <h3>Total Classes</h3>
            <p><?php echo $classCount; ?></p>
        </div>
    </div>
</div>

<?php if (isset($notice_msg)): ?> <div class="alert alert-success mt-4"><?php echo htmlspecialchars($notice_msg); ?></div> <?php endif; ?>
<?php if (isset($notice_err)): ?> <div class="alert alert-error mt-4"><?php echo htmlspecialchars($notice_err); ?></div> <?php endif; ?>

<div class="flex flex-wrap gap-24 mt-4 mb-4" style="gap: 24px; flex-wrap: wrap;">
    <div style="flex: 1; min-width: 300px;">
        <div class="card" style="height: 100%;">
            <div class="card-header">Post Announcement</div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="post_notice">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Audience</label>
                    <select name="audience" class="form-control">
                        <option value="general">Everyone (General)</option>
                        <option value="faculty">Faculty Only</option>
                        <option value="student">Students Only</option>
                        <option value="parent">Parents Only</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="message" class="form-control" rows="4" required style="resize:vertical;"></textarea>
                </div>
                <button type="submit" class="btn btn-primary mt-2">Broadcast Notice</button>
            </form>
        </div>
    </div>
    <div style="flex: 2; min-width: 400px;">
        <div class="card" style="height: 100%;">
            <div class="card-header">Recent Announcements</div>
            <div style="max-height: 480px; overflow-y: auto; padding-right:10px;">
                <?php foreach($notices as $n): ?>
                    <div style="background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:15px; margin-bottom:15px;">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
                            <div>
                                <h4 style="margin:0 0 5px; color:var(--text-primary);"><?php echo htmlspecialchars($n['title']); ?></h4>
                                <span style="font-size:12px; background:#E0E7FF; color:#4F46E5; padding:2px 8px; border-radius:4px; font-weight:600; text-transform:uppercase;">To: <?php echo htmlspecialchars($n['target_audience']); ?></span>
                                <span style="font-size:12px; color:var(--text-muted); margin-left:10px;">By <?php echo htmlspecialchars($n['author_name']); ?> &bull; <?php echo date('M d, g:i A', strtotime($n['created_at'])); ?></span>
                            </div>
                            <a href="?delete_notice=<?php echo $n['id']; ?>" style="color:#EF4444; font-size:13px; text-decoration:none;" onclick="return confirm('Delete this notice?')">Delete</a>
                        </div>
                        <p style="margin:0; font-size:14px; color:var(--text-secondary); white-space:pre-wrap;"><?php echo htmlspecialchars($n['message']); ?></p>
                    </div>
                <?php endforeach; ?>
                <?php if(empty($notices)): ?>
                    <p style="color:var(--text-muted); text-align:center; padding:20px;">No announcements posted.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="flex flex-wrap gap-24" style="gap: 24px; flex-wrap: wrap;">
    <div style="flex: 1; min-width: 300px;">
        <div class="card" style="height: 100%;">
            <div class="card-header">Today's Attendance Outlook</div>
            <div style="display: flex; gap: 20px; align-items: center; justify-content: center; padding: 20px 0;">
                <div style="text-align: center;">
                    <h4 style="margin: 0; color: var(--text-muted); font-weight: 500; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;">Present</h4>
                    <p style="margin: 10px 0 0; font-size: 42px; font-weight: 700; color: #10B981;"><?php echo $presentToday; ?></p>
                </div>
                <div style="width: 1px; height: 60px; background: var(--border);"></div>
                <div style="text-align: center;">
                    <h4 style="margin: 0; color: var(--text-muted); font-weight: 500; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;">Absent</h4>
                    <p style="margin: 10px 0 0; font-size: 42px; font-weight: 700; color: #EF4444;"><?php echo $absentToday; ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <div style="flex: 2; min-width: 400px;">
        <div class="card" style="height: 100%;">
            <div class="card-header">Recent Enrollments</div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Roll No</th>
                            <th>Name</th>
                            <th>Class</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($recentStudents as $s): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($s['roll_no']); ?></td>
                            <td><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($s['class_name'] ?? 'Unassigned'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($recentStudents)): ?>
                        <tr><td colspan="3">No records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
