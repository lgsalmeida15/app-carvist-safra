<?php

namespace App\Services;

use App\Helpers\StringHelper;
use App\Helpers\FormatHelper;
use PDO;

class SyncService
{
    private PDO $pdo;
    private array $cacheClientes = [];
    private array $cacheVistoriadores = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Carrega cadastros em memória para evitar múltiplas consultas ao banco.
     */
    private function carregarCaches(): void
    {
        // Cache de Clientes: nome_normalizado => id
        $stmt = $this->pdo->query("SELECT id, nome, nome_normalizado FROM clientes WHERE ativo = true");
        while ($row = $stmt->fetch()) {
            if ($row['nome_normalizado']) {
                $this->cacheClientes[$row['nome_normalizado']] = (int)$row['id'];
            }
            $this->cacheClientes[StringHelper::normalizar($row['nome'])] = (int)$row['id'];
        }

        // Cache de Vistoriadores: nome_sistema => id
        $stmt = $this->pdo->query("SELECT id, nome_sistema, nome_padrao FROM vistoriadores WHERE ativo = true");
        while ($row = $stmt->fetch()) {
            $this->cacheVistoriadores[trim($row['nome_sistema'])] = (int)$row['id'];
            if ($row['nome_padrao']) {
                $this->cacheVistoriadores[trim($row['nome_padrao'])] = (int)$row['id'];
            }
        }
    }

    /**
     * Executa a sincronização de carvist_raw para vistorias com alta performance.
     * 
     * @param int|null $jobId ID do job para acompanhamento de progresso
     * @return array Resumo da execução
     */
    public function executar(?int $jobId = null): array
    {
        $resumo = [
            'total' => 0,
            'processados' => 0,
            'inseridos' => 0,
            'atualizados' => 0,
            'erros' => 0,
            'sem_vistoriador' => 0,
            'detalhes' => []
        ];

        // 1. Carrega caches e busca registros pendentes
        $this->carregarCaches();
        
        $stmtRaw = $this->pdo->query("SELECT * FROM carvist_raw WHERE sincronizado = false ORDER BY nr_vistoria ASC");
        $rows = $stmtRaw->fetchAll();
        $resumo['total'] = count($rows);

        if ($jobId) {
            $this->pdo->prepare("UPDATE sync_jobs SET total = :total, status = 'RODANDO', iniciado_em = NOW() WHERE id = :id")
                      ->execute([':total' => $resumo['total'], ':id' => $jobId]);
        }

        if ($resumo['total'] === 0) {
            $this->finalizarJob($jobId, $resumo);
            return $resumo;
        }

        // 2. Prepara Statements fora do loop
        $sqlInsert = "INSERT INTO vistorias (
            nr_vistoria, sistema_origem, cliente_id, vistoriador_id,
            tipo_servico, tipo_servico_padronizado, status_laudo,
            data_vistoria, data_agendamento, data_finalizado,
            placa, chassi, marca, modelo, ano_fab, ano_modelo, cor,
            valor_faturamento, cidade, uf, link_laudo, cpf_cnpj,
            solicitante, nr_contrato, nr_requisicao,
            nr_laudo_ecv, sla_vistoriador, sla_mesa,
            vistoriador_texto, analista,
            valor_vistoria, valor_vistoriador, comissao, patio, proponente,
            atualizado_em
        ) VALUES (
            :nr_vistoria, :sistema_origem, :cliente_id, :vistoriador_id,
            :tipo_servico, :tipo_servico_padronizado, :status_laudo,
            :data_vistoria, :data_agendamento, :data_finalizado,
            :placa, :chassi, :marca, :modelo, :ano_fab, :ano_modelo, :cor,
            :valor_faturamento, :cidade, :uf, :link_laudo, :cpf_cnpj,
            :solicitante, :nr_contrato, :nr_requisicao,
            :nr_laudo_ecv, :sla_vistoriador, :sla_mesa,
            :vistoriador_texto, :analista,
            :valor_vistoria, :valor_vistoriador, :comissao, :patio, :proponente,
            NOW()
        ) ON CONFLICT (nr_vistoria) DO UPDATE SET
            status_laudo            = EXCLUDED.status_laudo,
            data_finalizado         = EXCLUDED.data_finalizado,
            link_laudo              = EXCLUDED.link_laudo,
            valor_faturamento       = EXCLUDED.valor_faturamento,
            tipo_servico_padronizado = EXCLUDED.tipo_servico_padronizado,
            atualizado_em           = NOW()
        WHERE vistorias.nr_relatorio IS NULL";

