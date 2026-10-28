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

function reportFilterParseDate(?string $value): ?DateTimeImmutable
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

$pageTitle = 'Lọc báo cáo theo thời gian';
$activeMenu = 'locbaocao';
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
        $rangeStartDate = reportFilterParseDate($_GET['from'] ?? null);
        $rangeEndDate = reportFilterParseDate($_GET['to'] ?? null);
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

$stores = $pdo->query("SELECT id, ten_cua_hang FROM cuahang ORDER BY ten_cua_hang")->fetchAll();
$storeId = (int)($_GET['store_id'] ?? 0);
$storeIds = array_map('intval', array_column($stores, 'id'));
if ($storeId !== 0 && !in_array($storeId, $storeIds, true)) {
    $storeId = 0;
}

$reportTypes = [
    'revenue' => 'Doanh thu & đối soát hóa đơn',
    'products' => 'Sản phẩm bán chạy',
    'inventory' => 'Tồn kho hiện tại',
    'customers' => 'Khách hàng đăng ký',
];
$reportType = $_GET['type'] ?? 'revenue';
if (!isset($reportTypes[$reportType])) {
    $reportType = 'revenue';
}

$storeCondition = $storeId > 0 ? ' AND h.cuahang_id = ?' : '';
$customerStoreCondition = $storeId > 0 ? ' AND k.cuahang_id = ?' : '';
$inventoryStoreCondition = $storeId > 0 ? ' AND tk.cuahang_id = ?' : '';
$params = [$rangeStart, $rangeEnd];
if ($storeId > 0) {
    $params[] = $storeId;
}

