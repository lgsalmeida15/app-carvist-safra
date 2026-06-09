<?php
require_once 'config.php';

// Filtros
$cliente_id = $_GET['cliente_id'] ?? '';
$uf = $_GET['uf'] ?? '';
$status = $_GET['status'] ?? '';
$data_ini = $_GET['data_ini'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$limit = 50;
$offset = ($pagina - 1) * $limit;

// Construção da Query
$where = ["1=1"];
$params = [];

if ($cliente_id) {
    $where[] = "v.cliente_id = :cliente_id";
    $params[':cliente_id'] = $cliente_id;
}
if ($uf) {
    $where[] = "v.uf = :uf";
    $params[':uf'] = $uf;
}
if ($status) {
    $where[] = "v.status_laudo = :status";
    $params[':status'] = $status;
}
if ($data_ini) {
    $where[] = "v.data_vistoria >= :data_ini";
    $params[':data_ini'] = $data_ini;
}
if ($data_fim) {
    $where[] = "v.data_vistoria <= :data_fim";
    $params[':data_fim'] = $data_fim . ' 23:59:59';
}

$whereSql = implode(" AND ", $where);

// Verifica se a tabela vistorias existe, se não, cria (fallback)
$pdo->exec("CREATE TABLE IF NOT EXISTS vistorias (
    id SERIAL PRIMARY KEY,
    nr_vistoria BIGINT UNIQUE NOT NULL,
    sistema_origem VARCHAR(20),
    cliente_id INT,
    patio VARCHAR(150),
    proponente VARCHAR(150),
    data_agendamento DATE,
    data_vistoria TIMESTAMP,
    data_finalizado TIMESTAMP,
    sla_vistoriador INT,
    sla_mesa INT,
    tipo_servico VARCHAR(50),
    tipo_servico_padronizado VARCHAR(50),
    status_laudo VARCHAR(50),
    obs TEXT,
    placa VARCHAR(10),
    chassi VARCHAR(25),
    marca VARCHAR(60),
    modelo VARCHAR(100),
    ano_fab INT,
    ano_modelo INT,
    cor VARCHAR(30),
    nr_laudo_ecv VARCHAR(50),
    valor_faturamento NUMERIC(10,2),
    cidade VARCHAR(80),
    uf CHAR(2),
    vistoriador_id INT,
    vistoriador_texto VARCHAR(150),
    analista VARCHAR(100),
    valor_vistoria NUMERIC(10,2),
    valor_vistoriador NUMERIC(10,2),
    comissao NUMERIC(10,2),
    valor_final_vistoriador NUMERIC(10,2),
    tipo_vistoriador VARCHAR(20),
    id_cliente_externo VARCHAR(50),
    link_laudo TEXT,
    cpf_cnpj VARCHAR(20),
    solicitante VARCHAR(100),
    nr_contrato VARCHAR(30),
    nr_requisicao VARCHAR(30),
    mes_ref VARCHAR(20),
    semana_ref VARCHAR(20),
    nr_relatorio VARCHAR(30),
    pagamento_vistoriador VARCHAR(30),
    data_pagamento_vistoriador DATE,
    flag_otimiza BOOLEAN,
    flag_rsci BOOLEAN,
    flag_pesquisa BOOLEAN,
    flag_molicar BOOLEAN,
    flag_molicar_safra BOOLEAN,
    flag_employer BOOLEAN,
    criado_em TIMESTAMP DEFAULT NOW(),
    atualizado_em TIMESTAMP DEFAULT NOW()
)");

// Verifica se tabelas de cadastro existem (necessárias para filtros)
$pdo->exec("CREATE TABLE IF NOT EXISTS clientes (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE,
    nome_normalizado VARCHAR(100),
    tipo VARCHAR(30),
    cod_back VARCHAR(10),
    prefixo_txt VARCHAR(80),
    hist_lanc_ecv VARCHAR(10),
    hist_lanc_avaliacao VARCHAR(10),
    ativo BOOLEAN DEFAULT true,
    criado_em TIMESTAMP DEFAULT NOW()
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS vistoriadores (
    id SERIAL PRIMARY KEY,
    nome_sistema VARCHAR(150) NOT NULL,
    nome_padrao VARCHAR(100),
    uf CHAR(2),
    cidade VARCHAR(80),
    tipo VARCHAR(20),
    cpf VARCHAR(14),
    email VARCHAR(100),
    whatsapp VARCHAR(20),
    emite_nf BOOLEAN DEFAULT false,
    preco_avaliacao NUMERIC(10,2),
    preco_avaliacao_avulsa NUMERIC(10,2),
    preco_desmobilizacao NUMERIC(10,2),
    preco_ecv NUMERIC(10,2),
    preco_ecv_avulsa NUMERIC(10,2),
    preco_segunda_via_ecv NUMERIC(10,2),
    preco_terceira_via_ecv NUMERIC(10,2),
    ativo BOOLEAN DEFAULT true,
    criado_em TIMESTAMP DEFAULT NOW()
)");

// Busca total para paginação
$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM vistorias v WHERE $whereSql");
$stmtTotal->execute($params);
$totalRegistros = $stmtTotal->fetchColumn();
$totalPaginas = ceil($totalRegistros / $limit);

// Busca registros
$sql = "SELECT v.*, c.nome as cliente_nome, vs.nome_padrao as vistoriador_nome
        FROM vistorias v
        LEFT JOIN clientes c ON c.id = v.cliente_id
        LEFT JOIN vistoriadores vs ON vs.id = v.vistoriador_id
        WHERE $whereSql
        ORDER BY v.data_vistoria DESC
        LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vistorias = $stmt->fetchAll();

// Busca dados para os filtros
$clientes = $pdo->query("SELECT id, nome FROM clientes ORDER BY nome")->fetchAll();
$ufs = $pdo->query("SELECT DISTINCT uf FROM vistorias WHERE uf IS NOT NULL ORDER BY uf")->fetchAll();
$statuses = $pdo->query("SELECT DISTINCT status_laudo FROM vistorias WHERE status_laudo IS NOT NULL ORDER BY status_laudo")->fetchAll();

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vistorias - Carvist</title>
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

        .filters-section {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
        }
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            align-items: end;
        }
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            font-size: 11px;
        }
        .filter-group select, .filter-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background: var(--bg-color);
            color: var(--text-color);
        }
        .btn-filter {
            background: var(--primary-color);
            color: white;
            padding: 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        .vistorias-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            background: var(--card-bg);
        }
        .vistorias-table th {
            background: var(--thead-bg);
            color: var(--thead-text);
            padding: 10px;
            text-align: left;
            position: sticky;
            top: 0;
        }
        .vistorias-table td {
            padding: 8px;
            border-bottom: 1px solid var(--border-color);
        }
        .vistorias-table tr:nth-child(even) {
            background: var(--row-even);
        }
        .vistorias-table tr:hover {
            background: var(--row-selected);
        }
        .status-badge {
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: bold;
        }
        .status-finalizado { background: #d4edda; color: #155724; }
        .status-pendente { background: #fff3cd; color: #856404; }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
        }
        .pagination a {
            padding: 5px 10px;
            border: 1px solid var(--border-color);
            text-decoration: none;
            color: var(--text-color);
            border-radius: 4px;
        }
        .pagination a.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
    </style>
</head>
<body>
<?php
$carvist_nav_active = 'vistorias';
$carvist_container_wide = true;
require __DIR__ . '/includes/header.php';
?>

<div class="filters-section">
    <form action="vistorias.php" method="GET" class="filters-grid">
        <div class="filter-group">
            <label>Cliente</label>
            <select name="cliente_id">
                <option value="">Todos</option>
                <?php foreach ($clientes as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo $cliente_id == $c['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['nome']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>UF</label>
            <select name="uf">
                <option value="">Todas</option>
                <?php foreach ($ufs as $u): ?>
                    <option value="<?php echo $u['uf']; ?>" <?php echo $uf == $u['uf'] ? 'selected' : ''; ?>>
                        <?php echo $u['uf']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>Status</label>
            <select name="status">
                <option value="">Todos</option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?php echo $s['status_laudo']; ?>" <?php echo $status == $s['status_laudo'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($s['status_laudo']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>Data Início</label>
            <input type="date" name="data_ini" value="<?php echo $data_ini; ?>">
        </div>
        <div class="filter-group">
            <label>Data Fim</label>
            <input type="date" name="data_fim" value="<?php echo $data_fim; ?>">
        </div>
        <button type="submit" class="btn-filter">Filtrar</button>
        <a href="vistorias.php" class="btn-filter" style="background: var(--secondary-color); text-decoration: none; text-align: center;">Limpar</a>
    </form>
</div>

<div style="margin-bottom: 10px; font-size: 12px;">
    Mostrando <strong><?php echo count($vistorias); ?></strong> de <strong><?php echo number_format($totalRegistros, 0, ',', '.'); ?></strong> registros.
</div>

<div style="overflow-x: auto;">
    <table class="vistorias-table">
        <thead>
            <tr>
                <th>ID Vistoria</th>
                <th>Data</th>
                <th>Cliente</th>
                <th>Placa</th>
                <th>Modelo</th>
                <th>UF</th>
                <th>Cidade</th>
                <th>Serviço</th>
                <th>Status</th>
                <th>Relatório</th>
                <th>Vistoriador</th>
                <th>Valor</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($vistorias as $v): ?>
                <tr>
                    <td><strong><?php echo $v['nr_vistoria']; ?></strong></td>
                    <td><?php echo date('d/m/Y', strtotime($v['data_vistoria'])); ?></td>
                    <td><?php echo htmlspecialchars($v['cliente_nome'] ?? '-'); ?></td>
                    <td><?php echo $v['placa']; ?></td>
                    <td><?php echo htmlspecialchars($v['modelo']); ?></td>
                    <td><?php echo $v['uf']; ?></td>
                    <td><?php echo htmlspecialchars($v['cidade']); ?></td>
                    <td><?php echo htmlspecialchars($v['tipo_servico_padronizado']); ?></td>
                    <td>
                        <span class="status-badge <?php echo strtolower($v['status_laudo']) === 'finalizado' ? 'status-finalizado' : 'status-pendente'; ?>">
                            <?php echo htmlspecialchars($v['status_laudo']); ?>
                        </span>
                    </td>
                    <td><?php echo $v['nr_relatorio'] ?: '-'; ?></td>
                    <td><?php echo htmlspecialchars($v['vistoriador_nome'] ?: $v['vistoriador_texto'] ?: '-'); ?></td>
                    <td>R$ <?php echo number_format($v['valor_faturamento'], 2, ',', '.'); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($vistorias)): ?>
                <tr>
                    <td colspan="12" style="text-align: center; padding: 20px;">Nenhuma vistoria encontrada.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPaginas > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
            <?php if ($i == 1 || $i == $totalPaginas || ($i >= $pagina - 2 && $i <= $pagina + 2)): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>" class="<?php echo $i == $pagina ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php elseif ($i == $pagina - 3 || $i == $pagina + 3): ?>
                <span>...</span>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
<?php endif; ?>

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
</script>

</div> <!-- Fecha o container aberto no header.php -->
</body>
</html>
