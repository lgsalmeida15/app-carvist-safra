<?php
require_once 'config.php';

// Verifica se a coluna 'sincronizado' existe na tabela carvist_raw
$checkColumn = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'carvist_raw' AND column_name = 'sincronizado'")->fetch();

if (!$checkColumn) {
    $pdo->exec("ALTER TABLE carvist_raw ADD COLUMN sincronizado BOOLEAN DEFAULT false");
    $pdo->exec("CREATE INDEX idx_carvist_raw_sync ON carvist_raw(sincronizado) WHERE sincronizado = false");
}

// Verifica se a tabela sync_log existe
$pdo->exec("CREATE TABLE IF NOT EXISTS sync_log (
    id SERIAL PRIMARY KEY,
    total_lidos INT,
    inseridos INT,
    atualizados INT,
    erros INT,
    sem_vistoriador INT,
    log_json JSONB,
    executado_por VARCHAR(100),
    executado_em TIMESTAMP DEFAULT NOW()
)");

// Verifica se a tabela sync_jobs existe
$pdo->exec("CREATE TABLE IF NOT EXISTS sync_jobs (
    id SERIAL PRIMARY KEY,
    status VARCHAR(20) DEFAULT 'PENDENTE',
    total INT DEFAULT 0,
    processados INT DEFAULT 0,
    inseridos INT DEFAULT 0,
    atualizados INT DEFAULT 0,
    erros INT DEFAULT 0,
    sem_vistoriador INT DEFAULT 0,
    log_json JSONB,
    iniciado_em TIMESTAMP,
    concluido_em TIMESTAMP,
    criado_em TIMESTAMP DEFAULT NOW()
)");

// Busca estatísticas
$totalRaw = $pdo->query("SELECT COUNT(*) FROM carvist_raw")->fetchColumn();
$pendentes = $pdo->query("SELECT COUNT(*) FROM carvist_raw WHERE sincronizado = false")->fetchColumn();
$totalVistorias = $pdo->query("SELECT COUNT(*) FROM vistorias")->fetchColumn();

// Busca histórico de logs
$logs = $pdo->query("SELECT * FROM sync_log ORDER BY executado_em DESC LIMIT 10")->fetchAll();

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sincronização - Carvist</title>
    <link rel="stylesheet" href="includes/app-shell.css">
    <style>
        :root {
            --bg-color: #f4f7f6;
            --text-color: #333;
            --card-bg: #fff;
            --border-color: #ddd;
            --thead-bg: #2c3e50;
            --thead-text: #fff;
            --row-even: #f9f9f9;
            --primary-color: #3498db;
            --accent-color: #e74c3c;
            --success-color: #27ae60;
            --font-size: 12px;
            --row-selected: #e3f2fd;
        }

        [data-theme="dark"] {
            --bg-color: #1a1a1a;
            --text-color: #e0e0e0;
            --card-bg: #2d2d2d;
            --border-color: #444;
            --thead-bg: #000;
            --thead-text: #fff;
            --row-even: #333;
            --primary-color: #2980b9;
            --row-selected: #1e3a5f;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            font-size: var(--font-size);
            margin: 0;
            padding: 0;
            transition: background 0.3s, color 0.3s;
        }

        .sync-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            text-align: center;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary-color);
            margin: 10px 0;
        }
        .stat-label {
            font-size: 14px;
            color: var(--text-color);
            opacity: 0.8;
        }
        .sync-header h2 {
            margin-top: 0;
            color: var(--primary-color);
        }
        .btn-sync {
            display: inline-block;
            padding: 15px 30px;
            background: var(--success-color);
            color: white;
            border: none;
            border-radius: 4px;
            font-weight: bold;
            font-size: 18px;
            cursor: pointer;
            text-decoration: none;
            transition: opacity 0.3s;
        }
        .btn-sync:hover { opacity: 0.9; }
        .btn-sync:disabled { background: var(--border-color); cursor: not-allowed; }

        /* Estilos da Barra de Progresso */
        .progress-container {
            display: none;
            margin: 20px 0;
            background: var(--card-bg);
            padding: 20px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        .progress-bar-bg {
            background: var(--border-color);
            height: 25px;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        .progress-bar-fill {
            background: var(--primary-color);
            height: 100%;
            width: 0%;
            transition: width 0.5s;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 12px;
        }
        .progress-stats {
            display: flex;
            justify-content: space-around;
            font-size: 12px;
        }
        .progress-stats span {
            font-weight: bold;
        }

        .log-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card-bg);
            border-radius: 8px;
            overflow: hidden;
            margin-top: 20px;
        }
        .log-table th, .log-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        .log-table th {
            background: var(--thead-bg);
            color: var(--thead-text);
        }
    </style>
</head>
<body>
<?php
$carvist_nav_active = 'sync';
$carvist_container_wide = true;
require __DIR__ . '/includes/header.php';
?>

<div id="sync-message-container"></div>

<div class="sync-header" style="margin-bottom: 20px;">
    <h2>Painel de Sincronização</h2>
    <p>Transforme os dados brutos da planilha em vistorias normalizadas para o faturamento.</p>
</div>

<div class="sync-container">
    <div class="stat-card">
        <div class="stat-label">Registros Brutos (Total)</div>
        <div class="stat-value"><?php echo number_format($totalRaw, 0, ',', '.'); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Pendentes de Sincronia</div>
        <div id="stat-pendentes" class="stat-value" style="color: var(--accent-color);"><?php echo number_format($pendentes, 0, ',', '.'); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Vistorias Processadas</div>
        <div id="stat-vistorias" class="stat-value" style="color: var(--success-color);"><?php echo number_format($totalVistorias, 0, ',', '.'); ?></div>
    </div>
</div>

