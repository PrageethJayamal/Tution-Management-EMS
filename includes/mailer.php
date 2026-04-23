<?php
/**
 * Helper function to systematically broadcast notice emails 
 * to proper faculty and parent audiences within a specific center.
 */
function send_notice_emails(PDO $pdo, $center_id, $audience, $author_name, $title, $message) {
    $recipient_emails = [];

    // Filter recipients based on the chosen audience constraints.
    if ($audience == 'general' || $audience == 'faculty') {
        if ($center_id === 0) { // 0 implies Superadmin global execution across all branches
            $stmt = $pdo->prepare("SELECT email FROM faculty WHERE email IS NOT NULL AND email != ''");
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare("SELECT email FROM faculty f JOIN users u ON f.user_id = u.id WHERE u.center_id = ? AND f.email IS NOT NULL AND f.email != ''");
            $stmt->execute([$center_id]);
        }
        $fac_emails = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $recipient_emails = array_merge($recipient_emails, $fac_emails);
    }

    if ($audience == 'general' || $audience == 'parent' || $audience == 'student') {
        // Students don't technically have emails mandated in schema, their assigned parents act as their proxy. 
        if ($center_id === 0) {
            $stmt = $pdo->prepare("SELECT email FROM parents WHERE email IS NOT NULL AND email != ''");
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare("SELECT email FROM parents p JOIN users u ON p.user_id = u.id WHERE u.center_id = ? AND p.email IS NOT NULL AND p.email != ''");
            $stmt->execute([$center_id]);
        }
        $par_emails = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $recipient_emails = array_merge($recipient_emails, $par_emails);
    }
    
    // Deduplicate any overlapping entries
    $recipient_emails = array_unique($recipient_emails);

    if (empty($recipient_emails)) {
        return false; // Nobody to send to
    }

    $subject = "New Class Announcement: " . $title;
    
    // Constructing a robust HTML envelope template natively
    $html_body = "
    <html>
    <head><title>Announcement</title></head>
    <body style='font-family: Arial, sans-serif; color: #333; line-height: 1.6;'>
        <div style='border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; max-width: 600px; margin: auto;'>
            <h2 style='color: #4F46E5; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; margin-top: 5px;'>$title</h2>
            <p style='font-size: 13px; color: #64748b; margin-bottom: 15px;'>Published by: <strong>$author_name</strong></p>
            <div style='padding: 15px; background: #f8fafc; border-radius: 6px; margin: 20px 0; border: 1px solid #e2e8f0;'>
                " . nl2br(htmlspecialchars($message)) . "
            </div>
            <p style='font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 10px; text-align: center;'>
                This is an automated notification spawned by the Tuition Administration system.
            </p>
        </div>
    </body>
    </html>
    ";

    // Required headers for native HTML mailing compilation
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: admin-noreply@tuitionmanagement.com\r\n";
    // We heavily rely on Bcc (Blind Carbon Copy) so parents cannot indiscriminately see each other's emails.
    $headers .= "Bcc: " . implode(',', $recipient_emails) . "\r\n";

    // Attempt silent dispatcher execution.
    // In local development like XAMPP, this routes perfectly into xampp/mailoutput files.
    @mail("undisclosed-recipients@tuitionmanagement.com", $subject, $html_body, $headers);
    return true;
}
?>
