// Tunggu sampai dokumen (jQuery) siap
$(document).ready(function() {
    
    // --- KODE UNTUK 'stok/terima.php' ---
    // Cek dulu, apakah kita ADA di halaman yang TEPAT?
    // (Kita hanya jalankan ini jika elemen #id_obat ada)
    if ($('#id_obat').length) {
        
        // [PERUBAHAN BESAR] Terapkan Select2 dengan AJAX
        $('#id_obat').select2({
            theme: "default",
            width: '93%', // Sesuaikan lebar
            placeholder: '-- Ketik Kode atau Nama Obat --',
            minimumInputLength: 2, // User harus ngetik min 2 huruf
            ajax: {
                // Panggil backend PHP kita
                url: BASE_URL + 'stok/ajax_get_obat.php', // BASE_URL dari header.php
                dataType: 'json',
                delay: 250, // Jeda 250ms setelah user ngetik
                data: function (params) {
                    // 'params.term' adalah ketikan user
                    return {
                        q: params.term 
                    };
                },
                processResults: function (data) {
                    // Format data JSON agar dibaca Select2
                    return {
                        results: data
                    };
                },
                cache: true
            }
        });

        // (Dropdown Unit tetap sama, pakai Select2 standar)
        $('#id_unit').select2({
            theme: "default",
            width: '93%',
            minimumResultsForSearch: Infinity // Sembunyikan search box
        });
    }

    // --- (Tempat untuk JS halaman lain nanti) ---
    // if ($('#id_form_resep').length) {
    //     // logic AJAX untuk resep "Struk Belanja"
    // }

});