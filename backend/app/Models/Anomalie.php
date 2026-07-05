<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Anomalie extends Model
{
    use HasFactory;

    protected $fillable = [
        'type', 'passeport_id', 'lot_id', 'description',
        'statut', 'signale_by', 'resolu_by', 'resolu_at',
    ];

    protected function casts(): array
    {
        return ['resolu_at' => 'datetime'];
    }

    public function passeport()
    {
        return $this->belongsTo(Passeport::class);
    }

    public function lot()
    {
        return $this->belongsTo(Lot::class);
    }

    public function signalePar()
    {
        return $this->belongsTo(User::class, 'signale_by');
    }

    public function resoluPar()
    {
        return $this->belongsTo(User::class, 'resolu_by');
    }

    public function scopeOuvert($query)
    {
        return $query->where('statut', 'ouvert');
    }
}
