<?php
require_once 'config.php';

// --- LÓGICA DE SALVAMENTO (POST) ---
$update_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_changes') {
    $selected_rows = $_POST['selected_rows'] ?? [];
    
    if (!empty($selected_rows)) {
        try {
            $pdo->beginTransaction();
            
            $sql_update = "UPDATE safra_combos SET rel = :rel WHERE placa = :placa";
            $stmt_update = $pdo->prepare($sql_update);
            
            foreach ($selected_rows as $placa) {
                $rel_value = $_POST['rel'][$placa] ?? '';
                $stmt_update->execute([
                    'rel' => $rel_value,
                    'placa' => $placa
                ]);
            }
            
            $pdo->commit();
            $update_msg = "Sucesso: " . count($selected_rows) . " registros atualizados.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = "Erro ao salvar: " . $e->getMessage();
        }
    } else {
        $update_msg = "Nenhum registro selecionado para salvar.";
    }
}

// --- LÓGICA DE BUSCA (GET) ---
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] === '1';
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$placa_filter = isset($_GET['placa']) ? strtoupper(trim($_GET['placa'])) : '';
$status_filter = isset($_GET['status_envio']) ? trim($_GET['status_envio']) : '';

$where = ["1=1"];
$params = [];

if ($placa_filter !== '') {
    if (strlen($placa_filter) === 7) {
        $where[] = "placa = :placa";
        $params['placa'] = $placa_filter;
    } else {
        $where[] = "placa ILIKE :placa";
        $params['placa'] = "%$placa_filter%";
    }
}

if ($status_filter !== '') {
    $where[] = "status_envio = :status_envio";
    $params['status_envio'] = $status_filter;
}

$where_clause = implode(' AND ', $where);

try {
    $count_sql = "SELECT COUNT(*) FROM safra_combos WHERE $where_clause";
    $stmt_count = $pdo->prepare($count_sql);
    $stmt_count->execute($params);
    $total_records = $stmt_count->fetchColumn();
    $total_pages = ceil($total_records / $limit);
} catch (Exception $e) {
    $total_records = 0; $total_pages = 0;
    $error_msg = "Erro na query: " . $e->getMessage();
}

$data = [];
if (!isset($error_msg)) {
    try {
        $sql = "SELECT * FROM safra_combos WHERE $where_clause ORDER BY data DESC, placa ASC LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();
    } catch (Exception $e) {
        $error_msg = "Erro ao buscar dados: " . $e->getMessage();
    }
}

