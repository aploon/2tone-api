<?php

namespace App\Support;

/**
 * Met le numéro au format attendu par les SMS (E.164 simple) : + puis chiffres uniquement.
 * L’utilisateur saisit ce qu’il veut ; on ne fait pas de « pays » ni de validation métier ici.
 */
final class PhoneE164
{
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $t = trim($input);
        if ($t === '') {
            return null;
        }

        if (str_starts_with($t, '00')) {
            $t = '+'.substr($t, 2);
        }

        if (str_starts_with($t, '+')) {
            $digits = preg_replace('/\D/', '', substr($t, 1));

            return $digits !== '' ? '+'.$digits : null;
        }

        $digits = preg_replace('/\D/', '', $t);

        return $digits !== '' ? '+'.$digits : null;
    }
}
