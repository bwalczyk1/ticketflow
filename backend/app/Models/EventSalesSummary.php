<?php

namespace App\Models;

use Database\Factories\EventSalesSummaryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(key: 'event_id', incrementing: false)]
#[Fillable(['event_id', 'tickets_sold', 'revenue', 'refunds', 'checked_in'])]
class EventSalesSummary extends Model
{
    /** @use HasFactory<EventSalesSummaryFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
