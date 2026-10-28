<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';

$vaiTro = $_SESSION['vai_tro'] ?? null;
if ($vaiTro !== 'full' && (!is_array($vaiTro) || !in_array('thong_ke', $vaiTro, true))) {
    header('Location: ' . url('index.php'));
    exit;
}

$pdo = new PDO('mysql:host=127.0.0.1;dbname=circlek;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

function comparisonParseDate(?string $value): ?DateTimeImmutable
{
    if (!$value) {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();

    if (!$date || ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) {
        return null;
    }

    return $date;
}

$pageTitle = 'Biểu đồ so sánh toàn chuỗi';
$activeMenu = 'sosanh';
$period = $_GET['period'] ?? '7days';
$today = new DateTimeImmutable('today');
$rangeError = null;
switch ($period) {
    case 'today':
        $rangeStartDate = $today;
        $rangeEndDate = $today;
        break;
    case '30days':
        $rangeStartDate = $today->modify('-29 days');
        $rangeEndDate = $today;
        break;
    case 'quarter':
        $month = (int)$today->format('n');
        $rangeStartDate = $today->setDate((int)$today->format('Y'), intdiv($month - 1, 3) * 3 + 1, 1);
        $rangeEndDate = $today;
        break;
    case 'custom':
        $rangeStartDate = comparisonParseDate($_GET['from'] ?? null);
        $rangeEndDate = comparisonParseDate($_GET['to'] ?? null);
        if (!$rangeStartDate || !$rangeEndDate || $rangeStartDate > $rangeEndDate) {
            $rangeError = 'Vui lòng chọn khoảng ngày hợp lệ, ngày bắt đầu không được sau ngày kết thúc.';
            $rangeStartDate = $today->modify('-6 days');
            $rangeEndDate = $today;
        }
        break;
    case '7days':
    default:
        $period = '7days';
        $rangeStartDate = $today->modify('-6 days');
        $rangeEndDate = $today;
        break;
}
$range = [
    'period' => $period,
    'start' => $rangeStartDate->format('Y-m-d'),
    'end' => $rangeEndDate->format('Y-m-d'),
    'error' => $rangeError,
];
$period = $range['period'];
$rangeStart = $range['start'] . ' 00:00:00';
$rangeEnd = (new DateTimeImmutable($range['end']))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';

$metrics = [
    'doanh_thu' => ['label' => 'Doanh thu theo số khách phải trả', 'unit' => '₫'],
    'so_don' => ['label' => 'Số hóa đơn hoàn thành', 'unit' => ''],
    'hoi_vien' => ['label' => 'Hội viên đăng ký mới', 'unit' => ''],
];
$metric = $_GET['metric'] ?? 'doanh_thu';
if (!isset($metrics[$metric])) {
    $metric = 'doanh_thu';
}

$salesStmt = $pdo->prepare("
    SELECT c.id, c.ten_cua_hang,
           COALESCE(SUM(h.khach_phai_tra), 0) AS doanh_thu,
           COALESCE(SUM(h.tong_tien), 0) AS tong_tien,
           COALESCE(SUM(items.gia_tri_hang), 0) AS gia_tri_hang,
           COUNT(DISTINCT h.id) AS so_don
    FROM cuahang c
    LEFT JOIN hoadon h
      ON h.cuahang_id = c.id
     AND h.trang_thai IN ('Hoàn thành', 'Đã thanh toán')
     AND h.ngay_tao >= ?
     AND h.ngay_tao < ?
    LEFT JOIN (
        SELECT hoadon_id, SUM(thanh_tien) AS gia_tri_hang
        FROM chitiethoadon
        GROUP BY hoadon_id
    ) items ON items.hoadon_id = h.id
    GROUP BY c.id, c.ten_cua_hang
    ORDER BY c.ten_cua_hang
");
$salesStmt->execute([$rangeStart, $rangeEnd]);
$storeRows = $salesStmt->fetchAll();

$customerStmt = $pdo->prepare("
    SELECT c.id, COUNT(k.id) AS hoi_vien
    FROM cuahang c
    LEFT JOIN khachhang k
      ON k.cuahang_id = c.id
     AND k.ngay_dang_ky >= ?
     AND k.ngay_dang_ky < ?
    GROUP BY c.id
");
$customerStmt->execute([$rangeStart, $rangeEnd]);
$customersByStore = [];
foreach ($customerStmt->fetchAll() as $row) {
    $customersByStore[(int)$row['id']] = (int)$row['hoi_vien'];
}

$labels = [];
$chartValues = [];
$totalRevenue = 0.0;
$totalOrders = 0;
$totalCustomers = 0;
foreach ($storeRows as &$store) {
    $store['doanh_thu'] = (float)$store['doanh_thu'];
    $store['so_don'] = (int)$store['so_don'];
    $store['hoi_vien'] = $customersByStore[(int)$store['id']] ?? 0;
    $totalRevenue += $store['doanh_thu'];
    $totalOrders += $store['so_don'];
    $totalCustomers += $store['hoi_vien'];
    $labels[] = $store['ten_cua_hang'];
    $chartValues[] = $store[$metric];
}
unset($store);

$totalSelectedMetric = [
    'doanh_thu' => $totalRevenue,
    'so_don' => $totalOrders,
    'hoi_vien' => $totalCustomers,
][$metric];

require ROOT_PATH . '/includes/header.php';
?>

<div class="bento-panel fade-up">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--violet), #ec4899)"><i class="fa-solid fa-chart-column"></i></span>
                So sánh cửa hàng
            </h5>
            <div class="panel-subtitle ms-5">Dữ liệu từ <?php echo e($range['start']); ?> đến <?php echo e($range['end']); ?></div>
        </div>
    </div>
    <?php if ($range['error']): ?>
        <div class="alert alert-warning"><?php echo e($range['error']); ?> Đang hiển thị 7 ngày gần nhất.</div>
    <?php endif; ?>
    <div class="alert alert-info">
        Doanh thu tính theo <code>hoadon.khach_phai_tra</code>. Giá trị hàng hóa trước giảm giá được lấy riêng từ chi tiết hóa đơn.
    </div>
    <form method="get" class="row g-3 align-items-end mb-4">
        <div class="col-md-3">
            <label class="form-label" for="period">Khoảng thời gian</label>
            <select class="form-select" name="period" id="period">
                <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>Hôm nay</option>
                <option value="7days" <?php echo $period === '7days' ? 'selected' : ''; ?>>7 ngày</option>
                <option value="30days" <?php echo $period === '30days' ? 'selected' : ''; ?>>30 ngày</option>
                <option value="quarter" <?php echo $period === 'quarter' ? 'selected' : ''; ?>>Quý này</option>
                <option value="custom" <?php echo $period === 'custom' ? 'selected' : ''; ?>>Tùy chọn</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="from">Từ ngày</label>
            <input class="form-control" type="date" name="from" id="from" value="<?php echo e($_GET['from'] ?? ''); ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="to">Đến ngày</label>
            <input class="form-control" type="date" name="to" id="to" value="<?php echo e($_GET['to'] ?? ''); ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="metric">Chỉ số biểu đồ</label>
            <select class="form-select" name="metric" id="metric">
                <?php foreach ($metrics as $key => $definition): ?>
                    <option value="<?php echo e($key); ?>" <?php echo $metric === $key ? 'selected' : ''; ?>><?php echo e($definition['label']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 d-flex justify-content-end">
            <button type="submit" class="btn-ck btn-ck-primary"><i class="fa-solid fa-filter"></i> Áp dụng</button>
        </div>
    </form>

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <span class="text-muted"><?php echo e($metrics[$metric]['label']); ?></span>
    </div>
    <div style="height: 330px; position: relative;">
        <canvas id="compareChart"></canvas>
    </div>
</div>

<div class="bento-panel fade-up d-1 mt-4">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--ck-amber), #f97316)"><i class="fa-solid fa-ranking-star"></i></span>
                Bảng thống kê cửa hàng
            </h5>
            <div class="panel-subtitle ms-5">Tỷ trọng tính theo chỉ số đang chọn</div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-modern mb-0">
            <thead>
                <tr>
                    <th>Cửa hàng</th>
                    <th class="text-end">Khách phải trả</th>
                    <th class="text-end">Tạm tính hóa đơn</th>
                    <th class="text-end">Chi tiết hàng</th>
                    <th class="text-end">Hóa đơn hoàn thành</th>
                    <th class="text-end">Hội viên mới</th>
                    <th>Tỷ trọng <?php echo e($metrics[$metric]['label']); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($storeRows as $store): ?>
                    <?php
                    $value = (float)$store[$metric];
                    $share = $totalSelectedMetric > 0 ? ($value / $totalSelectedMetric) * 100 : 0;
                    ?>
                    <tr>
                        <td><span class="fw-semibold"><?php echo e($store['ten_cua_hang']); ?></span></td>
                        <td class="text-end"><span class="num-font fw-bold"><?php echo number_format($store['doanh_thu'], 0, ',', '.'); ?> ₫</span></td>
                        <td class="text-end"><span class="num-font fw-bold"><?php echo number_format((float)$store['tong_tien'], 0, ',', '.'); ?> ₫</span></td>
                        <td class="text-end"><span class="num-font fw-bold"><?php echo number_format((float)$store['gia_tri_hang'], 0, ',', '.'); ?> ₫</span></td>
                        <td class="text-end"><span class="num-font fw-bold"><?php echo number_format($store['so_don'], 0, ',', '.'); ?></span></td>
                        <td class="text-end"><span class="num-font fw-bold"><?php echo number_format($store['hoi_vien'], 0, ',', '.'); ?></span></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress-slim flex-grow-1" style="min-width: 90px;">
                                    <div class="progress-slim-bar" style="width: <?php echo number_format($share, 2, '.', ''); ?>%; background: var(--ck-red);"></div>
                                </div>
                                <span class="small"><?php echo number_format($share, 1, ',', '.'); ?>%</span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$storeRows): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Chưa có cửa hàng trong cơ sở dữ liệu.</td></tr>
                <?php endif; ?>
                <tr style="background-color: rgba(236, 72, 153, 0.05);">
                    <td><span class="fw-bold text-uppercase" style="color: #ec4899;">Tổng toàn chuỗi</span></td>
                    <td class="text-end"><span class="num-font fw-bold" style="color: #ec4899;"><?php echo number_format($totalRevenue, 0, ',', '.'); ?> ₫</span></td>
                    <td class="text-end"><span class="num-font fw-bold" style="color: #ec4899;"><?php echo number_format(array_sum(array_column($storeRows, 'tong_tien')), 0, ',', '.'); ?> ₫</span></td>
                    <td class="text-end"><span class="num-font fw-bold" style="color: #ec4899;"><?php echo number_format(array_sum(array_column($storeRows, 'gia_tri_hang')), 0, ',', '.'); ?> ₫</span></td>
                    <td class="text-end"><span class="num-font fw-bold" style="color: #ec4899;"><?php echo number_format($totalOrders, 0, ',', '.'); ?></span></td>
                    <td class="text-end"><span class="num-font fw-bold" style="color: #ec4899;"><?php echo number_format($totalCustomers, 0, ',', '.'); ?></span></td>
                    <td><span class="fw-bold" style="color: #ec4899;"><?php echo $totalSelectedMetric > 0 ? '100%' : '0%'; ?></span></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
document.querySelectorAll('#from, #to').forEach(function(input) {
    input.addEventListener('change', function() {
        document.getElementById('period').value = 'custom';
    });
});

document.addEventListener("DOMContentLoaded", function() {
    const labels = <?php echo json_encode($labels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    const values = <?php echo json_encode($chartValues, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    const metric = <?php echo json_encode($metric, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    const label = <?php echo json_encode($metrics[$metric]['label'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    const currency = metric === 'doanh_thu';

    new Chart(document.getElementById('compareChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: label,
                data: values,
                backgroundColor: metric === 'doanh_thu' ? CK.colors.red : (metric === 'so_don' ? CK.colors.ocean : CK.colors.emerald),
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { callback: value => currency ? new Intl.NumberFormat('vi-VN').format(value) + ' ₫' : value } } }
        }
    });
});
</script>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
