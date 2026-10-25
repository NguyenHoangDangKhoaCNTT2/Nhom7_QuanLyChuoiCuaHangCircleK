<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';

$pageTitle  = 'Dashboard thống kê cửa hàng';
$activeMenu = 'dashboard';

require ROOT_PATH . '/includes/header.php';
?>

<section class="hero-banner mb-4 fade-up">
    <div class="hero-content">
        <div class="hero-badge"><span class="pulse-dot" style="width:7px;height:7px;"></span><span>Dashboard cửa hàng • Circle K Quận 1</span></div>
        <h2 class="hero-title">Thống kê hoạt động cửa hàng</h2>
        <p class="hero-desc">Theo dõi doanh thu, số đơn hàng và hội viên mới của cửa hàng trong ngày.</p>
    </div>
</section>

<div class="row g-4 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="kpi-card fade-up d-1" style="--theme-color: #e11d48">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="kpi-label">Doanh thu hôm nay</div>
                        <h3 class="kpi-value num-font">56,452,500<span class="kpi-unit">₫</span></h3>
                    </div>
                    <div class="kpi-icon-box"><i class="fa-solid fa-sack-dollar"></i></div>
                </div>
                <div>
                    <span class="trend-pill trend-up"><i class="fa-solid fa-arrow-trend-up"></i> 8.5%</span>
                    <div class="kpi-sub">So với hôm qua</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="kpi-card fade-up d-2" style="--theme-color: #0ea5e9">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="kpi-label">Tổng đơn hàng</div>
                        <h3 class="kpi-value num-font">652<span class="kpi-unit">đơn</span></h3>
                    </div>
                    <div class="kpi-icon-box"><i class="fa-solid fa-receipt"></i></div>
                </div>
                <div>
                    <span class="trend-pill trend-up"><i class="fa-solid fa-arrow-trend-up"></i> 12.4%</span>
                    <div class="kpi-sub">Trung bình 86,500₫/đơn</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="kpi-card fade-up d-3" style="--theme-color: #10b981">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="kpi-label">Hội viên mới</div>
                        <h3 class="kpi-value num-font">+58<span class="kpi-unit">thẻ</span></h3>
                    </div>
                    <div class="kpi-icon-box"><i class="fa-solid fa-user-plus"></i></div>
                </div>
                <div>
                    <span class="trend-pill trend-down"><i class="fa-solid fa-arrow-trend-down"></i> 2.1%</span>
                    <div class="kpi-sub">So với tuần trước</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="kpi-card fade-up d-4" style="--theme-color: #f59e0b">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="kpi-label">Sản phẩm sắp hết</div>
                        <h3 class="kpi-value num-font">7<span class="kpi-unit">mặt hàng</span></h3>
                    </div>
                    <div class="kpi-icon-box"><i class="fa-solid fa-triangle-exclamation"></i></div>
                </div>
                <div>
                    <span class="trend-pill trend-up"><i class="fa-solid fa-arrow-trend-up"></i> 3 mặt hàng</span>
                    <div class="kpi-sub">Cần nhập thêm</div>
                </div>
            </div>
        </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12 col-xl-8">
<div class="bento-panel fade-up d-1">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--ck-red), var(--ck-orange))"><i class="fa-solid fa-chart-line"></i></span>
                Doanh thu 7 ngày
            </h5>
            <div class="panel-subtitle ms-5">So sánh với tuần trước</div>
        </div>
        <div class="segmented-control" role="group"><button type="button" class="seg-btn active">7 Ngày</button><button type="button" class="seg-btn">30 Ngày</button><button type="button" class="seg-btn">Quý này</button></div>
    </div>
    <!-- Canvas Biểu đồ đường -->
    <div style="height: 300px; position: relative;">
        <canvas id="revenueLineChart"></canvas>
    </div>
</div>
    </div>
    <div class="col-12 col-xl-4">
<div class="bento-panel fade-up d-2">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--ocean), #6366f1)"><i class="fa-solid fa-chart-pie"></i></span>
                Cơ cấu doanh thu
            </h5>
            <div class="panel-subtitle ms-5">Theo danh mục sản phẩm</div>
        </div>
        
    </div>
    <!-- Canvas Biểu đồ tròn -->
    <div style="height: 300px; position: relative;">
        <canvas id="revenuePieChart"></canvas>
    </div>
