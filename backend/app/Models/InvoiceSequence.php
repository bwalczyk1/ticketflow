<?php

namespace App\Models;

use App\Enums\InvoiceType;
use Database\Factories\InvoiceSequenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Table(key: 'year', incrementing: false, timestamps: false)]
#[Fillable(['year', 'type', 'last_number'])]
class InvoiceSequence extends Model
{
    /** @use HasFactory<InvoiceSequenceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => InvoiceType::class,
        ];
    }
}