if ($reportType === 'revenue') {
    $columns = ['Ngày', 'Cửa hàng', 'Số hóa đơn hoàn thành', 'Tạm tính hóa đơn', 'Giá trị chi tiết hàng', 'Giảm giá', 'Khách phải trả', 'Đã thanh toán'];
    $sql = "
        SELECT DATE(h.ngay_tao) AS ngay, c.ten_cua_hang,
               COUNT(DISTINCT h.id) AS so_don,
               COALESCE(SUM(h.tong_tien), 0) AS tam_tinh,
               COALESCE(SUM(items.gia_tri_hang), 0) AS gia_tri_hang,
               COALESCE(SUM(h.tien_giam_gia), 0) AS tien_giam_gia,
               COALESCE(SUM(h.khach_phai_tra), 0) AS khach_phai_tra,
               COALESCE(SUM(payments.da_thu), 0) AS da_thu
        FROM hoadon h
        JOIN cuahang c ON c.id = h.cuahang_id
        LEFT JOIN (
            SELECT hoadon_id, SUM(thanh_tien) AS gia_tri_hang
            FROM chitiethoadon
            GROUP BY hoadon_id
        ) items ON items.hoadon_id = h.id
        LEFT JOIN (
            SELECT hoadon_id, SUM(so_tien_thanh_toan) AS da_thu
            FROM thanhtoan
            GROUP BY hoadon_id
        ) payments ON payments.hoadon_id = h.id
        WHERE h.trang_thai IN ('Hoàn thành', 'Đã thanh toán')
          AND h.ngay_tao >= ?
          AND h.ngay_tao < ?{$storeCondition}
        GROUP BY DATE(h.ngay_tao), h.cuahang_id, c.ten_cua_hang
        ORDER BY ngay DESC, c.ten_cua_hang
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $dataIssueSql = "
        SELECT COUNT(*)
        FROM hoadon h
        LEFT JOIN (
            SELECT hoadon_id, SUM(thanh_tien) AS gia_tri_hang
            FROM chitiethoadon
            GROUP BY hoadon_id
        ) items ON items.hoadon_id = h.id
        LEFT JOIN (
            SELECT hoadon_id, SUM(so_tien_thanh_toan) AS da_thu
            FROM thanhtoan
            GROUP BY hoadon_id
        ) payments ON payments.hoadon_id = h.id
        WHERE h.trang_thai IN ('Hoàn thành', 'Đã thanh toán')
          AND h.ngay_tao >= ?
          AND h.ngay_tao < ?{$storeCondition}
          AND (
              h.tong_tien <> COALESCE(items.gia_tri_hang, 0)
              OR h.khach_phai_tra <> COALESCE(payments.da_thu, 0)
          )
    ";
    $dataIssueStmt = $pdo->prepare($dataIssueSql);
    $dataIssueStmt->execute($params);
    $inconsistentInvoices = (int)$dataIssueStmt->fetchColumn();
} elseif ($reportType === 'products') {
    $columns = ['Sản phẩm', 'Danh mục', 'Số lượng bán', 'Giá trị hàng trước giảm giá'];
    $sql = "
        SELECT sp.ten_san_pham, dm.ten_danh_muc,
               SUM(ct.so_luong) AS so_luong_ban,
               SUM(ct.thanh_tien) AS doanh_thu
        FROM chitiethoadon ct
        JOIN hoadon h ON h.id = ct.hoadon_id
        JOIN sanpham sp ON sp.id = ct.sanpham_id
        JOIN danhmuc dm ON dm.id = sp.danhmuc_id
        WHERE h.trang_thai IN ('Hoàn thành', 'Đã thanh toán')
          AND h.ngay_tao >= ?
          AND h.ngay_tao < ?{$storeCondition}
        GROUP BY sp.id, sp.ten_san_pham, dm.ten_danh_muc
        ORDER BY so_luong_ban DESC, sp.ten_san_pham
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} elseif ($reportType === 'inventory') {
    $columns = ['Cửa hàng', 'Sản phẩm', 'Danh mục', 'Số lượng tồn'];
    $inventoryParams = $storeId > 0 ? [$storeId] : [];
    $sql = "
        SELECT c.ten_cua_hang, sp.ten_san_pham, dm.ten_danh_muc, tk.so_luong
        FROM tonkho tk
        JOIN cuahang c ON c.id = tk.cuahang_id
        JOIN sanpham sp ON sp.id = tk.sanpham_id
        JOIN danhmuc dm ON dm.id = sp.danhmuc_id
        WHERE 1 = 1{$inventoryStoreCondition}
        ORDER BY c.ten_cua_hang, sp.ten_san_pham
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($inventoryParams);
    $rows = $stmt->fetchAll();
} else {
    $columns = ['Ngày đăng ký', 'Cửa hàng', 'Hội viên mới'];
    $customerParams = [$rangeStart, $rangeEnd];
    if ($storeId > 0) {
        $customerParams[] = $storeId;
    }
    $sql = "
        SELECT DATE(k.ngay_dang_ky) AS ngay, c.ten_cua_hang, COUNT(k.id) AS so_khach
        FROM khachhang k
        JOIN cuahang c ON c.id = k.cuahang_id
        WHERE k.ngay_dang_ky >= ?
          AND k.ngay_dang_ky < ?{$customerStoreCondition}
        GROUP BY DATE(k.ngay_dang_ky), k.cuahang_id, c.ten_cua_hang
        ORDER BY ngay DESC, c.ten_cua_hang
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($customerParams);
    $rows = $stmt->fetchAll();
}

require ROOT_PATH . '/includes/header.php';
?>

