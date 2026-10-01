<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

/**
 * CLABE interbancaria: 18 digitos; el ultimo es un digito verificador que se calcula con
 * los primeros 17 (pesos 3, 7 y 1 en ciclo). Evita errores de captura que mandarian
 * una transferencia a otra cuenta.
 */
class ClabeInterbancaria implements Rule
{
    public function passes($attribute, $value): bool
    {
        $clabe = (string) $value;

        if (! preg_match('/^\d{18}$/', $clabe)) {
            return false;
        }

        $pesos = [3, 7, 1];
        $suma = 0;

        for ($i = 0; $i < 17; $i++) {
            $suma += ((int) $clabe[$i] * $pesos[$i % 3]) % 10;
        }

        return (int) $clabe[17] === (10 - ($suma % 10)) % 10;
    }

    public function message(): string
    {
        return 'La CLABE debe tener 18 dígitos y un dígito verificador correcto. Revísala.';
    }
}
