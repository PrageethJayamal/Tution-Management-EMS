<?php
require_once '../config/db.php';
require_once '../includes/header.php';

if ($_SESSION['role'] !== 'student') die("<div class='alert alert-error'>Access Denied</div>");

$message = ''; $error = '';
$user_id = $_SESSION['user_id'];

// Get current student identity and profile data
$stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = ?");
$stmt->execute([$user_id]);
$student = $stmt->fetch();

if (!$student) {
    die("Student profile missing. Please contact administration.");
}

$student_id = $student['id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_profile') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $new_password = trim($_POST['new_password']);
    
    // File Upload Handler Subsystem
    $upload_success = true;
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $filename = $_FILES['profile_photo']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $target_dir = "../assets/uploads/profiles/";
            $new_filename = 'stu_' . $student_id . '_' . time() . '.' . $ext;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_file)) {
                // Delete old profile photo if exists to conserve server disk space
                if (!empty($student['profile_photo']) && file_exists("../" . $student['profile_photo'])) {
                    unlink("../" . $student['profile_photo']);
                }
                
                // Update photo tracker path
                $photo_path = "assets/uploads/profiles/" . $new_filename;
                $pdo->prepare("UPDATE students SET profile_photo = ? WHERE id = ?")->execute([$photo_path, $student_id]);
            } else {
                $upload_success = false;
                $error = "Failed to secure physical file payload on the server disk.";
            }
        } else {
            $upload_success = false;
            $error = "Invalid file type. Only JPG and PNG allowed.";
        }
    }
    
    if ($upload_success) {
        try {
            $pdo->beginTransaction();
            
            // Update Core Details
            $update_stmt = $pdo->prepare("UPDATE students SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?");
            $update_stmt->execute([$first_name, $last_name, $email, $phone, $student_id]);
            
            // Update Credentials if requested by student
            if (!empty($new_password)) {
                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $user_id]);
            }
            
            $pdo->commit();
            $message = "Profile configuration successfully modified!";
            
            // Refresh data buffer
            $stmt->execute([$user_id]);
            $student = $stmt->fetch();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Database Modification Execution Fault: " . $e->getMessage();
        }
    }
}
?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">Account Profile Settings</div>
    
    <?php if ($message): ?> <div class="alert alert-success"><?php echo $message; ?></div> <?php endif; ?>
    <?php if ($error): ?> <div class="alert alert-error"><?php echo $error; ?></div> <?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data">
        <input type="hidden" name="action" value="update_profile">
        
        <div style="display: flex; gap: 40px; margin-top: 20px; flex-wrap: wrap;">
            
            <!-- Avatar Upload Masking Zone -->
            <div style="text-align: center; flex: 1; min-width: 200px;">
                <div style="width: 150px; height: 150px; background: #e2e8f0; border-radius: 50%; border: 4px solid var(--border); overflow: hidden; margin: 0 auto; box-shadow: 0 4px 6px rgba(0,0,0,0.1); position: relative; display: flex; align-items: center; justify-content: center;">
                    <?php if (!empty($student['profile_photo'])): ?>
                        <img src="../<?php echo htmlspecialchars($student['profile_photo']); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <span style="font-size: 50px; color: #94a3b8; font-weight: bold;"><?php echo strtoupper(substr($student['first_name'],0,1)); ?></span>
                    <?php endif; ?>
                </div>
                
                <div style="margin-top: 20px;">
                    <label style="display: block; font-weight: bold; font-size: 14px; margin-bottom: 8px; color: var(--text-main);">Upload Portrait (.JPG or .PNG)</label>
                    <input type="file" name="profile_photo" accept="image/png, image/jpeg, image/jpg" style="font-size: 13px; max-width: 100%;" class="form-control">
                </div>
            </div>
            
            <!-- Core Form Structure -->
            <div style="flex: 2; min-width: 300px;">
                <h4 style="margin-top: 0; color: var(--text-main); margin-bottom: 15px; border-bottom: 2px solid var(--border); padding-bottom: 5px;">Basic Information</h4>
                
                <div style="display: flex; gap: 15px;">
                    <div class="form-group" style="flex: 1;">
                        <label>First Name</label>
                        <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($student['first_name']); ?>" required>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Last Name</label>
                        <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($student['last_name']); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Contact Email Address</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($student['email'] ?? ''); ?>" placeholder="john.doe@example.com">
                </div>

                <div class="form-group">
                    <label>Mobile Number</label>
                    <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>" placeholder="(555) 123-4567">
                </div>

                <h4 style="margin-top: 30px; color: var(--text-main); margin-bottom: 15px; border-bottom: 2px solid var(--border); padding-bottom: 5px;">Security Controls</h4>
                
                <div class="form-group">
                    <label>Reset Account Password</label>
                    <input type="password" name="new_password" class="form-control" placeholder="Leave explicitly empty to keep strictly unchanged">
                </div>
                
                <div style="margin-top: 25px; text-align: right;">
                    <button type="submit" class="btn btn-primary" style="padding: 10px 30px;">Save Profile Layout</button>
                </div>
            </div>
        </div>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>
