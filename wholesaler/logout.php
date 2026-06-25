<?php
session_start();
unset($_SESSION['wholesaler_id']);
unset($_SESSION['wholesaler_role']);
unset($_SESSION['wholesaler_name']);
header('Location: login.php');
exit;
?>
