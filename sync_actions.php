<?php
/**
 * Endpoints AJAX para controle da sincronização.
 */

require_once 'config.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'iniciar') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => 'Método inválido']);
        exit;
    }

    // 1. Cria o registro do job
    $stmt = $pdo->prepare("INSERT INTO sync_jobs (status, criado_em) VALUES ('PENDENTE', NOW()) RETURNING id");
    $stmt->execute();
    $jobId = $stmt->fetchColumn();

    // 2. Dispara o worker em background
    // No Windows, usamos 'start /B' para rodar em background sem abrir janela
    $cmd = "start /B php " . __DIR__ . "/cron/sync_worker.php $jobId > nul 2>&1";
    pclose(popen($cmd, "r"));

    echo json_encode(['job_id' => $jobId]);
    exit;
}

if ($action === 'progresso') {
    $jobId = (int)($_GET['job_id'] ?? 0);
    if (!$jobId) {
        echo json_encode(['error' => 'Job ID inválido']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM sync_jobs WHERE id = ?");
    $stmt->execute([$jobId]);
    $job = $stmt->fetch();

    if (!$job) {
        echo json_encode(['error' => 'Job não encontrado']);
        exit;
    }

    echo json_encode($job);
    exit;
}

echo json_encode(['error' => 'Ação inválida']);
