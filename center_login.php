<?php
require_once 'config/db.php';

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: index.php");
        exit;
    } else {
        header("Location: " . $_SESSION['role'] . "/index.php");
        exit;
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role IN ('admin', 'superadmin')");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['center_id'] = $user['center_id'];
        
        if ($user['role'] === 'superadmin') {
            header("Location: superadmin/index.php");
        } else {
            header("Location: index.php");
        }
        exit;
    } else {
        $error = "Invalid center administrator credentials!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TuitionCenter - Institute Access</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #FFFFFF; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; color: #111827; padding: 20px; box-sizing: border-box; -webkit-font-smoothing: antialiased; }
        .page-wrapper { display: flex; gap: 50px; width: 100%; max-width: 1000px; align-items: center; max-height: 90vh; }
        .login-box { background: #FFFFFF; padding: 50px; border-radius: 16px; border: 1px solid #E5E7EB; box-shadow: 0 20px 40px -10px rgba(0,0,0,0.1); width: 100%; flex: 1; }
        .image-box { flex: 1.2; position: relative; display: flex; min-height: 550px; background: linear-gradient(135deg, #6366F1 0%, #8B5CF6 100%); border-radius: 20px; padding: 50px; color: white; flex-direction: column; justify-content: center; overflow: hidden; }
        .image-box h1 { font-size: 46px; line-height: 1.1; margin: 0 0 20px; font-weight: 700; letter-spacing: -1.5px; }
        .image-box p { font-size: 18px; opacity: 0.9; margin: 0; line-height: 1.5; font-weight: 300; }
        .image-box::after { content: ''; position: absolute; right: -50px; bottom: -50px; width: 300px; height: 300px; background: rgba(255,255,255,0.1); border-radius: 50%; pointer-events: none; }
        .image-box::before { content: ''; position: absolute; left: -50px; top: -50px; width: 150px; height: 150px; background: rgba(255,255,255,0.1); border-radius: 50%; pointer-events: none; }
        @media (max-width: 850px) { .page-wrapper { flex-direction: column; max-width: 450px; max-height: none; } .image-box { display: none; } }
        h2 { margin: 0 0 10px; color: #111827; font-size: 28px; font-weight: 700; letter-spacing: -0.5px; }
        .subtitle { color: #6B7280; font-size: 15px; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; color: #111827; }
        input[type="text"], input[type="password"] { width: 100%; padding: 14px; border: 1px solid #E5E7EB; background: #FAFAFA; border-radius: 8px; font-size: 15px; transition: all 0.3s; font-family: inherit; box-sizing: border-box; }
        input:focus { outline: none; border-color: #111827; background: #FFFFFF; }
        button { width: 100%; padding: 15px; background: #111827; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 15px; font-weight: 600; transition: transform 0.2s, box-shadow 0.2s; margin-top: 10px; }
        button:hover { transform: translateY(-2px); box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2); }
        .error { color: #111827; background: #F3F4F6; padding: 12px; border-left: 4px solid #111827; border-radius: 4px; margin-bottom: 20px; font-size: 14px; font-weight: 500; }
        .register-link { display: block; margin-top: 25px; font-size: 15px; color: #6B7280; }
        .register-link a { color: #6366F1; font-weight: 600; text-decoration: none; transition: color 0.2s; }
        .register-link a:hover { color: #4F46E5; }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <div class="login-box">
            <h2>Institute Login</h2>
            <p class="subtitle">Enter your center credentials to continue.</p>
            
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label>Administrator Username</label>
                    <input type="text" name="username" required autocomplete="username">
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required autocomplete="current-password">
                </div>

                <button type="submit">Access Platform</button>
            </form>
            
            <div class="register-link">
                Don't have an account? <a href="center_register.php">Register your Center</a>
            </div>
            
            <div style="margin-top: 30px; font-size: 13px; color: #9CA3AF; border-top: 1px solid #E5E7EB; padding-top: 20px;">
                <p style="margin: 0 0 5px;">Student, Faculty, or Parent?</p>
                <a href="index.php#portals" style="color: #6B7280; text-decoration: none;">Go to Sub-Portals →</a>
            </div>
        </div>
        
        <div class="image-box">
            <h1>TuitionCenter.</h1>
            <p>The centralized platform to manage your educational institute. Log in to access your administrative tools, monitor student performance, and coordinate your faculty seamlessly.</p>
        </div>
    </div>
</body>
</html>
