<?php

namespace App\Models;

use App\Enums\InvoiceType;
use Database\Factories\InvoiceSequenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Eloquent has no composite keys, so the second key column is added here.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    protected function setKeysForSelectQuery($query): Builder
    {
        return $query->where('year', $this->year)->where('type', $this->type);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    protected function setKeysForSaveQuery($query): Builder
    {
        return $this->setKeysForSelectQuery($query);
    }
}
