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

function productReportParseDate(?string $value): ?DateTimeImmutable
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

$pageTitle = 'Thống kê sản phẩm bán chạy';
$activeMenu = 'banchay';
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
        $rangeStartDate = productReportParseDate($_GET['from'] ?? null);
        $rangeEndDate = productReportParseDate($_GET['to'] ?? null);
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

$categories = $pdo->query("SELECT id, ten_danh_muc FROM danhmuc ORDER BY ten_danh_muc")->fetchAll();
$categoryFilter = (int)($_GET['category'] ?? 0);
$categoryIds = array_map('intval', array_column($categories, 'id'));
if ($categoryFilter !== 0 && !in_array($categoryFilter, $categoryIds, true)) {
    $categoryFilter = 0;
}

$filterSql = '';
$params = [$rangeStart, $rangeEnd];
if ($categoryFilter > 0) {
    $filterSql = ' AND sp.danhmuc_id = ?';
    $params[] = $categoryFilter;
}

$topStmt = $pdo->prepare("
    SELECT sp.id, sp.ten_san_pham, dm.ten_danh_muc,
           SUM(ct.so_luong) AS so_luong_ban,
           SUM(ct.thanh_tien) AS gia_tri_hang
    FROM chitiethoadon ct
    JOIN hoadon h ON h.id = ct.hoadon_id
    JOIN sanpham sp ON sp.id = ct.sanpham_id
    JOIN danhmuc dm ON dm.id = sp.danhmuc_id
    WHERE h.trang_thai IN ('Hoàn thành', 'Đã thanh toán')
      AND h.ngay_tao >= ?
      AND h.ngay_tao < ?{$filterSql}
    GROUP BY sp.id, sp.ten_san_pham, dm.ten_danh_muc
    ORDER BY so_luong_ban DESC, sp.ten_san_pham
    LIMIT 5
");
$topStmt->execute($params);
$topProducts = $topStmt->fetchAll();

$trendStmt = $pdo->prepare("
    SELECT DATE(h.ngay_tao) AS ngay, SUM(ct.so_luong) AS so_luong_ban
    FROM chitiethoadon ct
    JOIN hoadon h ON h.id = ct.hoadon_id
    JOIN sanpham sp ON sp.id = ct.sanpham_id
    WHERE h.trang_thai IN ('Hoàn thành', 'Đã thanh toán')
      AND h.ngay_tao >= ?
      AND h.ngay_tao < ?{$filterSql}
    GROUP BY DATE(h.ngay_tao)
    ORDER BY ngay
");
$trendStmt->execute($params);
$quantityByDate = [];
foreach ($trendStmt->fetchAll() as $row) {
    $quantityByDate[$row['ngay']] = (int)$row['so_luong_ban'];
}

$chartLabels = [];
$chartQuantities = [];
$day = new DateTimeImmutable($range['start']);
$lastDay = new DateTimeImmutable($range['end']);
for (; $day <= $lastDay; $day = $day->modify('+1 day')) {
    $date = $day->format('Y-m-d');
    $chartLabels[] = $day->format('d/m');
    $chartQuantities[] = $quantityByDate[$date] ?? 0;
}

$selectedCategoryName = 'Tất cả danh mục';
foreach ($categories as $category) {
    if ((int)$category['id'] === $categoryFilter) {
        $selectedCategoryName = $category['ten_danh_muc'];
        break;
    }
}

require ROOT_PATH . '/includes/header.php';
?>

<div class="bento-panel fade-up mb-4">
    <form method="get" class="row g-3 align-items-end">
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
            <label class="form-label" for="category">Danh mục</label>
            <select class="form-select" name="category" id="category">
                <option value="0">Tất cả danh mục</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo (int)$category['id']; ?>" <?php echo $categoryFilter === (int)$category['id'] ? 'selected' : ''; ?>>
                        <?php echo e($category['ten_danh_muc']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 d-flex justify-content-end">
            <button type="submit" class="btn-ck btn-ck-primary"><i class="fa-solid fa-filter"></i> Áp dụng</button>
        </div>
    </form>
    <?php if ($range['error']): ?>
        <div class="alert alert-warning mt-3 mb-0"><?php echo e($range['error']); ?> Đang hiển thị 7 ngày gần nhất.</div>
    <?php endif; ?>
</div>

<div class="row g-4">
    <div class="col-12 col-xl-5">
        <div class="bento-panel fade-up h-100">
            <div class="panel-header">
                <div>
                    <h5 class="panel-title">
                        <span class="title-icon" style="background: linear-gradient(135deg, var(--emerald), #059669)"><i class="fa-solid fa-ranking-star"></i></span>
                        Top 5 sản phẩm bán chạy
                    </h5>
                    <div class="panel-subtitle ms-5">Theo số lượng trên hóa đơn hoàn thành · <?php echo e($selectedCategoryName); ?></div>
                    <div class="panel-subtitle ms-5">Giá trị hiển thị: tổng tiền chi tiết hàng trước giảm giá.</div>
                </div>
            </div>
            <?php if ($topProducts): ?>
                <?php
                $colors = ['#f97316', '#e11d48', '#0ea5e9', '#f59e0b', '#8b5cf6'];
                $icons = ['fa-box', 'fa-box-open', 'fa-cube', 'fa-bag-shopping', 'fa-tag'];
                $maxQuantity = max(array_map('intval', array_column($topProducts, 'so_luong_ban')));
                foreach ($topProducts as $index => $product):
                    $color = $colors[$index % count($colors)];
                    $icon = $icons[$index % count($icons)];
                    $percent = $maxQuantity > 0 ? ((int)$product['so_luong_ban'] / $maxQuantity) * 100 : 0;
                ?>
                    <div class="prod-row">
                        <span class="prod-rank-icon" style="color: <?php echo e($color); ?>; background: color-mix(in srgb, <?php echo e($color); ?> 12%, transparent);"><i class="fa-solid <?php echo e($icon); ?>"></i></span>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-bold" style="font-size: 0.9rem;"><?php echo e($product['ten_san_pham']); ?></span>
                                <div class="text-end">
                                    <span class="fw-bold num-font" style="color: <?php echo e($color); ?>; font-size: 0.88rem;"><?php echo number_format((int)$product['so_luong_ban'], 0, ',', '.'); ?> sản phẩm</span>
                                    <small class="text-muted ms-1" style="font-size: 0.76rem;">(<?php echo number_format((float)$product['gia_tri_hang'], 0, ',', '.'); ?> ₫)</small>
                                </div>
                            </div>
                            <div class="progress-slim">
                                <div class="progress-slim-bar" style="width: <?php echo number_format($percent, 2, '.', ''); ?>%; background: linear-gradient(90deg, <?php echo e($color); ?>, color-mix(in srgb, <?php echo e($color); ?> 60%, #fff));"></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center text-muted py-4">Không có sản phẩm bán ra trong khoảng thời gian này.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-12 col-xl-7">
        <div class="bento-panel fade-up d-1 h-100">
            <div class="panel-header">
                <div>
                    <h5 class="panel-title">
                        <span class="title-icon" style="background: linear-gradient(135deg, var(--ocean), #6366f1)"><i class="fa-solid fa-chart-line"></i></span>
                        Xu hướng số lượng bán
                    </h5>
                    <div class="panel-subtitle ms-5"><?php echo e($selectedCategoryName); ?> · <?php echo e((new DateTimeImmutable($range['start']))->format('d-m-Y')); ?> đến <?php echo e((new DateTimeImmutable($range['end']))->format('d-m-Y')); ?></div>
                </div>
            </div>
            <div style="height: 300px; position: relative;">
                <canvas id="salesTrendChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('#from, #to').forEach(function(input) {
    input.addEventListener('change', function() {
        document.getElementById('period').value = 'custom';
    });
});

document.addEventListener("DOMContentLoaded", function() {
    const labels = <?php echo json_encode($chartLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    const quantities = <?php echo json_encode($chartQuantities, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

    new Chart(document.getElementById('salesTrendChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Số lượng bán',
                data: quantities,
                borderColor: CK.colors.ocean,
                backgroundColor: CK.alpha(CK.colors.ocean, 0.1),
                borderWidth: 2.5,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });
});
</script>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
