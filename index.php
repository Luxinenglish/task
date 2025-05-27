<?php
define('DATA_FILE', 'task.json');

// Initialisation du fichier si vide
if (!file_exists(DATA_FILE)) {
    file_put_contents(DATA_FILE, json_encode(['tasks' => []], JSON_PRETTY_PRINT));
}

// Charger les données
$data = json_decode(file_get_contents(DATA_FILE), true);

// Ajouter une tâche
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
    $name = trim($_POST['name']);
    if (!empty($name)) {
        $data['tasks'][] = ['name' => $name, 'history' => []];
        file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT));
    }
    header("Location: index.php");
    exit;
}

// Mise à jour de l'état d'une tâche pour aujourd'hui uniquement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $taskId = (int)$_POST['task_id'];
    $date = date('Y-m-d');
    $status = $_POST['status'] === '1' ? 1 : 0;

    if (isset($data['tasks'][$taskId])) {
        $data['tasks'][$taskId]['history'][$date] = $status;
        file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT));
    }
    exit;
}

// Données pour le graphique
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<h1>📅 Suivi des Tâches Quotidiennes</h1>

<form method="POST" class="d-flex mb-4">
    <input type="text" name="name" class="form-control me-2" required placeholder="Ajouter une tâche">
    <button type="submit" name="add_task" class="btn btn-primary">Ajouter</button>
</form>

<canvas id="chart" height="100" class="mb-4"></canvas>

<div class="list-group">
    <?php
    $today = date('Y-m-d');
    foreach ($data['tasks'] as $i => $task):
        $checked = !empty($task['history'][$today]) ? 'checked' : '';
        ?>
        <label class="list-group-item d-flex align-items-center">
            <input type="checkbox" class="form-check-input me-2" data-id="<?= $i ?>" <?= $checked ?>>
            <?= htmlspecialchars($task['name']) ?>
        </label>
    <?php endforeach; ?>
</div>

<script>
    // Graph
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

    // Envoi état tâche pour AUJOURD'HUI uniquement
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
            }).then(() => updateChart());
        });
    });

    // Notification 21h
    if (Notification.permission !== "granted") {
        Notification.requestPermission();
    }

    function sendNotif() {
        if (Notification.permission === "granted") {
            new Notification("🕘 Il est 21h ! Pense à cocher tes tâches.");
        }
    }

    setInterval(() => {
        const now = new Date();
        if (now.getHours() === 21 && now.getMinutes() === 0 && now.getSeconds() < 5) {
            sendNotif();
        }
    }, 1000);
</script>
</body>
</html>
