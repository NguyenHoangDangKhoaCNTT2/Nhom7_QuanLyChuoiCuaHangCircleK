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

function dashboardTrend(float $current, float $previous): array
{
    if ($previous == 0.0) {
        return [
            'class' => 'trend-up',
            'icon' => 'fa-arrow-trend-up',
            'label' => $current == 0.0 ? '0%' : 'Mới',
        ];
    }

    $change = (($current - $previous) / $previous) * 100;
    $isUp = $change >= 0;

    return [
        'class' => $isUp ? 'trend-up' : 'trend-down',
        'icon' => $isUp ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down',
        'label' => sprintf('%s%.1f%%', $isUp ? '+' : '', $change),
    ];
}

$pageTitle = 'Dashboard Cửa Hàng';
$activeMenu = 'dashboard';
$period = $_GET['period'] ?? '7days';
$today = new DateTimeImmutable('today');
switch ($period) {
    case '30days':
        $rangeStartDate = $today->modify('-29 days');
        break;
    case 'quarter':
        $month = (int)$today->format('n');
        $rangeStartDate = $today->setDate((int)$today->format('Y'), intdiv($month - 1, 3) * 3 + 1, 1);
        break;
    default:
        $period = '7days';
        $rangeStartDate = $today->modify('-6 days');
        break;
}
$rangeStartDate ??= $today;
$range = ['period' => $period, 'start' => $rangeStartDate->format('Y-m-d'), 'end' => $today->format('Y-m-d')];
$period = $range['period'];
$rangeStart = $range['start'] . ' 00:00:00';
$rangeEnd = (new DateTimeImmutable($range['end']))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
$yesterdayStart = $today->modify('-1 day')->format('Y-m-d') . ' 00:00:00';
$todayStart = $today->format('Y-m-d') . ' 00:00:00';
$tomorrowStart = $today->modify('+1 day')->format('Y-m-d') . ' 00:00:00';

$kpiStmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN h.ngay_tao >= ? THEN h.khach_phai_tra ELSE 0 END), 0) AS doanh_thu_hom_nay,
        COALESCE(SUM(CASE WHEN h.ngay_tao < ? THEN h.khach_phai_tra ELSE 0 END), 0) AS doanh_thu_hom_qua,
        COUNT(CASE WHEN h.ngay_tao >= ? THEN h.id END) AS don_hom_nay,
        COUNT(CASE WHEN h.ngay_tao < ? THEN h.id END) AS don_hom_qua
    FROM hoadon h
    WHERE h.trang_thai IN ('Hoàn thành', 'Đã thanh toán')
      AND h.ngay_tao >= ?
      AND h.ngay_tao < ?
");
$kpiStmt->execute([$todayStart, $todayStart, $todayStart, $todayStart, $yesterdayStart, $tomorrowStart]);
$kpi = $kpiStmt->fetch();

