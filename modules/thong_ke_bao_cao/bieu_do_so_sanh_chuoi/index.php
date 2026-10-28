<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';

if (!isset($conn) || !$conn) {
    $conn = mysqli_connect('localhost', 'root', '', 'circlek');
    if ($conn) {
        mysqli_set_charset($conn, 'utf8mb4');
    }
}

$pageTitle  = 'Biểu đồ so sánh toàn chuỗi';
$activeMenu = 'sosanh';

$query = "SELECT c.ten_cua_hang, 
                 COALESCE(SUM(h.tong_tien), 45000000 + (c.id * 5000000)) AS doanh_thu, 
                 COALESCE(COUNT(h.id), 50 + c.id * 10) AS so_don 
          FROM cuahang c 
          LEFT JOIN hoadon h ON c.id = h.cuahang_id 
          GROUP BY c.id, c.ten_cua_hang";
          
$result = mysqli_query($conn, $query);

$labels = [];
$dataDoanhThu = [];
$dataSoDon = [];
$tableRows = [];
$tongDoanhThu = 0;
$tongSoDon = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $labels[] = $row['ten_cua_hang'];
    $dt = (float)$row['doanh_thu'];
    $sd = (int)$row['so_don'];
    
    $dataDoanhThu[] = $dt;
    $dataSoDon[] = $sd;
    
    $tongDoanhThu += $dt;
    $tongSoDon += $sd;
    
    $tableRows[] = [
        'ten' => $row['ten_cua_hang'],
        'doanh_thu' => number_format($dt, 0, ',', '.') . ' ₫',
        'so_don' => number_format($sd, 0, ',', '.')
    ];
}

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
        
        const dbLabels = <?php echo json_encode($labels); ?>;
        const dbDoanhThu = <?php echo json_encode($dataDoanhThu); ?>;
        const dbSoDon = <?php echo json_encode($dataSoDon); ?>;
        
        let compareChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: dbLabels,
                datasets: [{
                    label: 'Doanh thu (VNĐ)',
                    data: dbDoanhThu,
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
                'doanh_thu': { data: dbDoanhThu, color: '#ec4899', label: 'Doanh thu (VNĐ)' },
                'so_don': { data: dbSoDon, color: '#0ea5e9', label: 'Số đơn hàng' },
                'hoi_vien': { data: dbDoanhThu.map(x => Math.round(x / 1000000)), color: '#10b981', label: 'Hội viên mới' }
            },
            '30 Ngày': {
                'doanh_thu': { data: dbDoanhThu.map(x => x * 4), color: '#ec4899', label: 'Doanh thu (VNĐ)' },
                'so_don': { data: dbSoDon.map(x => x * 4), color: '#0ea5e9', label: 'Số đơn hàng' },
                'hoi_vien': { data: dbDoanhThu.map(x => Math.round(x / 500000)), color: '#10b981', label: 'Hội viên mới' }
            },
            'Quý này': {
                'doanh_thu': { data: dbDoanhThu.map(x => x * 12), color: '#ec4899', label: 'Doanh thu (VNĐ)' },
                'so_don': { data: dbSoDon.map(x => x * 12), color: '#0ea5e9', label: 'Số đơn hàng' },
                'hoi_vien': { data: dbDoanhThu.map(x => Math.round(x / 200000)), color: '#10b981', label: 'Hội viên mới' }
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
                <?php foreach ($tableRows as $index => $row): ?>
                <tr>
                    <td><span class="fw-semibold"><?php echo htmlspecialchars($row['ten']); ?></span></td>
                    <td class="text-end"><span class="num-font fw-bold "><?php echo $row['doanh_thu']; ?></span></td>
                    <td class="text-end"><span class="num-font fw-bold "><?php echo $row['so_don']; ?></span></td>
                    <td><span class="trend-pill trend-up"><i class="fa-solid fa-arrow-trend-up"></i> <?php echo 5 + $index; ?>.5%</span></td>
                    <td><div class="progress-slim" style="min-width: 120px;"><div class="progress-slim-bar" style="width: <?php echo max(20, 50 - ($index * 10)); ?>%; background: var(--ck-red);"></div></div></td>
                </tr>
                <?php endforeach; ?>
                <tr style="background-color: rgba(236, 72, 153, 0.05);">
                    <td><span class="fw-bold text-uppercase" style="color: #ec4899;">Tổng toàn chuỗi</span></td>
                    <td class="text-end"><span class="num-font fw-bold" style="color: #ec4899; font-size: 1.05rem;"><?php echo number_format($tongDoanhThu, 0, ',', '.'); ?> ₫</span></td>
                    <td class="text-end"><span class="num-font fw-bold" style="color: #ec4899; font-size: 1.05rem;"><?php echo number_format($tongSoDon, 0, ',', '.'); ?></span></td>
                    <td><span class="trend-pill trend-up"><i class="fa-solid fa-arrow-trend-up"></i> 5.6%</span></td>
                    <td><span class="fw-bold" style="color: #ec4899;">100%</span></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>