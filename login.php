<?php
require_once 'config/db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: " . $_SESSION['role'] . "/index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    $role_requested = $_GET['role'] ?? 'student';

    if ($user && password_verify($password, $user['password'])) {
        
        // Strict Authorization Gateway (Prevent Admin Logging into Faculty Portal, etc)
        $auth_secure = false;
        if ($user['role'] === $role_requested) {
            $auth_secure = true;
        } elseif ($user['role'] === 'superadmin' && $role_requested === 'admin') {
            $auth_secure = true;
        }
        
        if (!$auth_secure) {
            $error = "Authentication Context Mismatch: These credentials are valid but do not belong to a " . ucfirst($role_requested) . " portal.";
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['center_id'] = $user['center_id'];
            
            header("Location: " . $user['role'] . "/index.php");
            exit;
        }
    } else {
        $error = "Invalid username or password!";
    }
}

$role_requested = $_GET['role'] ?? 'student';
$role_title = 'Welcome Back';
$bg_image = 'assets/images/student_img.jpg';

if ($role_requested === 'admin') {
    $role_title = 'Institute Login';
} elseif ($role_requested === 'faculty') {
    $role_title = 'Faculty Login';
} elseif ($role_requested === 'student') {
    $role_title = 'Student Login';
} elseif ($role_requested === 'parent') {
    $role_title = 'Parent Login';
    $bg_image = 'assets/images/parent_img.jpg';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TuitionCenter - Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #FFFFFF; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; color: #111827; padding: 20px; box-sizing: border-box; -webkit-font-smoothing: antialiased; }
        
        .page-wrapper {
            display: flex;
            gap: 50px;
            width: 100%;
            max-width: 900px;
            align-items: center;
            max-height: 90vh;
        }

        .login-box { 
            background: #FFFFFF; 
            padding: 40px 45px; 
            border-radius: 16px; 
            border: 1px solid #E5E7EB; 
            box-shadow: 0 20px 40px -10px rgba(0,0,0,0.1); 
            width: 100%; 
            flex: 1;
        }

        .image-box {
            flex: 1;
            position: relative;
            display: flex;
            min-height: 500px;
            background-image: url('<?php echo $bg_image; ?>');
            background-size: contain;
            background-position: center;
            background-repeat: no-repeat;
        }

        @media (max-width: 850px) {
            .page-wrapper { flex-direction: column; max-width: 450px; max-height: none; }
            .image-box { display: none; }
        }

        h2 { text-align: center; margin: 0 0 25px; color: #111827; font-size: 26px; font-weight: 700; letter-spacing: -0.5px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; color: #111827; }
        input[type="text"], input[type="password"] { width: 100%; padding: 12px 15px; border: 1px solid #E5E7EB; background: #FAFAFA; border-radius: 8px; font-size: 14px; transition: all 0.3s; font-family: inherit; box-sizing: border-box; }
        input:focus { outline: none; border-color: #111827; background: #FFFFFF; }
        
        button { width: 100%; padding: 14px; background: linear-gradient(135deg, #6366F1 0%, #8B5CF6 100%); color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 15px; font-weight: 600; transition: transform 0.2s, box-shadow 0.2s; margin-top: 10px; box-shadow: 0 4px 6px rgba(99, 102, 241, 0.25); }
        button:hover { transform: translateY(-2px); box-shadow: 0 8px 12px rgba(99, 102, 241, 0.35); }
        
        .error { color: #111827; background: #F3F4F6; padding: 12px; border-left: 4px solid #111827; border-radius: 4px; text-align: center; margin-bottom: 20px; font-size: 14px; font-weight: 500; }
        .back-link { display: block; text-align: center; margin-top: 25px; color: #6B7280; font-size: 14px; font-weight: 500; text-decoration: none; transition: color 0.2s; }
        .back-link:hover { color: #111827; }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <div class="login-box">
            <h2><?php echo $role_title; ?></h2>
            
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required autocomplete="username">
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required autocomplete="current-password">
                </div>

                <button type="submit">Sign In</button>
            </form>
            
            <div style="text-align: center; margin-top: 25px; font-size: 14px;">
                <span style="color: #6B7280;">New to TuitionCenter?</span>
                <a href="register.php" style="color: #111827; font-weight: 600; text-decoration: none;">Create an account</a>
            </div>
            
            <?php if ($role_requested === 'admin'): ?>
            <div style="text-align: center; margin-top: 15px; font-size: 14px;">
                <span style="color: #6B7280;">Want to enroll your institute?</span>
                <a href="center_register.php" style="color: #6366F1; font-weight: 600; text-decoration: none;">Register Institute</a>
            </div>
            <?php endif; ?>

            <a href="index.php#portals" class="back-link">&larr; Back to Home</a>
        </div>
        
        <div class="image-box"></div>
    </div>
</body>
</html>
