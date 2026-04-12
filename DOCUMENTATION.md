# Documentação do Sistema Carvist

Este documento descreve a arquitetura, segurança e funcionalidades do Sistema Carvist.

## 1. Visão Geral
O Sistema Carvist é uma plataforma interna para gestão e auditoria de laudos de vistoria (Matriz Safra, Combos e 2ª Via). Ele permite a visualização, edição e exportação de dados integrados com um banco de dados PostgreSQL.

## 2. Arquitetura Técnica
*   **Linguagem:** PHP 8.1+
*   **Banco de Dados:** PostgreSQL 15+
*   **Servidor Web:** Apache (XAMPP)
*   **Interface:** HTML5, CSS3 (App Shell Design), JavaScript Vanilla (AJAX).

## 3. Segurança
A segurança é tratada em múltiplas camadas:

### 3.1. Autenticação de Servidor
*   **Basic Auth:** O acesso a qualquer arquivo do diretório é protegido por `.htaccess` e `.htpasswd`. Nenhuma informação é servida sem autenticação prévia do navegador.
*   **Proteção de Arquivos:** Arquivos sensíveis como `.env` e `.htpasswd` são protegidos contra acesso direto via regras de negação no Apache.

### 3.2. Segurança de Dados (SQL Injection)
*   **PDO Prepared Statements:** Todas as consultas ao banco de dados utilizam `PDO::prepare()` e marcadores nomeados. Isso neutraliza ataques de SQL Injection, tratando entradas de usuário estritamente como dados.
*   **Remoção de SQL Dinâmico:** Parâmetros de injeção manual (como o antigo `sql_extra`) foram removidos em favor de filtros estruturados.

### 3.3. Integridade de Exibição (XSS)
*   **Sanitização de Saída:** Todos os dados exibidos nas tabelas passam pela função `htmlspecialchars()`, prevenindo a execução de scripts maliciosos injetados no banco de dados.

## 4. Funcionalidades Principais
*   **Matriz Safra:** Gestão principal de laudos com edição de valores e serviços.
*   **Safra Combos & 2ª Via:** Auditoria de registros específicos com edição em massa do campo REL.
*   **Exportação:** Geração de arquivos CSV baseada nos filtros aplicados em tela.
*   **Interface Responsiva:** Suporte a Tema Escuro (Dark Mode) e feedbacks visuais animados para ações do usuário.

## 5. Manutenção e Boas Práticas
*   **Configurações:** Variáveis de ambiente devem ser mantidas no arquivo `.env`.
*   **Nulos:** O código está preparado para o PHP 8.1+, tratando valores nulos com o operador `??` para evitar avisos de depreciação em funções de formatação.
