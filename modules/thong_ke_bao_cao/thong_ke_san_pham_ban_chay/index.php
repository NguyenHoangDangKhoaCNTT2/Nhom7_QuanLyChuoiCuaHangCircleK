<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';

$pageTitle  = 'Thống kê sản phẩm bán chạy';
$activeMenu = 'banchay';

require ROOT_PATH . '/includes/header.php';
?>

<div class="row g-4">
    <div class="col-12 col-xl-5">
<div class="bento-panel fade-up">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--emerald), #059669)"><i class="fa-solid fa-ranking-star"></i></span>
                Top 5 sản phẩm bán chạy
            </h5>
            <div class="panel-subtitle ms-5">Xếp hạng theo số lượng tiêu thụ</div>
        </div>
        
    </div>
        <div class="prod-row">
            <span class="prod-rank-icon" style="color: #f97316; background: color-mix(in srgb, #f97316 12%, transparent);"><i class="fa-solid fa-mug-hot"></i></span>
            <div class="flex-grow-1">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fw-bold" style="font-size: 0.9rem;">Cà phê sữa đá lon</span>
                    <div><span class="fw-bold num-font" style="color: #f97316; font-size: 0.88rem;">482 phần</span> <small class="text-muted ms-1" style="font-size: 0.76rem;">(14,460,000₫)</small></div>
                </div>
                <div class="progress-slim"><div class="progress-slim-bar" style="width: 100%; background: linear-gradient(90deg, #f97316, color-mix(in srgb, #f97316 60%, #fff));"></div></div>
            </div>
        </div>
        <div class="prod-row">
            <span class="prod-rank-icon" style="color: #e11d48; background: color-mix(in srgb, #e11d48 12%, transparent);"><i class="fa-solid fa-bowl-food"></i></span>
            <div class="flex-grow-1">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fw-bold" style="font-size: 0.9rem;">Mì Hảo Hảo ly</span>
                    <div><span class="fw-bold num-font" style="color: #e11d48; font-size: 0.88rem;">391 phần</span> <small class="text-muted ms-1" style="font-size: 0.76rem;">(9,775,000₫)</small></div>
                </div>
                <div class="progress-slim"><div class="progress-slim-bar" style="width: 81%; background: linear-gradient(90deg, #e11d48, color-mix(in srgb, #e11d48 60%, #fff));"></div></div>
            </div>
        </div>
        <div class="prod-row">
            <span class="prod-rank-icon" style="color: #0ea5e9; background: color-mix(in srgb, #0ea5e9 12%, transparent);"><i class="fa-solid fa-bottle-water"></i></span>
            <div class="flex-grow-1">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fw-bold" style="font-size: 0.9rem;">Nước khoáng Aquafina</span>
                    <div><span class="fw-bold num-font" style="color: #0ea5e9; font-size: 0.88rem;">356 phần</span> <small class="text-muted ms-1" style="font-size: 0.76rem;">(3,560,000₫)</small></div>
                </div>
                <div class="progress-slim"><div class="progress-slim-bar" style="width: 74%; background: linear-gradient(90deg, #0ea5e9, color-mix(in srgb, #0ea5e9 60%, #fff));"></div></div>
            </div>
        </div>
        <div class="prod-row">
            <span class="prod-rank-icon" style="color: #f59e0b; background: color-mix(in srgb, #f59e0b 12%, transparent);"><i class="fa-solid fa-bread-slice"></i></span>
            <div class="flex-grow-1">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fw-bold" style="font-size: 0.9rem;">Bánh mì Sandwich</span>
                    <div><span class="fw-bold num-font" style="color: #f59e0b; font-size: 0.88rem;">274 phần</span> <small class="text-muted ms-1" style="font-size: 0.76rem;">(6,028,000₫)</small></div>
                </div>
                <div class="progress-slim"><div class="progress-slim-bar" style="width: 57%; background: linear-gradient(90deg, #f59e0b, color-mix(in srgb, #f59e0b 60%, #fff));"></div></div>
            </div>
        </div>
        <div class="prod-row">
            <span class="prod-rank-icon" style="color: #8b5cf6; background: color-mix(in srgb, #8b5cf6 12%, transparent);"><i class="fa-solid fa-bolt"></i></span>
            <div class="flex-grow-1">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fw-bold" style="font-size: 0.9rem;">Nước tăng lực Sting</span>
                    <div><span class="fw-bold num-font" style="color: #8b5cf6; font-size: 0.88rem;">219 phần</span> <small class="text-muted ms-1" style="font-size: 0.76rem;">(3,066,000₫)</small></div>
                </div>
                <div class="progress-slim"><div class="progress-slim-bar" style="width: 45%; background: linear-gradient(90deg, #8b5cf6, color-mix(in srgb, #8b5cf6 60%, #fff));"></div></div>
            </div>
        </div>
</div>
    </div>
    <div class="col-12 col-xl-7">
<div class="bento-panel fade-up d-1">
    <div class="panel-header">
        <div>
            <h5 class="panel-title">
                <span class="title-icon" style="background: linear-gradient(135deg, var(--ocean), #6366f1)"><i class="fa-solid fa-chart-line"></i></span>
                Xu hướng bán hàng
            </h5>
            <div class="panel-subtitle ms-5">Số lượng bán theo ngày của sản phẩm chọn</div>
        </div>
        <div class="segmented-control" role="group"><button type="button" class="seg-btn active">7 Ngày</button><button type="button" class="seg-btn">30 Ngày</button><button type="button" class="seg-btn">Quý này</button></div>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3"><select class="form-select" style="width: auto;"><option>Tất cả danh mục</option><option>Đồ uống</option><option>Đồ ăn</option><option>Snack</option></select></div>
    <div class="placeholder-box" style="min-height: 300px;"><i class="fa-solid fa-chart-line"></i><span>Biểu đồ xu hướng — vẽ bằng Chart.js, dùng CK trong charts-default.js</span></div>
</div>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
