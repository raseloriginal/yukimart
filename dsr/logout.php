<?php
session_start();
unset($_SESSION['dsr_id']);
unset($_SESSION['dsr_role']);
unset($_SESSION['dsr_name']);
header('Location: login.php');
exit;
?>
