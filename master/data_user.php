<?php

require_once '../config.php';


require_once '../templates/auth_check.php';

if ($_SESSION['role_id'] != 1) {
    echo "<script>alert('Akses Ditolak! Anda bukan Super Admin.'); window.location.href = '" . BASE_URL . "dashboard.php';</script>";
    exit;
}

$page_title = "Kelola Akun User";


try {

    $stmt_role = $pdo->query("SELECT id_role, nama_role FROM tbl_role ORDER BY nama_role ASC");
    $roles = $stmt_role->fetchAll();

    $stmt_poli_depan = $pdo->query("SELECT id_poli, nama_poli 
                                   FROM tbl_poli 
                                   WHERE id_unit_stok_default = 2 
                                   ORDER BY nama_poli ASC");
    $polis_depan = $stmt_poli_depan->fetchAll();

    $stmt_poli_belakang = $pdo->query("SELECT id_poli, nama_poli 
                                      FROM tbl_poli 
                                      WHERE id_unit_stok_default NOT IN (1, 2) 
                                      ORDER BY nama_poli ASC");
    $polis_belakang = $stmt_poli_belakang->fetchAll();

    $sql_users = "SELECT 
                    u.*, 
                    r.nama_role,
                    p.nama_poli
                  FROM 
                    tbl_user u
                  JOIN 
                    tbl_role r ON u.id_role = r.id_role
                  LEFT JOIN 
                    tbl_poli p ON u.id_poli = p.id_poli
                  ORDER BY 
                    u.id_user ASC";
    $stmt_users = $pdo->query($sql_users);
    $users = $stmt_users->fetchAll();

} catch (PDOException $e) {

    die("Error mengambil data: " . $e->getMessage());
}

include '../templates/header.php';

?>

<main class="content">
    
    <h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
    
    <p>Di sini Anda bisa menambah, mengedit, dan menghapus akun user untuk semua role.</p>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Tambah User Baru</h6>
        </div>
        <div class="card-body">
            
            <?php if (isset($_GET['status'])): ?>
                <?php if ($_GET['status'] == 'tambah_sukses'): ?>
                    <div class="alert alert-success">User baru berhasil ditambahkan!</div>
                <?php elseif ($_GET['status'] == 'update_sukses'): ?>
                    <div class="alert alert-info">Data user berhasil diupdate!</div>
                <?php elseif ($_GET['status'] == 'hapus_sukses'): ?>
                    <div class="alert alert-success">User berhasil dihapus!</div>
                <?php elseif ($_GET['status'] == 'gagal'): ?>
                     <div class="alert alert-danger">Operasi gagal. Silakan coba lagi.</div>
                <?php endif; ?>
            <?php endif; ?>

            <form action="<?php echo BASE_URL; ?>master/proses_crud_user.php" method="POST">
                <input type="hidden" name="action" value="create">
                
                <div class="form-row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="nama_lengkap">Nama Lengkap</label>
                            <input type="text" id="nama_lengkap" name="nama_lengkap" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" class="form-control" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" class="form-control" required>
                            <small class="form-text text-muted">(Min. 6 karakter)</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                         <div class="form-group">
                            <label for="id_role">Role / Peran</label>
                            <select id="id_role" name="id_role" class="form-control" required>
                                <option value="">-- Pilih Role --</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['id_role']; ?>"><?php echo htmlspecialchars($role['nama_role']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-row" id="baris_terkait_poli" style="display: none;"> <div class="col-md-6">
                        <div class="form-group">
                            <label for="id_poli">Terkait Poli</label>
                            <select id="id_poli" name="id_poli" class="form-control">
                                <option value="">-- Pilih Role Dulu --</option>
                            </select>
                            <small class="form-text text-muted">(Wajib diisi jika Role-nya "Poli Depan" / "Poli Belakang")</small>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-success">
                    <i class="fas fa-plus"></i> Simpan User Baru
                </button>
            </form>
        </div>
    </div>


    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Daftar User Sistem</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama Lengkap</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Terkait Poli</th>
                            <th>Aktif?</th>
                            <th>Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="7" class="text-center">Belum ada data user.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id_user']; ?></td>
                                    <td><?php echo htmlspecialchars($user['nama_lengkap']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['nama_role']); ?></td>
                                    <td><?php echo htmlspecialchars(isset($user['nama_poli']) ? $user['nama_poli'] : 'N/A'); ?></td>
                                    <td>
                                        <?php if ($user['is_active']): ?>
                                            <span class="badge badge-success">Ya</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Tidak</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="#" class="btn btn-warning btn-sm">
                                            <i class="fas fa-edit"></i> Ubah
                                        </a>
                                        <a href="<?php echo BASE_URL; ?>master/proses_crud_user.php?action=delete&id=<?php echo $user['id_user']; ?>" 
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('Apakah Anda yakin ingin menghapus user ini? (<?php echo htmlspecialchars($user['username']); ?>)');">
                                           <i class="fas fa-trash"></i> Hapus
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</main>
<script>
$(document).ready(function() {
    
    const polisDepan = <?php echo json_encode($polis_depan); ?>;
    const polisBelakang = <?php echo json_encode($polis_belakang); ?>;

    
    const roleDropdown = $('#id_role'); 
    const poliRow = $('#baris_terkait_poli');
    const poliDropdown = $('#id_poli');

    
    function updatePoliOptions(poliList) {
        poliDropdown.empty(); 
        
       
        poliDropdown.append(new Option('-- Pilih Poli Terkait --', ''));

        
        $.each(poliList, function (index, poli) {
            poliDropdown.append(new Option(poli.nama_poli, poli.id_poli));
        });
    }

    
    function togglePoliDropdown() {
        const selectedRole = roleDropdown.val();

        
        if (selectedRole == '1' || selectedRole == '2' || selectedRole == '') {
           
            poliRow.hide(); 
            poliDropdown.prop('required', false); 
            poliDropdown.empty();
            
            
            poliDropdown.append(new Option('-- Tidak Terkait Poli --', 'NULL'));

        } else if (selectedRole == '3') {
            
            poliRow.show(); 
            poliDropdown.prop('required', true); 
            updatePoliOptions(polisDepan);    

        } else if (selectedRole == '4') {
            
            poliRow.show(); 
            poliDropdown.prop('required', true); 
            updatePoliOptions(polisBelakang);   
        }
    }


    roleDropdown.on('change', togglePoliDropdown);

    togglePoliDropdown();
    

    if (typeof $().select2 === 'function') {
        $('#id_role').select2({ theme: 'bootstrap4', width: '100%' });
        $('#id_poli').select2({ theme: 'bootstrap4', width: '100%' });
    }
});
</script>


<?php

include '../templates/footer.php';
?>