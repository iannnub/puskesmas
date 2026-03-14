<?php

require_once '../config.php';


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
   
    $username = htmlspecialchars($_POST['username']);
    $password_dari_form = htmlspecialchars($_POST['password']);

    
    if (empty($username) || empty($password_dari_form)) {
        
        header("Location: " . BASE_URL . "auth/login.php?error=1");
        exit;
    }

    try {
       
        $sql = "SELECT 
                    u.id_user, 
                    u.username, 
                    u.password, 
                    u.nama_lengkap, 
                    u.id_role, 
                    u.id_poli,
                    r.nama_role,
                    p.id_unit_stok_default 
                FROM 
                    tbl_user u
                JOIN 
                    tbl_role r ON u.id_role = r.id_role
                LEFT JOIN 
                    tbl_poli p ON u.id_poli = p.id_poli
                WHERE 
                    u.username = ? AND u.is_active = 1";
        
       
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username]);

      
        $user = $stmt->fetch();

       
        if ($user && password_verify($password_dari_form, $user['password'])) {

            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id_user'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['role_id'] = $user['id_role'];       
            $_SESSION['role_nama'] = $user['nama_role'];   
            
           
            $_SESSION['poli_id'] = $user['id_poli'];      
            $_SESSION['unit_stok_id'] = $user['id_unit_stok_default']; 

            
            header("Location: " . BASE_URL . "dashboard.php");
            exit;

        } else {
           
            header("Location: " . BASE_URL . "auth/login.php?error=1");
            exit;
        }

    } catch (PDOException $e) {
        
        die("Error query: " . $e->getMessage());
    }

} else {
   
    header("Location: " . BASE_URL . "auth/login.php");
    exit;
}
?>