<!-- Barra de Progresso -->
<div id="progress-section" class="progress-container">
    <h3 id="progress-title">Sincronizando Dados...</h3>
    <div class="progress-bar-bg">
        <div id="progress-bar" class="progress-bar-fill">0%</div>
    </div>
    <div class="progress-stats">
        <div>Processados: <span id="count-processados">0</span> / <span id="count-total">0</span></div>
        <div style="color: var(--success-color)">Inseridos: <span id="count-inseridos">0</span></div>
        <div style="color: var(--primary-color)">Atualizados: <span id="count-atualizados">0</span></div>
        <div style="color: var(--accent-color)">Erros: <span id="count-erros">0</span></div>
    </div>
</div>

<div style="text-align: center; margin-bottom: 40px;">
    <button id="btn-iniciar-sync" class="btn-sync" <?php echo $pendentes == 0 ? 'disabled' : ''; ?>>
        <?php echo $pendentes == 0 ? 'Nada para sincronizar' : 'Sincronizar Agora'; ?>
    </button>
</div>

<h3>Histórico de Sincronização</h3>
<table class="log-table">
    <thead>
        <tr>
            <th>Data/Hora</th>
            <th>Total Lidos</th>
            <th>Inseridos</th>
            <th>Atualizados</th>
            <th>Erros</th>
            <th>Sem Vistoriador</th>
        </tr>
    </thead>
    <tbody id="log-history-body">
        <?php foreach ($logs as $log): ?>
            <tr>
                <td><?php echo date('d/m/Y H:i', strtotime($log['executado_em'])); ?></td>
                <td><?php echo $log['total_lidos']; ?></td>
                <td><?php echo $log['inseridos']; ?></td>
                <td><?php echo $log['atualizados']; ?></td>
                <td><?php echo $log['erros']; ?></td>
                <td><?php echo $log['sem_vistoriador']; ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($logs)): ?>
            <tr>
                <td colspan="6" style="text-align: center;">Nenhum log encontrado.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<script>
    const btnIniciar = document.getElementById('btn-iniciar-sync');
    const progressSection = document.getElementById('progress-section');
    const progressBar = document.getElementById('progress-bar');
    const countProcessados = document.getElementById('count-processados');
    const countTotal = document.getElementById('count-total');
    const countInseridos = document.getElementById('count-inseridos');
    const countAtualizados = document.getElementById('count-atualizados');
    const countErros = document.getElementById('count-erros');
    const msgContainer = document.getElementById('sync-message-container');

    btnIniciar.addEventListener('click', async () => {
        btnIniciar.disabled = true;
        btnIniciar.textContent = 'Iniciando...';
        
        try {
            const response = await fetch('sync_actions.php?action=iniciar', { method: 'POST' });
            const data = await response.json();
            
            if (data.job_id) {
                progressSection.style.display = 'block';
                iniciarPolling(data.job_id);
            } else {
                alert('Erro ao iniciar sincronização: ' + (data.error || 'Erro desconhecido'));
                btnIniciar.disabled = false;
                btnIniciar.textContent = 'Sincronizar Agora';
            }
        } catch (e) {
            alert('Erro na requisição: ' + e.message);
            btnIniciar.disabled = false;
        }
    });

    function iniciarPolling(jobId) {
        const intervalo = setInterval(async () => {
            try {
                const response = await fetch(`sync_actions.php?action=progresso&job_id=${jobId}`);
                const job = await response.json();

                const percent = job.total > 0 ? Math.round((job.processados / job.total) * 100) : 0;
                progressBar.style.width = percent + '%';
                progressBar.textContent = percent + '%';
                
                countProcessados.textContent = job.processados;
                countTotal.textContent = job.total;
                countInseridos.textContent = job.inseridos;
                countAtualizados.textContent = job.atualizados;
                countErros.textContent = job.erros;

                if (job.status === 'CONCLUIDO' || job.status === 'ERRO') {
                    clearInterval(intervalo);
                    finalizarSync(job);
                }
            } catch (e) {
                console.error('Erro no polling:', e);
            }
        }, 2000);
    }

    function finalizarSync(job) {
        btnIniciar.textContent = 'Sincronização Finalizada';
        document.getElementById('progress-title').textContent = job.status === 'CONCLUIDO' ? 'Sincronização Concluída!' : 'Erro na Sincronização';
        
        if (job.status === 'CONCLUIDO') {
            msgContainer.innerHTML = `
                <div class="banner success-banner">
                    <span class="tarja-animada tarja-sucesso-small">SUCESSO</span>
                    Sincronização finalizada: ${job.inseridos} inseridos, ${job.atualizados} atualizados.
                </div>
            `;
            // Recarrega a página após 3 segundos para atualizar estatísticas e histórico
            setTimeout(() => window.location.reload(), 3000);
        } else {
            msgContainer.innerHTML = `
                <div class="banner error-banner">
                    <span class="tarja-animada tarja-erro-small">ERRO</span>
                    Ocorreu um erro durante o processamento. Verifique os logs.
                </div>
            `;
        }
    }

    // Tema Dark/Light
    const themeToggle = document.getElementById('themeToggle');
    const currentTheme = localStorage.getItem('theme') || 'light';
    if (currentTheme === 'dark') document.body.setAttribute('data-theme', 'dark');

    themeToggle.addEventListener('click', () => {
        let theme = document.body.getAttribute('data-theme');
        if (theme === 'dark') {
            document.body.removeAttribute('data-theme');
            localStorage.setItem('theme', 'light');
        } else {
            document.body.setAttribute('data-theme', 'dark');
            localStorage.setItem('theme', 'dark');
        }
    });
</script>

</div> <!-- Fecha o container aberto no header.php -->
</body>
</html>
