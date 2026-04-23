<?php
require_once '../config/db.php';
require_once '../includes/header.php';

if ($_SESSION['role'] !== 'admin') die("<div class='alert alert-error'>Access Denied</div>");

// Get logs
$logs = $pdo->query("SELECT a.*, u.username, u.role FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 100")->fetchAll();
?>

<div class="card">
    <div class="card-header">System Activity Log</div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Timestamp</th><th>User</th><th>Role</th><th>Action</th><th>Context</th></tr></thead>
            <tbody>
                <?php foreach($logs as $log): ?>
                <tr>
                    <td style="color:var(--text-muted); font-size:13px;"><?php echo htmlspecialchars($log['created_at']); ?></td>
                    <td><?php echo htmlspecialchars($log['username'] ?? 'System/Anonymous'); ?></td>
                    <td style="text-transform: capitalize;"><?php echo htmlspecialchars($log['role'] ?? '-'); ?></td>
                    <td><span style="font-weight:600; color:var(--primary);"><?php echo htmlspecialchars($log['action']); ?></span></td>
                    <td style="font-size:13px; max-width:300px;"><?php echo htmlspecialchars($log['context']); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($logs)): ?><tr><td colspan="5">No logs found yet. Actions will populate here automatically.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