// Busca status distintos para o select
$status_list = [];
try {
    $status_list = $pdo->query("SELECT DISTINCT status_envio FROM safra_combos WHERE status_envio IS NOT NULL ORDER BY status_envio")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// --- RESPOSTA AJAX ---
if ($is_ajax) {
    header('Content-Type: application/json');
    ob_start();
    ?>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th><input type="checkbox" id="selectAll"></th>
                    <th>Placa</th>
                    <th>Data</th>
                    <th>Patio</th>
                    <th>REL (Editável)</th>
                    <th>Valor</th>
                    <th>Serviço</th>
                    <th>Status Envio</th>
                    <th>Nº Laudo</th>
                    <th>Links</th>
                    <th>Marca</th>
                    <th>Modelo</th>
                    <th>Ano</th>
                    <th>Cor</th>
                    <th>Cidade</th>
                    <th>UF</th>
                    <th>Chassi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data)): ?>
                    <tr><td colspan="17" style="text-align: center;">Nenhum registro encontrado.</td></tr>
                <?php else: ?>
                    <?php foreach ($data as $row): ?>
                        <tr id="row-<?php echo $row['placa']; ?>">
                            <td><input type="checkbox" name="selected_rows[]" value="<?php echo $row['placa']; ?>" class="row-checkbox"></td>
                            <td><strong><?php echo htmlspecialchars($row['placa'] ?? ''); ?></strong></td>
                            <td><?php echo ($row['data'] ?? '') ? date('d/m/Y', strtotime($row['data'])) : '-'; ?></td>
                            <td><small><?php echo htmlspecialchars($row['patio'] ?? ''); ?></small></td>
                            <td><input type="text" name="rel[<?php echo $row['placa']; ?>]" value="<?php echo htmlspecialchars($row['rel'] ?? ''); ?>" class="edit-input rel-input data-input"></td>
                            <td>R$ <?php echo number_format((float)($row['valor'] ?? 0), 2, ',', '.'); ?></td>
                            <td><small><?php echo htmlspecialchars($row['servico'] ?? ''); ?></small></td>
                            <td><small><?php echo htmlspecialchars($row['status_envio'] ?? ''); ?></small></td>
                            <td><small><?php echo htmlspecialchars($row['numero_laudo'] ?? ''); ?></small></td>
                            <td>
                                    <?php 
                                        $link_ecv = $row['link_ecv'] ?? '';
                                        $url_ecv = (strpos($link_ecv, 'vistoriago.com.br') !== false) ? $link_ecv : "https://www.vistoriago.com.br/" . $link_ecv;
                                        if ($link_ecv): 
                                    ?>
                                        <a href="<?php echo htmlspecialchars($url_ecv); ?>" target="_blank" class="link-btn">ECV</a>
                                    <?php endif; ?>
                                <?php if ($row['link_avaliacao'] ?? ''): ?><a href="<?php echo htmlspecialchars($row['link_avaliacao'] ?? ''); ?>" target="_blank" class="link-btn" style="background:#e67e22">AVAL</a><?php endif; ?>
                            </td>
                            <td><small><?php echo htmlspecialchars($row['marca'] ?? ''); ?></small></td>
                            <td><small><?php echo htmlspecialchars($row['modelo'] ?? ''); ?></small></td>
                            <td><small><?php echo htmlspecialchars($row['ano'] ?? ''); ?></small></td>
                            <td><small><?php echo htmlspecialchars($row['cor'] ?? ''); ?></small></td>
                            <td><small><?php echo htmlspecialchars($row['cidade'] ?? ''); ?></small></td>
                            <td><small><?php echo htmlspecialchars($row['uf'] ?? ''); ?></small></td>
                            <td><small><?php echo htmlspecialchars($row['chassi'] ?? ''); ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="javascript:void(0)" onclick="changePage(<?php echo $page - 1; ?>)" class="btn btn-secondary">Anterior</a>
        <?php endif; ?>
        <span>Página <strong><?php echo $page; ?></strong> de <?php echo max(1, $total_pages); ?></span>
        <?php if ($page < $total_pages): ?>
            <a href="javascript:void(0)" onclick="changePage(<?php echo $page + 1; ?>)" class="btn btn-secondary">Próxima</a>
        <?php endif; ?>
    </div>
    <?php
    $html = ob_get_clean();
    echo json_encode([
        'html' => $html,
        'total_records' => $total_records,
        'page' => $page,
        'total_pages' => $total_pages
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Safra Combos - Listagem</title>
    <link rel="stylesheet" href="includes/app-shell.css">
    <style>
        :root {
            --bg-color: #f4f7f6; --text-color: #333; --card-bg: #fff; --border-color: #ddd;
            --thead-bg: #2c3e50; --thead-text: #fff; --row-even: #f9f9f9;
            --primary-color: #3498db; --accent-color: #e74c3c; --success-color: #27ae60;
            --font-size: 12px; --row-selected: #e3f2fd;
        }
        [data-theme="dark"] {
            --bg-color: #1a1a1a; --text-color: #e0e0e0; --card-bg: #2d2d2d; --border-color: #444;
            --thead-bg: #000; --thead-text: #fff; --row-even: #333; --primary-color: #2980b9;
            --row-selected: #1e3a5f;
        }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--bg-color); color: var(--text-color); font-size: var(--font-size); margin: 0; padding: 0; transition: 0.3s; }
        .filter-section, .action-bar { background: var(--card-bg); padding: 15px; border-radius: 8px; border: 1px solid var(--border-color); margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: end; }
        .filter-group { display: flex; flex-direction: column; gap: 5px; }
        label { font-weight: bold; }
        input, select { padding: 6px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--card-bg); color: var(--text-color); font-size: var(--font-size); }
        .btn-group { display: flex; gap: 10px; align-items: center; }
        button, .btn { padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; transition: 0.2s; font-size: var(--font-size); }
        .btn-primary { background: var(--primary-color); color: white; }
        .btn-success { background: var(--success-color); color: white; }
        .btn-clear { background: var(--accent-color); color: white; text-decoration: none; }
        .btn-secondary { background: #95a5a6; color: white; text-decoration: none; }
        button:hover { opacity: 0.8; }
        .table-responsive { overflow-x: auto; background: var(--card-bg); border-radius: 8px; border: 1px solid var(--border-color); box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; min-width: 1400px; }
        th { background-color: var(--thead-bg); color: var(--thead-text); text-align: left; padding: 10px; position: sticky; top: 0; z-index: 10; }
        td { padding: 8px 10px; border-bottom: 1px solid var(--border-color); }
        tr:nth-child(even) { background-color: var(--row-even); }
        tr.selected { background-color: var(--row-selected) !important; }
        .edit-input { width: 100%; box-sizing: border-box; }
        .pagination { display: flex; justify-content: center; align-items: center; gap: 15px; margin-top: 20px; }
        
        /* Tarjas Animadas */
        .tarja-animada {
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(1.05); }
            100% { opacity: 1; transform: scale(1); }
        }

        .tarja-sucesso-small { background: #27ae60; color: #fff; animation: none; }
        .tarja-erro-small { background: #e74c3c; color: #fff; animation: none; }

        .banner { padding: 12px; border-radius: 4px; margin-bottom: 15px; border: 1px solid transparent; display: flex; align-items: center; gap: 10px; }
        .error-banner { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .success-banner { background: #d4edda; color: #155724; border-color: #c3e6cb; }

        .link-btn { display: inline-block; padding: 4px 8px; background: var(--primary-color); color: white; border-radius: 4px; text-decoration: none; font-size: 10px; }
    </style>
</head>
<body>
<?php
$carvist_nav_active = 'combos';
require __DIR__ . '/includes/header.php';
?>

    <?php if (isset($error_msg)): ?>
        <div class="banner error-banner"><span class="tarja-animada tarja-erro-small">ERRO</span> <?php echo htmlspecialchars($error_msg); ?></div>
    <?php endif; ?>
    <?php if ($update_msg): ?>
        <div class="banner success-banner"><span class="tarja-animada tarja-sucesso-small">SUCESSO</span> <?php echo htmlspecialchars($update_msg); ?></div>
    <?php endif; ?>

    <section class="filter-section">
        <form id="filterForm" method="GET">
            <div class="filter-grid">
                <div class="filter-group">
                    <label for="placa">Placa</label>
                    <input type="text" name="placa" id="placa" value="<?php echo htmlspecialchars($placa_filter); ?>" placeholder="ABC1234">
                </div>
                <div class="filter-group">
                    <label for="status_envio">Status Envio</label>
                    <select name="status_envio" id="status_envio">
                        <option value="">Todos</option>
                        <?php foreach ($status_list as $s): ?>
                            <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $status_filter === $s ? 'selected' : ''; ?>><?php echo htmlspecialchars($s); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="btn-group">
                    <button type="submit" class="btn-primary">Filtrar</button>
                    <a href="combos.php" class="btn btn-clear">Limpar</a>
                    <button type="button" id="btnExport" class="btn-secondary" style="background: #27ae60;">Exportar CSV</button>
                </div>
            </div>
        </form>
    </section>

    <form id="saveForm" method="POST">
        <input type="hidden" name="action" value="save_changes">
        
        <div class="action-bar" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div class="stats">
                Encontrados: <strong id="totalRecordsDisplay"><?php echo $total_records; ?></strong> | <span id="selectedCount">0</span> selecionados
            </div>
            <div class="btn-group">
                <input type="text" id="bulkRelValue" placeholder="Valor para REL" style="width: 150px;">
                <button type="button" class="btn-primary" id="btnApplyBulk">Aplicar a Selecionados</button>
                <button type="submit" class="btn-success" id="btnSave" disabled>Salvar Alterações</button>
            </div>
        </div>

        <div id="tableContainer">
            <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll"></th>
                        <th>Placa</th>
                        <th>Data</th>
                        <th>Patio</th>
                        <th>REL (Editável)</th>
                        <th>Valor</th>
                        <th>Serviço</th>
                        <th>Status Envio</th>
                        <th>Nº Laudo</th>
                        <th>Links</th>
                        <th>Marca</th>
                        <th>Modelo</th>
                        <th>Ano</th>
                        <th>Cor</th>
                        <th>Cidade</th>
                        <th>UF</th>
                        <th>Chassi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data)): ?>
                        <tr><td colspan="17" style="text-align: center;">Nenhum registro encontrado.</td></tr>
                    <?php else: ?>
                        <?php foreach ($data as $row): ?>
                            <tr id="row-<?php echo $row['placa']; ?>">
                                <td><input type="checkbox" name="selected_rows[]" value="<?php echo $row['placa']; ?>" class="row-checkbox"></td>
                                <td><strong><?php echo htmlspecialchars($row['placa'] ?? ''); ?></strong></td>
                                <td><?php echo ($row['data'] ?? '') ? date('d/m/Y', strtotime($row['data'])) : '-'; ?></td>
                                <td><small><?php echo htmlspecialchars($row['patio'] ?? ''); ?></small></td>
                                <td><input type="text" name="rel[<?php echo $row['placa']; ?>]" value="<?php echo htmlspecialchars($row['rel'] ?? ''); ?>" class="edit-input rel-input data-input"></td>
                                <td>R$ <?php echo number_format((float)($row['valor'] ?? 0), 2, ',', '.'); ?></td>
                                <td><small><?php echo htmlspecialchars($row['servico'] ?? ''); ?></small></td>
                                <td><small><?php echo htmlspecialchars($row['status_envio'] ?? ''); ?></small></td>
                                <td><small><?php echo htmlspecialchars($row['numero_laudo'] ?? ''); ?></small></td>
                                <td>
                                    <?php 
                                        $link_ecv = $row['link_ecv'] ?? '';
                                        $url_ecv = (strpos($link_ecv, 'vistoriago.com.br') !== false) ? $link_ecv : "https://www.vistoriago.com.br/" . $link_ecv;
                                        if ($link_ecv): 
                                    ?>
                                        <a href="<?php echo htmlspecialchars($url_ecv); ?>" target="_blank" class="link-btn">ECV</a>
                                    <?php endif; ?>
                                    <?php if ($row['link_avaliacao'] ?? ''): ?><a href="<?php echo htmlspecialchars($row['link_avaliacao'] ?? ''); ?>" target="_blank" class="link-btn" style="background:#e67e22">AVAL</a><?php endif; ?>
                                </td>
                                <td><small><?php echo htmlspecialchars($row['marca'] ?? ''); ?></small></td>
                                <td><small><?php echo htmlspecialchars($row['modelo'] ?? ''); ?></small></td>
                                <td><small><?php echo htmlspecialchars($row['ano'] ?? ''); ?></small></td>
                                <td><small><?php echo htmlspecialchars($row['cor'] ?? ''); ?></small></td>
                                <td><small><?php echo htmlspecialchars($row['cidade'] ?? ''); ?></small></td>
                                <td><small><?php echo htmlspecialchars($row['uf'] ?? ''); ?></small></td>
                                <td><small><?php echo htmlspecialchars($row['chassi'] ?? ''); ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>

    <div id="paginationContainer">
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="btn btn-secondary">Anterior</a>
            <?php endif; ?>
            <span>Página <strong><?php echo $page; ?></strong> de <?php echo max(1, $total_pages); ?></span>
            <?php if ($page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="btn btn-secondary">Próxima</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Tema
    const themeToggle = document.getElementById('themeToggle');
    if (localStorage.getItem('theme') === 'dark') document.body.setAttribute('data-theme', 'dark');
    themeToggle.addEventListener('click', () => {
        let theme = document.body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        if (theme === 'dark') document.body.setAttribute('data-theme', 'dark');
        else document.body.removeAttribute('data-theme');
        localStorage.setItem('theme', theme);
    });

    // Seleção e UI
    const tableContainer = document.getElementById('tableContainer');
    const paginationContainer = document.getElementById('paginationContainer');
    const totalRecordsDisplay = document.getElementById('totalRecordsDisplay');
    const btnSave = document.getElementById('btnSave');
    const selectedCountSpan = document.getElementById('selectedCount');

    function initTableEvents() {
        const selectAll = document.getElementById('selectAll');
        const rowCheckboxes = document.querySelectorAll('.row-checkbox');
        
        if (selectAll) {
            selectAll.addEventListener('change', () => {
                rowCheckboxes.forEach(cb => cb.checked = selectAll.checked);
                updateUI();
            });
        }

        rowCheckboxes.forEach(cb => cb.addEventListener('change', updateUI));

        const dataInputs = document.querySelectorAll('.data-input');
        dataInputs.forEach(input => {
            input.addEventListener('change', (e) => {
                const row = e.target.closest('tr');
                const checkbox = row.querySelector('.row-checkbox');
                checkbox.checked = true;
                updateUI();
            });
        });
    }

    function updateUI() {
        const rowCheckboxes = document.querySelectorAll('.row-checkbox');
        const selected = document.querySelectorAll('.row-checkbox:checked');
        selectedCountSpan.textContent = selected.length;
        btnSave.disabled = selected.length === 0;
        rowCheckboxes.forEach(cb => {
            const row = cb.closest('tr');
            if (cb.checked) row.classList.add('selected');
            else row.classList.remove('selected');
        });
    }

    initTableEvents();

    // AJAX para Filtro e Paginação
    let searchTimeout;
    const filterForm = document.getElementById('filterForm');

    function loadData(page = 1) {
        const formData = new FormData(filterForm);
        const params = new URLSearchParams(formData);
        params.append('ajax', '1');
        params.append('page', page);

        showLoading('Carregando dados...');

        fetch('combos.php?' + params.toString())
            .then(response => response.json())
            .then(data => {
                tableContainer.innerHTML = data.html;
                totalRecordsDisplay.textContent = data.total_records;
                initTableEvents();
                updateUI();
                hideLoading();
            })
            .catch(error => {
                console.error('Erro:', error);
                hideLoading();
                alert('Erro ao carregar dados.');
            });
    }

    function changePage(page) {
        loadData(page);
    }

    // Debounce na busca por placa
    document.getElementById('placa').addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            loadData(1);
        }, 500);
    });

    filterForm.addEventListener('submit', (e) => {
        e.preventDefault();
        loadData(1);
    });

    // Edição em Massa
    document.getElementById('btnApplyBulk').addEventListener('click', () => {
        const val = document.getElementById('bulkRelValue').value;
        if (!val) { alert('Digite um valor para aplicar.'); return; }
        const selected = document.querySelectorAll('.row-checkbox:checked');
        if (selected.length === 0) { alert('Selecione ao menos uma linha.'); return; }
        selected.forEach(cb => {
            const row = cb.closest('tr');
            row.querySelector('.rel-input').value = val;
        });
    });

    // Loader
    const loadingOverlay = document.getElementById('loadingOverlay');
    const loadingText = document.getElementById('loadingText');

    function showLoading(text = 'Processando...') {
        if (loadingText) loadingText.textContent = text;
        loadingOverlay.style.display = 'flex';
    }

    function hideLoading() {
        loadingOverlay.style.display = 'none';
    }

    document.getElementById('saveForm').addEventListener('submit', () => showLoading('Gravando no banco...'));

    // Lógica de Exportação
    document.getElementById('btnExport').addEventListener('click', () => {
        const form = document.getElementById('filterForm');
        const formData = new FormData(form);
        const params = new URLSearchParams(formData);
        params.append('table', 'safra_combos');
        
        showLoading('Preparando exportação...');
        window.location.href = 'export.php?' + params.toString();
        setTimeout(hideLoading, 2000);
    });
</script>

</body>
</html>
