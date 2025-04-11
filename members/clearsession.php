<?php
require_once '../config.php';

session_start();

if (isset($_GET['clear']) && $_GET['clear'] === 'success') {
    unset($_SESSION['success']);
    unset($_SESSION['member_number']);
    unset($_SESSION['member_name']);
}

echo 'OK';
?>