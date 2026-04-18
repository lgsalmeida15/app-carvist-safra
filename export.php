<?php
require_once 'config.php';

// 1. Validar a tabela solicitada
$allowed_tables = ['matriz_safra', 'vw_safra_combos', 'vw_safra_ecv_demais_vias'];
$table = $_GET['table'] ?? '';

if (!in_array($table, $allowed_tables)) {
    die("Tabela não permitida.");
}

// 2. Reconstruir a lógica de filtros (idêntica às páginas originais)
$where = ["1=1"];
$params = [];

if ($table === 'matriz_safra') {
    $placa = isset($_GET['placa']) ? strtoupper(trim($_GET['placa'])) : '';
    $servico = isset($_GET['servico']) ? trim($_GET['servico']) : '';
    $status_filter = isset($_GET['status']) ? (is_array($_GET['status']) ? $_GET['status'] : [trim($_GET['status'])]) : [];
    $status_filter = array_filter($status_filter, function($v) { return $v !== ''; });
    $enviada = isset($_GET['enviada']) ? trim($_GET['enviada']) : '';
    $data_inicio = isset($_GET['data_inicio']) ? trim($_GET['data_inicio']) : '';
    $data_fim = isset($_GET['data_fim']) ? trim($_GET['data_fim']) : '';

    if ($placa !== '') {
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
    if (!empty($status_filter)) {
        $status_placeholders = [];
        foreach ($status_filter as $i => $st) {
            $key = "status_" . $i;
            $status_placeholders[] = ":" . $key;
            $params[$key] = $st;
        }
        $where[] = "status IN (" . implode(',', $status_placeholders) . ")";
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
    $order_by = "id DESC";
    $columns = "id, laudo_data, patio, placa, marca, modelo, ano_modelo, cor, chassi, servico, uf, cidade, unidade_negocio, via_laudo, numero_laudo, valor, link_ecv, link_avaliacao, alterada, ultima_atualizacao, enviada_ao_banco, rel, status, uf_vistoriador";

} elseif ($table === 'vw_safra_combos') {
    $placa_filter = isset($_GET['placa']) ? strtoupper(trim($_GET['placa'])) : '';
    $status_filter = isset($_GET['status_envio']) ? trim($_GET['status_envio']) : '';
    $data_inicio = isset($_GET['data_inicio']) ? trim($_GET['data_inicio']) : '';
    $data_fim = isset($_GET['data_fim']) ? trim($_GET['data_fim']) : '';

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
    if ($data_inicio !== '') {
        $where[] = "data >= :data_inicio";
        $params['data_inicio'] = $data_inicio;
    }
    if ($data_fim !== '') {
        $where[] = "data <= :data_fim";
        $params['data_fim'] = $data_fim;
    }
    $order_by = "data DESC, placa ASC";
    $columns = "data, patio, placa, marca, modelo, ano, cor, chassi, servico, uf_vistoriador, numero_laudo, link_ecv, link_avaliacao, tipo_servico_preco, modalidade_preco, regiao_preco, status_envio, valor_preco";

} elseif ($table === 'vw_safra_ecv_demais_vias') {
    $placa_filter = isset($_GET['placa']) ? strtoupper(trim($_GET['placa'])) : '';
    $status_filter = isset($_GET['status_envio']) ? trim($_GET['status_envio']) : '';
    $data_inicio = isset($_GET['data_inicio']) ? trim($_GET['data_inicio']) : '';
    $data_fim = isset($_GET['data_fim']) ? trim($_GET['data_fim']) : '';

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
    if ($data_inicio !== '') {
        $where[] = "data >= :data_inicio";
        $params['data_inicio'] = $data_inicio;
    }
    if ($data_fim !== '') {
        $where[] = "data <= :data_fim";
        $params['data_fim'] = $data_fim;
    }
    $order_by = "id_laudo DESC";
    $columns = "id_laudo, data, patio, placa, marca, modelo, ano, cor, chassi, tipo_laudo, uf_vistoriador, numero_laudo, rel, valor, link_laudo, via_laudo, status_envio, status, regiao_preco, valor_preco";
}

$where_clause = implode(' AND ', $where);

// 3. Buscar os dados (sem limite de paginação)
try {
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
