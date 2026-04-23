<?php
require_once '../config/db.php';
require_once '../includes/header.php';

if ($_SESSION['role'] !== 'superadmin') {
    die("<div class='alert alert-error'>Access Denied</div>");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'post_global_notice') {
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    $audience = $_POST['audience'];
    $author_id = $_SESSION['user_id'];
    
    try {
        $pdo->beginTransaction();
        $centers = $pdo->query("SELECT id FROM centers")->fetchAll(PDO::FETCH_COLUMN);
        
        $stmt = $pdo->prepare("INSERT INTO notices (center_id, author_id, target_audience, title, message) VALUES (?, ?, ?, ?, ?)");
        foreach($centers as $c_id) {
            $stmt->execute([$c_id, $author_id, $audience, $title, $message]);
        }
        $pdo->commit();
        
        require_once '../includes/mailer.php';
        send_notice_emails($pdo, 0, $audience, $_SESSION['username'], 'GLOBAL: ' . $title, $message);
        
        $notice_msg = "Global announcement broadcasted to " . count($centers) . " centers!";
    } catch (Exception $e) { 
        $pdo->rollBack();
        $notice_err = "Failed to post: " . $e->getMessage(); 
    }
}

if (isset($_GET['delete_notice_title'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM notices WHERE title = ? AND author_id = ?");
        $stmt->execute([urldecode($_GET['delete_notice_title']), $_SESSION['user_id']]);
        $notice_msg = "Global announcement removed across all centers.";
    } catch(Exception $e) {}
}

$notices = $pdo->prepare("SELECT DISTINCT title, message, target_audience, created_at FROM notices WHERE author_id = ? ORDER BY created_at DESC LIMIT 10");
$notices->execute([$_SESSION['user_id']]);
$notices = $notices->fetchAll();

$total_centers  = $pdo->query("SELECT COUNT(*) FROM centers")->fetchColumn();
$total_students = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$total_faculty  = $pdo->query("SELECT COUNT(*) FROM faculty")->fetchColumn();
$total_parents  = $pdo->query("SELECT COUNT(*) FROM parents")->fetchColumn();

$recent_centers = $pdo->query("SELECT name, center_code, created_at FROM centers ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Analytics Data: Enrollment by Center
$enrollment_query = $pdo->query("SELECT c.name, COUNT(u.id) as student_count FROM centers c LEFT JOIN users u ON c.id = u.center_id AND u.role = 'student' GROUP BY c.id ORDER BY student_count DESC LIMIT 10")->fetchAll();
$max_enrollment = 0;
foreach($enrollment_query as $row) {
    if($row['student_count'] > $max_enrollment) $max_enrollment = $row['student_count'];
}
$max_enrollment = max($max_enrollment, 1);

// Analytics Data: System Wide Fee Status
$total_paid = $pdo->query("SELECT COUNT(*) FROM students WHERE payment_status = 'paid'")->fetchColumn();
$total_unpaid = $pdo->query("SELECT COUNT(*) FROM students WHERE payment_status = 'unpaid'")->fetchColumn();
$total_fee_records = $total_paid + $total_unpaid;
$paid_percent = $total_fee_records > 0 ? round(($total_paid / $total_fee_records) * 100) : 0;
$unpaid_percent = $total_fee_records > 0 ? 100 - $paid_percent : 0;
?>
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 24px; margin-bottom: 30px;">
    <div class="stat-card" style="background:#fff; padding:25px; border-radius:12px; border:1px solid #E5E7EB; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05); text-align:center;">
        <h3 style="margin:0; font-size:15px; color:#6B7280; font-weight:600;">Total Institutes</h3>
        <div class="value" style="font-size:36px; font-weight:700; color:#111827; margin-top:10px;"><?php echo $total_centers; ?></div>
    </div>
    <div class="stat-card" style="background:#fff; padding:25px; border-radius:12px; border:1px solid #E5E7EB; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05); text-align:center;">
        <h3 style="margin:0; font-size:15px; color:#6B7280; font-weight:600;">Enrolled Students</h3>
        <div class="value" style="font-size:36px; font-weight:700; color:#6366F1; margin-top:10px;"><?php echo $total_students; ?></div>
    </div>
    <div class="stat-card" style="background:#fff; padding:25px; border-radius:12px; border:1px solid #E5E7EB; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05); text-align:center;">
        <h3 style="margin:0; font-size:15px; color:#6B7280; font-weight:600;">Active Faculty</h3>
        <div class="value" style="font-size:36px; font-weight:700; color:#10B981; margin-top:10px;"><?php echo $total_faculty; ?></div>
    </div>
    <div class="stat-card" style="background:#fff; padding:25px; border-radius:12px; border:1px solid #E5E7EB; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05); text-align:center;">
        <h3 style="margin:0; font-size:15px; color:#6B7280; font-weight:600;">Linked Parents</h3>
        <div class="value" style="font-size:36px; font-weight:700; color:#F59E0B; margin-top:10px;"><?php echo $total_parents; ?></div>
    </div>
</div>

<?php if (isset($notice_msg)): ?> <div class="alert alert-success" style="margin-bottom:20px;"><?php echo htmlspecialchars($notice_msg); ?></div> <?php endif; ?>
<?php if (isset($notice_err)): ?> <div class="alert alert-error" style="margin-bottom:20px;"><?php echo htmlspecialchars($notice_err); ?></div> <?php endif; ?>

<div class="flex flex-wrap gap-24" style="gap: 24px; flex-wrap: wrap;">
    <div style="flex: 2; min-width: 400px;">
        <div class="card">
            <div class="card-header">System Overview</div>
            <div style="padding: 20px; color: var(--text-secondary); line-height: 1.6;">
                <p>Welcome to the global platform dashboard. As a System Administrator, you have complete oversight across all independent Tuition Centers.</p>
                <div style="background:var(--bg); border:1px solid var(--border); padding:20px; border-radius:8px; margin-top:20px;">
                    <h4 style="margin:0 0 10px; color:var(--text-primary);">Quick Actions</h4>
                    <ul style="margin:0; padding-left:20px;">
                        <li style="margin-bottom:8px;"><a href="centers.php" style="color:var(--accent); font-weight:500;">Provision a new Tuition Center &rarr;</a></li>
                        <li><a href="centers.php" style="color:var(--accent); font-weight:500;">Review center administrative accounts &rarr;</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div style="flex: 1; min-width: 300px;">
        <div class="card">
            <div class="card-header">Recently Onboarded Centers</div>
            <div class="table-responsive">
                <table>
                    <tbody>
                        <?php foreach($recent_centers as $rc): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600; color:var(--text-primary);"><?php echo htmlspecialchars($rc['name']); ?></div>
                                <div style="font-size:12px; color:var(--text-muted); margin-top:3px;">Joined <?php echo date('M d', strtotime($rc['created_at'])); ?></div>
                            </td>
                            <td style="text-align:right;">
                                <span style="background:var(--bg); padding:4px 8px; border-radius:4px; font-size:12px; font-weight:600; font-family:monospace; border:1px solid var(--border);"><?php echo htmlspecialchars($rc['center_code']); ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($recent_centers)): ?>
                        <tr><td>No centers registered yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- START NATIVE PHP ANALYTICS MODULE -->
<div class="flex flex-wrap gap-24" style="gap: 24px; flex-wrap: wrap; margin-bottom: 30px;">
    <!-- Enrollment Bar Chart (Pure CSS) -->
    <div style="flex: 2; min-width: 400px;">
        <div class="card" style="height: 100%; display: flex; flex-direction: column;">
            <div class="card-header">Enrollment Distribution (Top 10 Centers)</div>
            <div style="padding: 20px; flex-grow: 1; display: flex; align-items: flex-end; justify-content: space-around; gap: 10px; height: 250px; border-bottom: 2px solid var(--border); overflow-x: auto;">
                <?php foreach($enrollment_query as $center): 
                    $height_pct = round(($center['student_count'] / $max_enrollment) * 100);
                ?>
                    <div style="flex: 1; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; min-width: 45px; height: 100%;">
                        <div style="font-size: 12px; font-weight: bold; color: var(--text-secondary); margin-bottom: 5px;"><?php echo $center['student_count']; ?></div>
                        <div style="width: 100%; max-width: 50px; background: linear-gradient(to top, #6366F1, #818CF8); height: <?php echo max($height_pct, 1); ?>%; border-radius: 4px 4px 0 0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);" title="<?php echo htmlspecialchars($center['name']); ?>: <?php echo $center['student_count']; ?> students"></div>
                        <div style="margin-top: 8px; font-size: 11px; font-weight: 500; color: var(--text-muted); text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 55px;" title="<?php echo htmlspecialchars($center['name']); ?>"><?php echo substr(htmlspecialchars($center['name']), 0, 8); ?></div>
                    </div>
                <?php endforeach; ?>
                <?php if(empty($enrollment_query)): ?>
                    <p style="color:var(--text-muted); margin:auto; align-self: center;">No enrollment data available.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Financial Health Ratio Bar (Pure CSS) -->
    <div style="flex: 1; min-width: 300px;">
        <div class="card" style="height: 100%; display: flex; flex-direction: column;">
            <div class="card-header">System-Wide Financial Health</div>
            <div style="padding: 20px; display: flex; flex-direction: column; justify-content: center; flex-grow: 1;">
                <p style="font-size: 14px; color: var(--text-secondary); margin-bottom: 25px; line-height: 1.5;">Proportional breakdown of collected tuition fees versus outstanding balances across the entire network.</p>
                
                <div style="display: flex; height: 35px; border-radius: 18px; overflow: hidden; background: #F3F4F6; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 20px;">
                    <?php if($total_fee_records > 0): ?>
                        <div style="width: <?php echo $paid_percent; ?>%; background: linear-gradient(90deg, #10B981, #34D399); display: flex; align-items: center; justify-content: center; color: white; font-size: 13px; font-weight: bold;"><?php echo $paid_percent > 10 ? $paid_percent.'%' : ''; ?></div>
                        <div style="width: <?php echo $unpaid_percent; ?>%; background: linear-gradient(90deg, #EF4444, #F87171); display: flex; align-items: center; justify-content: center; color: white; font-size: 13px; font-weight: bold;"><?php echo $unpaid_percent > 10 ? $unpaid_percent.'%' : ''; ?></div>
                    <?php else: ?>
                        <div style="width: 100%; display: flex; align-items: center; justify-content: center; color: var(--text-muted); font-size: 13px;">No recorded fee transactions.</div>
                    <?php endif; ?>
                </div>
                
                <div style="display: flex; justify-content: center; gap: 30px; margin-top: 10px;">
                    <div style="text-align: center;">
                        <span style="display: inline-block; width: 14px; height: 14px; border-radius: 50%; background: #10B981; margin-right: 6px; vertical-align: middle;"></span>
                        <span style="font-size: 14px; font-weight: 600; color: var(--text-main); vertical-align: middle;">Collected (<?php echo $total_paid; ?>)</span>
                    </div>
                    <div style="text-align: center;">
                        <span style="display: inline-block; width: 14px; height: 14px; border-radius: 50%; background: #EF4444; margin-right: 6px; vertical-align: middle;"></span>
                        <span style="font-size: 14px; font-weight: 600; color: var(--text-main); vertical-align: middle;">Outstanding (<?php echo $total_unpaid; ?>)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- END NATIVE PHP ANALYTICS MODULE -->

<div class="flex flex-wrap gap-24 mt-4 mb-4" style="gap: 24px; flex-wrap: wrap;">
    <div style="flex: 1; min-width: 300px;">
        <div class="card" style="height: 100%;">
            <div class="card-header" style="color:#BE185D;">Broadcast Global Announcement</div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="post_global_notice">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Audience</label>
                    <select name="audience" class="form-control">
                        <option value="general">Everyone (All Roles)</option>
                        <option value="faculty">Faculty Only</option>
                        <option value="student">Students Only</option>
                        <option value="parent">Parents Only</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="message" class="form-control" rows="4" required style="resize:vertical;"></textarea>
                </div>
                <button type="submit" class="btn btn-primary mt-2" style="background:linear-gradient(135deg, #EC4899 0%, #BE185D 100%); width:100%;">Broadcast System-Wide</button>
            </form>
        </div>
    </div>
    <div style="flex: 2; min-width: 400px;">
        <div class="card" style="height: 100%;">
            <div class="card-header">Your Global Announcements</div>
            <div style="max-height: 480px; overflow-y: auto; padding-right:10px;">
                <?php foreach($notices as $n): ?>
                    <div style="background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:15px; margin-bottom:15px;">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
                            <div>
                                <h4 style="margin:0 0 5px; color:var(--text-primary);"><?php echo htmlspecialchars($n['title']); ?></h4>
                                <span style="font-size:12px; background:#FCE7F3; color:#BE185D; padding:2px 8px; border-radius:4px; font-weight:600; text-transform:uppercase;">To: <?php echo htmlspecialchars($n['target_audience']); ?></span>
                                <span style="font-size:12px; color:var(--text-muted); margin-left:10px;"><?php echo date('M d, g:i A', strtotime($n['created_at'])); ?></span>
                            </div>
                            <a href="?delete_notice_title=<?php echo urlencode($n['title']); ?>" style="color:#EF4444; font-size:13px; text-decoration:none;" onclick="return confirm('Globally delete this notice from ALL centers?')">Delete</a>
                        </div>
                        <p style="margin:0; font-size:14px; color:var(--text-secondary); white-space:pre-wrap;"><?php echo htmlspecialchars($n['message']); ?></p>
                    </div>
                <?php endforeach; ?>
                <?php if(empty($notices)): ?>
                    <p style="color:var(--text-muted); text-align:center; padding:20px;">No global announcements authored.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
