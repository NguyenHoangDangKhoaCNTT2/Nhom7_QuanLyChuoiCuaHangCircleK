/* File dùng chung — không sửa riêng lẻ.
   Object toàn cục CK để mọi biểu đồ cùng màu/font/tooltip (màu đọc từ biến CSS trong theme.css):
   CK.colors.red|orange|amber|ocean|emerald|violet|ink500...  CK.alpha(hex, 0.2)  CK.vnd(n)  CK.tooltip  CK.scales() */
(function () {
    const css = name => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

    const colors = {
        red:     css('--ck-red'),
        orange:  css('--ck-orange'),
        amber:   css('--ck-amber'),
        ocean:   css('--ocean'),
        emerald: css('--emerald'),
        violet:  css('--violet'),
        ink900:  css('--ink-900'),
        ink500:  css('--ink-500'),
        ink400:  css('--ink-400'),
        grid:    css('--border-subtle'),
        surface: css('--surface'),
        muted:   '#cbd5e1'
    };

    function alpha(hex, a) {
        const h = hex.replace('#', '');
        const n = parseInt(h.length === 3 ? h.replace(/./g, '$&$&') : h, 16);
        return `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, ${a})`;
    }

    const vnd = n => new Intl.NumberFormat('vi-VN').format(n) + ' ₫';

    const tooltip = {
        backgroundColor: colors.ink900, padding: 12, cornerRadius: 10
    };

    const scales = (opts = {}) => ({
        y: Object.assign({
            beginAtZero: true, border: { display: false },
            grid: { color: colors.grid }, ticks: { font: { weight: '600' } }
        }, opts.y || {}),
        x: Object.assign({
            border: { display: false }, grid: { display: false },
            ticks: { font: { weight: '600' } }
        }, opts.x || {})
    });

    window.CK = { colors, alpha, vnd, tooltip, scales };

    if (!window.Chart) return;
    Chart.defaults.font.family = "'Be Vietnam Pro', sans-serif";
    Chart.defaults.color = colors.ink500;

    // Sparkline thẻ KPI: <canvas class="spark-canvas" data-color="#e11d48" data-spark='[1,2,3]'></canvas>
    document.querySelectorAll('.spark-canvas').forEach(cv => {
        const data  = JSON.parse(cv.dataset.spark);
        const color = cv.dataset.color || '#e11d48';
        const ctx   = cv.getContext('2d');
        const grad  = ctx.createLinearGradient(0, 0, 0, 42);
        grad.addColorStop(0, color + '45');
        grad.addColorStop(1, color + '00');

        new Chart(cv, {
            type: 'line',
            data: {
                labels: data.map((_, i) => i),
                datasets: [{
                    data, borderColor: color, borderWidth: 2.2,
                    backgroundColor: grad, fill: true,
                    pointRadius: 0, tension: 0.42
                }]
            },
            options: {
                responsive: false, animation: false,
                plugins: { legend: { display: false }, tooltip: { enabled: false } },
                scales: { x: { display: false }, y: { display: false } }
            }
        });
    });
})();
