<?php

namespace App\Helpers;

use DateTime;

class FormatHelper
{
    /**
     * Converte data da planilha (dd/mm/yyyy) para formato do banco (yyyy-mm-dd).
     * RN-03: Conversão de datas
     * 
     * @param string|null $v
     * @return string|null
     */
    public static function converterData(?string $v): ?string
    {
        if (!$v || trim($v) === '') return null;
        
        $d = DateTime::createFromFormat('d/m/Y', trim($v));
        return $d ? $d->format('Y-m-d') : null;
    }

    /**
     * Converte valor monetário da planilha (R$ 96,50) para float (96.50).
     * RN-04: Conversão de valores monetários
     * 
     * @param string|null $v
     * @return float|null
     */
    public static function converterValor(?string $v): ?float
    {
        if (!$v || trim($v) === '') return null;
        
        // Remove "R$", espaços e pontos de milhar
        $s = preg_replace('/[R$\s\.]/', '', $v);
        // Substitui vírgula decimal por ponto
        $s = str_replace(',', '.', $s);
        
        return is_numeric($s) ? (float)$s : null;
    }

    /**
     * Converte valor para centavos (inteiro).
     * RN-05: Valores no TXT PAN são em centavos
     * 
     * @param float $valor
     * @return int
     */
    public static function paraCentavos(float $valor): int
    {
        return (int) round($valor * 100);
    }

    /**
     * Corrige o número de contrato para o padrão Banco PAN.
     * RN-09: Correção do número de contrato PAN
     * 
     * @param string $v
     * @return string
     */
    public static function corrigirContratoPan(string $v): string
    {
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
}
