<?php
// 1. Panggil "jantung" config.php
require_once '../config.php';

// 2. Panggil "satpam" auth_check.php
require_once '../templates/auth_check.php';

// 3. (SATPAM 2: ROLE CHECK)
// Hanya SUPER ADMIN (Role ID 1) yang boleh mengakses halaman ini
if ($_SESSION['role_id'] != 1) {
    // Jika bukan Super Admin, tendang paksa
    echo "<script>alert('Akses Ditolak! Anda bukan Super Admin.'); window.location.href = '" . BASE_URL . "dashboard.php';</script>";
    exit;
}

// 4. Set Judul Halaman
$page_title = "Kelola Akun User";

// 5. [LOGIKA BARU] Logic untuk AMBIL DATA (READ)
try {
    // Ambil data Role (Ini tetap sama)
    $stmt_role = $pdo->query("SELECT id_role, nama_role FROM tbl_role ORDER BY nama_role ASC");
    $roles = $stmt_role->fetchAll();

    // Ambil data Poli Depan (Asumsi ID 2 = APOTEK dari tbl_unit)
    $stmt_poli_depan = $pdo->query("SELECT id_poli, nama_poli 
                                   FROM tbl_poli 
                                   WHERE id_unit_stok_default = 2 
                                   ORDER BY nama_poli ASC");
    $polis_depan = $stmt_poli_depan->fetchAll();

    // Ambil data Poli Belakang (ID Unit BUKAN 1 atau 2)
    $stmt_poli_belakang = $pdo->query("SELECT id_poli, nama_poli 
                                      FROM tbl_poli 
                                      WHERE id_unit_stok_default NOT IN (1, 2) 
                                      ORDER BY nama_poli ASC");
    $polis_belakang = $stmt_poli_belakang->fetchAll();

    // Ambil semua data user untuk ditampilkan di tabel (Ini tetap sama)
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
    // Tangani error jika query gagal
    die("Error mengambil data: " . $e->getMessage());
}

// 6. Panggil "Kepala" (Template Header)
include '../templates/header.php';
// (Sidebar.php dipanggil OTOMATIS oleh header.php)
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
    // --- 1. "Amunisi" Data dari PHP ---
    const polisDepan = <?php echo json_encode($polis_depan); ?>;
    const polisBelakang = <?php echo json_encode($polis_belakang); ?>;

    // --- 2. "Tangkap" Elemen HTML ---
    const roleDropdown = $('#id_role'); // Pakai jQuery
    const poliRow = $('#baris_terkait_poli');
    const poliDropdown = $('#id_poli');

    // --- 3. Buat Fungsi "Pengisi Dropdown" ---
    function updatePoliOptions(poliList) {
        poliDropdown.empty(); // Kosongkan dropdown
        
        // Tambahkan opsi default
        poliDropdown.append(new Option('-- Pilih Poli Terkait --', ''));

        // Isi dropdown dengan list poli yang sesuai
        $.each(poliList, function (index, poli) {
            poliDropdown.append(new Option(poli.nama_poli, poli.id_poli));
        });
    }

    // --- 4. Upgrade Fungsi "Satpam" (togglePoliDropdown) ---
    function togglePoliDropdown() {
        const selectedRole = roleDropdown.val();

        // Cek Role ID
        if (selectedRole == '1' || selectedRole == '2' || selectedRole == '') {
            // ROLE: Super Admin / Admin / Belum Dipilih
            poliRow.hide(); // Sembunyikan baris
            poliDropdown.prop('required', false); // Tidak wajib
            poliDropdown.empty(); // Kosongkan total
            
            // PENTING: Tambahkan 1 opsi "NULL"
            poliDropdown.append(new Option('-- Tidak Terkait Poli --', 'NULL'));

        } else if (selectedRole == '3') {
            // ROLE: Poli Depan
            poliRow.show(); // Tampilkan baris
            poliDropdown.prop('required', true); // Wajib diisi
            updatePoliOptions(polisDepan);      // Isi dengan Poli Depan

        } else if (selectedRole == '4') {
            // ROLE: Poli Belakang
            poliRow.show(); // Tampilkan baris
            poliDropdown.prop('required', true); // Wajib diisi
            updatePoliOptions(polisBelakang);   // Isi dengan Poli Belakang
        }
    }

    // --- 5. Pasang "Satpam" & Jalankan ---
    roleDropdown.on('change', togglePoliDropdown);
    
    // Jalankan "Satpam" sekali saat halaman dimuat
    togglePoliDropdown();
    
    // [REFACTOR] Terapkan Select2 ke dropdown (agar konsisten)
    if (typeof $().select2 === 'function') {
        $('#id_role').select2({ theme: 'bootstrap4', width: '100%' });
        $('#id_poli').select2({ theme: 'bootstrap4', width: '100%' });
    }
});
</script>


<?php
// 8. Panggil "Kaki" (Template Footer)
include '../templates/footer.php';
?>