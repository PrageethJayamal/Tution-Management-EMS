<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$role = $_SESSION['role'];
$username = $_SESSION['username'];
$page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>School Management System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">
        SMS Workspace
    </div>
    <ul class="sidebar-menu">
        <li><a href="index.php" class="<?php echo $page == 'index.php' ? 'active' : ''; ?>">Dashboard</a></li>
        
        <?php if ($role == 'superadmin'): ?>
            <li><a href="centers.php" class="<?php echo $page == 'centers.php' ? 'active' : ''; ?>">Manage Centers</a></li>
        <?php elseif ($role == 'admin'): ?>
            <li><a href="faculty.php" class="<?php echo $page == 'faculty.php' ? 'active' : ''; ?>">Faculty</a></li>
            <li><a href="students.php" class="<?php echo $page == 'students.php' ? 'active' : ''; ?>">Students</a></li>
            <li><a href="parents.php" class="<?php echo $page == 'parents.php' ? 'active' : ''; ?>">Parents</a></li>
            <li><a href="classes.php" class="<?php echo $page == 'classes.php' ? 'active' : ''; ?>">Classes</a></li>
            <li><a href="timetable.php" class="<?php echo $page == 'timetable.php' ? 'active' : ''; ?>">Timetable</a></li>
            <li><a href="logs.php" class="<?php echo $page == 'logs.php' ? 'active' : ''; ?>">Activity Logs</a></li>
        <?php elseif ($role == 'faculty'): ?>
            <li><a href="attendance.php" class="<?php echo $page == 'attendance.php' ? 'active' : ''; ?>">Attendance</a></li>
            <li><a href="grades.php" class="<?php echo $page == 'grades.php' ? 'active' : ''; ?>">Grades</a></li>
            <li><a href="remarks.php" class="<?php echo $page == 'remarks.php' ? 'active' : ''; ?>">Remarks</a></li>
        <?php elseif ($role == 'student'): ?>
            <li><a href="attendance.php" class="<?php echo $page == 'attendance.php' ? 'active' : ''; ?>">My Attendance</a></li>
            <li><a href="grades.php" class="<?php echo $page == 'grades.php' ? 'active' : ''; ?>">My Grades</a></li>
            <li><a href="profile.php" class="<?php echo $page == 'profile.php' ? 'active' : ''; ?>">Edit Profile</a></li>
        <?php elseif ($role == 'parent'): ?>
            <li><a href="attendance.php" class="<?php echo $page == 'attendance.php' ? 'active' : ''; ?>">Child Attendance</a></li>
            <li><a href="grades.php" class="<?php echo $page == 'grades.php' ? 'active' : ''; ?>">Child Grades</a></li>
        <?php endif; ?>
    </ul>
</div>

<div class="main-wrapper">
    <div class="topbar">
        <div class="breadcrumb">
            <?php echo ucfirst($role); ?> Portal
        </div>
        <div class="user-info">
            Welcome, <?php echo htmlspecialchars($username); ?> | 
            <a href="../logout.php" class="btn btn-danger btn-sm" style="margin-left:10px;">Logout</a>
        </div>
    </div>
    <div class="content">
