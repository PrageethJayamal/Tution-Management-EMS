<?php
require_once '../config/db.php';
require_once '../includes/header.php';

if ($_SESSION['role'] !== 'student') die("<div class='alert alert-error'>Access Denied</div>");

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT s.*, c.name as class_name, c.day_of_week, c.start_time, c.end_time FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE user_id = ?");
$stmt->execute([$user_id]);
$student = $stmt->fetch();

$c_id = $_SESSION['center_id'];
$notices = $pdo->prepare("SELECT n.*, u.username as author_name FROM notices n JOIN users u ON n.author_id = u.id WHERE n.center_id = ? AND n.target_audience IN ('general', 'student') ORDER BY n.created_at DESC LIMIT 5");
$notices->execute([$c_id]);
$notices = $notices->fetchAll();
?>

<div class="card">
    <div class="card-header">Student Profile</div>
    <div style="display: flex; gap: 30px; align-items: flex-start; flex-wrap: wrap;">
        
        <?php if (!empty($student['profile_photo'])): ?>
            <div style="flex-shrink: 0;">
                <img src="../<?php echo htmlspecialchars($student['profile_photo']); ?>" alt="Avatar" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid var(--border); box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            </div>
        <?php endif; ?>
        
        <div style="font-size: 16px; line-height: 1.6; flex: 1; min-width: 250px;">
            <p><strong>Name:</strong> <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></p>
            <p><strong>Roll Number:</strong> <?php echo htmlspecialchars($student['roll_no']); ?></p>
            <p><strong>Contact Matrix:</strong> 
                <?php echo !empty($student['email']) ? htmlspecialchars($student['email']) : '<span style="color:var(--text-muted);font-size:14px;">No Email Configured</span>'; ?> 
                | 
                <?php echo !empty($student['phone']) ? htmlspecialchars($student['phone']) : '<span style="color:var(--text-muted);font-size:14px;">No Phone Configured</span>'; ?>
            </p>
            <p><strong>Class:</strong> <?php echo htmlspecialchars($student['class_name'] ?? 'Not Assigned'); ?></p>
            <?php if (!empty($student['day_of_week'])): ?>
                <p><strong>Schedule:</strong> <span style="background:var(--bg); padding:2px 8px; border:1px solid var(--border); border-radius:4px; font-size:14px;">🗓️ <?php echo htmlspecialchars($student['day_of_week']); ?>, <?php echo date('g:i A', strtotime($student['start_time'])); ?> &mdash; <?php echo date('g:i A', strtotime($student['end_time'])); ?></span></p>
            <?php endif; ?>
            <p><strong>Fee Status:</strong> 
                <?php if (isset($student['payment_status']) && $student['payment_status'] == 'paid'): ?>
                    <span style="color:var(--success);font-weight:bold;">Paid</span>
                    <a href="receipt.php?student_id=<?php echo $student['id']; ?>" class="btn btn-sm" target="_blank" style="margin-left:15px; padding:4px 8px; font-size:12px; background:var(--bg); border:1px solid var(--border); color:var(--text-primary); text-decoration:none; border-radius:4px;">📄 Generate Receipt</a>
                <?php else: ?>
                    <span style="color:var(--error);font-weight:bold;">Unpaid</span>
                <?php endif; ?>
            </p>
        </div>
    </div>
    <?php if (isset($student['payment_status']) && $student['payment_status'] == 'unpaid'): ?>
        <div class="alert alert-error" style="margin-top:15px; background-color: #fef2f2; border: 1px solid #f87171; color: #b91c1c;">
            <strong>Reminder:</strong> Your tuition fees are currently unpaid. Please arrange for payment at the center.
        </div>
    <?php endif; ?>
    
    <?php
    $rem_stmt = $pdo->prepare("SELECT r.*, f.first_name, f.last_name FROM remarks r JOIN faculty f ON r.faculty_id = f.id WHERE r.student_id = ? ORDER BY r.created_at DESC");
    $rem_stmt->execute([$student['id']]);
    $remarks = $rem_stmt->fetchAll();
    ?>
    <?php if(!empty($remarks)): ?>
        <div style="margin-top:20px; border-top:1px solid var(--border); padding-top:15px;">
            <h4 style="margin:0 0 10px 0; color:var(--text-main);">Teacher Remarks</h4>
            <?php foreach($remarks as $rem): ?>
                <?php 
                    $bg = '#f8fafc'; $col = '#475569';
                    if ($rem['severity'] == 'positive') { $bg = '#ecfdf5'; $col = '#059669'; }
                    if ($rem['severity'] == 'negative') { $bg = '#fef2f2'; $col = '#dc2626'; }
                ?>
                <div style="background: <?php echo $bg; ?>; color: <?php echo $col; ?>; padding: 12px; border-radius: 6px; font-size: 13px; border-left: 4px solid <?php echo $col; ?>; margin-bottom:10px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                    <div style="display:flex; justify-content:space-between; margin-bottom:4px; font-weight:600;">
                        <span>By <?php echo htmlspecialchars($rem['first_name'].' '.$rem['last_name']); ?></span>
                        <span style="font-size:11px; opacity:0.8;"><?php echo date('M d, Y', strtotime($rem['created_at'])); ?></span>
                    </div>
                    <div style="font-weight:normal; white-space: pre-wrap;"><?php echo htmlspecialchars($rem['remark']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
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
