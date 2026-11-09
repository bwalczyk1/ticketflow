<?php

namespace App\Models;

use Database\Factories\SentNotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Table(timestamps: false)]
#[Fillable(['key', 'sent_at'])]
class SentNotification extends Model
{
    /** @use HasFactory<SentNotificationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'immutable_datetime',
        ];
    }
}
