<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';

$pageTitle  = 'Biểu đồ so sánh toàn chuỗi';
$activeMenu = 'sosanh';

require ROOT_PATH . '/includes/header.php';
?>

<div class="bento-panel fade-up">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--violet), #ec4899)"><i class="fa-solid fa-chart-column"></i></span>
                So sánh doanh thu giữa các cửa hàng
            </h5>
            <div class="panel-subtitle ms-5">Toàn bộ chuỗi Circle K</div>
        </div>
        <div class="segmented-control" role="group"><button type="button" class="seg-btn active">7 Ngày</button><button type="button" class="seg-btn">30 Ngày</button><button type="button" class="seg-btn">Quý này</button></div>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3"><select class="form-select" style="width: auto;"><option>Doanh thu</option><option>Số đơn hàng</option><option>Hội viên mới</option></select><button type="button" class="btn-ck btn-ck-soft ms-auto"><i class="fa-solid fa-file-export"></i> Xuất Excel</button></div>
    <div class="placeholder-box" style="min-height: 330px;"><i class="fa-solid fa-chart-column"></i><span>Biểu đồ cột so sánh các cửa hàng — vẽ bằng Chart.js, dùng CK trong charts-default.js</span></div>
</div>

<div class="bento-panel fade-up d-1 mt-4">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--ck-amber), #f97316)"><i class="fa-solid fa-ranking-star"></i></span>
                Bảng xếp hạng cửa hàng
            </h5>
            <div class="panel-subtitle ms-5">Số liệu chi tiết từng cửa hàng</div>
        </div>
        
    </div>
    <div class="table-responsive">
        <table class="table table-modern mb-0">
            <thead><tr><th>Cửa hàng</th><th class="text-end">Doanh thu</th><th class="text-end">Số đơn</th><th>Tăng trưởng</th><th>Tỷ trọng</th></tr></thead>
            <tbody>
                <tr><td><span class="fw-semibold">Circle K Quận 1</span></td><td class="text-end"><span class="num-font fw-bold ">56,452,500 ₫</span></td><td class="text-end"><span class="num-font fw-bold ">652</span></td><td><span class="trend-pill trend-up"><i class="fa-solid fa-arrow-trend-up"></i> 8.5%</span></td><td><div class="progress-slim" style="min-width: 120px;"><div class="progress-slim-bar" style="width: 45%; background: var(--ck-red);"></div></div></td></tr>
                <tr><td><span class="fw-semibold">Circle K Quận 3</span></td><td class="text-end"><span class="num-font fw-bold ">37,635,000 ₫</span></td><td class="text-end"><span class="num-font fw-bold ">488</span></td><td><span class="trend-pill trend-down"><i class="fa-solid fa-arrow-trend-down"></i> 2.1%</span></td><td><div class="progress-slim" style="min-width: 120px;"><div class="progress-slim-bar" style="width: 30%; background: var(--ck-amber);"></div></div></td></tr>
            </tbody>
        </table>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
