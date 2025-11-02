<?php
require_once __DIR__ . '/session_check.php';
if (session_status() == PHP_SESSION_NONE) session_start();
// Simple permission: only logged in users can view (you can adjust to role checks)
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}
?>
<!doctype html>
<html lang="<?php echo get_lang_code(); ?>" <?php echo (get_lang_code() === 'ar')? 'dir="rtl"' : ''; ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo (get_lang_code() === 'ar') ? 'لوحة الإحصائيات والتقارير' : 'Statistics & Reports'; ?></title>
    <link rel="stylesheet" href="assets/css/modern_styles.css">
    <style>
        .reports-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px; }
        .card { background:#fff;padding:16px;border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,.08);} 
        .kpi-row { display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap; }
        .kpi { flex:1; min-width:160px; padding:12px; background:#f7f7f7; border-radius:6px; text-align:center; }
        .chart-canvas { width:100%; height:320px; }
    </style>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php require_once __DIR__ . '/navbar.php'; ?>
    <main style="padding:20px;">
        <h1><?php echo (get_lang_code() === 'ar') ? 'لوحة الإحصائيات والتقارير' : 'Statistics & Reports Dashboard'; ?></h1>

        <!-- KPI cards (placeholders for future expansion) -->
        <div class="kpi-row">
            <div class="kpi card">
                <div class="kpi-title"><?php echo (get_lang_code() === 'ar') ? 'إجمالي الأنشطة (آخر 7 أيام)' : 'Total Activities (last 7 days)'; ?></div>
                <div id="kpi_activities_7d">--</div>
            </div>
            <div class="kpi card">
                <div class="kpi-title"><?php echo (get_lang_code() === 'ar') ? 'إجمالي زيارات العقارات (آخر 7 أيام)' : 'Property Visits (last 7 days)'; ?></div>
                <div id="kpi_visits_7d">--</div>
            </div>
            <div class="kpi card">
                <div class="kpi-title"><?php echo (get_lang_code() === 'ar') ? 'العقارات المضافة (آخر 30 يوم)' : 'Properties Added (last 30 days)'; ?></div>
                <div id="kpi_added_30d">--</div>
            </div>
            <div class="kpi card">
                <div class="kpi-title"><?php echo (get_lang_code() === 'ar') ? 'المستخدمون النشطون (اليوم)' : 'Active Users (today)'; ?></div>
                <div id="kpi_active_today">--</div>
            </div>
        </div>

        <div class="reports-grid">
            <div class="card">
                <h3><?php echo (get_lang_code() === 'ar') ? 'تصنيف الأنشطة' : 'Activity Breakdown'; ?></h3>
                <canvas id="activityPie" class="chart-canvas"></canvas>
            </div>

            <div class="card">
                <h3><?php echo (get_lang_code() === 'ar') ? 'الموظفون الأكثر نشاطاً' : 'Top 5 Active Employees'; ?></h3>
                <canvas id="topEmployeesBar" class="chart-canvas"></canvas>
            </div>

            <div class="card">
                <h3><?php echo (get_lang_code() === 'ar') ? 'العقارات الأكثر زيارة' : 'Most Visited Properties'; ?></h3>
                <canvas id="mostVisitedBar" class="chart-canvas"></canvas>
            </div>

            <div class="card">
                <h3><?php echo (get_lang_code() === 'ar') ? 'العقارات الأكثر تعديلاً' : 'Most Edited Properties'; ?></h3>
                <canvas id="mostEditedBar" class="chart-canvas"></canvas>
            </div>

            <div class="card" style="grid-column: 1 / -1;">
                <h3><?php echo (get_lang_code() === 'ar') ? 'النشاط عبر الزمن (آخر 30 يوم)' : 'Activity Over Time (last 30 days)'; ?></h3>
                <canvas id="activityOverTime" class="chart-canvas" style="height:340px;"></canvas>
            </div>
        </div>

        <script>
        async function loadReports() {
            try {
                const res = await fetch('api/reports_data.php');
                if (!res.ok) throw new Error('Network response was not ok');
                const data = await res.json();

                // Activity Breakdown (Pie)
                const actions = data.activity_breakdown || [];
                const pieLabels = actions.map(a => a.action);
                const pieValues = actions.map(a => a.cnt);
                const pieCtx = document.getElementById('activityPie').getContext('2d');
                new Chart(pieCtx, {
                    type: 'pie',
                    data: { labels: pieLabels, datasets: [{ data: pieValues, backgroundColor: ['#4e79a7','#f28e2b','#e15759','#76b7b2','#59a14f','#edc949'] }] },
                    options: { responsive: true }
                });

                // Top Employees (Bar)
                const emps = data.top_employees || [];
                const empLabels = emps.map(e => e.name || ('#' + (e.employee_id || 'N')));
                const empValues = emps.map(e => e.cnt);
                const barCtx = document.getElementById('topEmployeesBar').getContext('2d');
                new Chart(barCtx, {
                    type: 'bar',
                    data: { labels: empLabels, datasets: [{ label: '<?php echo (get_lang_code() === 'ar')? 'عدد الأنشطة' : 'Activity Count'; ?>', data: empValues, backgroundColor: '#4e79a7' }] },
                    options: { responsive: true, scales: { y: { beginAtZero:true } } }
                });

                // Optionally fill KPI placeholders if returned
                if (data.kpis) {
                    document.getElementById('kpi_activities_7d').textContent = data.kpis.activities_7d ?? '--';
                    document.getElementById('kpi_visits_7d').textContent = data.kpis.visits_7d ?? '--';
                    document.getElementById('kpi_added_30d').textContent = data.kpis.added_30d ?? '--';
                    document.getElementById('kpi_active_today').textContent = data.kpis.active_today ?? '--';
                }

                // Most Visited Properties (Bar)
                const visited = data.most_visited_properties || [];
                const visitedLabels = visited.map(p => p.title || ('#' + (p.property_id || 'N')));
                const visitedValues = visited.map(p => p.cnt);
                if (visitedLabels.length) {
                    const vctx = document.getElementById('mostVisitedBar').getContext('2d');
                    new Chart(vctx, {
                        type: 'bar',
                        data: { labels: visitedLabels, datasets: [{ label: '<?php echo (get_lang_code() === 'ar')? 'عدد الزيارات' : 'Visits'; ?>', data: visitedValues, backgroundColor: '#59a14f' }] },
                        options: { responsive:true, scales:{ y:{ beginAtZero:true } } }
                    });
                }

                // Most Edited Properties (Bar)
                const edited = data.most_edited_properties || [];
                const editedLabels = edited.map(p => p.title || ('#' + (p.property_id || 'N')));
                const editedValues = edited.map(p => p.cnt);
                if (editedLabels.length) {
                    const ectx = document.getElementById('mostEditedBar').getContext('2d');
                    new Chart(ectx, {
                        type: 'bar',
                        data: { labels: editedLabels, datasets: [{ label: '<?php echo (get_lang_code() === 'ar')? 'عدد التعديلات' : 'Edits'; ?>', data: editedValues, backgroundColor: '#e15759' }] },
                        options: { responsive:true, scales:{ y:{ beginAtZero:true } } }
                    });
                }

                // Activity Over Time (Line)
                const series = data.activity_over_time || [];
                if (series.length) {
                    const dates = series.map(s => s.date);
                    const cnts = series.map(s => s.cnt);
                    const lctx = document.getElementById('activityOverTime').getContext('2d');
                    new Chart(lctx, {
                        type: 'line',
                        data: { labels: dates, datasets: [{ label: '<?php echo (get_lang_code() === 'ar')? 'عدد الأنشطة' : 'Activities'; ?>', data: cnts, borderColor: '#4e79a7', backgroundColor: 'rgba(78,121,167,0.08)', fill:true }] },
                        options: { responsive:true, scales:{ y:{ beginAtZero:true } }, elements: { point: { radius: 2 } } }
                    });
                }

            } catch (err) {
                console.error(err);
                const container = document.createElement('div');
                container.className = 'card';
                container.textContent = 'Failed to load reports: ' + err.message;
                document.querySelector('main').appendChild(container);
            }
        }
        window.addEventListener('DOMContentLoaded', loadReports);
        </script>
    </main>
</body>
</html>
