<?php
define('DATA_FILE', 'task.json');

// Initialisation
if (!file_exists(DATA_FILE)) {
    file_put_contents(DATA_FILE, json_encode(['tasks' => [], 'homeworks' => []], JSON_PRETTY_PRINT));
}
$data = json_decode(file_get_contents(DATA_FILE), true);

// Ajouter une tâche
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
    $name = trim($_POST['name']);
    if ($name !== '') {
        $data['tasks'][] = ['name' => $name, 'history' => []];
        file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT));
    }
    header("Location: index.php");
    exit;
}

// Ajouter un devoir
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_homework'])) {
    $title = trim($_POST['title']);
    $due = $_POST['due_date'];
    if ($title !== '' && $due !== '') {
        $data['homeworks'][] = ['title' => $title, 'due' => $due, 'done' => false];
        file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT));
    }
    header("Location: index.php");
    exit;
}

// Marquer un devoir comme fait
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_homework'])) {
    $id = (int)$_POST['homework_id'];
    if (isset($data['homeworks'][$id])) {
        $data['homeworks'][$id]['done'] = !$data['homeworks'][$id]['done'];
        file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT));
    }
    exit;
}

// Mise à jour de l'état d'une tâche pour AUJOURD'HUI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $id = (int)$_POST['task_id'];
    $status = $_POST['status'] === '1' ? 1 : 0;
    $today = date('Y-m-d');
    if (isset($data['tasks'][$id])) {
        $data['tasks'][$id]['history'][$today] = $status;
        file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT));
    }
    exit;
}

// Données du graphique
if (isset($_GET['chart'])) {
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
    <title>Suivi Tâches</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0d6efd">
    <link rel="manifest" href="manifest.json">
    <link rel="icon" href="icon-192.png">
    <link rel="apple-touch-icon" href="icon-192.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
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
<body class="p-4">
<h1><?= ['😊', '🚀', '🌟', '🔥', '💡', '🎉', '🌈'][date('z') % 7] ?> <?= date('j') ?> <?= date('F') ?> - Suivi des Tâches</h1>

<!-- Formulaires -->
<form method="POST" class="d-flex mb-3">
    <input name="name" class="form-control me-2" placeholder="Ajouter une tâche" required>
    <button type="submit" name="add_task" class="btn btn-primary">Ajouter</button>
</form>
<form method="POST" class="d-flex mb-4 gap-2">
    <input name="title" class="form-control" placeholder="Titre du devoir" required>
    <input type="date" name="due_date" class="form-control" required>
    <button type="submit" name="add_homework" class="btn btn-warning">Ajouter Devoir</button>
</form>

<!-- Graphique -->
<canvas id="chart" height="100" class="mb-4"></canvas>

<!-- Liste des tâches -->
<h2>🚀 Tâches</h2>
<div class="list-group">
    <?php foreach ($data['tasks'] as $i => $task):
        $today = date('Y-m-d');
        $checked = !empty($task['history'][$today]) ? 'checked' : '';
        ?>
        <label class="list-group-item d-flex align-items-center">
            <input type="checkbox" class="form-check-input me-2" data-id="<?= $i ?>" <?= $checked ?>>
            <?= htmlspecialchars($task['name']) ?>
        </label>
    <?php endforeach; ?>
</div>

<!-- Liste des devoirs -->
<h2 class="mt-5">📚 Devoirs</h2>
<div class="list-group">
    <?php foreach ($data['homeworks'] as $i => $hw): ?>
        <form method="POST" class="list-group-item d-flex justify-content-between align-items-center">
            <div>
                <input type="hidden" name="homework_id" value="<?= $i ?>">
                <input type="hidden" name="toggle_homework" value="1">
                <button type="submit" class="btn btn-sm <?= $hw['done'] ? 'btn-success' : 'btn-outline-secondary' ?>">
                    <?= $hw['done'] ? '✔️' : '❌' ?>
                </button>
                <strong><?= htmlspecialchars($hw['title']) ?></strong>
                <small class="text-muted"> - À rendre le <?= date('d/m/Y', strtotime($hw['due'])) ?></small>
            </div>
        </form>
    <?php endforeach; ?>
</div>

<script>
    // Graphique
    async function updateChart() {
        const res = await fetch('?chart=1');
        const json = await res.json();
        const ctx = document.getElementById('chart').getContext('2d');
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
    updateChart();

    // Checkbox tâches
    document.querySelectorAll('input[type=checkbox]').forEach(cb => {
        cb.addEventListener('change', () => {
            fetch('index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    update_status: 1,
                    task_id: cb.dataset.id,
                    status: cb.checked ? 1 : 0
                })
            }).then(updateChart);
        });
    });

    // Notification à 21h
    if ('Notification' in window) {
        Notification.requestPermission();
        async function notif21h() {
            const now = new Date();
            if (now.getHours() === 21 && now.getMinutes() === 0 && now.getSeconds() < 5) {
                new Notification("🕘 Il est 21h ! Pense à cocher tes tâches.");
            }
        }
        setInterval(notif21h, 1000);
    }

    // Service Worker pour PWA
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('sw.js').then(() =>
            console.log('Service worker enregistré.')
        ).catch(console.error);
    }
</script>
</body>
</html>
