<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    protected $table = 'notifications';

    public $timestamps = false;

    protected $fillable = [
        'type', 'channel', 'notifiable_type', 'notifiable_id',
        'title', 'message', 'data',
        'read_at', 'sent_at', 'failed_at', 'error_msg',
    ];

    protected function casts(): array
    {
        return [
            'data'       => 'array',
            'read_at'    => 'datetime',
            'sent_at'    => 'datetime',
            'failed_at'  => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function notifiable()
    {
        return $this->morphTo();
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('notifiable_type', 'user')
                     ->where('notifiable_id', $userId);
    }

    public function markAsRead(): void
    {
        if (is_null($this->read_at)) {
            $this->update(['read_at' => now()]);
        }
    }

    public static function notifyUser(User $user, string $type, string $title, string $message, array $data = [], string $channel = 'in_app'): static
    {
        return static::create([
            'type'            => $type,
            'channel'         => $channel,
            'notifiable_type' => 'user',
            'notifiable_id'   => $user->id,
            'title'           => $title,
            'message'         => $message,
            'data'            => $data ?: null,
            'sent_at'         => now(),
        ]);
    }
}
