<?php

namespace App\Actions\General;

use App\Models\Manager\CurrencyModel;

class MoneyAction
{
    public static function format(
        float|int $amount = 0,
        int|string|null $decimalPlaces = null,
        ?string $currency = null, bool $formatWithSymbol = false): ?string
    {

        // Passo 1: Organiza o número considerando as casas decimais
        $amountAsString = (string) $amount;

        if (isset($currency)) {

            $currencyGet = self::notSearchInDBThisCurrencys($currency);

            // se n tiver, procura na db
            if (! $currencyGet) {
                $currencyGet = CurrencyModel::where('code', $currency)->first();
                $decimalPlaces = $currencyGet->decimal_separator;

            } else {
                $decimalPlaces = $currencyGet->decimal_separator;
            }

        }

        // Adiciona ponto antes das últimas casas decimais
        $amountAsString = substr($amountAsString, 0, -$decimalPlaces).'.'.substr($amountAsString, -$decimalPlaces);

        // Passo 2: Formata o número com separador de milhar (ponto) e decimal (vírgula)
        $formattedAmount = number_format((float) $amountAsString, $decimalPlaces, ',', '.');

        if ($formatWithSymbol == true && ! empty($currency)) {

            $currencyGet = self::notSearchInDBThisCurrencys($currency);
            // se n tiver, procura na db
            if (! $currencyGet) {
                $currencyGet = CurrencyModel::where('code', $currency)->first();
                $formattedAmount = $currencyGet->symbol.' '.$formattedAmount;

            } else {
                $formattedAmount = $currencyGet->symbol.' '.$formattedAmount;
            }

        }

        $formattedAmount = strtoupper($formattedAmount);

        return $formattedAmount;
    }

    // Método para remover caracteres não numéricos (como pontos, vírgulas) de um valor monetário
    public static function sanitize(string $amount): int
    {
        // Remove todos os caracteres não numéricos, incluindo espaços, vírgulas e pontos
        $sanitizedAmount = preg_replace('/[^0-9]/', '', $amount);

        if (empty($sanitizedAmount)) {
            $sanitizedAmount = 0;
        }

        // Retorna o valor sanitizado como número inteiro
        return (int) $sanitizedAmount;
    }

    private static function notSearchInDBThisCurrencys(?string $currency = null)
    {

        $currency = strtolower($currency);

        $array = [
            'eur' => (object) [
                'code' => 'eur',
                'symbol' => '€',
                'symbol_2' => 'EUR',
                'decimal_separator' => 2,
            ],
            'usd' => (object) [
                'code' => 'usd',
                'symbol' => '$',
                'symbol_2' => 'USD',
                'decimal_separator' => 2,
            ],
            'brl' => (object) [
                'code' => 'brl',
                'symbol' => 'R$',
                'symbol_2' => 'BRL',
                'decimal_separator' => 2,
            ],
            'aoa' => (object) [
                'code' => 'aoa',
                'symbol' => 'Kz',
                'symbol_2' => 'AOA',
                'decimal_separator' => 2,
            ],
        ];

        if (isset($array[$currency])) {
            return $array[$currency];
        } else {
            return false;
        }
    }

    /**
     * Darken a hex color by a percentage
     */
    public static function darkenColor(string $hexColor, int $percent): string
    {
        // Remove # if present
        $hexColor = ltrim($hexColor, '#');

        // Ensure we have a valid hex color
        if (strlen($hexColor) !== 6 || !ctype_xdigit($hexColor)) {
            return '#1e40af'; // Default dark blue
        }

        // Convert to RGB
        $r = hexdec(substr($hexColor, 0, 2));
        $g = hexdec(substr($hexColor, 2, 2));
        $b = hexdec(substr($hexColor, 4, 2));

        // Darken by percentage
        $r = max(0, $r - ($r * $percent / 100));
        $g = max(0, $g - ($g * $percent / 100));
        $b = max(0, $b - ($b * $percent / 100));

        // Convert back to hex
        return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT) .
                    str_pad(dechex($g), 2, '0', STR_PAD_LEFT) .
                    str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
    }
}
