<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Ambassade extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'nom', 'pays', 'pays_id', 'ville',
        'email_contact', 'responsable', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function pays()
    {
        return $this->belongsTo(Pays::class);
    }

    public function lots()
    {
        return $this->hasMany(Lot::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getNomCompletAttribute(): string
    {
        return "{$this->nom} ({$this->pays})";
    }
}
