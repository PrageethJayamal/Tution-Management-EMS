<?php
$host = '127.0.0.1';
$db = 'school_mng';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Connection failed: " . $e->getMessage());
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function log_activity($pdo, $user_id, $action, $context = '') {
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, context) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $action, $context]);
    } catch (Exception $e) { /* Silently fail if table not generated */ }
}
?>
