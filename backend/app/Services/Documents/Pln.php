<?php

namespace App\Services\Documents;

class Pln
{
    /**
     * Money is stored as integer grosze; only documents turn it into text.
     */
    public static function format(int $grosze): string
    {
        $sign = $grosze < 0 ? '-' : '';

        return $sign.number_format(abs($grosze) / 100, 2, ',', ' ').' zł';
    }
}
