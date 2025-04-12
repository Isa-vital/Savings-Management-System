<?php
define('BASE_DIR', __DIR__);
define('BASE_URL', '/savingssystem');

// Then in logout.php:
require_once BASE_DIR . '/config.php';
header("Location: " . BASE_URL . "/auth/login.php");