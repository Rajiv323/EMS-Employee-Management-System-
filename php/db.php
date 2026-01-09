<?php
$host = "localhost";
$user = "root";     // your XAMPP/WAMP username
$pass = "";         // your DB password
$db   = "ems";   // your database name

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "DB Connection failed"]));
}
?>
