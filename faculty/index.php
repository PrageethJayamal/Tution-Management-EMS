<?php
require_once '../config/db.php';
require_once '../includes/header.php';

if ($_SESSION['role'] !== 'faculty') die("<div class='alert alert-error'>Access Denied</div>");

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id FROM faculty WHERE user_id = ?");
$stmt->execute([$user_id]);
$faculty_id = $stmt->fetchColumn();

// Get their classes
$stmt = $pdo->prepare("SELECT * FROM classes WHERE faculty_id = ?");
$stmt->execute([$faculty_id]);
$classes = $stmt->fetchAll();

$c_id = $_SESSION['center_id'];
$author_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'post_notice') {
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    $audience = $_POST['audience'];
    
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
        $pdo->prepare("DELETE FROM notices WHERE id = ? AND author_id = ?")->execute([$_GET['delete_notice'], $author_id]);
        $notice_msg = "Announcement deleted.";
    } catch(Exception $e) {}
}

$notices = $pdo->prepare("SELECT n.*, u.username as author_name FROM notices n JOIN users u ON n.author_id = u.id WHERE n.center_id = ? AND (n.target_audience IN ('general', 'faculty') OR n.author_id = ?) ORDER BY n.created_at DESC LIMIT 10");
$notices->execute([$c_id, $author_id]);
$notices = $notices->fetchAll();
?>

<div class="card">
    <div class="card-header">Faculty Dashboard</div>
    <p>Welcome to your dashboard. Here are your assigned classes:</p>
    
    <div class="dashboard-grid mt-4">
        <?php foreach($classes as $c): ?>
            <div class="stat-card" style="background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);">
                <h3>Class</h3>
                <p><?php echo htmlspecialchars($c['name']); ?></p>
            </div>
        <?php endforeach; ?>
        <?php if(empty($classes)): ?>
            <div class="alert alert-error mt-4" style="grid-column: 1 / -1;">You have no classes assigned. Contact admin to assign classes.</div>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($notice_msg)): ?> <div class="alert alert-success mt-4"><?php echo htmlspecialchars($notice_msg); ?></div> <?php endif; ?>
<?php if (isset($notice_err)): ?> <div class="alert alert-error mt-4"><?php echo htmlspecialchars($notice_err); ?></div> <?php endif; ?>

<div class="flex flex-wrap gap-24 mt-4" style="gap: 24px; flex-wrap: wrap;">
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
                        <option value="general">Students & Parents</option>
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
            <div class="card-header">Notice Board</div>
            <div style="max-height: 480px; overflow-y: auto; padding-right:10px;">
                <?php foreach($notices as $n): ?>
                    <div style="background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:15px; margin-bottom:15px;">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
                            <div>
                                <h4 style="margin:0 0 5px; color:var(--text-primary);"><?php echo htmlspecialchars($n['title']); ?></h4>
                                <span style="font-size:12px; background:#E0E7FF; color:#4F46E5; padding:2px 8px; border-radius:4px; font-weight:600; text-transform:uppercase;">To: <?php echo htmlspecialchars($n['target_audience']); ?></span>
                                <span style="font-size:12px; color:var(--text-muted); margin-left:10px;">By <?php echo htmlspecialchars($n['author_name']); ?> &bull; <?php echo date('M d, g:i A', strtotime($n['created_at'])); ?></span>
                            </div>
                            <?php if($n['author_id'] == $author_id): ?>
                                <a href="?delete_notice=<?php echo $n['id']; ?>" style="color:#EF4444; font-size:13px; text-decoration:none;" onclick="return confirm('Delete this notice?')">Delete</a>
                            <?php endif; ?>
                        </div>
                        <p style="margin:0; font-size:14px; color:var(--text-secondary); white-space:pre-wrap;"><?php echo htmlspecialchars($n['message']); ?></p>
                    </div>
                <?php endforeach; ?>
                <?php if(empty($notices)): ?>
                    <p style="color:var(--text-muted); text-align:center; padding:20px;">No announcements available.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
