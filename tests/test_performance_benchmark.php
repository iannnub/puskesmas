<?php
// tests/test_performance_benchmark.php
// Performance Profiling & 20-Concurrent User Stress Test Suite
// Verifies SLA targets in PERFORMANCE_BASELINE.md & PERFORMANCE_OPTIMIZATION_REPORT.md

$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../config.php';

echo "====================================================================\n";
echo "   SIVO PUSKESMAS - PERFORMANCE PROFILING & CONCURRENCY BENCHMARK   \n";
echo "====================================================================\n\n";

$sla_passed = true;

// -----------------------------------------------------------------------------
// 1. DATABASE QUERY PROFILING (LPLPO AGGREGATION & INDEX UTILIZATION)
// -----------------------------------------------------------------------------
echo "[1] Database Query Profiling: Menguji Query LPLPO dengan Composite Index...\n";

// Query EXPLAIN untuk memverifikasi penggunaan composite index idx_log_obat_unit_tgl
$explain_sql = "
    EXPLAIN SELECT 
        o.id_obat, 
        o.nama_obat, 
        o.satuan, 
        si.stok_akhir,
        COALESCE((SELECT SUM(masuk) FROM tbl_log_stok WHERE id_obat = o.id_obat AND id_unit = 5 AND tgl_log BETWEEN '2026-01-01 00:00:00' AND '2026-12-31 23:59:59'), 0) AS penerimaan
    FROM tbl_obat o
    LEFT JOIN tbl_stok_inventori si ON o.id_obat = si.id_obat AND si.id_unit = 5
    ORDER BY o.nama_obat ASC
    LIMIT 100
";

$explain_rows = $pdo->query($explain_sql)->fetchAll(PDO::FETCH_ASSOC);
$used_indices = [];
foreach ($explain_rows as $row) {
    if (!empty($row['key'])) {
        $used_indices[] = $row['key'];
    }
}
echo "   -> Key / Index yang terdeteksi pada EXPLAIN: " . implode(', ', array_unique($used_indices)) . "\n";

// Mengukur waktu eksekusi riil query kompleks
$query_iterations = 5;
$query_times = [];

$benchmark_sql = "
    SELECT 
        o.id_obat, 
        o.nama_obat, 
        o.satuan, 
        si.stok_akhir,
        COALESCE((SELECT SUM(masuk) FROM tbl_log_stok WHERE id_obat = o.id_obat AND id_unit = 5 AND tgl_log BETWEEN '2026-01-01 00:00:00' AND '2026-12-31 23:59:59'), 0) AS penerimaan,
        COALESCE((SELECT SUM(keluar) FROM tbl_log_stok WHERE id_obat = o.id_obat AND id_unit = 5 AND sumber_data = 'Resep' AND tgl_log BETWEEN '2026-01-01 00:00:00' AND '2026-12-31 23:59:59'), 0) AS pemakaian
    FROM tbl_obat o
    LEFT JOIN tbl_stok_inventori si ON o.id_obat = si.id_obat AND si.id_unit = 5
    ORDER BY o.nama_obat ASC
    LIMIT 200
";

for ($i = 0; $i < $query_iterations; $i++) {
    $t_start = microtime(true);
    $stmt = $pdo->query($benchmark_sql);
    $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $t_end = microtime(true);
    $query_times[] = ($t_end - $t_start) * 1000; // ms
}

$avg_query_time = array_sum($query_times) / count($query_times);
$min_query_time = min($query_times);
$max_query_time = max($query_times);

echo sprintf("   -> Rata-rata Waktu Eksekusi Query (%d iterasi): %.2f ms (Min: %.2f ms, Max: %.2f ms)\n", 
    $query_iterations, $avg_query_time, $min_query_time, $max_query_time
);

if ($avg_query_time < 100.0) {
    echo "   -> PASS: Waktu query jauh di bawah batas SLA (< 100 ms).\n";
} else {
    echo "   -> FAIL: Query melebihi batas SLA 100 ms!\n";
    $sla_passed = false;
}

