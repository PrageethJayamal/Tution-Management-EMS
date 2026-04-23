<?php
require_once '../config/db.php';
require_once '../includes/header.php';

if ($_SESSION['role'] !== 'parent') die("<div class='alert alert-error'>Access Denied</div>");

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id, first_name, last_name FROM parents WHERE user_id = ?");
$stmt->execute([$user_id]);
$parent = $stmt->fetch();
$parent_id = $parent['id'] ?? 0;

$children = [];
if ($parent_id) {
    $children = $pdo->query("SELECT s.*, c.name as class_name, c.day_of_week, c.start_time, c.end_time FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.parent_id = $parent_id")->fetchAll();
}

$c_id = $_SESSION['center_id'];
$notices = $pdo->prepare("SELECT n.*, u.username as author_name FROM notices n JOIN users u ON n.author_id = u.id WHERE n.center_id = ? AND n.target_audience IN ('general', 'parent') ORDER BY n.created_at DESC LIMIT 5");
$notices->execute([$c_id]);
$notices = $notices->fetchAll();
?>

<div class="card">
    <div class="card-header">Parent Dashboard</div>
    <p>Welcome, <?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?>.</p>
    
    <?php
    $unpaid_alert = false;
    foreach ($children as $c) {
        if (isset($c['payment_status']) && $c['payment_status'] == 'unpaid') {
            $unpaid_alert = true;
            break;
        }
    }
    ?>
    <?php if ($unpaid_alert): ?>
        <div class="alert alert-error" style="margin-top:20px; background-color: #fef2f2; border: 1px solid #f87171; color: #b91c1c; padding: 15px; border-radius: 8px;">
            <strong>Action Required:</strong> One or more of your associated student profiles currently has an unpaid tuition balance. Please make a payment to the center administration at your earliest convenience.
        </div>
    <?php endif; ?>
    
    <h3 class="mt-4" style="margin-top: 20px; margin-bottom: 10px; font-size: 16px;">Your Children:</h3>
    <div class="dashboard-grid">
        <?php foreach($children as $child): ?>
            <?php
            // Pull specific disciplinary/behavioral remarks attached to this child
            $c_id = $child['id'];
            $rem_stmt = $pdo->prepare("SELECT r.*, f.first_name, f.last_name FROM remarks r JOIN faculty f ON r.faculty_id = f.id WHERE r.student_id = ? ORDER BY r.created_at DESC");
            $rem_stmt->execute([$c_id]);
            $remarks = $rem_stmt->fetchAll();
            ?>
            <div style="display:flex; flex-direction:column; gap:10px;">
                <div class="stat-card" style="background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%); margin-bottom: 0;">
                    <h3 style="font-size: 18px; font-weight: bold; margin-bottom: 10px;"><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></h3>
                    <p style="font-size: 14px; margin: 0; font-weight: normal;">Roll No: <?php echo htmlspecialchars($child['roll_no']); ?></p>
                    <p style="font-size: 14px; margin: 0; font-weight: normal;">Class: <?php echo htmlspecialchars($child['class_name'] ?? 'N/A'); ?></p>
                    <?php if (!empty($child['day_of_week'])): ?>
                        <p style="font-size: 13px; margin: 0; font-weight: 500; margin-top:5px; background:rgba(255,255,255,0.1); padding:2px 6px; border-radius:4px; display:inline-block;">🗓️ <?php echo htmlspecialchars($child['day_of_week']); ?> (<?php echo date('g:i', strtotime($child['start_time'])) . ' - ' . date('g:i A', strtotime($child['end_time'])); ?>)</p>
                    <?php endif; ?>
                    <p style="font-size: 14px; margin: 0; font-weight: bold; margin-top: 10px;">
                        Fee Status: 
                        <?php if (isset($child['payment_status']) && $child['payment_status'] == 'paid'): ?>
                            <span style="color: #6EE7B7;">Paid</span>
                            <br><a href="../student/receipt.php?student_id=<?php echo $child['id']; ?>" target="_blank" style="display:inline-block; margin-top:8px; padding:4px 10px; font-size:12px; font-weight:normal; background:rgba(255,255,255,0.2); color:#fff; text-decoration:none; border-radius:4px; border:1px solid rgba(255,255,255,0.4);">📄 Download Receipt</a>
                        <?php else: ?>
                            <span style="color: #FCA5A5;">Unpaid (Reminder)</span>
                        <?php endif; ?>
                    </p>
                </div>
                
                <?php foreach($remarks as $rem): ?>
                    <?php 
                        $bg = '#f8fafc'; $col = '#475569';
                        if ($rem['severity'] == 'positive') { $bg = '#ecfdf5'; $col = '#059669'; }
                        if ($rem['severity'] == 'negative') { $bg = '#fef2f2'; $col = '#dc2626'; }
                    ?>
                    <div style="background: <?php echo $bg; ?>; color: <?php echo $col; ?>; padding: 12px; border-radius: 6px; font-size: 13px; border-left: 4px solid <?php echo $col; ?>; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                        <div style="display:flex; justify-content:space-between; margin-bottom:4px; font-weight:600;">
                            <span>Teacher Note: <?php echo htmlspecialchars($rem['first_name'].' '.$rem['last_name']); ?></span>
                            <span style="font-size:11px; opacity:0.8;"><?php echo date('M d, Y', strtotime($rem['created_at'])); ?></span>
                        </div>
                        <div style="font-weight:normal; white-space: pre-wrap;"><?php echo htmlspecialchars($rem['remark']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        <?php if(empty($children)): ?>
            <div class="alert alert-error" style="grid-column: 1 / -1;">No children linked to your account.</div>
        <?php endif; ?>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">Notice Board</div>
    <div style="max-height: 480px; overflow-y: auto; padding-right:10px;">
        <?php foreach($notices as $n): ?>
            <div style="background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:15px; margin-bottom:15px;">
                <div style="margin-bottom:10px;">
                    <h4 style="margin:0 0 5px; color:var(--text-primary);"><?php echo htmlspecialchars($n['title']); ?></h4>
                    <span style="font-size:12px; color:var(--text-muted);">From <?php echo htmlspecialchars($n['author_name']); ?> &bull; <?php echo date('M d, g:i A', strtotime($n['created_at'])); ?></span>
                </div>
                <p style="margin:0; font-size:14px; color:var(--text-secondary); white-space:pre-wrap;"><?php echo htmlspecialchars($n['message']); ?></p>
            </div>
        <?php endforeach; ?>
        <?php if(empty($notices)): ?>
            <p style="color:var(--text-muted); padding:10px 0;">No active announcements.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
