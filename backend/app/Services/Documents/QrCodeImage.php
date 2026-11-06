<?php

namespace App\Services\Documents;

use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class QrCodeImage
{
    /**
     * A PNG data URI, which dompdf embeds without fetching anything.
     */
    public static function dataUri(string $text): string
    {
        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64' => true,
            'scale' => 6,
        ]);

        return (new QRCode($options))->render($text);
    }
}
