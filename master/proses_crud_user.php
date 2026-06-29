<?php

require_once '../config.php';

require_once '../templates/auth_check.php';

if ($_SESSION['role_id'] != 1) {

    header("Location: " . BASE_URL . "master/data_user.php?status=gagal_akses");
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {

    switch ($action) {

        case 'create':
           
            $nama_lengkap = htmlspecialchars($_POST['nama_lengkap']);
            $username = htmlspecialchars($_POST['username']);
            $password_dari_form = $_POST['password']; 
            $id_role = (int)$_POST['id_role'];
            $id_poli_input = $_POST['id_poli']; 

  
            if (empty($nama_lengkap) || empty($username) || empty($password_dari_form) || empty($id_role)) {
                throw new Exception("Semua field (kecuali Poli) wajib diisi.");
            }
            if (strlen($password_dari_form) < 6) {
                throw new Exception("Password minimal 6 karakter.");
            }
            
   
            $id_poli = NULL;
            if ($id_role == 3 || $id_role == 4) { 
                if ($id_poli_input == "NULL" || empty($id_poli_input)) {
                    throw new Exception("Role Poli wajib memilih 'Terkait Poli'.");
                }
                $id_poli = (int)$id_poli_input;
            }

           
            $hashed_password = password_hash($password_dari_form, PASSWORD_BCRYPT);

            $sql = "INSERT INTO tbl_user (id_role, id_poli, username, password, nama_lengkap, is_active) 
                    VALUES (?, ?, ?, ?, ?, 1)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_role, $id_poli, $username, $hashed_password, $nama_lengkap]);

        
            header("Location: " . BASE_URL . "master/data_user.php?status=tambah_sukses");
            exit;

        case 'update':

            $id_user_edit = (int)$_POST['id_user'];
            $nama_lengkap = htmlspecialchars($_POST['nama_lengkap']);
            $username = htmlspecialchars($_POST['username']);
            $password_dari_form = $_POST['password']; 
            $id_role = (int)$_POST['id_role'];
            $id_poli_input = $_POST['id_poli'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
           
            $id_poli = NULL;
            if ($id_role == 3 || $id_role == 4) {
                $id_poli = (int)$id_poli_input;
            }
            
           
            if (!empty($password_dari_form)) {
                
                if (strlen($password_dari_form) < 6) {
                    throw new Exception("Password baru minimal 6 karakter.");
                }
                $hashed_password = password_hash($password_dari_form, PASSWORD_BCRYPT);
                
               
                $sql = "UPDATE tbl_user SET id_role=?, id_poli=?, username=?, password=?, nama_lengkap=?, is_active=?
                        WHERE id_user=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id_role, $id_poli, $username, $hashed_password, $nama_lengkap, $is_active, $id_user_edit]);
                
            } else {
         
                
              
                $sql = "UPDATE tbl_user SET id_role=?, id_poli=?, username=?, nama_lengkap=?, is_active=?
                        WHERE id_user=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id_role, $id_poli, $username, $nama_lengkap, $is_active, $id_user_edit]);
            }
            
         
            header("Location: " . BASE_URL . "master/data_user.php?status=update_sukses");
            exit;

  
        case 'delete':
         
            $id_user_hapus = (int)$_GET['id'];
       
            if ($id_user_hapus == $_SESSION['user_id']) {
                throw new Exception("Anda tidak bisa menghapus akun Anda sendiri.");
            }
         
            if ($id_user_hapus == 1) { 
                 throw new Exception("Akun root Super Admin tidak boleh dihapus.");
            }

            $sql = "DELETE FROM tbl_user WHERE id_user = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_user_hapus]);

            header("Location: " . BASE_URL . "master/data_user.php?status=hapus_sukses");
            exit;

        default:
            throw new Exception("Aksi tidak dikenali.");
    }

} catch (Exception $e) {

    echo "Error: " . $e->getMessage();
    echo "<br><a href='" . BASE_URL . "master/data_user.php'>Kembali ke Data User</a>";

}
?>