<div class="bento-panel fade-up">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--violet), #ec4899)"><i class="fa-solid fa-filter"></i></span>
                Bộ lọc báo cáo
            </h5>
            <div class="panel-subtitle ms-5">Dữ liệu lấy trực tiếp từ hóa đơn, chi tiết hóa đơn, tồn kho và khách hàng</div>
        </div>
    </div>
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
            <input type="date" class="form-control" name="from" id="from" value="<?php echo e($_GET['from'] ?? ''); ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="to">Đến ngày</label>
            <input type="date" class="form-control" name="to" id="to" value="<?php echo e($_GET['to'] ?? ''); ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="store_id">Cửa hàng</label>
            <select class="form-select" name="store_id" id="store_id">
                <option value="0">Toàn chuỗi</option>
                <?php foreach ($stores as $store): ?>
                    <option value="<?php echo (int)$store['id']; ?>" <?php echo $storeId === (int)$store['id'] ? 'selected' : ''; ?>>
                        <?php echo e($store['ten_cua_hang']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-9">
            <label class="form-label" for="type">Loại báo cáo</label>
            <select class="form-select" name="type" id="type">
                <?php foreach ($reportTypes as $key => $label): ?>
                    <option value="<?php echo e($key); ?>" <?php echo $reportType === $key ? 'selected' : ''; ?>><?php echo e($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn-ck btn-ck-primary flex-grow-1"><i class="fa-solid fa-magnifying-glass-chart"></i> Áp dụng</button>
            <button type="button" id="btnExport" class="btn-ck btn-ck-soft"><i class="fa-solid fa-file-export"></i> CSV</button>
        </div>
    </form>
    <?php if ($range['error']): ?>
        <div class="alert alert-warning mt-3 mb-0"><?php echo e($range['error']); ?> Đang hiển thị 7 ngày gần nhất.</div>
    <?php endif; ?>
    <?php if ($reportType === 'revenue' && $inconsistentInvoices > 0): ?>
        <div class="alert alert-warning mt-3 mb-0">
            <strong>Dữ liệu hóa đơn chưa khớp:</strong>
            <?php echo number_format($inconsistentInvoices, 0, ',', '.'); ?> hóa đơn trong bộ lọc có tổng tiền khác chi tiết hàng hoặc số phải trả khác tổng thanh toán.
            Các cột được lấy riêng từ <code>hoadon</code>, <code>chitiethoadon</code> và <code>thanhtoan</code>.
        </div>
    <?php endif; ?>
    <?php if ($reportType === 'inventory'): ?>
        <div class="text-muted small mt-3">Tồn kho là ảnh chụp số lượng hiện tại; bộ lọc thời gian không áp dụng cho báo cáo này.</div>
    <?php endif; ?>
</div>

<div class="bento-panel fade-up d-1 mt-4">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--ocean), #6366f1)"><i class="fa-solid fa-table"></i></span>
                <?php echo e($reportTypes[$reportType]); ?>
            </h5>
            <div class="panel-subtitle ms-5">
                <?php if ($reportType === 'inventory'): ?>
                    Số lượng tồn hiện tại<?php echo $storeId > 0 ? '' : ' tại toàn chuỗi'; ?>
                <?php else: ?>
                    Từ <?php echo e($range['start']); ?> đến <?php echo e($range['end']); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="table-responsive">
        <table id="reportTable" class="table table-modern mb-0">
            <thead>
                <tr>
                    <?php foreach ($columns as $column): ?>
                        <th><?php echo e($column); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows): ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php if ($reportType === 'revenue'): ?>
                                <td><?php echo e(date('d/m/Y', strtotime($row['ngay']))); ?></td>
                                <td><?php echo e($row['ten_cua_hang']); ?></td>
                                <td class="text-end"><span class="num-font fw-bold"><?php echo number_format((int)$row['so_don'], 0, ',', '.'); ?></span></td>
                                <td class="text-end"><span class="num-font fw-bold"><?php echo number_format((float)$row['tam_tinh'], 0, ',', '.'); ?> ₫</span></td>
                                <td class="text-end"><span class="num-font fw-bold"><?php echo number_format((float)$row['gia_tri_hang'], 0, ',', '.'); ?> ₫</span></td>
                                <td class="text-end"><span class="num-font fw-bold"><?php echo number_format((float)$row['tien_giam_gia'], 0, ',', '.'); ?> ₫</span></td>
                                <td class="text-end"><span class="num-font fw-bold"><?php echo number_format((float)$row['khach_phai_tra'], 0, ',', '.'); ?> ₫</span></td>
                                <td class="text-end"><span class="num-font fw-bold"><?php echo number_format((float)$row['da_thu'], 0, ',', '.'); ?> ₫</span></td>
                            <?php elseif ($reportType === 'products'): ?>
                                <td><?php echo e($row['ten_san_pham']); ?></td>
                                <td><?php echo e($row['ten_danh_muc']); ?></td>
                                <td class="text-end"><span class="num-font fw-bold"><?php echo number_format((int)$row['so_luong_ban'], 0, ',', '.'); ?></span></td>
                                <td class="text-end"><span class="num-font fw-bold"><?php echo number_format((float)$row['doanh_thu'], 0, ',', '.'); ?> ₫</span></td>
                            <?php elseif ($reportType === 'inventory'): ?>
                                <td><?php echo e($row['ten_cua_hang']); ?></td>
                                <td><?php echo e($row['ten_san_pham']); ?></td>
                                <td><?php echo e($row['ten_danh_muc']); ?></td>
                                <td class="text-end"><span class="num-font fw-bold"><?php echo number_format((int)$row['so_luong'], 0, ',', '.'); ?></span></td>
                            <?php else: ?>
                                <td><?php echo e(date('d/m/Y', strtotime($row['ngay']))); ?></td>
                                <td><?php echo e($row['ten_cua_hang']); ?></td>
                                <td class="text-end"><span class="num-font fw-bold"><?php echo number_format((int)$row['so_khach'], 0, ',', '.'); ?></span></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="<?php echo count($columns); ?>" class="text-center text-muted py-4">Không có dữ liệu phù hợp với bộ lọc.</td></tr>
                <?php endif; ?>
            </tbody>
            <?php if ($rows): ?>
                <tfoot>
                    <tr class="table-light fw-bold">
                        <?php if ($reportType === 'revenue'): ?>
                            <th colspan="2">Tổng cộng</th>
                            <th class="text-end"><?php echo number_format(array_sum(array_column($rows, 'so_don')), 0, ',', '.'); ?></th>
                            <th class="text-end"><?php echo number_format(array_sum(array_column($rows, 'tam_tinh')), 0, ',', '.'); ?> ₫</th>
                            <th class="text-end"><?php echo number_format(array_sum(array_column($rows, 'gia_tri_hang')), 0, ',', '.'); ?> ₫</th>
                            <th class="text-end"><?php echo number_format(array_sum(array_column($rows, 'tien_giam_gia')), 0, ',', '.'); ?> ₫</th>
                            <th class="text-end"><?php echo number_format(array_sum(array_column($rows, 'khach_phai_tra')), 0, ',', '.'); ?> ₫</th>
                            <th class="text-end"><?php echo number_format(array_sum(array_column($rows, 'da_thu')), 0, ',', '.'); ?> ₫</th>
                        <?php elseif ($reportType === 'products'): ?>
                            <th colspan="2">Tổng cộng</th>
                            <th class="text-end"><?php echo number_format(array_sum(array_column($rows, 'so_luong_ban')), 0, ',', '.'); ?></th>
                            <th class="text-end"><?php echo number_format(array_sum(array_column($rows, 'doanh_thu')), 0, ',', '.'); ?> ₫</th>
                        <?php elseif ($reportType === 'inventory'): ?>
                            <th colspan="3">Tổng số lượng tồn</th>
                            <th class="text-end"><?php echo number_format(array_sum(array_column($rows, 'so_luong')), 0, ',', '.'); ?></th>
                        <?php else: ?>
                            <th colspan="2">Tổng hội viên đăng ký</th>
                            <th class="text-end"><?php echo number_format(array_sum(array_column($rows, 'so_khach')), 0, ',', '.'); ?></th>
                        <?php endif; ?>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<script>
document.querySelectorAll('#from, #to').forEach(function(input) {
    input.addEventListener('change', function() {
        document.getElementById('period').value = 'custom';
    });
});

document.getElementById('btnExport').addEventListener('click', function() {
    const table = document.getElementById('reportTable');
    const rows = Array.from(table.querySelectorAll('tr')).map(row =>
        Array.from(row.querySelectorAll('th, td')).map(cell =>
            '"' + cell.innerText.trim().replace(/"/g, '""') + '"'
        ).join(',')
    );
    const csv = '\uFEFF' + rows.join('\r\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'bao_cao_circle_k.csv';
    link.click();
    URL.revokeObjectURL(link.href);
});
</script>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
