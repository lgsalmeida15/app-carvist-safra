<?php
/**
 * Worker para processamento de sincronização em background.
 * Chamado via: php cron/sync_worker.php {jobId}
 */

require_once __DIR__ . '/../config.php';

use App\Services\SyncService;

if ($argc < 2) {
    die("ID do Job não fornecido.\n");
}

$jobId = (int)$argv[1];

// Aumenta o tempo limite para processamento longo
set_time_limit(0);
ini_set('memory_limit', '512M');

try {
    $syncService = new SyncService($pdo);
    $syncService->executar($jobId);
} catch (Exception $e) {
    // Em caso de erro fatal não capturado pelo SyncService
    $pdo->prepare("UPDATE sync_jobs SET status = 'ERRO', log_json = :log, concluido_em = NOW() WHERE id = :id")
        ->execute([
            ':log' => json_encode(['erro_fatal' => $e->getMessage()]),
            ':id' => $jobId
        ]);
}
