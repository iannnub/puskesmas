<?php

require_once 'config.php';

if (isset($_SESSION['user_id'])) {

    header("Location: " . BASE_URL . "dashboard.php");
    exit;
    
} else {
    

    header("Location: " . BASE_URL . "auth/login.php");
    exit;
    
}

?>