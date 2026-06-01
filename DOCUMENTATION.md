# Documentação do Sistema Carvist

Este documento descreve a arquitetura, segurança e funcionalidades do Sistema Carvist.

## 1. Visão Geral
O Sistema Carvist é uma plataforma interna para gestão e auditoria de laudos de vistoria. Atualmente focado em importação de dados e gestão de tabelas de preços, integrado com um banco de dados PostgreSQL.

## 2. Arquitetura Técnica
*   **Linguagem:** PHP 8.1+
*   **Banco de Dados:** PostgreSQL 15+ (Tabelas: `matriz_safra`, `unidade_negocio_apigo`, `tabela_precos_vistorias`)
*   **Servidor Web:** Apache (XAMPP)
*   **Interface:** HTML5, CSS3 (App Shell Design), JavaScript Vanilla (AJAX/Fetch), jQuery + Select2.
*   **Integração:** Webhooks n8n com autenticação básica.

## 3. Segurança
A segurança é tratada em múltiplas camadas:

### 3.1. Autenticação de Servidor
*   **Basic Auth:** O acesso a qualquer arquivo do diretório é protegido por `.htaccess` e `.htpasswd`. Nenhuma informação é servida sem autenticação prévia do navegador.
*   **Proteção de Arquivos:** Arquivos sensíveis como `.env`, `.htpasswd`, `.htaccess` e arquivos de documentação `.md` são protegidos contra acesso direto via regras de negação no Apache. A pasta `includes/` também possui bloqueio de acesso direto, permitindo apenas o carregamento de recursos visuais (CSS/JS/Imagens).

### 3.2. Segurança de Dados (SQL Injection)
*   **PDO Prepared Statements:** Todas as consultas ao banco de dados utilizam `PDO::prepare()` e marcadores nomeados. Isso neutraliza ataques de SQL Injection, tratando entradas de usuário estritamente como dados.

### 3.3. Integridade de Exibição (XSS)
*   **Sanitização de Saída:** Todos os dados exibidos nas tabelas passam pela função `htmlspecialchars()`, prevenindo a execução de scripts maliciosos injetados no banco de dados.

## 4. Funcionalidades Principais

### 4.1. Módulos Ativos
*   **Importar (`importar.php`):** Interface para upload de planilhas Excel/CSV que envia os dados diretamente para um webhook do n8n via cURL com autenticação básica.
*   **Tabela de Preços (`tabela_precos.php`):** Gestão de valores por tipo de serviço, modalidade e região.

### 4.2. Módulos de Backup (Desativados)
As seguintes funcionalidades foram migradas para a pasta `backup_pages/` e removidas do menu principal:
*   **Matriz Safra:** Gestão principal de laudos.
*   **Safra Combos:** Auditoria de registros de combos.
*   **Safra 2ª VIA+:** Auditoria de registros de 2ª via.
*   **Unidades de Negócio:** Cadastro de unidades.

### 4.3. Recursos Transversais
*   **Interface Responsiva:** Suporte a Tema Escuro (Dark Mode) sincronizado via `localStorage` e feedbacks visuais animados (App Shell).
*   **Filtros Dinâmicos:** Filtros com suporte a múltipla seleção (Select2).

## 5. Automação n8n
O sistema interage com workflows do n8n para processamento de dados importados e automação de relatórios. As URLs e credenciais dos webhooks são gerenciadas via variáveis de ambiente no arquivo `.env`.

## 6. Manutenção e Boas Práticas
*   **Configurações:** Variáveis de ambiente devem ser mantidas no arquivo `.env` e carregadas via `includes/Env.php`.
*   **Segurança:** Sempre utilizar HTTPS para proteger a autenticação básica e os dados trafegados.
*   **Conexão:** Centralizada em `config.php` utilizando PDO.
