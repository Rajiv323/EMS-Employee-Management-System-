<?php
date_default_timezone_set('Asia/Kathmandu');

$host = "localhost";
$user = "root";     // your XAMPP/WAMP username
$pass = "";         // your DB password
$db   = "ems";   // your database name

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "DB Connection failed"]));
}
// Auto-reject pending leave requests whose start date is today or earlier
// This runs on every page load where db.php is included to ensure stale pending
// requests are cleaned up without requiring a specific page visit.
try {
    $today = date('Y-m-d');
    $auto_reject = $conn->prepare("UPDATE leave_requests SET status = 'Rejected', remarks = 'Auto-rejected: Leave start date has passed' WHERE status = 'Pending' AND start_date <= ?");
    if ($auto_reject) {
        $auto_reject->bind_param('s', $today);
        $auto_reject->execute();
        $auto_reject->close();
    }
} catch (Exception $e) {
    // ignore failures here to avoid breaking site if table missing
}
?>
