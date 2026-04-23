<?php
require_once 'config/db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: " . $_SESSION['role'] . "/index.php");
    exit;
}

$error = '';
$success = '';

$classes = $pdo->query("SELECT * FROM classes ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $role = $_POST['role_type'] ?? '';
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $fname = trim($_POST['first_name']);
    $lname = trim($_POST['last_name']);

    try {
        $pdo->beginTransaction();
        
        if(empty($username) || empty($password) || empty($fname) || empty($lname)) {
            throw new Exception("Please fill all required basic fields.");
        }

        $center_code = strtoupper(trim($_POST['center_code'] ?? ''));
        if(empty($center_code)) throw new Exception("Center Code is required.");
        
        $stmt_cc = $pdo->prepare("SELECT id FROM centers WHERE center_code = ?");
        $stmt_cc->execute([$center_code]);
        $center = $stmt_cc->fetch();
        if(!$center) throw new Exception("Invalid Center Code. Please verify with your institute.");
        $center_id = $center['id'];

        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) throw new Exception("Username is already taken.");
        
        $hash = password_hash($password, PASSWORD_DEFAULT);

        if ($role === 'student') {
            $roll_no = strtoupper(trim($_POST['roll_no']));
            $class_id = $_POST['class_id'] ? $_POST['class_id'] : null;
            if(empty($roll_no)) throw new Exception("Roll number is required for students.");
            if (strpos($roll_no, 'STU') !== 0) throw new Exception("Roll Number must start with 'STU'.");
            
            $stmt = $pdo->prepare("SELECT id FROM students WHERE roll_no = ?");
            $stmt->execute([$roll_no]);
            if ($stmt->rowCount() > 0) throw new Exception("Roll Number is already registered.");
            
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role, center_id) VALUES (?, ?, 'student', ?)");
            $stmt->execute([$username, $hash, $center_id]);
            $user_id = $pdo->lastInsertId();
            
            $stmt = $pdo->prepare("INSERT INTO students (user_id, first_name, last_name, roll_no, class_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $fname, $lname, $roll_no, $class_id]);

        } elseif ($role === 'parent') {
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone']);
            if(empty($email)) throw new Exception("Email is required for parents.");
            
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role, center_id) VALUES (?, ?, 'parent', ?)");
            $stmt->execute([$username, $hash, $center_id]);
            $user_id = $pdo->lastInsertId();
            
            $stmt = $pdo->prepare("INSERT INTO parents (user_id, first_name, last_name, email, phone) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $fname, $lname, $email, $phone]);
            $parent_id = $pdo->lastInsertId();
            
            // Link children if roll numbers provided
            if (!empty($_POST['student_rolls'])) {
                $rolls = array_map('trim', explode(',', $_POST['student_rolls']));
                foreach ($rolls as $r) {
                    if (!empty($r)) {
                        $upd = $pdo->prepare("UPDATE students SET parent_id = ? WHERE roll_no = ?");
                        $upd->execute([$parent_id, $r]);
                    }
                }
            }
        } else {
            throw new Exception("Please select an account type.");
        }

        $pdo->commit();
        $success = "Registration successful! You may now sign in.";
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
    <title>TuitionCenter - Register</title>
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
            padding: 30px 35px; 
            border-radius: 16px; 
            border: 1px solid #E5E7EB; 
            box-shadow: 0 20px 40px -10px rgba(0,0,0,0.1); 
            width: 100%; 
            flex: 1;
            overflow-y: auto;
            max-height: 100%;
            transition: transform 0.7s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .image-box {
            flex: 1;
            position: relative;
            display: flex;
            min-height: 500px;
            transition: transform 0.7s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .page-wrapper.swapped .login-box {
            transform: translateX(calc(100% + 50px));
        }

        .page-wrapper.swapped .image-box {
            transform: translateX(calc(-100% - 50px));
        }

        .image-layer {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background-size: contain;
            background-position: center;
            background-repeat: no-repeat;
            transition: opacity 0.7s ease-in-out;
        }

        #img-student { background-image: url('assets/images/student_img.jpg'); z-index: 2; opacity: 1; }
        #img-parent { background-image: url('assets/images/parent_img.jpg'); z-index: 1; opacity: 0; }

        @media (max-width: 850px) {
            .page-wrapper { flex-direction: column; max-width: 450px; max-height: none; }
            .image-box { display: none; }
            .login-box { overflow-y: visible; transform: none !important; }
        }
        h2 { text-align: center; margin: 0 0 20px; color: #111827; font-size: 24px; font-weight: 700; letter-spacing: -0.5px; }
        .form-group { margin-bottom: 15px; }
        .row { display: flex; gap: 12px; }
        .row .form-group { flex: 1; }
        label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 13px; color: #111827; }
        input[type="text"], input[type="password"], input[type="email"], select { width: 100%; padding: 10px 12px; border: 1px solid #E5E7EB; background: #FAFAFA; border-radius: 6px; font-size: 13px; transition: all 0.3s; font-family: inherit; box-sizing: border-box; }
        input:focus, select:focus { outline: none; border-color: #111827; background: #FFFFFF; }
        button { width: 100%; padding: 12px; background: linear-gradient(135deg, #6366F1 0%, #8B5CF6 100%); color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; transition: transform 0.2s, box-shadow 0.2s; margin-top: 5px; box-shadow: 0 4px 6px rgba(99, 102, 241, 0.25); }
        button:hover { transform: translateY(-2px); box-shadow: 0 8px 12px rgba(99, 102, 241, 0.35); }
        .error { color: #111827; background: #F3F4F6; padding: 10px; border-left: 4px solid #111827; border-radius: 4px; text-align: center; margin-bottom: 15px; font-size: 13px; font-weight: 500; }
        .success { color: #065F46; background: #ECFDF5; padding: 10px; border-left: 4px solid #10B981; border-radius: 4px; text-align: center; margin-bottom: 15px; font-size: 13px; font-weight: 500; }
        .back-link { display: block; text-align: center; margin-top: 15px; color: #6B7280; font-size: 13px; font-weight: 500; text-decoration: none; transition: color 0.2s; }
        .back-link:hover { color: #111827; }
        
        .role-switch { display: flex; gap: 8px; margin-bottom: 20px; }
        .role-option { flex: 1; cursor: pointer; border: 1px solid #E5E7EB; padding: 10px; border-radius: 6px; text-align: center; font-weight: 500; font-size: 13px; color: #6B7280; transition: all 0.2s; background: #FAFAFA; }
        .role-option.active { border-color: #6366F1; color: #6366F1; background: #EEF2FF; box-shadow: 0 2px 4px rgba(99, 102, 241, 0.1); }
        .role-option input { display: none; }
        .hidden { display: none; }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <div class="login-box">
            <h2>Create Account</h2>

        <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        
        <form method="POST" action="">
            <div class="role-switch">
                <label class="role-option active" id="lbl-student">
                    <input type="radio" name="role_type" value="student" checked onchange="toggleRole('student')">
                    Student
                </label>
                <label class="role-option" id="lbl-parent">
                    <input type="radio" name="role_type" value="parent" onchange="toggleRole('parent')">
                    Parent
                </label>
            </div>

            <div class="row">
                <div class="form-group">
                    <label>First Name</label><input type="text" name="first_name" required>
                </div>
                <div class="form-group">
                    <label>Last Name</label><input type="text" name="last_name" required>
                </div>
            </div>

            <div class="form-group">
                <label>Username</label><input type="text" name="username" required>
            </div>
            
            <div class="form-group">
                <label>Password</label><input type="password" name="password" required>
            </div>
            
            <div class="form-group">
                <label>Center Code</label>
                <input type="text" name="center_code" required placeholder="Provided by your institute (e.g. A1B2C3)" style="text-transform: uppercase;">
            </div>

            <!-- Student Specific -->
            <div id="student-fields">
                <div class="row">
                    <div class="form-group">
                        <label>Roll Number <small style="color:#6B7280;font-weight:normal;">(Must start with STU)</small></label>
                        <input type="text" name="roll_no" id="inp_roll" placeholder="e.g. STU001" pattern="^STU.*" title="Roll number must begin with STU">
                    </div>
                    <div class="form-group">
                        <label>Select Class (Optional)</label>
                        <select name="class_id">
                            <option value="">-- None --</option>
                            <?php foreach($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Parent Specific -->
            <div id="parent-fields" class="hidden">
                <div class="row">
                    <div class="form-group">
                        <label>Email Address</label><input type="email" name="email" id="inp_email">
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label><input type="text" name="phone">
                    </div>
                </div>
                <div class="form-group">
                    <label>Link Students &mdash; <small style="color:#6B7280;font-weight:normal;">Enter child Roll Numbers starting with STU (comma separated)</small></label>
                    <input type="text" name="student_rolls" placeholder="e.g. STU001, STU002">
                </div>
            </div>

            <button type="submit">Create Account</button>
        </form>
        <div style="text-align: center; margin-top: 25px; font-size: 14px;">
            <span style="color: #6B7280;">Already have an account?</span>
            <a href="login.php" style="color: #111827; font-weight: 600; text-decoration: none;">Sign in</a>
        </div>
        <a href="index.php" class="back-link">&larr; Back to Home</a>
        </div>

        <div class="image-box">
            <div id="img-student" class="image-layer"></div>
            <div id="img-parent" class="image-layer"></div>
        </div>
    </div>

    <script>
        function toggleRole(role) {
            document.getElementById('lbl-student').classList.remove('active');
            document.getElementById('lbl-parent').classList.remove('active');
            document.getElementById('lbl-' + role).classList.add('active');

            const wrapper = document.querySelector('.page-wrapper');

            if (role === 'student') {
                wrapper.classList.remove('swapped');
                document.getElementById('student-fields').classList.remove('hidden');
                document.getElementById('parent-fields').classList.add('hidden');
                document.getElementById('inp_roll').required = true;
                document.getElementById('inp_email').required = false;
                
                document.getElementById('img-student').style.opacity = '1';
                document.getElementById('img-parent').style.opacity = '0';
            } else {
                wrapper.classList.add('swapped');
                document.getElementById('student-fields').classList.add('hidden');
                document.getElementById('parent-fields').classList.remove('hidden');
                document.getElementById('inp_roll').required = false;
                document.getElementById('inp_email').required = true;
                
                document.getElementById('img-student').style.opacity = '0';
                document.getElementById('img-parent').style.opacity = '1';
            }
        }
        // Initialize
        toggleRole('student');
    </script>
</body>
</html>
