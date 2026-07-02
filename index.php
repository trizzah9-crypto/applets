<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}
 // Redirect to prevent re-submission
    header("Location: dashboard.php");

?>


