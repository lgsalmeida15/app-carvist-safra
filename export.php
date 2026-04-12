<?php
require_once 'config.php';

// 1. Validar a tabela solicitada
$allowed_tables = ['matriz_safra', 'safra_combos', 'safra_segunda_via'];
$table = $_GET['table'] ?? '';

if (!in_array($table, $allowed_tables)) {
    die("Tabela não permitida.");
}

// 2. Reconstruir a lógica de filtros (idêntica às páginas originais)
$where = ["1=1"];
$params = [];

if ($table === 'matriz_safra') {
    // ... código existente ...
    $placa = isset($_GET['placa']) ? trim($_GET['placa']) : '';
    $servico = isset($_GET['servico']) ? trim($_GET['servico']) : '';
    $enviada = isset($_GET['enviada']) ? trim($_GET['enviada']) : '';
    $data_inicio = isset($_GET['data_inicio']) ? trim($_GET['data_inicio']) : '';
    $data_fim = isset($_GET['data_fim']) ? trim($_GET['data_fim']) : '';
    $sql_extra = isset($_GET['sql_extra']) ? trim($_GET['sql_extra']) : '';

    if ($placa !== '') {
        $where[] = "placa ILIKE :placa";
        $params['placa'] = "%$placa%";
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

    // SQL Extra Safe Check
    if ($sql_extra !== '') {
        $forbidden_words = ['DROP', 'DELETE', 'UPDATE', 'INSERT', 'TRUNCATE', 'ALTER', 'GRANT', 'REVOKE'];
        $sql_extra_safe = true;
        foreach ($forbidden_words as $word) {
            if (stripos($sql_extra, $word) !== false) {
                $sql_extra_safe = false;
                break;
            }
        }
        if ($sql_extra_safe) {
            $where[] = "($sql_extra)";
        }
    }
    $order_by = "id DESC";
} elseif ($table === 'safra_combos') {
    $placa_filter = isset($_GET['placa']) ? trim($_GET['placa']) : '';
    $status_filter = isset($_GET['status_envio']) ? trim($_GET['status_envio']) : '';

    if ($placa_filter !== '') {
        $where[] = "placa ILIKE :placa";
        $params['placa'] = "%$placa_filter%";
    }
    if ($status_filter !== '') {
        $where[] = "status_envio = :status_envio";
        $params['status_envio'] = $status_filter;
    }
    $order_by = "data DESC, placa ASC";
} elseif ($table === 'safra_segunda_via') {
    $placa_filter = isset($_GET['placa']) ? trim($_GET['placa']) : '';
    $status_filter = isset($_GET['status_envio']) ? trim($_GET['status_envio']) : '';

    if ($placa_filter !== '') {
        $where[] = "placa ILIKE :placa";
        $params['placa'] = "%$placa_filter%";
    }
    if ($status_filter !== '') {
        $where[] = "status_envio = :status_envio";
        $params['status_envio'] = $status_filter;
    }
    $order_by = "id_laudo DESC";
}

$where_clause = implode(' AND ', $where);

// 3. Buscar os dados (sem limite de paginação)
try {
    $columns = "*";
    if ($table === 'matriz_safra') {
        // Seleciona todas as colunas exceto alterada e ultima_atualizacao
        $columns = "id, laudo_data, placa, valor, servico, via_ecv, enviada_ao_banco, patio, marca, modelo, ano_modelo, cor, chassi, cidade, uf, unidade_negocio, numero_laudo, link_ecv, link_avaliacao";
    }
    $sql = "SELECT $columns FROM $table WHERE $where_clause ORDER BY $order_by";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Erro ao buscar dados para exportação: " . $e->getMessage());
}

// 4. Configurar headers para download de CSV
$filename = "exportacao_" . $table . "_" . date('Y-m-d_H-i') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// 5. Gerar o CSV
$output = fopen('php://output', 'w');

// Adicionar BOM para compatibilidade com Excel (acentos)
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

if (!empty($data)) {
    // Cabeçalhos (chaves do primeiro array)
    fputcsv($output, array_keys($data[0]), ';');

    // Dados
    foreach ($data as $row) {
        fputcsv($output, $row, ';');
    }
} else {
    fputcsv($output, ["Nenhum dado encontrado"], ';');
}

fclose($output);
exit;
