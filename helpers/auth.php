<?php
// auth.php
function require_admin() {
    if (!isset($_SESSION['user'])) {
        $_SESSION['error'] = "Please login first";
        header('Location: ../index.php');
        exit();
    }
    
    if ($_SESSION['user']['role'] !== 'admin') {
        $_SESSION['error'] = "Admin privileges required";
        header('Location: ../index.php');
        exit();
    }
}

function require_login() {
    if (!isset($_SESSION['user'])) {
        $_SESSION['error'] = "Please login first";
        header('Location: ../index.php');
        exit();
    }
}
?>