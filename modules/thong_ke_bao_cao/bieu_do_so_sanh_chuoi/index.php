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
        <div class="segmented-control time-filter" role="group">
            <button type="button" class="seg-btn active">7 Ngày</button>
            <button type="button" class="seg-btn">30 Ngày</button>
            <button type="button" class="seg-btn">Quý này</button>
        </div>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <select class="form-select" id="dataTypeFilter" style="width: auto;">
            <option value="doanh_thu">Doanh thu</option>
            <option value="so_don">Số đơn hàng</option>
            <option value="hoi_vien">Hội viên mới</option>
        </select>
        <button type="button" class="btn-ck btn-ck-soft ms-auto"><i class="fa-solid fa-file-export"></i> Xuất Excel</button>
    </div>
    <canvas id="compareChart" style="max-height: 330px;"></canvas>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const ctx = document.getElementById('compareChart').getContext('2d');
        
        let compareChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Circle K Quận 1', 'Circle K Quận 3', 'Circle K Quận 5', 'Circle K Bình Thạnh'],
                datasets: [{
                    label: 'Doanh thu (VNĐ)',
                    data: [56452500, 37635000, 42100000, 28500000],
                    backgroundColor: '#ec4899',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });

        const chartData = {
            '7 Ngày': {
                'doanh_thu': { data: [56452500, 37635000, 42100000, 28500000], color: '#ec4899', label: 'Doanh thu (VNĐ)' },
                'so_don': { data: [652, 488, 520, 310], color: '#0ea5e9', label: 'Số đơn hàng' },
                'hoi_vien': { data: [58, 35, 42, 20], color: '#10b981', label: 'Hội viên mới' }
            },
            '30 Ngày': {
                'doanh_thu': { data: [215000000, 150000000, 185000000, 110000000], color: '#ec4899', label: 'Doanh thu (VNĐ)' },
                'so_don': { data: [2400, 1850, 2100, 1300], color: '#0ea5e9', label: 'Số đơn hàng' },
                'hoi_vien': { data: [210, 150, 175, 85], color: '#10b981', label: 'Hội viên mới' }
            },
            'Quý này': {
                'doanh_thu': { data: [650000000, 480000000, 540000000, 320000000], color: '#ec4899', label: 'Doanh thu (VNĐ)' },
                'so_don': { data: [7500, 5600, 6300, 3900], color: '#0ea5e9', label: 'Số đơn hàng' },
                'hoi_vien': { data: [680, 420, 510, 290], color: '#10b981', label: 'Hội viên mới' }
            }
        };

        function updateCompareChart() {
            const timeRange = document.querySelector('.time-filter .seg-btn.active').innerText.trim();
            const dataType = document.getElementById('dataTypeFilter').value;
            const newData = chartData[timeRange][dataType];

            if (newData) {
                compareChart.data.datasets[0].data = newData.data;
                compareChart.data.datasets[0].label = newData.label;
                compareChart.data.datasets[0].backgroundColor = newData.color;
                compareChart.update();
            }
        }

        document.getElementById('dataTypeFilter').addEventListener('change', updateCompareChart);

        const timeButtons = document.querySelectorAll('.time-filter .seg-btn');
        timeButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                timeButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                updateCompareChart();
            });
        });
    });
</script>    
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
                <!-- Đã cập nhật đủ 4 cửa hàng -->
                <tr><td><span class="fw-semibold">Circle K Quận 1</span></td><td class="text-end"><span class="num-font fw-bold ">56,452,500 ₫</span></td><td class="text-end"><span class="num-font fw-bold ">652</span></td><td><span class="trend-pill trend-up"><i class="fa-solid fa-arrow-trend-up"></i> 8.5%</span></td><td><div class="progress-slim" style="min-width: 120px;"><div class="progress-slim-bar" style="width: 45%; background: var(--ck-red);"></div></div></td></tr>
                <tr><td><span class="fw-semibold">Circle K Quận 3</span></td><td class="text-end"><span class="num-font fw-bold ">37,635,000 ₫</span></td><td class="text-end"><span class="num-font fw-bold ">488</span></td><td><span class="trend-pill trend-down"><i class="fa-solid fa-arrow-trend-down"></i> 2.1%</span></td><td><div class="progress-slim" style="min-width: 120px;"><div class="progress-slim-bar" style="width: 30%; background: var(--ck-amber);"></div></div></td></tr>
                <tr><td><span class="fw-semibold">Circle K Quận 5</span></td><td class="text-end"><span class="num-font fw-bold ">42,100,000 ₫</span></td><td class="text-end"><span class="num-font fw-bold ">520</span></td><td><span class="trend-pill trend-up"><i class="fa-solid fa-arrow-trend-up"></i> 5.2%</span></td><td><div class="progress-slim" style="min-width: 120px;"><div class="progress-slim-bar" style="width: 38%; background: var(--ocean);"></div></div></td></tr>
                <tr><td><span class="fw-semibold">Circle K Bình Thạnh</span></td><td class="text-end"><span class="num-font fw-bold ">28,500,000 ₫</span></td><td class="text-end"><span class="num-font fw-bold ">310</span></td><td><span class="trend-pill trend-down"><i class="fa-solid fa-arrow-trend-down"></i> 1.5%</span></td><td><div class="progress-slim" style="min-width: 120px;"><div class="progress-slim-bar" style="width: 22%; background: var(--emerald);"></div></div></td></tr>
            
                <tr style="background-color: rgba(236, 72, 153, 0.05);">
                <td><span class="fw-bold text-uppercase" style="color: #ec4899;">Tổng toàn chuỗi</span></td>
                <td class="text-end"><span class="num-font fw-bold" style="color: #ec4899; font-size: 1.05rem;">164,687,500 ₫</span></td>
                <td class="text-end"><span class="num-font fw-bold" style="color: #ec4899; font-size: 1.05rem;">1,970</span></td>
    <td><span class="trend-pill trend-up"><i class="fa-solid fa-arrow-trend-up"></i> 5.6%</span></td>
    <td><span class="fw-bold" style="color: #ec4899;">100%</span></td>
</tr>

            </tbody>
        </table>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>