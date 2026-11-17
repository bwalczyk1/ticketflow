<?php

namespace App\Services\Documents;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PdfFile
{
    /**
     * A half-written file must never sit under the final name, so a retry can safely overwrite it.
     */
    public static function store(string $path, string $contents): string
    {
        $disk = Storage::disk('local');
        $temporaryPath = $path.'.'.Str::random(8).'.tmp';

        $disk->put($temporaryPath, $contents);
        $disk->move($temporaryPath, $path);

        return $path;
    }
}
