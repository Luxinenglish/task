<?php
$data = json_decode(file_get_contents('task.json'), true);

function getDonutStats($tasks, $period) {
    $done = 0;
    $total = 0;
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    if ($period === 'today_vs_yesterday') {
        $doneToday = 0;
        $doneYesterday = 0;

        foreach ($tasks as $task) {
            if (isset($task['history'][$today])) {
                $doneToday += $task['history'][$today];
            }
            if (isset($task['history'][$yesterday])) {
                $doneYesterday += $task['history'][$yesterday];
            }
        }

        $progress = $doneYesterday > 0 ? round(($doneToday / $doneYesterday) * 100, 2) : ($doneToday > 0 ? 100 : 0);
        $progress = min($progress, 200);

        return [
            'labels' => ["Aujourd'hui vs Hier"],
            'data' => [$progress, 100 - min($progress, 100)]
        ];
    }

    foreach ($tasks as $task) {
        foreach ($task['history'] as $date => $status) {
            if (
                $period === 'week' && strtotime($date) >= strtotime("-7 days", strtotime($today)) ||
                $period === 'day' && $date === $yesterday ||
                $period === 'all'
            ) {
                $done += $status;
                $total += 1;
            }
        }
    }

    $donePercent = $total > 0 ? round(($done / $total) * 100, 2) : 0;
    $notDonePercent = 100 - $donePercent;

    return [
        'labels' => ['Tâches faites', 'Tâches non faites'],
        'data' => [$donePercent, $notDonePercent]
    ];
}
// API
if (isset($_GET['donut'])) {
    $type = $_GET['donut'];
    echo json_encode(getDonutStats($data['tasks'], $type));
    exit;
}
// Données du graphique
if (isset($_GET['evolutionchart'])) {
    $labels = [];
    $values = [];
    for ($i = 0; $i < 30; $i++) {
        $date = date('Y-m-d', strtotime("-" . (29 - $i) . " days"));
        $labels[] = date('d/m', strtotime($date));
        $done = 0;
        foreach ($data['tasks'] as $task) {
            $done += !empty($task['history'][$date]) ? 1 : 0;
        }
        $values[] = $done;
    }
    echo json_encode(['labels' => $labels, 'data' => $values]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Statistiques</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            max-width: 1200px;
            margin: auto;
        }

        h2 {
            text-align: center;
            margin-top: 40px;
        }

        canvas {
            width: 100% !important;
            height: auto !important;
        }

        .donut-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
        }

        .donut-chart {
            flex: 1 1 250px;
            max-width: 300px;
            min-width: 200px;
        }

        @media (max-width: 768px) {
            .donut-container {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
</head>
<body>

<h2>Évolution mensuelle des tâches</h2>
<canvas id="evolutionchart" height="100" class="mb-4"></canvas>

<h2>Progression globale</h2>
<div class="donut-container">
    <div class="donut-chart">
        <canvas id="donutAll"></canvas>
    </div>
    <div class="donut-chart">
        <canvas id="donutWeek"></canvas>
    </div>
    <div class="donut-chart">
        <canvas id="donutTodayVsYesterday"></canvas>
    </div>
</div>

<script>
    // Graphique
    async function updateevolutionChart() {
        const res = await fetch('?evolutionchart=1');
        const json = await res.json();
        const ctx = document.getElementById('evolutionchart').getContext('2d');
        if (window.myChart) window.myChart.destroy();
        window.myChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: json.labels,
                datasets: [{
                    label: "Tâches accomplies",
                    data: json.data,
                    borderColor: 'blue',
                    backgroundColor: 'lightblue',
                    fill: true
                }]
            }
        });
    }
    updateevolutionChart();
    async function drawDonutChart(id, period, title) {
        try {
            const res = await fetch('?donut=' + period);
            const json = await res.json();

            const ctx = document.getElementById(id).getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: json.labels,
                    datasets: [{
                        data: json.data,
                        backgroundColor: ['#28a745', '#dc3545']
                    }]
                },
                options: {
                    plugins: {
                        title: {
                            display: true,
                            text: title
                        }
                    }
                }
            });
        } catch (e) {
            console.error("Erreur lors du chargement du graphique " + id, e);
        }
    }



    drawDonutChart('donutAll', 'all', 'Tâches accomplies (Total)');
    drawDonutChart('donutWeek', 'week', 'Tâches accomplies (7 derniers jours)');
    drawDonutChart('donutTodayVsYesterday', 'today_vs_yesterday', "Tâches aujourd'hui comparé à hier");
</script>

</body>
</html>
