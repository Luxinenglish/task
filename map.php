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
        $progress = min($progress, 200); // Cap à 200% si explosion

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


function getMonthlyEvolution($tasks) {
    $monthlyData = [];

    foreach ($tasks as $task) {
        foreach ($task['history'] as $date => $status) {
            $month = date('Y-m', strtotime($date));
            if (!isset($monthlyData[$month])) {
                $monthlyData[$month] = [];
            }

            if (!isset($monthlyData[$month][$date])) {
                $monthlyData[$month][$date] = ['done' => 0, 'total' => 0];
            }

            $monthlyData[$month][$date]['done'] += $status;
            $monthlyData[$month][$date]['total'] += 1;
        }
    }

    $result = ['labels' => [], 'datasets' => []];
    $colors = ['#007bff', '#28a745', '#ffc107', '#6610f2', '#e83e8c', '#fd7e14'];
    $i = 0;

    foreach ($monthlyData as $month => $days) {
        $dates = array_keys($days);
        sort($dates);

        if (count($dates) > count($result['labels'])) {
            $result['labels'] = $dates;
        }

        $data = [];
        foreach ($result['labels'] as $date) {
            if (isset($days[$date])) {
                $ratio = round(($days[$date]['done'] / $days[$date]['total']) * 100, 2);
                $data[] = $ratio;
            } else {
                $data[] = null;
            }
        }

        $result['datasets'][] = [
            'label' => $month,
            'data' => $data,
            'fill' => false,
            'borderColor' => $colors[$i % count($colors)],
            'tension' => 0.3
        ];

        $i++;
    }

    return $result;
}

// API
if (isset($_GET['donut'])) {
    $type = $_GET['donut'];
    echo json_encode(getDonutStats($data['tasks'], $type));
    exit;
}

if (isset($_GET['evolution'])) {
    echo json_encode(getMonthlyEvolution($data['tasks']));
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Statistiques</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<h2>Évolution mensuelle des tâches</h2>
<canvas id="evolutionChart" height="120"></canvas>

<h2>Progression globale</h2>
<canvas id="donutAll" height="120"></canvas>

<h2>Sur les 7 derniers jours</h2>
<canvas id="donutWeek" height="120"></canvas>

<h2>Aujourd’hui vs Hier</h2>
<canvas id="donutTodayVsYesterday" height="120"></canvas>


<script>
    async function drawDonutChart(id, period, title) {
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
    }

    async function drawEvolutionChart() {
        const res = await fetch('?evolution');
        const json = await res.json();
        const ctx = document.getElementById('evolutionChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: json,
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: '% Tâches accomplies'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Dates'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Taux de réussite par jour (par mois superposé)'
                    }
                }
            }
        });
    }

    drawEvolutionChart();
    drawDonutChart('donutAll', 'all', 'Tâches accomplies (Total)');
    drawDonutChart('donutWeek', 'week', 'Tâches accomplies (7 derniers jours)');
    drawDonutChart('donutTodayVsYesterday', 'today_vs_yesterday', "Tâches aujourd'hui comparé à hier");
</script>
</body>
</html>