</div>
    </div>
</div>

<div class="bento-panel fade-up d-3">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--violet), #ec4899)"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                Giao dịch gần đây
            </h5>
            <div class="panel-subtitle ms-5">Hóa đơn phát sinh mới nhất tại cửa hàng</div>
        </div>
        
    </div>
    <div class="table-responsive">
        <table class="table table-modern mb-0">
            <thead><tr><th>Số HĐ</th><th>Thu ngân</th><th>Thanh toán</th><th>Thời gian</th><th>Trạng thái</th><th class="text-end">Giá trị</th></tr></thead>
            <tbody>
                <tr><td><span class="order-code">#HD1024</span></td><td>Trương Thị C</td><td>Ví MoMo</td><td>14:32</td><td><span class="st-badge st-ok"><i class="fa-solid fa-circle-check"></i> Hoàn tất</span></td><td class="text-end"><span class="num-font fw-bold ">125,000 ₫</span></td></tr>
                <tr><td><span class="order-code">#HD1020</span></td><td>Lê Văn D</td><td>VNPay QR</td><td>13:28</td><td><span class="st-badge st-wait"><i class="fa-solid fa-clock"></i> Đang xử lý</span></td><td class="text-end"><span class="num-font fw-bold ">159,000 ₫</span></td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Script khởi tạo biểu đồ và tương tác lọc số liệu động -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // 1. Khởi tạo biểu đồ đường (Line Chart) và lưu vào biến revenueChart
        const lineCtx = document.getElementById('revenueLineChart').getContext('2d');
        let revenueChart = new Chart(lineCtx, {
            type: 'line',
            data: {
                labels: ['Ngày -6', 'Ngày -5', 'Ngày -4', 'Ngày -3', 'Ngày -2', 'Hôm qua', 'Hôm nay'],
                datasets: [{
                    label: 'Doanh thu (VNĐ)',
                    data: [12500000, 14200000, 11800000, 15600000, 13400000, 16800000, 19500000],
                    borderColor: '#e11d48',
                    backgroundColor: 'rgba(225, 29, 72, 0.1)',
                    borderWidth: 2,
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

        // 2. Khai báo bộ số liệu giả lập cho các khoảng thời gian
        const chartData = {
            '7 Ngày': {
                labels: ['Ngày -6', 'Ngày -5', 'Ngày -4', 'Ngày -3', 'Ngày -2', 'Hôm qua', 'Hôm nay'],
                data: [12500000, 14200000, 11800000, 15600000, 13400000, 16800000, 19500000]
            },
            '30 Ngày': {
                labels: ['Tuần 1', 'Tuần 2', 'Tuần 3', 'Tuần 4'],
                data: [85000000, 92000000, 78000000, 105000000]
            },
            'Quý này': {
                labels: ['Tháng 7', 'Tháng 8', 'Tháng 9'],
                data: [350000000, 420000000, 380000000]
            }
        };

        // 3. Bắt sự kiện click vào các nút lọc thời gian
        const revenueButtons = document.querySelectorAll('.bento-panel.d-1 .seg-btn');
        revenueButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                // Đổi trạng thái hiển thị của nút
                revenueButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                // Lấy tên nút được bấm và cập nhật lại biểu đồ
                let timeRange = this.innerText.trim();
                if(chartData[timeRange]) {
                    revenueChart.data.labels = chartData[timeRange].labels;
                    revenueChart.data.datasets[0].data = chartData[timeRange].data;
                    revenueChart.update(); // Lệnh vẽ lại biểu đồ với hiệu ứng trượt
                }
            });
        });

        // 4. Vẽ biểu đồ tròn (Pie Chart) giữ nguyên
        const pieCtx = document.getElementById('revenuePieChart').getContext('2d');
        new Chart(pieCtx, {
            type: 'pie',
            data: {
                labels: ['Nước giải khát', 'Đồ ăn nhanh', 'Bánh kẹo', 'Khác'],
                datasets: [{
                    data: [45, 25, 20, 10],
                    backgroundColor: ['#0ea5e9', '#e11d48', '#f59e0b', '#10b981']
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    });
</script>

<?php require ROOT_PATH . '/includes/footer.php'; ?>