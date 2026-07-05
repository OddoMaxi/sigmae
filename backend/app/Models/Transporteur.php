<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Transporteur extends Model
{
    use HasFactory;

    const TYPES = ['aerien', 'maritime', 'routier', 'courrier', 'autre'];

    const TYPE_LABELS = [
        'aerien'   => 'Aérien',
        'maritime' => 'Maritime',
        'routier'  => 'Routier',
        'courrier' => 'Courrier',
        'autre'    => 'Autre',
    ];

    protected $fillable = [
        'nom', 'type', 'contact', 'telephone', 'email', 'adresse',
        'pays_desservis', 'lien_suivi', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'      => 'boolean',
            'pays_desservis' => 'array',
        ];
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function lots()
    {
        return $this->hasMany(Lot::class);
    }

    // ── Accessors ────────────────────────────────────────────────────────────

    public function getTypeLabelAttribute(): ?string
    {
        return $this->type ? (self::TYPE_LABELS[$this->type] ?? $this->type) : null;
    }

    /**
     * Génère l'URL de suivi pour un numéro de tracking donné.
     * Le lien_suivi peut contenir le placeholder {numero}.
     */
    public function urlSuivi(string $numeroTracking): ?string
    {
        if (! $this->lien_suivi) {
            return null;
        }

        return str_replace('{numero}', urlencode($numeroTracking), $this->lien_suivi);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('nom',     'ilike', "%{$term}%")
              ->orWhere('contact', 'ilike', "%{$term}%")
              ->orWhere('email',   'ilike', "%{$term}%");
        });
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
