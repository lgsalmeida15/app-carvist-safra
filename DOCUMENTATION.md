# Documentação do Sistema Carvist

Este documento descreve a arquitetura, segurança e funcionalidades do Sistema Carvist.

## 1. Visão Geral
O Sistema Carvist é uma plataforma interna para gestão e auditoria de laudos de vistoria (Matriz Safra, Combos e 2ª Via). Ele permite a visualização, edição e exportação de dados integrados com um banco de dados PostgreSQL.

## 2. Arquitetura Técnica
*   **Linguagem:** PHP 8.1+
*   **Banco de Dados:** PostgreSQL 15+ (Tabelas: `matriz_safra`, `unidade_negocio_apigo`, `tabela_precos_vistorias`)
*   **Views de Auditoria:** `vw_safra_combos`, `vw_safra_ecv_demais_vias`
*   **Servidor Web:** Apache (XAMPP)
*   **Interface:** HTML5, CSS3 (App Shell Design), JavaScript Vanilla (AJAX/Fetch), jQuery + Select2.

## 3. Segurança
A segurança é tratada em múltiplas camadas:

### 3.1. Autenticação de Servidor
*   **Basic Auth:** O acesso a qualquer arquivo do diretório é protegido por `.htaccess` e `.htpasswd`. Nenhuma informação é servida sem autenticação prévia do navegador.
*   **Proteção de Arquivos:** Arquivos sensíveis como `.env` e `.htpasswd` são protegidos contra acesso direto via regras de negação no Apache.

### 3.2. Segurança de Dados (SQL Injection)
*   **PDO Prepared Statements:** Todas as consultas ao banco de dados utilizam `PDO::prepare()` e marcadores nomeados. Isso neutraliza ataques de SQL Injection, tratando entradas de usuário estritamente como dados.
*   **Remoção de SQL Dinâmico:** Parâmetros de injeção manual foram removidos em favor de filtros estruturados.

### 3.3. Integridade de Exibição (XSS)
*   **Sanitização de Saída:** Todos os dados exibidos nas tabelas passam pela função `htmlspecialchars()`, prevenindo a execução de scripts maliciosos injetados no banco de dados.

## 4. Funcionalidades Principais

### 4.1. Módulos de Gestão
*   **Matriz Safra (`index.php`):** Gestão principal de laudos com edição de `via_laudo`, `valor`, `servico`, `rel`, `uf_vistoriador` e status de envio ao banco. Possui edição em massa para o campo `REL`.
*   **Unidades de Negócio (`unidades.php`):** Cadastro e edição de unidades de negócio, permitindo ativar/desativar e vincular clientes.
*   **Tabela de Preços (`tabela_precos.php`):** Gestão de valores por tipo de serviço, modalidade e região.

### 4.2. Módulos de Auditoria (Read-only)
*   **Safra Combos (`combos.php`):** Visualização de registros baseada na view `vw_safra_combos`.
*   **Safra 2ª VIA+ (`segunda_via.php`):** Visualização de registros baseada na view `vw_safra_ecv_demais_vias`.

### 4.3. Recursos Transversais
*   **Exportação:** Geração de arquivos CSV (`export.php`) baseada nos filtros aplicados em tela para todas as tabelas e views.
*   **Interface Responsiva:** Suporte a Tema Escuro (Dark Mode) sincronizado via `localStorage` e feedbacks visuais animados (App Shell).
*   **Filtros Dinâmicos:** Filtros com debounce e suporte a múltipla seleção (Select2).

## 5. Automação n8n
O sistema é alimentado por workflows do n8n que realizam:
1.  **Ingestão:** Daily Job para coleta de dados do VISTORIAGO.
2.  **Higienização:** Remoção de duplicidades e ordenação cronológica.
3.  **Enriquecimento:** Consulta à API da Otmiza para identificação de pátios e ECVs.

## 6. Manutenção e Boas Práticas
*   **Configurações:** Variáveis de ambiente devem ser mantidas no arquivo `.env` e carregadas via `includes/Env.php`.
*   **Nulos:** O código utiliza o operador `??` para tratar valores nulos, garantindo compatibilidade com PHP 8.1+.
*   **Conexão:** Centralizada em `config.php` utilizando PDO.
