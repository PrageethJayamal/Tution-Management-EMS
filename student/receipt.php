<?php
require_once '../config/db.php';


if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['student', 'parent'])) {
    die("Access Denied");
}

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
if (!$student_id) die("Invalid Target");

// Security Check: Make sure the logged-in user is either this student, or the parent of this student.
$is_authorized = false;
$user_id = $_SESSION['user_id'];
$center_id = $_SESSION['center_id'];

if ($_SESSION['role'] === 'student') {
    // students table doesn't have center_id, so join with users
    $stmt = $pdo->prepare("SELECT s.id FROM students s JOIN users u ON s.user_id = u.id WHERE s.id = ? AND s.user_id = ? AND u.center_id = ?");
    $stmt->execute([$student_id, $user_id, $center_id]);
    if ($stmt->fetch()) $is_authorized = true;
} else if ($_SESSION['role'] === 'parent') {
    // Parent logic mapping user_id -> parent_id -> student.parent_id
    $stmt = $pdo->prepare("SELECT s.id FROM students s JOIN parents p ON s.parent_id = p.id WHERE s.id = ? AND p.user_id = ?");
    $stmt->execute([$student_id, $user_id]);
    if ($stmt->fetch()) $is_authorized = true;
}

if (!$is_authorized) {
    die("Unauthorized request for this receipt.");
}

// Fetch Full Information for Invoice
$stmt = $pdo->prepare("
    SELECT s.*, c.name as class_name, ctr.name as center_name, ctr.center_code
    FROM students s 
    JOIN users u ON s.user_id = u.id
    LEFT JOIN classes c ON s.class_id = c.id 
    JOIN centers ctr ON u.center_id = ctr.id
    WHERE s.id = ? AND s.payment_status = 'paid'
");
$stmt->execute([$student_id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    die("Error: Invoice cannot be generated because payment status is unpaid or record doesn't exist.");
}

$invoice_number = "INV-" . date('Ym') . "-" . str_pad($invoice['id'], 5, "0", STR_PAD_LEFT);
$date_issued = date('F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt - <?php echo $invoice_number; ?></title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4F46E5;
            --text-main: #111827;
            --text-muted: #6B7280;
            --border: #E5E7EB;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: #f3f4f6;
            margin: 0;
            padding: 40px;
            color: var(--text-main);
        }
        .invoice-box {
            max-width: 800px;
            margin: auto;
            padding: 50px;
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            background-color: white;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid var(--border);
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: var(--primary);
            font-size: 32px;
            margin: 0 0 5px 0;
        }
        .company-details {
            text-align: right;
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.6;
        }
        .invoice-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
        }
        .bill-to p { margin: 5px 0; color: var(--text-muted); font-size: 15px; }
        .bill-to h3 { margin: 0 0 10px 0; font-size: 18px; }
        .meta-data p { margin: 5px 0; font-size: 15px; text-align: right; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid var(--border); }
        th { background: #F9FAFB; font-weight: 600; color: var(--text-muted); text-transform: uppercase; font-size: 12px; }
        td { font-size: 15px; }
        .total-row { font-weight: bold; font-size: 18px; }
        .total-row td { border-top: 2px solid var(--text-main); }
        
        .footer { text-align: center; color: var(--text-muted); font-size: 14px; margin-top: 50px; }
        .footer p { margin: 5px 0; }
        
        .print-btn-container { text-align: center; margin-bottom: 30px; }
        .btn-print {
            background-color: var(--primary); color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; font-size: 16px; cursor: pointer; transition: 0.2s ease; font-family: 'Inter', sans-serif;
        }
        .btn-print:hover { background-color: #4338CA; }

        .payment-stamp {
            display: inline-block;
            color: #059669;
            background: #D1FAE5;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
            border: 1px solid #10B981;
            text-transform: uppercase;
        }

        /* 
         * The crucial CSS that makes this act like a PDF generator natively 
         */
        @media print {
            body { background: white; padding: 0; }
            .invoice-box { box-shadow: none; border: none; padding: 0; max-width: 100%; }
            .print-btn-container { display: none; }
            @page { margin: 2cm; }
        }
    </style>
</head>
<body>

<div class="print-btn-container">
    <button class="btn-print" onclick="window.print()">🖨️ Print / Save as PDF</button>
</div>

<div class="invoice-box">
    <div class="header">
        <div>
            <h1>OFFICIAL RECEIPT</h1>
            <div class="payment-stamp">✓ Payment Settled</div>
        </div>
        <div class="company-details">
            <strong style="color:var(--text-main); font-size:16px;"><?php echo htmlspecialchars($invoice['center_name']); ?></strong><br>
            Branch Code: <?php echo htmlspecialchars($invoice['center_code']); ?><br>
            Multi-Center Regional Office<br>
            contact@tuitionnetwork.com
        </div>
    </div>
    
    <div class="invoice-details">
        <div class="bill-to">
            <h3>Student Profile</h3>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']); ?></p>
            <p><strong>Roll No:</strong> <?php echo htmlspecialchars($invoice['roll_no']); ?></p>
        </div>
        <div class="meta-data">
            <p><strong>Receipt ID:</strong> <?php echo $invoice_number; ?></p>
            <p><strong>Date Issued:</strong> <?php echo $date_issued; ?></p>
            <p><strong>Term:</strong> Academic Year 2026</p>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th style="width: 25%;">Academic Class</th>
                <th style="width: 20%; text-align: right;">Amount Recieved</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Monthly Tuition Standard Fee Installment</td>
                <td><?php echo htmlspecialchars($invoice['class_name'] ?? 'Unassigned'); ?></td>
                <td style="text-align: right;">$ 150.00</td> <!-- Hardcoded for structural integrity as no explicit term-fee struct exists yet -->
            </tr>
            <tr class="total-row">
                <td colspan="2" style="text-align: right;">Total Paid</td>
                <td style="text-align: right;">$ 150.00</td>
            </tr>
        </tbody>
    </table>
    
    <div class="footer">
        <p>Thank you for choosing <?php echo htmlspecialchars($invoice['center_name']); ?> for your continuous education.</p>
        <p>This is a computer-generated document and requires no physical signature.</p>
    </div>
</div>

</body>
</html>
