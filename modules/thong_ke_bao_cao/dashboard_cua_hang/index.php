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
<div class="placeholder-box" style="min-height: 300px;"><i class="fa-solid fa-chart-line"></i><span>Biểu đồ đường doanh thu — vẽ bằng Chart.js, dùng CK trong charts-default.js</span></div>
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
<div class="placeholder-box" style="min-height: 300px;"><i class="fa-solid fa-chart-pie"></i><span>Biểu đồ tròn theo danh mục</span></div>
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

<?php require ROOT_PATH . '/includes/footer.php'; ?>
