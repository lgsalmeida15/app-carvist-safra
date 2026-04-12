<?php
require_once 'config.php';

// --- LÓGICA DE SALVAMENTO (POST) ---
$update_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_changes') {
    $selected_ids = $_POST['selected_rows'] ?? [];
    
    if (!empty($selected_ids)) {
        try {
            $pdo->beginTransaction();
            
            // Prepared statements para otimização e segurança
            $sql_update = "UPDATE matriz_safra 
                           SET via_ecv = :via_ecv, 
                               valor = :valor, 
                               servico = :servico, 
                               enviada_ao_banco = true,
                               alterada = CASE WHEN :has_changed = '1' THEN 'MANUAL' ELSE alterada END,
                               ultima_atualizacao = NOW()
                           WHERE id = :id";
            $stmt_update = $pdo->prepare($sql_update);
            
            foreach ($selected_ids as $id) {
                $id = (int)$id;
                $via_ecv = $_POST['via_ecv'][$id] ?? '';
                $valor = $_POST['valor'][$id] ?? 0;
                $servico_edit = $_POST['servico_edit'][$id] ?? '';
                $has_changed = $_POST['changed'][$id] ?? '0'; // Flag JS para detectar mudança
                
                $stmt_update->execute([
                    'via_ecv' => $via_ecv,
                    'valor' => str_replace(',', '.', $valor), // Garante formato decimal
                    'servico' => $servico_edit,
                    'has_changed' => $has_changed,
                    'id' => $id
                ]);
            }
            
            $pdo->commit();
            $update_msg = "Sucesso: " . count($selected_ids) . " registros atualizados e marcados como enviados.";
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

$placa = isset($_GET['placa']) ? strtoupper(trim($_GET['placa'])) : '';
$servico = isset($_GET['servico']) ? trim($_GET['servico']) : '';
$enviada = isset($_GET['enviada']) ? trim($_GET['enviada']) : '';
$data_inicio = isset($_GET['data_inicio']) ? trim($_GET['data_inicio']) : '';
$data_fim = isset($_GET['data_fim']) ? trim($_GET['data_fim']) : '';

$where = ["1=1"];
$params = [];

if ($placa !== '') {
    // Se a placa tiver 7 caracteres, busca exata é mais rápida. Se não, usa ILIKE.
    if (strlen($placa) === 7) {
        $where[] = "placa = :placa";
        $params['placa'] = $placa;
    } else {
        $where[] = "placa ILIKE :placa";
        $params['placa'] = "%$placa%";
    }
}

if ($servico !== '') {
    $where[] = "servico = :servico";
    $params['servico'] = $servico;
}

if ($enviada === 'sim') {
    $where[] = "enviada_ao_banco = true";
} elseif ($enviada === 'nao') {
    $where[] = "(enviada_ao_banco = false OR enviada_ao_banco IS NULL)";
}

if ($data_inicio !== '') {
    $where[] = "laudo_data >= :data_inicio";
    $params['data_inicio'] = $data_inicio;
}

if ($data_fim !== '') {
    $where[] = "laudo_data <= :data_fim";
    $params['data_fim'] = $data_fim;
}

$where_clause = implode(' AND ', $where);

try {
    $count_sql = "SELECT COUNT(*) FROM matriz_safra WHERE $where_clause";
    $stmt_count = $pdo->prepare($count_sql);
    $stmt_count->execute($params);
    $total_records = $stmt_count->fetchColumn();
    $total_pages = ceil($total_records / $limit);
} catch (Exception $e) {
    $total_records = 0;
    $total_pages = 0;
    $error_msg = "Erro na query: " . $e->getMessage();
}

$data = [];
if (!isset($error_msg)) {
    try {
        $sql = "SELECT * FROM matriz_safra WHERE $where_clause ORDER BY id DESC LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();
    } catch (Exception $e) {
        $error_msg = "Erro ao buscar dados: " . $e->getMessage();
    }
}

$servicos_list = [];
try {
    $servicos_list = $pdo->query("SELECT DISTINCT servico FROM matriz_safra WHERE servico IS NOT NULL ORDER BY servico")->fetchAll(PDO::FETCH_COLUMN);
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
                    <th>
                        <div style="display: flex; flex-direction: column; align-items: center; gap: 2px;">
                            <span style="font-size: 9px; color: #aaa; text-transform: uppercase; white-space: nowrap;">Enviar</span>
                            <input type="checkbox" id="selectAll" title="Selecionar todos para envio">
                        </div>
                    </th>
                    <th>ID</th>
                    <th>Data Laudo</th>
                    <th>Placa</th>
                    <th>Valor (Editável)</th>
                    <th>Serviço (Editável)</th>
                    <th>VIA ECV (Editável)</th>
                    <th>Enviada?</th>
                    <th>Patio</th>
                    <th>Marca</th>
                    <th>Modelo</th>
                    <th>Ano Modelo</th>
                    <th>Cor</th>
                    <th>Chassi</th>
                    <th>Cidade</th>
                    <th>UF</th>
                    <th>Unidade Negócio</th>
                    <th>Nº Laudo</th>
                    <th>Links</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data)): ?>
                    <tr><td colspan="21" style="text-align: center;">Nenhum registro encontrado.</td></tr>
                <?php else: ?>
                    <?php foreach ($data as $row): ?>
                        <tr id="row-<?php echo $row['id']; ?>">
                            <td>
                                <input type="checkbox" name="selected_rows[]" value="<?php echo $row['id']; ?>" class="row-checkbox">
                                <input type="hidden" name="changed[<?php echo $row['id']; ?>]" value="0" class="changed-flag">
                            </td>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo $row['laudo_data'] ? date('d/m/Y', strtotime($row['laudo_data'])) : '-'; ?></td>
                            <td><strong><?php echo htmlspecialchars($row['placa'] ?? ''); ?></strong></td>
                            <td>
                                <input type="text" name="valor[<?php echo $row['id']; ?>]" 
                                       value="<?php echo number_format((float)($row['valor'] ?? 0), 2, '.', ''); ?>" 
                                       class="edit-input edit-input-valor data-input">
                            </td>
                            <td>
                                <select name="servico_edit[<?php echo $row['id']; ?>]" class="edit-input data-input">
                                    <?php foreach ($servicos_list as $s): ?>
                                        <option value="<?php echo htmlspecialchars($s ?? ''); ?>" <?php echo ($row['servico'] ?? '') === $s ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($s ?? ''); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="text" name="via_ecv[<?php echo $row['id']; ?>]" 
                                       value="<?php echo htmlspecialchars($row['via_ecv'] ?? ''); ?>" 
                                       class="edit-input data-input">
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo ($row['enviada_ao_banco'] ?? false) ? 'true' : 'false'; ?>">
                                    <?php echo ($row['enviada_ao_banco'] ?? false) ? 'SIM' : 'NÃO'; ?>
                                </span>
                            </td>
                            <td><small><?php echo htmlspecialchars($row['patio'] ?? ''); ?></small></td>
                            <td><small><?php echo htmlspecialchars($row['marca'] ?? ''); ?></small></td>
                            <td><small><?php echo htmlspecialchars($row['modelo'] ?? ''); ?></small></td>
                            <td><small><?php echo htmlspecialchars($row['ano_modelo'] ?? ''); ?></small></td>
                            <td><small><?php echo htmlspecialchars($row['cor'] ?? ''); ?></small></td>
                            <td><small><?php echo htmlspecialchars($row['chassi'] ?? ''); ?></small></td>
                            <td><small><?php echo htmlspecialchars($row['cidade'] ?? ''); ?></small></td>
                            <td><small><?php echo htmlspecialchars($row['uf'] ?? ''); ?></small></td>
                            <td><small><?php echo htmlspecialchars($row['unidade_negocio'] ?? ''); ?></small></td>
                            <td><small><?php echo htmlspecialchars($row['numero_laudo'] ?? ''); ?></small></td>
                            <td>
                                <?php if ($row['link_ecv'] ?? ''): ?><a href="https://www.vistoriago.com.br/<?php echo htmlspecialchars($row['link_ecv'] ?? ''); ?>" target="_blank" class="link-btn">ECV</a><?php endif; ?>
                                <?php if ($row['link_avaliacao'] ?? ''): ?><a href="<?php echo htmlspecialchars($row['link_avaliacao'] ?? ''); ?>" target="_blank" class="link-btn" style="background:#e67e22">AVAL</a><?php endif; ?>
                            </td>
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
    <title>Matriz Safra - Listagem</title>
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

        .filter-section, .action-bar {
            background: var(--card-bg);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .filter-group { display: flex; flex-direction: column; gap: 5px; }

        label { font-weight: bold; }

        input, select, textarea {
            padding: 6px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background: var(--card-bg);
            color: var(--text-color);
            font-size: var(--font-size);
        }

        textarea { height: 50px; resize: vertical; }

        .btn-group { display: flex; gap: 10px; align-items: center; }

        button, .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: opacity 0.2s;
            font-size: var(--font-size);
        }

        .btn-primary { background: var(--primary-color); color: white; }
        .btn-success { background: var(--success-color); color: white; }
        .btn-clear { background: var(--accent-color); color: white; text-decoration: none; }
        .btn-secondary { background: #95a5a6; color: white; text-decoration: none; }
        
        button:hover { opacity: 0.8; }

        .table-responsive {
            overflow-x: auto;
            background: var(--card-bg);
            border-radius: 8px;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        table { width: 100%; border-collapse: collapse; min-width: 2000px; }

        th {
            background-color: var(--thead-bg);
            color: var(--thead-text);
            text-align: left;
            padding: 10px;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        td { padding: 8px 10px; border-bottom: 1px solid var(--border-color); }

        tr:nth-child(even) { background-color: var(--row-even); }
        tr.selected { background-color: var(--row-selected) !important; }

        .edit-input { width: 100%; box-sizing: border-box; }
        .edit-input-valor { width: 80px; }

        .pagination { display: flex; justify-content: center; align-items: center; gap: 15px; margin-top: 20px; }

        .loader {
            display: none; border: 3px solid #f3f3f3; border-top: 3px solid var(--primary-color);
            border-radius: 50%; width: 18px; height: 18px; animation: spin 1s linear infinite; margin-left: 10px;
        }

        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

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

        .tarja-processando { background: #f1c40f; color: #000; }
        .tarja-sucesso { background: #27ae60; color: #fff; animation: none; }
        .tarja-erro { background: #e74c3c; color: #fff; animation: none; }

        .banner { padding: 12px; border-radius: 4px; margin-bottom: 15px; border: 1px solid transparent; display: flex; align-items: center; gap: 10px; }
        .tarja-animada {
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .tarja-sucesso-small { background: #27ae60; color: #fff; }
        .tarja-erro-small { background: #e74c3c; color: #fff; }

        .error-banner { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .success-banner { background: #d4edda; color: #155724; border-color: #c3e6cb; }

        .status-badge {
            padding: 2px 6px; border-radius: 10px; font-size: 10px; font-weight: bold;
        }
        .status-true { background: #d4edda; color: #155724; }
        .status-false { background: #f8d7da; color: #721c24; }
        .link-btn { display: inline-block; padding: 4px 8px; background: var(--primary-color); color: white; border-radius: 4px; text-decoration: none; font-size: 10px; }
    </style>
</head>
<body>
<?php
$carvist_nav_active = 'matriz';
$carvist_container_wide = true;
require __DIR__ . '/includes/header.php';
?>

    <?php if (isset($error_msg)): ?>
        <div class="banner error-banner">
            <span class="tarja-animada tarja-erro-small">ERRO</span>
            <?php echo htmlspecialchars($error_msg); ?>
        </div>
    <?php endif; ?>
    <?php if ($update_msg): ?>
        <div class="banner success-banner">
            <span class="tarja-animada tarja-sucesso-small">SUCESSO</span>
            <?php echo htmlspecialchars($update_msg); ?>
        </div>
    <?php endif; ?>

    <section class="filter-section">
        <form id="filterForm" method="GET" action="index.php">
            <div class="filter-grid">
                <div class="filter-group">
                    <label for="placa">Placa</label>
                    <input type="text" name="placa" id="placa" value="<?php echo htmlspecialchars($placa); ?>" placeholder="ABC1234">
                </div>
                <div class="filter-group">
                    <label for="servico">Serviço</label>
                    <select name="servico" id="servico">
                        <option value="">Todos</option>
                        <?php foreach ($servicos_list as $s): ?>
                            <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $servico === $s ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="enviada">Enviada?</label>
                    <select name="enviada" id="enviada">
                        <option value="">Todos</option>
                        <option value="sim" <?php echo $enviada === 'sim' ? 'selected' : ''; ?>>Sim</option>
                        <option value="nao" <?php echo $enviada === 'nao' ? 'selected' : ''; ?>>Não</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="data_inicio">Data Início</label>
                    <input type="date" name="data_inicio" id="data_inicio" value="<?php echo htmlspecialchars($data_inicio); ?>">
                </div>
                <div class="filter-group">
                    <label for="data_fim">Data Fim</label>
                    <input type="date" name="data_fim" id="data_fim" value="<?php echo htmlspecialchars($data_fim); ?>">
                </div>
                <div class="btn-group">
                    <button type="submit" class="btn-primary">Filtrar</button>
                    <a href="index.php" class="btn btn-clear">Limpar</a>
                    <button type="button" id="btnExport" class="btn-secondary" style="background: #27ae60;">Exportar CSV</button>
                    <div id="loader" class="loader"></div>
                </div>
            </div>
        </form>
    </section>

    <form id="saveForm" method="POST" action="index.php">
        <input type="hidden" name="action" value="save_changes">
        
        <div class="action-bar" style="display: flex; justify-content: space-between; align-items: center;">
            <div class="stats">
                Encontrados: <strong id="totalRecordsDisplay"><?php echo $total_records; ?></strong> | 
                <span id="selectedCount">0</span> selecionados
            </div>
            <button type="submit" class="btn-success" id="btnSave" disabled>Salvar Alterações Selecionadas</button>
        </div>

        <div id="tableContainer">
            <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>
                            <div style="display: flex; flex-direction: column; align-items: center; gap: 2px;">
                                <span style="font-size: 9px; color: #aaa; text-transform: uppercase; white-space: nowrap;">Enviar</span>
                                <input type="checkbox" id="selectAll" title="Selecionar todos para envio">
                            </div>
                        </th>
                        <th>ID</th>
                        <th>Data Laudo</th>
                        <th>Placa</th>
                        <th>Valor (Editável)</th>
                        <th>Serviço (Editável)</th>
                        <th>VIA ECV (Editável)</th>
                        <th>Enviada?</th>
                        <th>Patio</th>
                        <th>Marca</th>
                        <th>Modelo</th>
                        <th>Ano Modelo</th>
                        <th>Cor</th>
                        <th>Chassi</th>
                        <th>Cidade</th>
                        <th>UF</th>
                        <th>Unidade Negócio</th>
                        <th>Nº Laudo</th>
                        <th>Links</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data)): ?>
                        <tr><td colspan="21" style="text-align: center;">Nenhum registro encontrado.</td></tr>
                    <?php else: ?>
                        <?php foreach ($data as $row): ?>
                            <tr id="row-<?php echo $row['id']; ?>">
                                <td>
                                    <input type="checkbox" name="selected_rows[]" value="<?php echo $row['id']; ?>" class="row-checkbox">
                                    <input type="hidden" name="changed[<?php echo $row['id']; ?>]" value="0" class="changed-flag">
                                </td>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo $row['laudo_data'] ? date('d/m/Y', strtotime($row['laudo_data'])) : '-'; ?></td>
                                <td><strong><?php echo htmlspecialchars($row['placa']); ?></strong></td>
                                <td>
                                    <input type="text" name="valor[<?php echo $row['id']; ?>]" 
                                           value="<?php echo number_format($row['valor'], 2, '.', ''); ?>" 
                                           class="edit-input edit-input-valor data-input">
                                </td>
                                <td>
                                    <select name="servico_edit[<?php echo $row['id']; ?>]" class="edit-input data-input">
                                        <?php foreach ($servicos_list as $s): ?>
                                            <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $row['servico'] === $s ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($s); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="via_ecv[<?php echo $row['id']; ?>]" 
                                           value="<?php echo htmlspecialchars($row['via_ecv']); ?>" 
                                           class="edit-input data-input">
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $row['enviada_ao_banco'] ? 'true' : 'false'; ?>">
                                        <?php echo $row['enviada_ao_banco'] ? 'SIM' : 'NÃO'; ?>
                                    </span>
                                </td>
                                <td><small><?php echo htmlspecialchars($row['patio']); ?></small></td>
                                <td><small><?php echo htmlspecialchars($row['marca']); ?></small></td>
                                <td><small><?php echo htmlspecialchars($row['modelo']); ?></small></td>
                                <td><small><?php echo htmlspecialchars($row['ano_modelo']); ?></small></td>
                                <td><small><?php echo htmlspecialchars($row['cor']); ?></small></td>
                                <td><small><?php echo htmlspecialchars($row['chassi']); ?></small></td>
                                <td><small><?php echo htmlspecialchars($row['cidade']); ?></small></td>
                                <td><small><?php echo htmlspecialchars($row['uf']); ?></small></td>
                                <td><small><?php echo htmlspecialchars($row['unidade_negocio']); ?></small></td>
                                <td><small><?php echo htmlspecialchars($row['numero_laudo']); ?></small></td>
                                <td>
                                    <?php if ($row['link_ecv']): ?><a href="https://www.vistoriago.com.br/<?php echo htmlspecialchars($row['link_ecv']); ?>" target="_blank" class="link-btn">ECV</a><?php endif; ?>
                                    <?php if ($row['link_avaliacao']): ?><a href="<?php echo htmlspecialchars($row['link_avaliacao']); ?>" target="_blank" class="link-btn" style="background:#e67e22">AVAL</a><?php endif; ?>
                                </td>
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

    // Lógica de Seleção e Edição
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

        rowCheckboxes.forEach(cb => {
            cb.addEventListener('change', updateUI);
        });

        const dataInputs = document.querySelectorAll('.data-input');
        dataInputs.forEach(input => {
            input.addEventListener('change', (e) => {
                const row = e.target.closest('tr');
                const checkbox = row.querySelector('.row-checkbox');
                const changedFlag = row.querySelector('.changed-flag');
                checkbox.checked = true;
                changedFlag.value = "1";
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

    // Inicializa eventos na primeira carga
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

        fetch('index.php?' + params.toString())
            .then(response => response.json())
            .then(data => {
                tableContainer.innerHTML = data.html;
                paginationContainer.innerHTML = ''; // O HTML já vem com a paginação se quisermos, mas aqui o PHP do AJAX já mandou.
                // Na verdade, no meu AJAX eu mandei a tabela e a paginação separadas ou juntas? 
                // Mandei juntas dentro de data.html (tabela + paginação).
                
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

    // Loader e Overlay
    const loadingOverlay = document.getElementById('loadingOverlay');
    const loadingText = document.getElementById('loadingText');

    function showLoading(text = 'Processando...') {
        loadingText.textContent = text;
        loadingOverlay.style.display = 'flex';
    }

    function hideLoading() {
        loadingOverlay.style.display = 'none';
    }

    // Loader no formulário de salvamento
    document.getElementById('saveForm').addEventListener('submit', () => {
        showLoading('Gravando no banco...');
    });

    // Lógica de Exportação
    document.getElementById('btnExport').addEventListener('click', () => {
        const form = document.getElementById('filterForm');
        const formData = new FormData(form);
        const params = new URLSearchParams(formData);
        params.append('table', 'matriz_safra');
        
        window.location.href = 'export.php?' + params.toString();
    });
</script>

</body>
</html>
