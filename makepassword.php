<?php
require('dbconnect.php');
$username = 'Ad';
$password = password_hash('f', PASSWORD_DEFAULT);

$stmt = $mysqli->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
$stmt->bind_param("ss", $username, $password);
$stmt->execute();
echo "Admin user created.";
?>
