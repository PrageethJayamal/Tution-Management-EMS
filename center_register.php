<?php
require_once 'config/db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: " . $_SESSION['role'] . "/index.php");
    exit;
}

$error = '';
$success = '';
$center_code_display = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $center_name = trim($_POST['center_name']);
    $admin_user = trim($_POST['admin_username']);
    $admin_pass = $_POST['admin_password'];

    try {
        $pdo->beginTransaction();

        if (empty($center_name) || empty($admin_user) || empty($admin_pass)) {
            throw new Exception("Please fill in all required fields.");
        }

        // Check if admin username exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$admin_user]);
        if ($stmt->rowCount() > 0) {
            throw new Exception("Administrator username is already taken. Please choose another.");
        }

        // Generate a random center code (6 uppercase alphanumeric)
        $center_code = strtoupper(substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6));

        // Ensure unique code
        while ($pdo->query("SELECT id FROM centers WHERE center_code = '$center_code'")->rowCount() > 0) {
            $center_code = strtoupper(substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6));
        }

        // 1. Create Center
        $stmt = $pdo->prepare("INSERT INTO centers (name, center_code) VALUES (?, ?)");
        $stmt->execute([$center_name, $center_code]);
        $center_id = $pdo->lastInsertId();

        // 2. Create Admin User for this center
        $hash = password_hash($admin_pass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, center_id) VALUES (?, ?, 'admin', ?)");
        $stmt->execute([$admin_user, $hash, $center_id]);

        $pdo->commit();
        $success = "Institution registered successfully!";
        $center_code_display = $center_code;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TuitionCenter - Register Institute</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #FAFAFA; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; color: #111827; padding: 20px; box-sizing: border-box; -webkit-font-smoothing: antialiased; }
        
        .login-box { 
            background: #FFFFFF; 
            padding: 40px 50px; 
            border-radius: 16px; 
            border: 1px solid #E5E7EB; 
            box-shadow: 0 20px 40px -10px rgba(0,0,0,0.1); 
            width: 100%; 
            max-width: 500px;
            box-sizing: border-box;
        }

        h2 { text-align: center; margin: 0 0 10px; color: #111827; font-size: 26px; font-weight: 700; letter-spacing: -0.5px; }
        p.subtitle { text-align: center; color: #6B7280; font-size: 15px; margin-bottom: 30px; }
        
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; color: #111827; }
        input[type="text"], input[type="password"] { width: 100%; padding: 12px 15px; border: 1px solid #E5E7EB; background: #FAFAFA; border-radius: 8px; font-size: 14px; transition: all 0.3s; font-family: inherit; box-sizing: border-box; }
        input:focus { outline: none; border-color: #6366F1; background: #FFFFFF; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15); }
        
        button { width: 100%; padding: 14px; background: linear-gradient(135deg, #6366F1 0%, #8B5CF6 100%); color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 15px; font-weight: 600; transition: transform 0.2s, box-shadow 0.2s; margin-top: 10px; box-shadow: 0 4px 6px rgba(99, 102, 241, 0.25); }
        button:hover { transform: translateY(-2px); box-shadow: 0 8px 12px rgba(99, 102, 241, 0.35); }
        
        .error { color: #991B1B; background: #FEF2F2; padding: 12px; border-left: 4px solid #EF4444; border-radius: 4px; text-align: center; margin-bottom: 20px; font-size: 14px; font-weight: 500; }
        .success { color: #065F46; background: #ECFDF5; padding: 20px; border-left: 4px solid #10B981; border-radius: 4px; text-align: center; margin-bottom: 20px; }
        .success strong { display: block; font-size: 24px; margin: 10px 0; color: #111827; letter-spacing: 2px; }
        
        .back-link { display: block; text-align: center; margin-top: 25px; color: #6B7280; font-size: 14px; font-weight: 500; text-decoration: none; transition: color 0.2s; }
        .back-link:hover { color: #111827; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Register Your Institute</h2>
        <p class="subtitle">Set up a dedicated portal for your tuition center.</p>

        <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success">
                <?php echo htmlspecialchars($success); ?><br>
                Share this Center Code with your students and parents to allow them to register under your institute:
                <strong><?php echo htmlspecialchars($center_code_display); ?></strong>
            </div>
            <a href="center_login.php" style="display:block; text-align:center; padding: 12px; background: #111827; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600;">Go to Admin Login</a>
        <?php else: ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label>Institute / Center Name</label>
                    <input type="text" name="center_name" required placeholder="e.g. Excellence Academy">
                </div>

                <div class="form-group">
                    <label>Administrator Username</label>
                    <input type="text" name="admin_username" required placeholder="To login to your admin portal">
                </div>
                
                <div class="form-group">
                    <label>Administrator Password</label>
                    <input type="password" name="admin_password" required>
                </div>

                <button type="submit">Create Center Portal</button>
            </form>
            <div style="text-align: center; margin-top: 25px; font-size: 14px;">
                <span style="color: #6B7280;">Already an administrator?</span>
                <a href="center_login.php" style="color: #6366F1; font-weight: 600; text-decoration: none;">Sign in here</a>
            </div>
        <?php endif; ?>
        <a href="index.php" class="back-link">&larr; Back to Home</a>
    </div>
</body>
</html>