// -----------------------------------------------------------------------------
// 2. CONCURRENT USER SIMULATION (20 SIMULTANEOUS REQUESTS VIA CURL MULTI)
// -----------------------------------------------------------------------------
echo "\n[2] Simulasi Konkurensi: Mengirim 20 Permintaan Simultan via curl_multi...\n";

$target_url = 'http://localhost/puskesmas/index.html';
$concurrency = 20;

$mh = curl_multi_init();
$curl_handles = [];

for ($i = 0; $i < $concurrency; $i++) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $target_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_multi_add_handle($mh, $ch);
    $curl_handles[$i] = $ch;
}

$t_burst_start = microtime(true);

$running = null;
do {
    curl_multi_exec($mh, $running);
    curl_multi_select($mh);
} while ($running > 0);

$t_burst_end = microtime(true);
$total_burst_time = ($t_burst_end - $t_burst_start) * 1000; // ms

$success_count = 0;
$ttfb_list = [];

foreach ($curl_handles as $i => $ch) {
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $total_time = curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000; // ms
    $starttransfer_time = curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME) * 1000; // TTFB in ms
    
    if ($http_code === 200) {
        $success_count++;
        $ttfb_list[] = $starttransfer_time;
    }
    
    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
}
curl_multi_close($mh);

$avg_ttfb = !empty($ttfb_list) ? (array_sum($ttfb_list) / count($ttfb_list)) : 0;
$max_ttfb = !empty($ttfb_list) ? max($ttfb_list) : 0;
$min_ttfb = !empty($ttfb_list) ? min($ttfb_list) : 0;
$throughput = ($success_count / (($t_burst_end - $t_burst_start) ?: 0.001));

echo sprintf("   -> Total Request: %d | Sukses (200 OK): %d/%d (%.1f%%)\n", 
    $concurrency, $success_count, $concurrency, ($success_count / $concurrency) * 100
);
echo sprintf("   -> Waktu Total Burst: %.2f ms | Throughput: %.1f req/detik\n", 
    $total_burst_time, $throughput
);
echo sprintf("   -> TTFB Metrik (20 Pengguna Konkuren): Rata-rata: %.2f ms (Min: %.2f ms, Max: %.2f ms)\n", 
    $avg_ttfb, $min_ttfb, $max_ttfb
);

if ($success_count === $concurrency && $avg_ttfb < 500.0) {
    echo "   -> PASS: 100% Request Berhasil, TTFB Konkuren berada di bawah batas toleransi SLA (< 500 ms).\n";
} else {
    echo "   -> FAIL: Terjadi kegagalan request atau TTFB melebihi batas SLA!\n";
    $sla_passed = false;
}

// -----------------------------------------------------------------------------
// 3. SINGLE-USER NORMAL LOAD BASELINE (TTFB & LATENCY)
// -----------------------------------------------------------------------------
echo "\n[3] Single-User Normal Load Baseline...\n";

$single_times = [];
for ($i = 0; $i < 5; $i++) {
    $ch = curl_init('http://localhost/puskesmas/index.html');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $start = microtime(true);
    curl_exec($ch);
    $ttfb = curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME) * 1000;
    curl_close($ch);
    $single_times[] = $ttfb;
}

$avg_single_ttfb = array_sum($single_times) / count($single_times);
echo sprintf("   -> Rata-rata TTFB Normal Load (1 Pengguna): %.2f ms (Target: < 200 ms)\n", $avg_single_ttfb);

if ($avg_single_ttfb < 200.0) {
    echo "   -> PASS: TTFB Normal Load memenuhi target SLA (< 200 ms).\n";
} else {
    echo "   -> FAIL: TTFB Normal Load melebihi 200 ms!\n";
    $sla_passed = false;
}

echo "\n====================================================================\n";
if ($sla_passed) {
    echo "HASIL AKHIR BENCHMARK: 100% TARGET SLA TERPENUHI (SISTEM OPTIMIZED)!\n";
} else {
    echo "HASIL AKHIR BENCHMARK: BEBERAPA TARGET SLA TIDAK TERCAPAI!\n";
}
echo "====================================================================\n";
