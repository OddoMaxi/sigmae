<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'passeport_id', 'recipient', 'sujet', 'statut', 'error_msg', 'sent_at',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function passeport()
    {
        return $this->belongsTo(Passeport::class);
    }
}
