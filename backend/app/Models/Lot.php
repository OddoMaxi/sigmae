<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Lot extends Model
{
    use HasFactory;

    // ── Statuts ───────────────────────────────────────────────────────────────
    const STATUT_BROUILLON    = 'brouillon';
    const STATUT_VALIDE       = 'valide';
    const STATUT_EXPEDIE      = 'expedie';
    const STATUT_RECU         = 'recu';
    const STATUT_RECU_PARTIEL = 'recu_partiel';
    const STATUT_ANOMALIE     = 'anomalie';

    const STATUT_LABELS = [
        'brouillon'    => 'Brouillon',
        'valide'       => 'Validé',
        'expedie'      => 'Expédié',
        'recu'         => 'Reçu',
        'recu_partiel' => 'Reçu partiellement',
        'anomalie'     => 'Anomalie',
    ];

    const STATUT_COLORS = [
        'brouillon'    => 'gray',
        'valide'       => 'blue',
        'expedie'      => 'indigo',
        'recu'         => 'green',
        'recu_partiel' => 'orange',
        'anomalie'     => 'red',
    ];

    // Statuts qui bloquent la modification du lot
    const STATUTS_IMMUABLES = ['expedie', 'recu', 'recu_partiel'];

    protected $fillable = [
        'reference', 'ambassade_id', 'transporteur_id', 'reference_suivi', 'statut',
        'date_expedition', 'date_reception_prevue', 'date_reception_effective',
        'qr_token', 'bordereau_path', 'notes', 'commentaire_ambassade', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_expedition'           => 'date',
            'date_reception_prevue'     => 'date',
            'date_reception_effective'  => 'datetime',
        ];
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function ambassade()
    {
        return $this->belongsTo(Ambassade::class);
    }

    public function transporteur()
    {
        return $this->belongsTo(Transporteur::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function passeports()
    {
        return $this->belongsToMany(Passeport::class, 'lot_passeports')
            ->withPivot('statut_reception', 'confirme_at', 'confirme_by', 'notes')
            ->withTimestamps();
    }

    public function anomalies()
    {
        return $this->hasMany(Anomalie::class);
    }

    public function trackingEvents()
    {
        return $this->morphMany(TrackingEvent::class, 'trackable')->orderBy('created_at');
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function getStatutLabelAttribute(): string
    {
        return self::STATUT_LABELS[$this->statut] ?? $this->statut;
    }

    public function getStatutColorAttribute(): string
    {
        return self::STATUT_COLORS[$this->statut] ?? 'gray';
    }

    /**
     * Génère l'URL de suivi transporteur si un numéro de référence est renseigné.
     */
    public function getTrackingUrlAttribute(): ?string
    {
        if (! $this->reference_suivi || ! $this->transporteur?->lien_suivi) {
            return null;
        }

        return $this->transporteur->urlSuivi($this->reference_suivi);
    }

    // ── Prédicats de statut ────────────────────────────────────────────────────

    public function isBrouillon(): bool    { return $this->statut === self::STATUT_BROUILLON; }
    public function isValide(): bool       { return $this->statut === self::STATUT_VALIDE; }
    public function isExpedie(): bool      { return $this->statut === self::STATUT_EXPEDIE; }
    public function isRecu(): bool         { return in_array($this->statut, [self::STATUT_RECU, self::STATUT_RECU_PARTIEL]); }
    public function isModifiable(): bool   { return $this->isBrouillon(); }

    public function canTransitionTo(string $statut): bool
    {
        $allowed = [
            self::STATUT_BROUILLON    => [self::STATUT_VALIDE],
            self::STATUT_VALIDE       => [self::STATUT_EXPEDIE, self::STATUT_BROUILLON],
            self::STATUT_EXPEDIE      => [self::STATUT_RECU, self::STATUT_RECU_PARTIEL, self::STATUT_ANOMALIE],
            self::STATUT_RECU_PARTIEL => [self::STATUT_RECU, self::STATUT_ANOMALIE],
            self::STATUT_ANOMALIE     => [self::STATUT_RECU],
        ];

        return in_array($statut, $allowed[$this->statut] ?? []);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function scopeEnCours($query)
    {
        return $query->whereIn('statut', [self::STATUT_VALIDE, self::STATUT_EXPEDIE]);
    }

    public static function generateReference(): string
    {
        $year  = now()->year;
        $count = self::whereYear('created_at', $year)->count() + 1;

        return sprintf('LOT-%d-%04d', $year, $count);
    }
}
