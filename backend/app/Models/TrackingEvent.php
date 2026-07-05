<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'trackable_type', 'trackable_id',
        'event', 'description', 'metadata',
        'triggered_by', 'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'metadata'   => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function trackable()
    {
        return $this->morphTo();
    }

    public function triggeredBy()
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public static function record(
        Model $target,
        string $event,
        ?string $description = null,
        array $metadata = [],
        ?User $user = null
    ): static {
        return static::create([
            'trackable_type' => $target->getMorphClass(),
            'trackable_id'   => $target->getKey(),
            'event'          => $event,
            'description'    => $description,
            'metadata'       => $metadata ?: null,
            'triggered_by'   => $user?->id ?? auth()->id(),
            'ip_address'     => request()?->ip(),
        ]);
    }
}
