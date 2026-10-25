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
        <!-- Thêm class và id vào các nút bấm -->
        <div class="segmented-control time-filter" role="group">
            <button type="button" class="seg-btn active">7 Ngày</button>
            <button type="button" class="seg-btn">30 Ngày</button>
            <button type="button" class="seg-btn">Quý này</button>
        </div>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <!-- Đã thêm ID 'categoryFilter' và value vào select -->
        <select class="form-select" style="width: auto;" id="categoryFilter">
            <option value="all">Tất cả danh mục</option>
            <option value="douong">Đồ uống</option>
            <option value="doan">Đồ ăn</option>
            <option value="snack">Snack</option>
        </select>
    </div>
    <!-- Thay thế hộp trống bằng Canvas biểu đồ -->
    <div style="height: 300px; position: relative;">
        <canvas id="salesTrendChart"></canvas>
    </div>
</div>
    </div>
</div>

<!-- Tích hợp Chart.js và script điều khiển số liệu -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById('salesTrendChart').getContext('2d');
    
    // 1. Khởi tạo biểu đồ đường
    let trendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Ngày -6', 'Ngày -5', 'Ngày -4', 'Ngày -3', 'Ngày -2', 'Hôm qua', 'Hôm nay'],
            datasets: [{
                label: 'Số lượng bán',
                data: [482, 391, 356, 274, 219, 310, 450],
                borderColor: '#0ea5e9',
                backgroundColor: 'rgba(14, 165, 233, 0.1)',
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

    // 2. Bộ dữ liệu giả lập cho từng Khoảng thời gian và Danh mục
    const salesData = {
        '7 Ngày': {
            'labels': ['Ngày -6', 'Ngày -5', 'Ngày -4', 'Ngày -3', 'Ngày -2', 'Hôm qua', 'Hôm nay'],
            'all': [482, 391, 356, 274, 219, 310, 450],
            'douong': [250, 200, 180, 150, 120, 160, 220],
            'doan': [182, 150, 136, 104, 79, 120, 180],
            'snack': [50, 41, 40, 20, 20, 30, 50]
        },
        '30 Ngày': {
            'labels': ['Tuần 1', 'Tuần 2', 'Tuần 3', 'Tuần 4'],
            'all': [1800, 1500, 1950, 2100],
            'douong': [900, 750, 950, 1100],
            'doan': [700, 600, 800, 850],
            'snack': [200, 150, 200, 150]
        },
        'Quý này': {
            'labels': ['Tháng 8', 'Tháng 9', 'Tháng 10'],
            'all': [5200, 6100, 5800],
            'douong': [2600, 3100, 2900],
            'doan': [2000, 2400, 2300],
            'snack': [600, 600, 600]
        }
    };

    // 3. Hàm cập nhật biểu đồ chung
    function updateChart() {
        // Lấy khoảng thời gian đang được chọn (nút active)
        const activePeriod = document.querySelector('.time-filter .seg-btn.active').innerText.trim();
        // Lấy danh mục đang được chọn
        const selectedCategory = document.getElementById('categoryFilter').value;

        // Cập nhật trục X và Data
        if (salesData[activePeriod]) {
            trendChart.data.labels = salesData[activePeriod].labels;
            trendChart.data.datasets[0].data = salesData[activePeriod][selectedCategory];
            trendChart.update();
        }
    }

    // 4. Bắt sự kiện click vào nút thời gian
    const timeButtons = document.querySelectorAll('.time-filter .seg-btn');
    timeButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            timeButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            updateChart();
        });
    });

    // 5. Bắt sự kiện thay đổi dropdown danh mục
    document.getElementById('categoryFilter').addEventListener('change', updateChart);
});
</script>

<?php require ROOT_PATH . '/includes/footer.php'; ?>