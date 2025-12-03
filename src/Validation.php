<?php

namespace BinanceAPI;

class Validation
{
    /**
     * Verifica campos obrigatórios; retorna mensagem de erro ou null se ok.
     *
     * @param array<string,mixed> $data
     * @param array<int,string> $fields
     */
    public static function requireFields(array $data, array $fields): ?string
    {
        foreach ($fields as $field) {
            if (empty($data[$field])) {
                return "Parâmetro \"$field\" é obrigatório";
            }
        }

        return null;
    }
}
