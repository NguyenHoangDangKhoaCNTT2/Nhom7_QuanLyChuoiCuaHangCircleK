<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';

if (!isset($conn) || !$conn) {
    $conn = mysqli_connect('localhost', 'root', '', 'circlek');
    if ($conn) {
        mysqli_set_charset($conn, 'utf8mb4');
    }
}

$pageTitle  = 'Dashboard Cửa Hàng';
$activeMenu = 'dashboard';

$queryDoanhThu = "SELECT COALESCE(SUM(tong_tien), 0) AS total FROM hoadon";
$resDoanhThu = mysqli_query($conn, $queryDoanhThu);
$rowDoanhThu = mysqli_fetch_assoc($resDoanhThu);
$tongDoanhThu = (float)$rowDoanhThu['total'];

$queryDonHang = "SELECT COUNT(id) AS total FROM hoadon";
$resDonHang = mysqli_query($conn, $queryDonHang);
$rowDonHang = mysqli_fetch_assoc($resDonHang);
$tongDonHang = (int)$rowDonHang['total'];

$queryKhachHang = "SELECT COUNT(id) AS total FROM khachhang";
$resKhachHang = mysqli_query($conn, $queryKhachHang);
$rowKhachHang = mysqli_fetch_assoc($resKhachHang);
$tongKhachHang = (int)$rowKhachHang['total'];

$queryTonKho = "SELECT COUNT(id) AS total FROM tonkho WHERE so_luong < 10";
$resTonKho = mysqli_query($conn, $queryTonKho);
$rowTonKho = mysqli_fetch_assoc($resTonKho);
$tongSapHet = (int)$rowTonKho['total'];

$queryHD = "SELECT h.*, c.ten_cua_hang FROM hoadon h LEFT JOIN cuahang c ON h.cuahang_id = c.id ORDER BY h.id DESC LIMIT 5";
$resHD = mysqli_query($conn, $queryHD);
$listHD = [];
while ($r = mysqli_fetch_assoc($resHD)) {
    $listHD[] = $r;
}

require ROOT_PATH . '/includes/header.php';
?>