        $stmtInsert = $this->pdo->prepare($sqlInsert);
        $stmtMarkSync = $this->pdo->prepare("UPDATE carvist_raw SET sincronizado = true WHERE id = ANY(:ids)");
        $stmtUpdateJob = $this->pdo->prepare("UPDATE sync_jobs SET 
            processados = :p, inseridos = :i, atualizados = :a, erros = :e, sem_vistoriador = :s 
            WHERE id = :id");

        // 3. Processamento em Lotes (Transactions)
        $batchSize = 100;
        $currentBatchIds = [];

        foreach (array_chunk($rows, $batchSize) as $chunk) {
            $this->pdo->beginTransaction();
            $currentBatchIds = [];

            foreach ($chunk as $raw) {
                try {
                    // Normalização e Transformação
                    $tipoServicoPadronizado = StringHelper::normalizar($raw['tipo_servico'] ?? '');
                    
                    // Resolver IDs via Cache
                    $clienteId = $this->cacheClientes[StringHelper::normalizar($raw['seguradora_cliente'] ?? '')] ?? null;
                    $vistoriadorId = $this->cacheVistoriadores[trim($raw['vistoriador'] ?? '')] ?? null;

                    if ($vistoriadorId === null && !empty($raw['vistoriador'])) {
                        $resumo['sem_vistoriador']++;
                    }

                    $origem = (str_contains(strtolower($raw['link_laudo'] ?? ''), 'carvist')) ? 'CARVIST' : 'GO';

                    // Executa INSERT/UPDATE
                    $stmtInsert->execute([
                        ':nr_vistoria' => $raw['nr_vistoria'],
                        ':sistema_origem' => $origem,
                        ':cliente_id' => $clienteId,
                        ':vistoriador_id' => $vistoriadorId,
                        ':tipo_servico' => $raw['tipo_servico'],
                        ':tipo_servico_padronizado' => $tipoServicoPadronizado,
                        ':status_laudo' => $raw['status_laudo'],
                        ':data_vistoria' => FormatHelper::converterData($raw['data_vistoria']),
                        ':data_agendamento' => FormatHelper::converterData($raw['data_agendamento']),
                        ':data_finalizado' => FormatHelper::converterData($raw['data_finalizado']),
                        ':placa' => $raw['placa'],
                        ':chassi' => $raw['chassi'],
                        ':marca' => $raw['marca'],
                        ':modelo' => $raw['modelo'],
                        ':ano_fab' => is_numeric($raw['ano_fab']) ? (int)$raw['ano_fab'] : null,
                        ':ano_modelo' => is_numeric($raw['ano_modelo']) ? (int)$raw['ano_modelo'] : null,
                        ':cor' => $raw['cor'],
                        ':valor_faturamento' => FormatHelper::converterValor($raw['valor']),
                        ':cidade' => $raw['cidade'],
                        ':uf' => $raw['uf'],
                        ':link_laudo' => $raw['link_laudo'],
                        ':cpf_cnpj' => $raw['cpf_cnpj'],
                        ':solicitante' => $raw['solicitante'],
                        ':nr_contrato' => $raw['nr_contrato'],
                        ':nr_requisicao' => $raw['nr_requisicao'],
                        ':nr_laudo_ecv' => $raw['nr_laudo_ecv'],
                        ':sla_vistoriador' => is_numeric($raw['sla_vistoriador']) ? (int)$raw['sla_vistoriador'] : null,
                        ':sla_mesa' => is_numeric($raw['sla_mesa']) ? (int)$raw['sla_mesa'] : null,
                        ':vistoriador_texto' => $raw['vistoriador'],
                        ':analista' => $raw['analista'],
                        ':valor_vistoria' => FormatHelper::converterValor($raw['valor_vistoria']),
                        ':valor_vistoriador' => FormatHelper::converterValor($raw['valor_vistoriador']),
                        ':comissao' => FormatHelper::converterValor($raw['comissao']),
                        ':patio' => $raw['patio'],
                        ':proponente' => $raw['proponente']
                    ]);

                    if ($stmtInsert->rowCount() > 0) {
                        $resumo['inseridos']++;
                    } else {
                        $resumo['atualizados']++;
                    }

                    $resumo['processados']++;
                    $currentBatchIds[] = $raw['id'];

                } catch (\Exception $e) {
                    $resumo['erros']++;
                    $resumo['processados']++;
                    $resumo['detalhes'][] = "Erro na vistoria {$raw['nr_vistoria']}: " . $e->getMessage();
                }
            }

            // 4. Finaliza o lote no banco
            if (!empty($currentBatchIds)) {
                // Converte array PHP para formato de array do PostgreSQL: {1,2,3}
                $pgArray = '{' . implode(',', $currentBatchIds) . '}';
                $stmtMarkSync->execute([':ids' => $pgArray]);
            }

            $this->pdo->commit();

            // Atualiza progresso do job ao final de cada lote
            if ($jobId) {
                $stmtUpdateJob->execute([
                    ':p' => $resumo['processados'],
                    ':i' => $resumo['inseridos'],
                    ':a' => $resumo['atualizados'],
                    ':e' => $resumo['erros'],
                    ':s' => $resumo['sem_vistoriador'],
                    ':id' => $jobId
                ]);
            }
        }

        $this->finalizarJob($jobId, $resumo);
        return $resumo;
    }

    private function finalizarJob(?int $jobId, array $resumo): void
    {
        // Grava log final
        $this->pdo->prepare("INSERT INTO sync_log (total_lidos, inseridos, atualizados, erros, sem_vistoriador, log_json) VALUES (?, ?, ?, ?, ?, ?)")
                  ->execute([
                      $resumo['total'],
                      $resumo['inseridos'],
                      $resumo['atualizados'],
                      $resumo['erros'],
                      $resumo['sem_vistoriador'],
                      json_encode($resumo['detalhes'])
                  ]);

        if ($jobId) {
            $this->pdo->prepare("UPDATE sync_jobs SET 
                status = 'CONCLUIDO', 
                concluido_em = NOW(),
                log_json = :log
                WHERE id = :id")
            ->execute([
                ':log' => json_encode($resumo['detalhes']),
                ':id' => $jobId
            ]);
        }
    }
}
