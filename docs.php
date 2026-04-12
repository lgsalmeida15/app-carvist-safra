<?php
require_once 'config.php';

$carvist_nav_active = 'docs';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentação - Sistema Carvist</title>
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

        .docs-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .docs-section {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            margin-bottom: 25px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            line-height: 1.6;
        }

        .docs-section h2 {
            margin-top: 0;
            color: var(--primary-color);
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-size: 18px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .docs-section h3 {
            color: var(--text-color);
            font-size: 14px;
            margin-top: 20px;
            margin-bottom: 10px;
            border-left: 4px solid var(--primary-color);
            padding-left: 10px;
        }

        .docs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .docs-card {
            background: var(--row-even);
            padding: 15px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
        }

        .docs-card strong {
            display: block;
            margin-bottom: 5px;
            color: var(--primary-color);
        }

        .table-responsive {
            overflow-x: auto;
            margin: 20px 0;
            border-radius: 6px;
            border: 1px solid var(--border-color);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: var(--font-size);
        }

        th {
            background-color: var(--thead-bg);
            color: var(--thead-text);
            text-align: left;
            padding: 10px;
            text-transform: uppercase;
        }

        td {
            padding: 10px;
            border-bottom: 1px solid var(--border-color);
        }

        tr:nth-child(even) {
            background-color: var(--row-even);
        }

        .badge {
            background: var(--success-color);
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
        }

        .n8n-map-container {
            margin: 20px 0;
            text-align: center;
        }

        .n8n-map {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: transform 0.2s;
        }

        .n8n-map:hover {
            transform: scale(1.005);
        }

        code {
            background: var(--row-even);
            padding: 2px 4px;
            border-radius: 3px;
            font-family: Consolas, monospace;
            color: var(--accent-color);
        }

        ul { padding-left: 20px; }
        li { margin-bottom: 5px; }

        .docs-footer {
            text-align: center;
            padding: 20px;
            color: #888;
            font-size: 11px;
        }
    </style>
</head>
<body>

<?php include 'includes/header.php'; ?>

<div class="docs-wrapper">
    
    <!-- Seção 1: Visão Geral (DOCUMENTATION.md) -->
    <section class="docs-section">
        <h2>1. Visão Geral do Sistema</h2>
        <p>O <strong>Sistema Carvist</strong> é uma plataforma interna para gestão e auditoria de laudos de vistoria (Matriz Safra, Combos e 2ª Via). Ele permite a visualização, edição e exportação de dados integrados com um banco de dados PostgreSQL.</p>
        
        <div class="docs-grid">
            <div class="docs-card">
                <strong>Arquitetura</strong>
                PHP 8.1+ / PostgreSQL 15+ / Apache
            </div>
            <div class="docs-card">
                <strong>Interface</strong>
                HTML5 / CSS3 (App Shell) / JS Vanilla
            </div>
            <div class="docs-card">
                <strong>Segurança</strong>
                Basic Auth / PDO Prepared / XSS Sanitization
            </div>
        </div>

        <h3>Segurança e Integridade</h3>
        <ul>
            <li><strong>Autenticação:</strong> Acesso protegido por <code>.htaccess</code> e <code>.htpasswd</code>.</li>
            <li><strong>SQL Injection:</strong> Uso obrigatório de <code>PDO::prepare()</code> em todas as queries.</li>
            <li><strong>XSS:</strong> Sanitização de saída com <code>htmlspecialchars()</code> em todas as tabelas.</li>
        </ul>
    </section>

    <!-- Seção 2: Automações n8n -->
    <section class="docs-section">
        <h2>2. Automação de Relatórios Safra (n8n)</h2>
        <p>Solução para automatizar a coleta, tratamento e classificação de dados de vistorias para o Banco Safra, consolidando informações de sistemas legados e governamentais.</p>

        <h3>Ecossistema de Sistemas</h3>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Sistema</th>
                        <th>Papel na Operação</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>VISTORIAGO</strong></td>
                        <td>Gestão de vistorias e centralização de dados (Legado).</td>
                        <td><span class="badge">Ativo (Fonte Principal)</span></td>
                    </tr>
                    <tr>
                        <td><strong>VISTOONLINE</strong></td>
                        <td>Novo sistema de gestão de vistorias.</td>
                        <td>Em transição</td>
                    </tr>
                    <tr>
                        <td><strong>OTMIZA</strong></td>
                        <td>Sistema do Detran para vistorias de transferência ECV.</td>
                        <td>Consulta de dados oficiais</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <h3>Fluxo de Dados (Workflow)</h3>
        <ul>
            <li><strong>Etapa 1 (Ingestão):</strong> Daily Job às 07:00 AM. Coleta dados dos últimos 90 dias do VISTORIAGO para a tabela <code>APIGO</code>.</li>
            <li><strong>Etapa 2 (Matriz):</strong> Filtra laudos pós 01/01/2026 e status "Finalizado". Extrai número do laudo ECV via link.</li>
            <li><strong>Etapa 3 (Higienização):</strong> Loop por placa, ordenação cronológica e remoção de duplicidades (mantém o registro mais recente).</li>
        </ul>

        <h3>Mapa da Automação</h3>
        <div class="n8n-map-container">
            <a href="includes/mapa_n8n.png" target="_blank">
                <img src="includes/mapa_n8n.png" alt="Fluxo n8n" class="n8n-map">
            </a>
            <p style="font-size: 10px; color: #888; margin-top: 10px;">Clique no mapa para visualizar em tamanho real</p>
        </div>

        <h3>Enriquecimento (Otmiza)</h3>
        <p>Subworkflow que utiliza a API da Otmiza para identificar o pátio correto da ECV. Fallback automático para o nome do cliente caso a consulta falhe.</p>
    </section>

    <!-- Seção 3: Regras de Negócio -->
    <section class="docs-section">
        <h2>3. Regras de Saída Final</h2>
        <div class="docs-grid">
            <div class="docs-card">
                <strong>Safra Combos</strong>
                Placa com Avaliação + 1ª via da ECV registradas simultaneamente.
            </div>
            <div class="docs-card">
                <strong>Safra Segunda Via</strong>
                Placas com mais de uma vistoria ECV (registros subsequentes à primeira cronológica).
            </div>
        </div>
    </section>

    <footer class="docs-footer">
        Sistema Carvist v1.0 | Gerado em <?php echo date('d/m/Y H:i'); ?>
    </footer>
</div>

<script>
    // Lógica de Tema (Sincronizada com o sistema)
    const themeToggle = document.getElementById('themeToggle');
    const currentTheme = localStorage.getItem('theme') || 'light';
    
    if (currentTheme === 'dark') {
        document.body.setAttribute('data-theme', 'dark');
    }

    themeToggle.addEventListener('click', () => {
        let theme = 'light';
        if (!document.body.hasAttribute('data-theme')) {
            document.body.setAttribute('data-theme', 'dark');
            theme = 'dark';
        } else {
            document.body.removeAttribute('data-theme');
        }
        localStorage.setItem('theme', theme);
    });
</script>
</body>
</html>