$customerKpiStmt = $pdo->prepare("
    SELECT
        COUNT(CASE WHEN ngay_dang_ky >= ? THEN id END) AS khach_hom_nay,
        COUNT(CASE WHEN ngay_dang_ky < ? THEN id END) AS khach_hom_qua
    FROM khachhang
    WHERE ngay_dang_ky >= ?
      AND ngay_dang_ky < ?
");
$customerKpiStmt->execute([$todayStart, $todayStart, $yesterdayStart, $tomorrowStart]);
$customerKpi = $customerKpiStmt->fetch();

$lowStock = (int)$pdo->query("SELECT COUNT(*) FROM tonkho WHERE so_luong < 10")->fetchColumn();

$revenueStmt = $pdo->prepare("
    SELECT DATE(h.ngay_tao) AS ngay, SUM(h.khach_phai_tra) AS doanh_thu
    FROM hoadon h
    WHERE h.trang_thai IN ('Hoàn thành', 'Đã thanh toán')
      AND h.ngay_tao >= ?
      AND h.ngay_tao < ?
    GROUP BY DATE(h.ngay_tao)
    ORDER BY ngay
");
$revenueStmt->execute([$rangeStart, $rangeEnd]);
$revenueByDate = [];
foreach ($revenueStmt->fetchAll() as $row) {
    $revenueByDate[$row['ngay']] = (float)$row['doanh_thu'];
}

$chartLabels = [];
$chartRevenue = [];
$day = new DateTimeImmutable($range['start']);
$lastDay = new DateTimeImmutable($range['end']);
for (; $day <= $lastDay; $day = $day->modify('+1 day')) {
    $date = $day->format('Y-m-d');
    $chartLabels[] = $day->format('d/m');
    $chartRevenue[] = $revenueByDate[$date] ?? 0;
}

$categoryStmt = $pdo->prepare("
    SELECT dm.ten_danh_muc, SUM(ct.thanh_tien) AS doanh_thu
    FROM hoadon h
    JOIN chitiethoadon ct ON ct.hoadon_id = h.id
    JOIN sanpham sp ON sp.id = ct.sanpham_id
    JOIN danhmuc dm ON dm.id = sp.danhmuc_id
    WHERE h.trang_thai IN ('Hoàn thành', 'Đã thanh toán')
      AND h.ngay_tao >= ?
      AND h.ngay_tao < ?
    GROUP BY dm.id, dm.ten_danh_muc
    ORDER BY doanh_thu DESC
");
$categoryStmt->execute([$rangeStart, $rangeEnd]);
$categoryRows = $categoryStmt->fetchAll();
$categoryLabels = array_column($categoryRows, 'ten_danh_muc');
$categoryRevenue = array_map('floatval', array_column($categoryRows, 'doanh_thu'));

$recentStmt = $pdo->query("
    SELECT h.id, h.ngay_tao, h.trang_thai, c.ten_cua_hang,
           h.tong_tien, h.khach_phai_tra,
           COALESCE(items.gia_tri_hang, 0) AS gia_tri_hang
    FROM hoadon h
    LEFT JOIN cuahang c ON c.id = h.cuahang_id
    LEFT JOIN (
        SELECT hoadon_id, SUM(thanh_tien) AS gia_tri_hang
        FROM chitiethoadon
        GROUP BY hoadon_id
    ) items ON items.hoadon_id = h.id
    ORDER BY h.ngay_tao DESC, h.id DESC
    LIMIT 5
");
$recentOrders = $recentStmt->fetchAll();
$revenueTrend = dashboardTrend((float)$kpi['doanh_thu_hom_nay'], (float)$kpi['doanh_thu_hom_qua']);
$orderTrend = dashboardTrend((float)$kpi['don_hom_nay'], (float)$kpi['don_hom_qua']);
$customerTrend = dashboardTrend((float)$customerKpi['khach_hom_nay'], (float)$customerKpi['khach_hom_qua']);

require ROOT_PATH . '/includes/header.php';
?>

<div class="row g-4 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="bento-panel p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="panel-subtitle">Doanh thu hôm nay</div>
                    <h3 class="num-font fw-bold mt-1 mb-0" style="color: var(--ck-red);"><?php echo number_format((float)$kpi['doanh_thu_hom_nay'], 0, ',', '.'); ?> ₫</h3>
                </div>
                <div class="title-icon rounded-circle p-3" style="background: rgba(239, 68, 68, 0.1); color: var(--ck-red);"><i class="fa-solid fa-wallet"></i></div>
            </div>
            <div class="mt-2"><span class="trend-pill <?php echo e($revenueTrend['class']); ?>"><i class="fa-solid <?php echo e($revenueTrend['icon']); ?>"></i> <?php echo e($revenueTrend['label']); ?></span> <span class="text-muted small">so với hôm qua</span></div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="bento-panel p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="panel-subtitle">Đơn hoàn thành hôm nay</div>
                    <h3 class="num-font fw-bold mt-1 mb-0"><?php echo number_format((int)$kpi['don_hom_nay'], 0, ',', '.'); ?></h3>
                </div>
                <div class="title-icon rounded-circle p-3" style="background: rgba(14, 165, 233, 0.1); color: var(--ck-blue);"><i class="fa-solid fa-receipt"></i></div>
            </div>
            <div class="mt-2"><span class="trend-pill <?php echo e($orderTrend['class']); ?>"><i class="fa-solid <?php echo e($orderTrend['icon']); ?>"></i> <?php echo e($orderTrend['label']); ?></span> <span class="text-muted small">so với hôm qua</span></div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="bento-panel p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="panel-subtitle">Hội viên đăng ký hôm nay</div>
                    <h3 class="num-font fw-bold mt-1 mb-0"><?php echo number_format((int)$customerKpi['khach_hom_nay'], 0, ',', '.'); ?></h3>
                </div>
                <div class="title-icon rounded-circle p-3" style="background: rgba(16, 185, 129, 0.1); color: var(--ck-green);"><i class="fa-solid fa-user-plus"></i></div>
            </div>
            <div class="mt-2"><span class="trend-pill <?php echo e($customerTrend['class']); ?>"><i class="fa-solid <?php echo e($customerTrend['icon']); ?>"></i> <?php echo e($customerTrend['label']); ?></span> <span class="text-muted small">so với hôm qua</span></div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="bento-panel p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="panel-subtitle">Mặt hàng sắp hết</div>
                    <h3 class="num-font fw-bold mt-1 mb-0" style="color: var(--ck-amber);"><?php echo number_format($lowStock, 0, ',', '.'); ?></h3>
                </div>
                <div class="title-icon rounded-circle p-3" style="background: rgba(245, 158, 11, 0.1); color: var(--ck-amber);"><i class="fa-solid fa-triangle-exclamation"></i></div>
            </div>
            <div class="mt-2"><span class="text-muted small">Dòng tồn kho có số lượng dưới 10</span></div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="bento-panel h-100">
            <div class="panel-header">
                <div>
                    <h5 class="panel-title">
                        <span class="title-icon" style="background: linear-gradient(135deg, var(--ck-red), #ea580c)"><i class="fa-solid fa-chart-line"></i></span>
                        Doanh thu hóa đơn theo thời gian
                    </h5>
                    <div class="panel-subtitle ms-5">Số khách phải trả trên hóa đơn, từ <?php echo e($range['start']); ?> đến <?php echo e($range['end']); ?></div>
                </div>
                <form method="get" class="d-flex gap-2">
                    <select name="period" class="form-select" aria-label="Khoảng thời gian" onchange="this.form.submit()">
                        <option value="7days" <?php echo $period === '7days' ? 'selected' : ''; ?>>7 ngày</option>
                        <option value="30days" <?php echo $period === '30days' ? 'selected' : ''; ?>>30 ngày</option>
                        <option value="quarter" <?php echo $period === 'quarter' ? 'selected' : ''; ?>>Quý này</option>
                    </select>
                </form>
            </div>
            <canvas id="revenueChart" style="max-height: 320px;"></canvas>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="bento-panel h-100">
            <div class="panel-header">
                <div>
                    <h5 class="panel-title">
                        <span class="title-icon" style="background: linear-gradient(135deg, var(--violet), #ec4899)"><i class="fa-solid fa-chart-pie"></i></span>
                        Giá trị hàng bán theo danh mục
                    </h5>
                    <div class="panel-subtitle ms-5">Tổng chi tiết hàng, trước giảm giá · cùng khoảng thời gian</div>
                </div>
            </div>
            <canvas id="categoryChart" style="max-height: 320px;"></canvas>
            <?php if (!$categoryRows): ?>
                <div class="text-muted small text-center mt-2">Chưa có chi tiết bán hàng trong khoảng thời gian này.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="bento-panel fade-up">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--ck-blue), var(--violet))"><i class="fa-solid fa-list-check"></i></span>
                Giao dịch gần đây
            </h5>
            <div class="panel-subtitle ms-5">Năm hóa đơn mới nhất từ cơ sở dữ liệu</div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-modern mb-0">
            <thead>
                <tr>
                    <th>Mã HĐ</th>
                    <th>Cửa hàng</th>
                    <th>Tạm tính hóa đơn</th>
                    <th>Chi tiết hàng</th>
                    <th>Khách phải trả</th>
                    <th>Trạng thái</th>
                    <th class="text-end">Thời gian</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recentOrders): ?>
                    <?php foreach ($recentOrders as $order): ?>
                    <tr>
                        <td><span class="fw-semibold">#HD-<?php echo (int)$order['id']; ?></span></td>
                        <td><?php echo e($order['ten_cua_hang'] ?? ''); ?></td>
                        <td><span class="num-font fw-bold"><?php echo number_format((float)$order['tong_tien'], 0, ',', '.'); ?> ₫</span></td>
                        <td><span class="num-font fw-bold"><?php echo number_format((float)$order['gia_tri_hang'], 0, ',', '.'); ?> ₫</span></td>
                        <td><span class="num-font fw-bold text-danger"><?php echo number_format((float)$order['khach_phai_tra'], 0, ',', '.'); ?> ₫</span></td>
                        <td><?php echo e($order['trang_thai'] ?? ''); ?></td>
                        <td class="text-end text-muted small"><?php echo e($order['ngay_tao'] ?? ''); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Chưa có hóa đơn.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const labels = <?php echo json_encode($chartLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    const revenue = <?php echo json_encode($chartRevenue, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    const categoryLabels = <?php echo json_encode($categoryLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    const categoryRevenue = <?php echo json_encode($categoryRevenue, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

    new Chart(document.getElementById('revenueChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Khách phải trả (₫)',
                data: revenue,
                borderColor: CK.colors.red,
                backgroundColor: CK.alpha(CK.colors.red, 0.05),
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
    });

    new Chart(document.getElementById('categoryChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: categoryLabels,
            datasets: [{
                data: categoryRevenue,
                backgroundColor: [CK.colors.red, CK.colors.ocean, CK.colors.emerald, CK.colors.amber, CK.colors.violet, '#14b8a6'],
                borderWidth: 0
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });
});
</script>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
