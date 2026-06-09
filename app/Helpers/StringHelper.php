<?php

namespace App\Helpers;

class StringHelper
{
    /**
     * Normaliza uma string: remove acentos, converte para maiúsculas e remove espaços extras.
     * RN-02: Normalização de strings
     * 
     * @param string $v
     * @return string
     */
    public static function normalizar(string $v): string
    {
        // Remove acentos usando iconv
        $v = iconv('UTF-8', 'ASCII//TRANSLIT', $v);
        
        // Remove caracteres não alfanuméricos básicos se necessário, 
        // mas o preg_replace abaixo foca em espaços extras.
        $v = preg_replace('/\s+/', ' ', $v);
        
        return strtoupper(trim($v));
    }

    /**
     * Limpa caracteres não numéricos de uma string.
     * 
     * @param string $v
     * @return string
     */
    public static function apenasNumeros(string $v): string
    {
        return preg_replace('/[^0-9]/', '', $v);
    }
}
