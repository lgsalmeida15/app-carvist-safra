# Sistema de Faturamento de Vistorias
**PHP + PostgreSQL — Migração da planilha Google Sheets / Apps Script**

---

## Índice

1. [Visão Geral](#1-visão-geral)
2. [Contexto de Negócio](#2-contexto-de-negócio)
3. [Stack Tecnológica](#3-stack-tecnológica)
4. [Estrutura de Pastas](#4-estrutura-de-pastas)
5. [Banco de Dados (PostgreSQL)](#5-banco-de-dados-postgresql)
6. [Módulos da Aplicação](#6-módulos-da-aplicação)
7. [Fluxos de Negócio](#7-fluxos-de-negócio)
8. [Regras de Negócio Críticas](#8-regras-de-negócio-críticas)
9. [Integrações Externas](#9-integrações-externas)
10. [Rotas da Aplicação](#10-rotas-da-aplicação)
11. [Schema SQL Completo](#11-schema-sql-completo)
12. [Ordem de Desenvolvimento](#12-ordem-de-desenvolvimento)
13. [Pontos de Atenção](#13-pontos-de-atenção)

---

## 1. Visão Geral

Este sistema substitui uma planilha Google Sheets com 36 abas e múltiplos Apps Scripts que gerenciam:

- Importação de vistorias veiculares de dois sistemas externos (Carvist e VistoriaGo)
- Faturamento para múltiplos clientes (Banco PAN, UNIDAS, LM, Banco SAFRA, DR.DOC, Toyota)
- Geração de arquivos TXT bancários (padrão Banco PAN)
- Download e organização de PDFs de laudos
- Controle financeiro (custos, DRE por filial, recebimentos)
- Gestão de vistoriadores e tabelas de preços

A planilha original tem um script central (`gerarLotesPan`) que roda no Google Apps Script. O objetivo é recriar toda essa lógica em PHP com banco PostgreSQL, com interface web e botões de ação substituindo as macros.

### Estratégia de ingestão de dados

A entrada de dados é feita pelo **n8n**, que lê o XLSX exportado do Carvist e grava diretamente no banco. Para suportar isso sem nenhuma lógica no n8n, o sistema usa **duas camadas de tabela**:

```
Planilha XLSX (Carvist)
        ↓
      n8n
        ↓  INSERT bruto, sem transformação
   carvist_raw          ← espelho exato da planilha, tudo TEXT
        ↓
   SyncService (PHP)    ← normaliza, resolve FKs, converte tipos
        ↓
    vistorias            ← tabela limpa usada por todo o faturamento
```

O n8n **nunca** precisa conhecer regras de negócio. Ele apenas mapeia coluna → campo e insere. Todo o tratamento fica no PHP.

---

## 2. Contexto de Negócio

### Clientes atendidos
| Cliente | Tipo | Faturamento |
|---|---|---|
| Banco PAN | Banco | 4 lotes quinzenais (TXT bancário) |
| UNIDAS | Locadora | Relatório único por período |
| LM | Locadora | Relatório único por período |
| Banco SAFRA | Banco | Combo ECV + Avaliação vinculados |
| DR. DOC | Despachante | Formato próprio |
| Banco Toyota | Banco | Em implantação |

### Tipos de serviço
| Código | Descrição | HistLanc PAN |
|---|---|---|
| `ECV` | 1ª via de ECV | 469 |
| `SEGUNDA VIA ECV` | 2ª via de ECV | 469 |
| `TERCEIRA VIA ECV` | 3ª via de ECV | 469 |
| `ECV AVULSA` | ECV avulsa | 469 |
| `AVALIACAO` | Avaliação de bem | 967 |
| `AVALIACAO AVULSA` | Avaliação avulsa | 967 |
| `DESMOBILIZACAO` | Desmobilização | — |

### Sistemas de origem das vistorias
- **Carvist** — `carvist.vistonline.com.br` — IDs menores (~15.4 milhões)
- **VistoriaGo** — `vistoriago.com.br` — IDs maiores (~855–880 mil)

---

## 3. Stack Tecnológica

```
Backend:    PHP 8.1+
Banco:      PostgreSQL 15+
Ingestão:   n8n (lê XLSX → grava carvist_raw)
Auth:       Sessão PHP nativa
PDFs:       cURL nativo PHP
Cron:       crontab Linux (retry de PDFs pendentes)
Storage:    Sistema de arquivos local /laudos/
Export:     PhpSpreadsheet (escrita) + download stream
```

### Dependências Composer (mínimas)
```json
{
  "require": {
    "phpoffice/phpspreadsheet": "^2.0",
    "vlucas/phpdotenv": "^5.0"
  }
}
```

---

## 4. Estrutura de Pastas

```
/
├── public/
│   ├── index.php
│   └── assets/
│       ├── css/
│       └── js/
│
├── app/
│   ├── Config/
│   │   ├── Database.php         # Conexão PDO PostgreSQL
│   │   └── App.php              # Constantes globais
│   │
│   ├── Models/
│   │   ├── CarvistRawModel.php  # Acesso à tabela carvist_raw
│   │   ├── VistoriaModel.php
│   │   ├── ClienteModel.php
│   │   ├── VistoriadorModel.php
│   │   ├── LoteModel.php
│   │   ├── ControleModel.php
│   │   ├── PanDemandaModel.php
│   │   ├── PdfPendenteModel.php
│   │   ├── RecebimentoModel.php
│   │   └── CustoModel.php
│   │
│   ├── Services/
│   │   ├── SyncService.php          # Lê carvist_raw → popula vistorias
│   │   ├── NormalizacaoService.php  # Helpers de limpeza e conversão
│   │   ├── LotePanService.php
│   │   ├── FaturamentoGeralService.php
│   │   ├── PdfDownloadService.php
│   │   └── ExportService.php
│   │
│   ├── Controllers/
│   │   ├── SyncController.php           # Dispara SyncService
│   │   ├── VistoriaController.php
│   │   ├── FaturamentoPanController.php
│   │   ├── FaturamentoGeralController.php
│   │   ├── CadastroController.php
│   │   ├── FinanceiroController.php
│   │   ├── PdfController.php
│   │   └── AuthController.php
│   │
│   └── Helpers/
│       ├── FormatHelper.php     # CPF, CNPJ, moeda, datas
│       ├── StringHelper.php     # normalizar(), limpar()
│       └── TxtGenerator.php    # Geração do arquivo TXT PAN
│
├── views/
│   ├── layout/
│   │   ├── header.php
│   │   ├── sidebar.php
│   │   └── footer.php
│   ├── sync/                    # Tela de monitoramento da ingestão
│   ├── vistorias/
│   ├── faturamento/
│   │   ├── pan/
│   │   └── geral/
│   ├── cadastros/
│   ├── financeiro/
│   ├── pdfs/
│   └── auth/
│
├── storage/
│   ├── laudos/
│   │   └── {nome_lote}/
│   ├── exports/
│   └── uploads/
│
├── database/
│   ├── schema.sql
│   ├── seeds/
│   │   ├── clientes.sql
│   │   ├── vistoriadores.sql
│   │   └── tabela_precos.sql
│   └── migrations/
│
├── cron/
│   └── retry_pdfs.php
│
├── .env
├── .env.example
├── composer.json
└── README.md
```

---

## 5. Banco de Dados (PostgreSQL)

### Grupos de tabelas

| Grupo | Tabelas | Descrição |
|---|---|---|
| **Ingestão** | `carvist_raw` | Espelho bruto da planilha XLSX, gravado pelo n8n |
| **Cadastros** | `clientes`, `vistoriadores`, `tabela_precos`, `sla_clientes` | Dados de referência |
| **Vistorias** | `vistorias` | Tabela normalizada, usada pelo faturamento |
| **PAN** | `pan_demandas`, `controle_pan`, `pan_pagamentos` | Específico Banco PAN |
| **Faturamento** | `controle_faturas`, `lotes_faturamento`, `lote_itens` | Geração de lotes |
| **PDFs** | `pdfs_pendentes` | Fila de download |
| **Financeiro** | `custos`, `recebimentos`, `dre_filial` | Controle financeiro |
| **Sistema** | `sync_log`, `audit_log` | Logs e rastreabilidade |

### Relacionamentos principais

```
carvist_raw ──► SyncService ──► vistorias

clientes ──< vistorias
clientes ──< tabela_precos
clientes ──< sla_clientes
clientes ──< controle_faturas
clientes ──< lotes_faturamento
clientes ──< recebimentos

vistoriadores ──< vistorias

lotes_faturamento ──< lote_itens
lote_itens >── vistorias
lote_itens ──< pdfs_pendentes

pan_demandas >── vistorias (nullable)

carvist_raw.nr_vistoria ── vistorias.nr_vistoria (sem FK, por design)
```

> `carvist_raw` e `vistorias` não têm FK entre si por design — a `carvist_raw` é imutável e a sincronia é gerenciada pelo `SyncService`, que controla o que já foi processado.

---

## 6. Módulos da Aplicação

---

### 6.1 Módulo: Ingestão via n8n → carvist_raw

**Responsabilidade do n8n:** apenas mapear colunas da planilha para campos da tabela e executar o INSERT. Sem lógica de negócio, sem conversão de tipos, sem resolução de FKs.

**Fluxo n8n:**
```
[Trigger: novo arquivo XLSX / schedule]
        ↓
[Read Binary File ou Google Sheets node]
        ↓
[Loop por linha da aba CARVIST]
        ↓
[Postgres node]
  INSERT INTO carvist_raw (
    nr_vistoria, seguradora_cliente, patio, proponente,
    data_agendamento, data_vistoria, data_finalizado,
    sla_vistoriador, sla_mesa, tipo_servico, status_laudo, obs,
    placa, chassi, marca, modelo, ano_fab, ano_modelo, cor,
    nr_laudo_ecv, valor, cidade, uf, vistoriador, analista,
    valor_vistoria, valor_vistoriador, comissao, id_cliente,
    link_laudo, cpf_cnpj, solicitante, nr_contrato, nr_requisicao,
    arquivo_origem
  ) VALUES (...)
  ON CONFLICT (nr_vistoria)
  DO UPDATE SET
    status_laudo       = EXCLUDED.status_laudo,
    data_finalizado    = EXCLUDED.data_finalizado,
    link_laudo         = EXCLUDED.link_laudo,
    valor              = EXCLUDED.valor,
    obs                = EXCLUDED.obs,
    sincronizado       = false,       -- marca para re-processar no sync
    atualizado_em      = NOW()
```

O campo `sincronizado = false` sinaliza para o `SyncService` que esse registro precisa ser reprocessado em `vistorias`.

---

### 6.2 Módulo: SyncService (carvist_raw → vistorias)

**Arquivo:** `app/Services/SyncService.php`

Este é o módulo central de transformação. Roda quando o usuário clica "Sincronizar" na interface, ou pode ser agendado via cron.

**Lógica completa:**

```php
// Busca registros novos ou atualizados desde a última sync
SELECT * FROM carvist_raw
WHERE sincronizado = false
ORDER BY nr_vistoria ASC;

// Para cada linha:
foreach ($rows as $raw) {

    // 1. Normalizar strings
    $tipoServico = normalizar($raw['tipo_servico']);
    // "AVALIAÇÃO" → "AVALIACAO", "Ecv" → "ECV"

    // 2. Resolver cliente_id
    $clienteId = buscarCliente($raw['seguradora_cliente']);
    // SELECT id FROM clientes WHERE nome_normalizado = normalizar($v)

    // 3. Resolver vistoriador_id
    $vistoriadorId = buscarVistoriador($raw['vistoriador']);
    // SELECT id FROM vistoriadores WHERE nome_sistema = trim($v)
    // NULL se não encontrar — registra no sync_log

    // 4. Detectar sistema_origem
    $origem = str_contains($raw['link_laudo'], 'carvist') ? 'CARVIST' : 'GO';

    // 5. Converter datas (vêm como string "dd/MM/yyyy" da planilha)
    $dataVistoria = converterData($raw['data_vistoria']); // → 'yyyy-MM-dd'

    // 6. Converter valor monetário ("R$ 96,50" ou "2,00" → 96.50)
    $valor = converterValor($raw['valor']);

    // 7. INSERT / UPDATE em vistorias
    INSERT INTO vistorias (
        nr_vistoria, sistema_origem, cliente_id, vistoriador_id,
        tipo_servico, tipo_servico_padronizado, status_laudo,
        data_vistoria, data_agendamento, data_finalizado,
        placa, chassi, marca, modelo, ano_fab, ano_modelo, cor,
        valor_faturamento, cidade, uf, link_laudo, cpf_cnpj,
        solicitante, nr_contrato, nr_requisicao,
        nr_laudo_ecv, sla_vistoriador, sla_mesa,
        vistoriador_texto, analista,
        valor_vistoriador, comissao, patio, proponente,
        atualizado_em
    ) VALUES (...)
    ON CONFLICT (nr_vistoria) DO UPDATE SET
        status_laudo            = EXCLUDED.status_laudo,
        data_finalizado         = EXCLUDED.data_finalizado,
        link_laudo              = EXCLUDED.link_laudo,
        valor_faturamento       = EXCLUDED.valor_faturamento,
        tipo_servico_padronizado = EXCLUDED.tipo_servico_padronizado,
        atualizado_em           = NOW()
    -- NÃO sobrescreve nr_relatorio se já preenchido:
    WHERE vistorias.nr_relatorio IS NULL;

    // 8. Marcar como sincronizado
    UPDATE carvist_raw SET sincronizado = true WHERE id = $raw['id'];
}

// 9. Gravar resumo em sync_log
INSERT INTO sync_log (total, atualizados, inseridos, erros, log_json, executado_em)
VALUES (...);
```

**Tela de monitoramento (view/sync/index.php):**
- Contador: X registros em carvist_raw, Y sincronizados, Z pendentes
- Botão "Sincronizar agora"
- Tabela do sync_log com histórico de execuções
- Alerta visual para registros com `vistoriador_id IS NULL` (vistoriador não encontrado)

---

### 6.3 Módulo: Vistorias

**Rotas:** `/vistorias`, `/vistorias/{id}`, `/vistorias/exportar`

Lê exclusivamente da tabela `vistorias` (já normalizada). Nunca acessa `carvist_raw` diretamente.

**Funcionalidades:**
- Listagem com filtros: cliente, UF, status_laudo, tipo_servico, período, vistoriador
- Paginação (50 por página)
- Visualização individual com link para laudo PDF
- Edição manual de campos específicos (nr_relatorio, valor, obs)
- Exportação filtrada para XLSX
- Badge visual indicando se o registro tem correspondência em `carvist_raw` com `sincronizado = false` (dado atualizado pendente)

**Query base:**
```sql
SELECT v.*, c.nome as cliente_nome, vs.nome_padrao as vistoriador_nome
FROM vistorias v
LEFT JOIN clientes c ON c.id = v.cliente_id
LEFT JOIN vistoriadores vs ON vs.id = v.vistoriador_id
WHERE 1=1
  AND (:cliente_id IS NULL OR v.cliente_id = :cliente_id)
  AND (:uf IS NULL OR v.uf = :uf)
  AND (:status IS NULL OR v.status_laudo = :status)
  AND (:tipo IS NULL OR v.tipo_servico_padronizado = :tipo)
  AND (:data_ini IS NULL OR v.data_vistoria >= :data_ini)
  AND (:data_fim IS NULL OR v.data_vistoria <= :data_fim)
ORDER BY v.data_vistoria DESC
LIMIT 50 OFFSET :offset
```

---

### 6.4 Módulo: Faturamento PAN

**Arquivo:** `app/Services/LotePanService.php`

Conversão direta da função `gerarLotesPan()` do Apps Script. Lê de `vistorias` (já normalizada).

**Fluxo completo:**

```
[Usuário clica "Gerar Lotes PAN"]
      │
      ├─ Seleciona quinzena (Q1 ou Q2) via modal
      │
      ├─ Busca vistorias:
      │    cliente = BANCO PAN
      │    status_laudo = 'FINALIZADO'
      │    nr_relatorio IS NULL
      │
      ├─ Separa grupos:
      │    ecvs[]       = tipo_servico_padronizado IN ('ECV','ECV AVULSA','SEGUNDA VIA ECV','TERCEIRA VIA ECV')
      │    avaliacoes[] = tipo_servico_padronizado IN ('AVALIACAO','AVALIACAO AVULSA')
      │
      ├─ Ordena cada grupo por placa ASC
      │
      ├─ Divide:
      │    Lote 1: ecvs[0 .. ceil(n/2)-1]        HistLanc: 469
      │    Lote 2: ecvs[ceil(n/2) .. n-1]         HistLanc: 469
      │    Lote 3: avaliacoes[0 .. ceil(n/2)-1]   HistLanc: 967
      │    Lote 4: avaliacoes[ceil(n/2) .. n-1]   HistLanc: 967
      │
      ├─ Para cada lote:
      │    a. Busca proximo_nr_relatorio em controle_faturas (SELECT FOR UPDATE)
      │    b. Gera arquivo TXT (tab-delimitado)
      │    c. Tenta baixar PDFs ECV via cURL
      │    d. Enfileira PDFs de avaliação em pdfs_pendentes
      │    e. INSERT em lotes_faturamento + lote_itens
      │    f. UPDATE nr_relatorio em vistorias
      │    g. INSERT em controle_pan
      │
      └─ Em transação única:
           UPDATE controle_faturas SET
             ultimo_nr_relatorio  = proximo_nr_relatorio,
             proximo_nr_relatorio = proximo_nr_relatorio + 4,
             data_ultima_fatura   = CURRENT_DATE
           WHERE cliente_id = {pan_id}
             AND tipo_fatura IN ('LOTE 1','LOTE 2','LOTE 3','LOTE 4')
```

**Formato do arquivo TXT PAN:**
```
NrOper\tCodBack\tCpfCgc\tHistLanc\tDtLanc\tValorLanc\tObs\r\n
{contrato}\t10\t{cpf_padded}\t{hist_lanc}\t{dd/MM/yyyy}\t{valor_centavos}\t{nr_vistoria}\r\n
```

**Regras de formatação:**
- `ValorLanc`: `round(valor * 100)` — inteiro, sem decimal
- `CpfCgc`: somente dígitos, CPF = 11 chars com zeros à esquerda, CNPJ = 14
- Nome do arquivo: `NPV_AC{ddMMyyyy}_104.50515_{nome_lote}.txt`

---

### 6.5 Módulo: Faturamento Geral

**Rotas:** `/faturamento/{cliente}` — `lm`, `unidas`, `safra`, `drdoc`, `toyota`

Cada cliente lê de `vistorias` com filtros específicos e gera seu relatório no formato adequado.

| Cliente | Particularidade |
|---|---|
| LM | Lote único por período, export XLSX |
| UNIDAS | Inclui campos de pátio e UF |
| SAFRA COMBO | Um veículo pode ter ECV + Avaliação vinculados (duplo laudo) |
| DR.DOC | Origem VistoriaGo, formato próprio |

---

### 6.6 Módulo: Gestão de PDFs

**Arquivo:** `app/Services/PdfDownloadService.php`

**ECV (download direto):**
```php
function baixarPdf(string $url, string $destino): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0',
        CURLOPT_TIMEOUT        => 30,
    ]);
    $bytes = curl_exec($ch);
    $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code < 200 || $code >= 300) return "ERRO HTTP $code";
    if (substr($bytes, 0, 5) !== '%PDF-') return 'NÃO É PDF';

    file_put_contents($destino, $bytes);
    return 'PDF OK';
}
```

**Avaliação (fila):** enfileirado em `pdfs_pendentes`, tentado via cron a cada 30 minutos.

```
# crontab
*/30 * * * * php /var/www/cron/retry_pdfs.php
```

---

### 6.7 Módulo: Cadastros

**Rotas:** `/cadastros/clientes`, `/cadastros/vistoriadores`, `/cadastros/precos`, `/cadastros/sla`

- CRUD completo de clientes e vistoriadores
- Tabela de preços editável por serviço × cliente × UF
- SLA por cliente/serviço
- Tela de DE-PARA de vistoriadores (para resolver registros com `vistoriador_id = NULL`)

---

### 6.8 Módulo: Financeiro

**Rotas:** `/financeiro/custos`, `/financeiro/recebimentos`, `/financeiro/dre`

- Lançamento e listagem de custos (fixo/variável)
- Controle de recebimentos por relatório com baixa manual
- DRE por filial/UF

---

## 7. Fluxos de Negócio

### Fluxo 1: Ciclo quinzenal PAN

```
1. n8n lê o XLSX exportado do Carvist e grava em carvist_raw
2. Analista abre o sistema e clica "Sincronizar"
   └─ SyncService normaliza carvist_raw → vistorias
3. Abre página "Faturamento PAN"
4. Confere as vistorias pendentes (sem nr_relatorio)
5. Clica "Gerar Lotes" → informa Q1 ou Q2
6. Sistema gera 4 lotes:
   - TXT bancário disponível para download
   - PDFs ECV baixados automaticamente
   - PDFs Avaliação enfileirados em pdfs_pendentes
7. Sistema atualiza numeração de relatórios (transação)
8. Analista baixa os TXTs e envia ao banco
9. Analista acompanha status dos PDFs pendentes
```

### Fluxo 2: Atualização de dado que chegou via n8n

```
1. n8n reprocessa o XLSX (status mudou de PENDENTE para FINALIZADO)
2. INSERT ... ON CONFLICT DO UPDATE em carvist_raw
   └─ sincronizado = false é marcado automaticamente
3. No painel de Sync, aparece "X registros pendentes de sincronização"
4. Analista clica "Sincronizar" (ou cron executa)
5. SyncService atualiza vistorias com os novos valores
   └─ Preserva nr_relatorio se já preenchido
```

### Fluxo 3: Vistoriador não encontrado

```
1. SyncService encontra vistoriador_texto = "SP/NOVO VISTORIADOR - FULANO"
2. Não encontra match em vistoriadores
3. Insere em vistorias com vistoriador_id = NULL
4. Registra aviso no sync_log
5. Painel de Sync exibe alerta: "3 vistorias com vistoriador não identificado"
6. Analista vai em Cadastros → Vistoriadores → cria o cadastro
7. Na próxima sync, o match é resolvido e vistoriador_id é preenchido
```

---

## 8. Regras de Negócio Críticas

### RN-01: carvist_raw é imutável
Nunca alterar dados de `carvist_raw` além dos campos de controle (`sincronizado`, `atualizado_em`). É o dado bruto preservado para auditoria.

### RN-02: Normalização de strings
```php
function normalizar(string $v): string {
    return strtoupper(trim(
        preg_replace('/\s+/', ' ',
        iconv('UTF-8', 'ASCII//TRANSLIT', $v))
    ));
}
// "AVALIAÇÃO AVULSA" → "AVALIACAO AVULSA"
// "São Paulo"        → "SAO PAULO"
```

### RN-03: Conversão de datas
```php
// Planilha exporta como "21/01/2026"
function converterData(?string $v): ?string {
    if (!$v) return null;
    $d = DateTime::createFromFormat('d/m/Y', trim($v));
    return $d ? $d->format('Y-m-d') : null;
}
```

### RN-04: Conversão de valores monetários
```php
// Planilha exporta como "R$ 96,50" ou "2,00" ou NULL
function converterValor(?string $v): ?float {
    if (!$v) return null;
    $s = preg_replace('/[R$\s\.]/', '', $v);
    $s = str_replace(',', '.', $s);
    return is_numeric($s) ? (float)$s : null;
}
```

### RN-05: Valores no TXT PAN são em centavos
```php
// Nunca usar float para o TXT — converter para inteiro
$centavos = (int) round($valor * 100);
// R$ 96,50 → 9650
```

### RN-06: nr_relatorio não pode ser sobrescrito pelo sync
```sql
ON CONFLICT (nr_vistoria) DO UPDATE SET
    status_laudo   = EXCLUDED.status_laudo,
    data_finalizado = EXCLUDED.data_finalizado,
    link_laudo     = EXCLUDED.link_laudo,
    atualizado_em  = NOW()
-- nr_relatorio é omitido propositalmente do UPDATE
-- para não apagar o que foi preenchido na geração do lote
```

### RN-07: Numeração de relatórios PAN é transacional
```sql
BEGIN;
SELECT proximo_nr_relatorio FROM controle_faturas
WHERE cliente_id = $pan_id AND tipo_fatura = 'LOTE 1'
FOR UPDATE;
-- ... processa os 4 lotes ...
UPDATE controle_faturas SET
  ultimo_nr_relatorio  = proximo_nr_relatorio,
  proximo_nr_relatorio = proximo_nr_relatorio + 4,
  data_ultima_fatura   = CURRENT_DATE
WHERE cliente_id = $pan_id
  AND tipo_fatura IN ('LOTE 1','LOTE 2','LOTE 3','LOTE 4');
COMMIT;
```

### RN-08: Tabela de preços — prioridade por UF específica
```sql
SELECT valor FROM tabela_precos
WHERE cliente_id = $cliente_id AND servico = $servico
  AND (uf = $uf OR uf = 'UF')
ORDER BY CASE WHEN uf = $uf THEN 0 ELSE 1 END
LIMIT 1;
```

### RN-09: Correção do número de contrato PAN
```php
function corrigirContratoPan(string $v): string {
    $c = strtoupper(preg_replace('/[^0-9A-Z]/', '', $v));
    if (empty($c)) return '';
    if (preg_match('/^190\d{9}$/', $c)) return $c;
    if (preg_match('/^\d{12}$/', $c)) {
        $sem = ltrim($c, '0');
        if (preg_match('/^\d{8,10}$/', $sem)) $c = $sem;
    }
    if (preg_match('/^9\d{7}$/', $c)) $c = '0' . $c;
    return $c;
}
```

---

## 9. Integrações Externas

### 9.1 n8n (ingestão de dados)
- **Direção:** n8n → PostgreSQL (`carvist_raw`)
- **Trigger:** upload de XLSX ou schedule periódico
- **Ação:** INSERT com ON CONFLICT DO UPDATE, sem lógica de negócio
- **Campos de controle que o n8n grava:** `arquivo_origem`, `importado_em`
- **Campo que o n8n nunca toca:** `sincronizado` (gerenciado pelo PHP)

### 9.2 Carvist (carvist.vistonline.com.br)
- **Tipo:** Download de PDF via URL pública
- **Método:** cURL GET com headers de browser
- **Verificação:** primeiros 5 bytes = `%PDF-`

### 9.3 VistoriaGo (vistoriago.com.br)
- **Tipo:** Download de PDF via URL pública
- **URLs de avaliação** usam parâmetro base64 e podem bloquear scrapers → fila `pdfs_pendentes`

### 9.4 Banco PAN
- **Integração:** sem API — sistema gera TXT que é enviado manualmente
- **Formato:** tab-delimitado, campos fixos, valores em centavos

---

## 10. Rotas da Aplicação

```
GET  /                              → Dashboard
GET  /login                         → Login
POST /login                         → Autenticar
GET  /logout                        → Encerrar sessão

── SYNC (ingestão n8n → vistorias) ──
GET  /sync                          → Painel: contadores raw/pendente/sincronizado
POST /sync/executar                 → Dispara SyncService
GET  /sync/log                      → Histórico de execuções do sync
GET  /sync/pendentes                → Lista registros carvist_raw não sincronizados
GET  /sync/sem-vistoriador          → Vistorias com vistoriador_id NULL

── VISTORIAS ──
GET  /vistorias                     → Listagem com filtros
GET  /vistorias/{id}               → Detalhe
PUT  /vistorias/{id}               → Edição manual
GET  /vistorias/exportar            → Export XLSX

── FATURAMENTO PAN ──
GET  /faturamento/pan               → Preview (vistorias pendentes)
POST /faturamento/pan/gerar         → Executar geração dos 4 lotes
GET  /faturamento/pan/lotes         → Histórico de lotes
GET  /faturamento/pan/lotes/{id}   → Detalhe do lote
GET  /faturamento/pan/txt/{id}     → Download do TXT

── FATURAMENTO GERAL ──
GET  /faturamento/lm                → Preview LM
POST /faturamento/lm/gerar         → Gerar
GET  /faturamento/unidas            → Preview UNIDAS
POST /faturamento/unidas/gerar     → Gerar
GET  /faturamento/safra             → Preview SAFRA COMBO
POST /faturamento/safra/gerar      → Gerar
GET  /faturamento/drdoc             → Preview DR.DOC
POST /faturamento/drdoc/gerar      → Gerar

── PDFs ──
GET  /pdfs/pendentes                → Fila
POST /pdfs/retry/{id}              → Retry manual
POST /pdfs/retry-todos              → Retry em lote

── CADASTROS ──
GET  /cadastros/clientes            → Lista + CRUD
GET  /cadastros/vistoriadores       → Lista + CRUD
GET  /cadastros/precos              → Tabela editável
GET  /cadastros/sla                 → SLA por cliente

── FINANCEIRO ──
GET  /financeiro/custos             → Lista + lançamento
GET  /financeiro/recebimentos       → Lista + baixa
GET  /financeiro/dre                → DRE por filial

── PAN DEMANDAS ──
GET  /pan/demandas                  → Base de veículos retomados
GET  /pan/pagamentos                → Histórico pagoPAN

── SISTEMA ──
GET  /sistema/audit                 → Audit log
GET  /sistema/configuracoes        → Config
```

---

## 11. Schema SQL Completo

```sql
-- ============================================================
-- INGESTÃO (gravado pelo n8n, dado bruto preservado)
-- ============================================================

CREATE TABLE carvist_raw (
    id                  SERIAL PRIMARY KEY,
    -- Colunas espelho da planilha (nomes originais, tudo TEXT)
    nr_vistoria         BIGINT NOT NULL,
    seguradora_cliente  TEXT,
    patio               TEXT,
    proponente          TEXT,
    data_agendamento    TEXT,
    data_vistoria       TEXT,
    data_finalizado     TEXT,
    sla_vistoriador     TEXT,
    sla_mesa            TEXT,
    tipo_servico        TEXT,
    status_laudo        TEXT,
    obs                 TEXT,
    placa               TEXT,
    chassi              TEXT,
    marca               TEXT,
    modelo              TEXT,
    ano_fab             TEXT,
    ano_modelo          TEXT,
    cor                 TEXT,
    nr_laudo_ecv        TEXT,
    valor               TEXT,
    cidade              TEXT,
    uf                  TEXT,
    vistoriador         TEXT,
    analista            TEXT,
    valor_vistoria      TEXT,
    valor_vistoriador   TEXT,
    comissao            TEXT,
    id_cliente          TEXT,
    link_laudo          TEXT,
    cpf_cnpj            TEXT,
    solicitante         TEXT,
    nr_contrato         TEXT,
    nr_requisicao       TEXT,
    -- Controle de ingestão (gerenciado pelo n8n e pelo PHP)
    arquivo_origem      TEXT,
    sincronizado        BOOLEAN DEFAULT false,
    importado_em        TIMESTAMP DEFAULT NOW(),
    atualizado_em       TIMESTAMP DEFAULT NOW()
);

CREATE UNIQUE INDEX idx_carvist_raw_nr ON carvist_raw(nr_vistoria);
CREATE INDEX idx_carvist_raw_sync ON carvist_raw(sincronizado) WHERE sincronizado = false;

-- Log de execuções do SyncService
CREATE TABLE sync_log (
    id              SERIAL PRIMARY KEY,
    total_lidos     INT,
    inseridos       INT,
    atualizados     INT,
    erros           INT,
    sem_vistoriador INT,
    log_json        JSONB,
    executado_por   VARCHAR(100),
    executado_em    TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- CADASTROS BASE
-- ============================================================

CREATE TABLE clientes (
    id                  SERIAL PRIMARY KEY,
    nome                VARCHAR(100) NOT NULL UNIQUE,
    nome_normalizado    VARCHAR(100),
    tipo                VARCHAR(30),
    cod_back            VARCHAR(10),
    prefixo_txt         VARCHAR(80),
    hist_lanc_ecv       VARCHAR(10),
    hist_lanc_avaliacao VARCHAR(10),
    ativo               BOOLEAN DEFAULT true,
    criado_em           TIMESTAMP DEFAULT NOW()
);

CREATE TABLE vistoriadores (
    id                      SERIAL PRIMARY KEY,
    nome_sistema            VARCHAR(150) NOT NULL,
    nome_padrao             VARCHAR(100),
    uf                      CHAR(2),
    cidade                  VARCHAR(80),
    tipo                    VARCHAR(20),
    cpf                     VARCHAR(14),
    email                   VARCHAR(100),
    whatsapp                VARCHAR(20),
    emite_nf                BOOLEAN DEFAULT false,
    preco_avaliacao         NUMERIC(10,2),
    preco_avaliacao_avulsa  NUMERIC(10,2),
    preco_desmobilizacao    NUMERIC(10,2),
    preco_ecv               NUMERIC(10,2),
    preco_ecv_avulsa        NUMERIC(10,2),
    preco_segunda_via_ecv   NUMERIC(10,2),
    preco_terceira_via_ecv  NUMERIC(10,2),
    ativo                   BOOLEAN DEFAULT true,
    criado_em               TIMESTAMP DEFAULT NOW()
);

CREATE TABLE tabela_precos (
    id          SERIAL PRIMARY KEY,
    servico     VARCHAR(60) NOT NULL,
    uf          CHAR(2),
    cliente_id  INT REFERENCES clientes(id),
    valor       NUMERIC(10,2) NOT NULL,
    criado_em   TIMESTAMP DEFAULT NOW(),
    UNIQUE (servico, uf, cliente_id)
);

CREATE TABLE sla_clientes (
    id                  SERIAL PRIMARY KEY,
    cliente_id          INT REFERENCES clientes(id),
    servico             VARCHAR(60),
    sla_dias            INT,
    prioridade          VARCHAR(20),
    critico             BOOLEAN DEFAULT false,
    considera_fds       BOOLEAN DEFAULT false,
    considera_feriado   BOOLEAN DEFAULT false,
    obs                 TEXT
);

-- ============================================================
-- VISTORIAS (tabela normalizada — alimentada pelo SyncService)
-- ============================================================

CREATE TABLE vistorias (
    id                          SERIAL PRIMARY KEY,
    nr_vistoria                 BIGINT UNIQUE NOT NULL,
    sistema_origem              VARCHAR(20),
    cliente_id                  INT REFERENCES clientes(id),
    patio                       VARCHAR(150),
    proponente                  VARCHAR(150),
    data_agendamento            DATE,
    data_vistoria               TIMESTAMP,
    data_finalizado             TIMESTAMP,
    sla_vistoriador             INT,
    sla_mesa                    INT,
    tipo_servico                VARCHAR(50),
    tipo_servico_padronizado    VARCHAR(50),
    status_laudo                VARCHAR(50),
    obs                         TEXT,
    placa                       VARCHAR(10),
    chassi                      VARCHAR(25),
    marca                       VARCHAR(60),
    modelo                      VARCHAR(100),
    ano_fab                     INT,
    ano_modelo                  INT,
    cor                         VARCHAR(30),
    nr_laudo_ecv                VARCHAR(50),
    valor_faturamento           NUMERIC(10,2),
    cidade                      VARCHAR(80),
    uf                          CHAR(2),
    vistoriador_id              INT REFERENCES vistoriadores(id),
    vistoriador_texto           VARCHAR(150),
    analista                    VARCHAR(100),
    valor_vistoria              NUMERIC(10,2),
    valor_vistoriador           NUMERIC(10,2),
    comissao                    NUMERIC(10,2),
    valor_final_vistoriador     NUMERIC(10,2),
    tipo_vistoriador            VARCHAR(20),
    id_cliente_externo          VARCHAR(50),
    link_laudo                  TEXT,
    cpf_cnpj                    VARCHAR(20),
    solicitante                 VARCHAR(100),
    nr_contrato                 VARCHAR(30),
    nr_requisicao               VARCHAR(30),
    mes_ref                     VARCHAR(20),
    semana_ref                  VARCHAR(20),
    nr_relatorio                VARCHAR(30),  -- preenchido ao gerar lote, nunca sobrescrito pelo sync
    pagamento_vistoriador       VARCHAR(30),
    data_pagamento_vistoriador  DATE,
    flag_otimiza                BOOLEAN,
    flag_rsci                   BOOLEAN,
    flag_pesquisa               BOOLEAN,
    flag_molicar                BOOLEAN,
    flag_molicar_safra          BOOLEAN,
    flag_employer               BOOLEAN,
    criado_em                   TIMESTAMP DEFAULT NOW(),
    atualizado_em               TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_vistorias_placa     ON vistorias(placa);
CREATE INDEX idx_vistorias_cliente   ON vistorias(cliente_id);
CREATE INDEX idx_vistorias_servico   ON vistorias(tipo_servico_padronizado);
CREATE INDEX idx_vistorias_data      ON vistorias(data_vistoria);
CREATE INDEX idx_vistorias_status    ON vistorias(status_laudo);
CREATE INDEX idx_vistorias_relatorio ON vistorias(nr_relatorio);

-- ============================================================
-- PAN
-- ============================================================

CREATE TABLE pan_demandas (
    id                  SERIAL PRIMARY KEY,
    placa               VARCHAR(10),
    chassi              VARCHAR(25),
    leiloeiro           VARCHAR(100),
    patio               VARCHAR(150),
    despachante         VARCHAR(100),
    nr_contrato         BIGINT,
    cpf_cnpj            BIGINT,
    veiculo_retomado    VARCHAR(30),
    criado_em_pan       TIMESTAMP,
    atualizado_em_pan   TIMESTAMP,
    passo               VARCHAR(80),
    uf_proprietario     CHAR(2),
    grupo_atribuicao    VARCHAR(100),
    modelo              VARCHAR(100),
    estado              VARCHAR(20),
    comentarios         TEXT,
    ultimo_comentario   TEXT,
    molicar             VARCHAR(50),
    vistoria_id         INT REFERENCES vistorias(id),
    importado_em        TIMESTAMP DEFAULT NOW()
);

CREATE TABLE pan_pagamentos (
    id                  SERIAL PRIMARY KEY,
    vistoria_id         INT REFERENCES vistorias(id),
    id_externo          BIGINT,
    patio               VARCHAR(150),
    data_vistoria       DATE,
    tipo_laudo          VARCHAR(50),
    placa               VARCHAR(10),
    chassi              VARCHAR(25),
    modelo              VARCHAR(100),
    nr_laudo_ecv        VARCHAR(50),
    nr_contrato         BIGINT,
    cpf                 BIGINT,
    qt_char_contrato    INT,
    qt_char_cpf         INT,
    data_solicitacao    DATE,
    status_oc           VARCHAR(20),
    cod_req             VARCHAR(30),
    cod_ritm            VARCHAR(30),
    qtde_descricao      VARCHAR(30),
    tipo_servico        VARCHAR(50),
    valor_solicitado    NUMERIC(12,2),
    valor_aprovado      NUMERIC(12,2),
    diferenca           NUMERIC(12,2),
    id_pagamento        BIGINT,
    data_aprovacao      DATE,
    data_pagamento      DATE,
    obs_parcial         TEXT,
    mes_referencia      VARCHAR(20),
    mes_importacao      VARCHAR(20),
    status_final        VARCHAR(20)
);

-- ============================================================
-- FATURAMENTO
-- ============================================================

CREATE TABLE controle_faturas (
    id                      SERIAL PRIMARY KEY,
    cliente_id              INT NOT NULL REFERENCES clientes(id),
    tipo_fatura             VARCHAR(50) NOT NULL,
    ultimo_nr_relatorio     INT DEFAULT 0,
    proximo_nr_relatorio    INT DEFAULT 1,
    data_ultima_fatura      DATE,
    valor_total_ultima      NUMERIC(12,2),
    obs                     TEXT,
    UNIQUE (cliente_id, tipo_fatura)
);

CREATE TABLE lotes_faturamento (
    id              SERIAL PRIMARY KEY,
    referencia      VARCHAR(20) NOT NULL,
    nome_lote       VARCHAR(80) NOT NULL UNIQUE,
    cliente_id      INT REFERENCES clientes(id),
    numero_lote     SMALLINT,
    tipo            VARCHAR(30),
    hist_lanc       VARCHAR(10),
    nr_relatorio    INT,
    qtde_itens      INT DEFAULT 0,
    valor_total     NUMERIC(12,2) DEFAULT 0,
    caminho_txt     TEXT,
    gerado_em       TIMESTAMP DEFAULT NOW(),
    gerado_por      VARCHAR(100)
);

CREATE TABLE lote_itens (
    id                      SERIAL PRIMARY KEY,
    lote_id                 INT NOT NULL REFERENCES lotes_faturamento(id),
    vistoria_id             INT REFERENCES vistorias(id),
    nr_vistoria             BIGINT,
    nr_vistoria_ecv         BIGINT,
    nr_vistoria_avaliacao   BIGINT,
    placa                   VARCHAR(10),
    contrato                VARCHAR(30),
    cpf_cnpj                VARCHAR(20),
    data_lancamento         DATE,
    valor_lancamento        NUMERIC(10,2),
    seq_no_lote             INT,
    status_pdf              VARCHAR(80),
    nome_arquivo_pdf        VARCHAR(200),
    link_pdf                TEXT
);

CREATE TABLE controle_pan (
    id                  SERIAL PRIMARY KEY,
    lote_id             INT REFERENCES lotes_faturamento(id),
    req                 VARCHAR(30),
    ritm                VARCHAR(30),
    nr_relatorio        INT,
    tipo_servico        VARCHAR(50),
    qtde                INT,
    valor_solicitado    NUMERIC(12,2),
    valor_aprovado      NUMERIC(12,2),
    diferenca           NUMERIC(12,2),
    nf                  VARCHAR(30),
    obs                 TEXT,
    registrado_em       TIMESTAMP DEFAULT NOW()
);

CREATE TABLE pdfs_pendentes (
    id                  SERIAL PRIMARY KEY,
    lote_item_id        INT REFERENCES lote_itens(id),
    nome_lote           VARCHAR(80),
    nr_relatorio        INT,
    seq                 INT,
    nr_vistoria         BIGINT,
    placa               VARCHAR(10),
    tipo_servico        VARCHAR(50),
    nome_arquivo_pdf    VARCHAR(200),
    link_pdf            TEXT,
    status              VARCHAR(20) DEFAULT 'PENDENTE',
    tentativas          INT DEFAULT 0,
    atualizado_em       TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- FINANCEIRO
-- ============================================================

CREATE TABLE recebimentos (
    id              SERIAL PRIMARY KEY,
    data_emissao    DATE,
    cliente_id      INT REFERENCES clientes(id),
    nr_relatorio    INT,
    valor           NUMERIC(12,2),
    nf              VARCHAR(30),
    data_pagamento  DATE,
    recebido        BOOLEAN DEFAULT false,
    obs             TEXT,
    criado_em       TIMESTAMP DEFAULT NOW()
);

CREATE TABLE custos (
    id                  SERIAL PRIMARY KEY,
    tipo                VARCHAR(50),
    categoria           VARCHAR(80),
    fixo_variavel       VARCHAR(10),
    descricao           TEXT,
    conta_corrente      VARCHAR(60),
    data                DATE,
    valor               NUMERIC(12,2),
    categoria_interna   VARCHAR(60),
    tipo_contrato       VARCHAR(10),
    clientes_alvo       TEXT,
    uf                  CHAR(2),
    rateio              VARCHAR(80),
    bloco_rateio        VARCHAR(80),
    chave_rateio        VARCHAR(100),
    uf_destino          CHAR(2),
    obs                 TEXT,
    criado_em           TIMESTAMP DEFAULT NOW()
);

CREATE TABLE dre_filial (
    id          SERIAL PRIMARY KEY,
    mes_ref     DATE,
    indicador   VARCHAR(100),
    consolidado NUMERIC(14,2),
    por_uf      JSONB,
    criado_em   TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- SISTEMA
-- ============================================================

CREATE TABLE audit_log (
    id          SERIAL PRIMARY KEY,
    acao        VARCHAR(80),
    usuario     VARCHAR(100),
    dados       JSONB,
    ip          VARCHAR(45),
    criado_em   TIMESTAMP DEFAULT NOW()
);
```

---

## 12. Ordem de Desenvolvimento

### Fase 1 — Fundação (semanas 1–2)
1. Configurar ambiente PHP + PostgreSQL
2. Executar `schema.sql`
3. Seeds: `clientes`, `vistoriadores`, `tabela_precos`
4. `Database.php` (PDO singleton)
5. `StringHelper::normalizar()`, `FormatHelper::converterData()`, `FormatHelper::converterValor()`
6. Layout base (header, sidebar, footer)
7. Tela de login com sessão PHP

### Fase 2 — Ingestão e Sync (semanas 3–4)
1. Configurar fluxo n8n: lê XLSX → INSERT em `carvist_raw`
2. Testar ON CONFLICT com reenvio do mesmo arquivo
3. `SyncService` — lê `carvist_raw` onde `sincronizado = false`
4. Lógica de match de cliente e vistoriador
5. INSERT/UPDATE em `vistorias` com preservação de `nr_relatorio`
6. Painel `/sync` com contadores e botão "Sincronizar"
7. Tela de vistoriadores sem match (para resolução manual)

### Fase 3 — Vistorias (semana 5)
1. `VistoriaModel` com filtros
2. Tela de listagem com filtros e paginação
3. Tela de detalhe
4. Exportação XLSX

### Fase 4 — Faturamento PAN (semanas 6–7)
1. `LotePanService` — separação, divisão, ordenação por placa
2. `TxtGenerator` — arquivo TXT bancário
3. `PdfDownloadService` — cURL para ECVs
4. Tela de preview dos lotes
5. Confirmação com transação (SELECT FOR UPDATE + UPDATE controle_faturas)
6. Download do TXT + painel de PDFs pendentes

### Fase 5 — Faturamento Geral (semana 8)
1. LM, UNIDAS, SAFRA COMBO, DR.DOC

### Fase 6 — PDFs e Cron (semana 9)
1. Tela de PDFs pendentes
2. Retry manual e em lote
3. `cron/retry_pdfs.php`

### Fase 7 — Cadastros e Financeiro (semanas 10–11)
1. CRUD clientes e vistoriadores
2. Tabela de preços editável
3. SLA por cliente
4. Custos, recebimentos, DRE

### Fase 8 — Polimento (semana 12)
1. Dashboard com KPIs
2. Audit log
3. Histórico de lotes e sync
4. Testes de ponta a ponta

---

## 13. Pontos de Atenção

### ⚠ n8n nunca aplica lógica de negócio
O n8n é apenas um transportador. Se houver problema de dados, a causa está sempre na planilha ou no `SyncService`, nunca no fluxo n8n.

### ⚠ carvist_raw é o dado de verdade
Se houver divergência entre `carvist_raw` e `vistorias`, `carvist_raw` prevalece. O `SyncService` pode ser reexecutado a qualquer momento para reprocessar.

### ⚠ sincronizado = false não apaga dados de faturamento
O campo `sincronizado` indica apenas que o dado bruto mudou. O `SyncService` nunca apaga `nr_relatorio`, `lote_itens` ou qualquer dado gerado pelo faturamento.

### ⚠ Geração de lotes é irreversível
Após gerar os lotes e atualizar `controle_faturas`, o desfazer é complexo. Implementar uma tela de "cancelar último lote" antes de ir para produção.

### ⚠ PDFs de avaliação PAN
O servidor VistoriaGo pode retornar HTML para scrapers. Preveja download manual como fallback.

### ⚠ Transação obrigatória na numeração
`SELECT FOR UPDATE` em `controle_faturas` antes de gerar lotes para evitar race condition.

### ⚠ SAFRA COMBO — duplo laudo
Um veículo pode ter ECV e Avaliação vinculados. `lote_itens` tem `nr_vistoria_ecv` e `nr_vistoria_avaliacao` para esse caso.

### ⚠ Vistoriadores sem match
Na primeira carga, provavelmente haverá vistoriadores novos não cadastrados. O painel de Sync deve mostrar claramente quantos registros estão com `vistoriador_id = NULL` para ação imediata.

### ⚠ Valores monetários no TXT PAN
Sempre inteiros em centavos. `round(valor * 100)`. Nunca floats diretamente.
