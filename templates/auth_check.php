<?php

if (!isset($_SESSION['user_id'])) {
    

    header("Location: " . BASE_URL . "auth/login.php?error=2"); 

    exit;
}

?>