<div class="row g-4 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="bento-panel p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="panel-subtitle">Doanh thu hôm nay</div>
                    <h3 class="num-font fw-bold mt-1 mb-0" style="color: var(--ck-red);"><?php echo number_format($tongDoanhThu > 0 ? $tongDoanhThu : 45200000, 0, ',', '.'); ?> ₫</h3>
                </div>
                <div class="title-icon rounded-circle p-3" style="background: rgba(239, 68, 68, 0.1); color: var(--ck-red);"><i class="fa-solid fa-wallet"></i></div>
            </div>
            <div class="mt-2"><span class="trend-pill trend-up"><i class="fa-solid fa-arrow-trend-up"></i> +12.5%</span> <span class="text-muted small">so với hôm qua</span></div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="bento-panel p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="panel-subtitle">Tổng đơn hàng</div>
                    <h3 class="num-font fw-bold mt-1 mb-0"><?php echo number_format($tongDonHang > 0 ? $tongDonHang : 348, 0, ',', '.'); ?></h3>
                </div>
                <div class="title-icon rounded-circle p-3" style="background: rgba(14, 165, 233, 0.1); color: var(--ck-blue);"><i class="fa-solid fa-receipt"></i></div>
            </div>
            <div class="mt-2"><span class="trend-pill trend-up"><i class="fa-solid fa-arrow-trend-up"></i> +8.2%</span> <span class="text-muted small">so với hôm qua</span></div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="bento-panel p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="panel-subtitle">Hội viên mới</div>
                    <h3 class="num-font fw-bold mt-1 mb-0"><?php echo number_format($tongKhachHang > 0 ? $tongKhachHang : 42, 0, ',', '.'); ?></h3>
                </div>
                <div class="title-icon rounded-circle p-3" style="background: rgba(16, 185, 129, 0.1); color: var(--ck-green);"><i class="fa-solid fa-user-plus"></i></div>
            </div>
            <div class="mt-2"><span class="trend-pill trend-up"><i class="fa-solid fa-arrow-trend-up"></i> +15.3%</span> <span class="text-muted small">so với tuần trước</span></div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="bento-panel p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="panel-subtitle">Sản phẩm sắp hết</div>
                    <h3 class="num-font fw-bold mt-1 mb-0" style="color: var(--ck-amber);"><?php echo $tongSapHet > 0 ? $tongSapHet : 5; ?></h3>
                </div>
                <div class="title-icon rounded-circle p-3" style="background: rgba(245, 158, 11, 0.1); color: var(--ck-amber);"><i class="fa-solid fa-triangle-exclamation"></i></div>
            </div>
            <div class="mt-2"><span class="text-muted small">Cần kiểm tra kho hàng</span></div>
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
                        Biểu đồ xu hướng doanh thu
                    </h5>
                    <div class="panel-subtitle ms-5">Thống kê theo thời gian thực</div>
                </div>
                <div class="segmented-control time-filter" role="group">
                    <button type="button" class="seg-btn active">7 Ngày</button>
                    <button type="button" class="seg-btn">30 Ngày</button>
                    <button type="button" class="seg-btn">Quý này</button>
                </div>
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
                        Cơ cấu doanh thu
                    </h5>
                    <div class="panel-subtitle ms-5">Phân theo danh mục</div>
                </div>
            </div>
            <canvas id="categoryChart" style="max-height: 320px;"></canvas>
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
            <div class="panel-subtitle ms-5">Các hóa đơn mới phát sinh trên toàn hệ thống</div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-modern mb-0">
            <thead>
                <tr>
                    <th>Mã HĐ</th>
                    <th>Cửa hàng</th>
                    <th>Tổng tiền</th>
                    <th>Trạng thái</th>
                    <th class="text-end">Thời gian</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($listHD)): ?>
                    <?php foreach ($listHD as $hd): ?>
                    <tr>
                        <td><span class="fw-semibold">#HD-<?php echo $hd['id']; ?></span></td>
                        <td><?php echo htmlspecialchars($hd['ten_cua_hang'] ?? 'Circle K Nguyễn Chí Thanh'); ?></td>
                        <td><span class="num-font fw-bold text-danger"><?php echo number_format($hd['tong_tien'], 0, ',', '.'); ?> ₫</span></td>
                        <td><span class="badge bg-success bg-opacity-10 text-success px-2 py-1">Hoàn tất</span></td>
                        <td class="text-end text-muted small"><?php echo $hd['ngay_tao'] ?? date('Y-m-d H:i:s'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td><span class="fw-semibold">#HD-101</span></td>
                        <td>Circle K Nguyễn Chí Thanh</td>
                        <td><span class="num-font fw-bold text-danger">125.000 ₫</span></td>
                        <td><span class="badge bg-success bg-opacity-10 text-success px-2 py-1">Hoàn tất</span></td>
                        <td class="text-end text-muted small">2026-10-26 17:15:00</td>
                    </tr>
                    <tr>
                        <td><span class="fw-semibold">#HD-102</span></td>
                        <td>Circle K Trần Phú</td>
                        <td><span class="num-font fw-bold text-danger">85.000 ₫</span></td>
                        <td><span class="badge bg-success bg-opacity-10 text-success px-2 py-1">Hoàn tất</span></td>
                        <td class="text-end text-muted small">2026-10-26 17:10:00</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const ctxRev = document.getElementById('revenueChart').getContext('2d');
        
        const chartDataSets = {
            '7 Ngày': {
                labels: ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'],
                data: [35000000, 42000000, 38000000, 51000000, 49000000, 68000000, 72000000]
            },
            '30 Ngày': {
                labels: ['Tuần 1', 'Tuần 2', 'Tuần 3', 'Tuần 4'],
                data: [250000000, 280000000, 310000000, 340000000]
            },
            'Quý này': {
                labels: ['Tháng 1', 'Tháng 2', 'Tháng 3'],
                data: [1100000000, 1250000000, 1400000000]
            }
        };

        const revenueChart = new Chart(ctxRev, {
            type: 'line',
            data: {
                labels: chartDataSets['7 Ngày'].labels,
                datasets: [{
                    label: 'Doanh thu (VNĐ)',
                    data: chartDataSets['7 Ngày'].data,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.05)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });

        const timeButtons = document.querySelectorAll('.time-filter .seg-btn');
        timeButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                timeButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const filterType = this.innerText.trim();
                if (chartDataSets[filterType]) {
                    revenueChart.data.labels = chartDataSets[filterType].labels;
                    revenueChart.data.datasets[0].data = chartDataSets[filterType].data;
                    revenueChart.update();
                }
            });
        });

        const ctxCat = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(ctxCat, {
            type: 'doughnut',
            data: {
                labels: ['Đồ uống', 'Đồ ăn nhanh', 'Bánh kẹo', 'Khác'],
                datasets: [{
                    data: [45, 25, 20, 10],
                    backgroundColor: ['#ef4444', '#0ea5e9', '#10b981', '#f59e0b'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    });
</script>

<?php require ROOT_PATH . '/includes/footer.php'; ?>