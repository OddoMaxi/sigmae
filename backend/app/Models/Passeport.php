<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Validation\ValidationException;

class Passeport extends Model
{
    use HasFactory;

    // ── Statuts (slugs internes) ───────────────────────────────────────────────

    const STATUT_ENROLEE            = 'enrolee';
    const STATUT_IMPRIME            = 'imprime';
    const STATUT_RECU_MAE           = 'recu_mae';
    const STATUT_EN_STOCK           = 'en_stock';
    const STATUT_EN_LOT             = 'en_lot';
    const STATUT_EXPEDIE            = 'expedie';
    const STATUT_EN_TRANSIT         = 'en_transit';
    const STATUT_RECU_AMBASSADE     = 'recu_ambassade';
    const STATUT_DISPONIBLE_RETRAIT = 'disponible_retrait';
    const STATUT_REMIS_CITOYEN      = 'remis_citoyen';
    const STATUT_ANOMALIE           = 'anomalie';

    /** Libellés affichés (API) */
    const STATUT_LABELS = [
        'enrolee'            => 'ENRÔLÉ',
        'imprime'            => 'IMPRIMÉ',
        'recu_mae'           => 'REÇU_MAE',
        'en_stock'           => 'EN_STOCK',
        'en_lot'             => 'EN_LOT',
        'expedie'            => 'EXPÉDIÉ',
        'en_transit'         => 'EN_TRANSIT',
        'recu_ambassade'     => 'REÇU_AMBASSADE',
        'disponible_retrait' => 'DISPONIBLE_RETRAIT',
        'remis_citoyen'      => 'REMIS_AU_CITOYEN',
        'anomalie'           => 'ANOMALIE',
        'livre'              => 'REMIS_AU_CITOYEN', // backward-compat
    ];

    /** Couleur badge frontend */
    const STATUT_COLORS = [
        'enrolee'            => 'violet',
        'imprime'            => 'gray',
        'recu_mae'           => 'blue',
        'en_stock'           => 'indigo',
        'en_lot'             => 'yellow',
        'expedie'            => 'orange',
        'en_transit'         => 'purple',
        'recu_ambassade'     => 'teal',
        'disponible_retrait' => 'green',
        'remis_citoyen'      => 'emerald',
        'anomalie'           => 'red',
        'livre'              => 'emerald',
    ];

    /**
     * Transitions autorisées : statut_actuel → [statuts_suivants_possibles]
     *
     * Les transitions via lot (en_lot→expedie, etc.) sont déclenchées
     * par les controllers Lot/Reception et ne passent pas par transitionTo().
     */
    const TRANSITIONS = [
        'enrolee'            => ['imprime'],  // MAE assigne le numéro → imprime
        'imprime'            => ['recu_mae'],
        'recu_mae'           => ['en_stock'],
        'en_stock'           => ['en_lot'],
        'en_lot'             => ['en_stock', 'expedie'],
        // expedie → recu_ambassade autorisé directement (lots sans suivi intermédiaire)
        'expedie'            => ['en_transit', 'recu_ambassade', 'anomalie'],
        'en_transit'         => ['recu_ambassade', 'anomalie'],
        'recu_ambassade'     => ['disponible_retrait', 'anomalie'],
        'disponible_retrait' => ['remis_citoyen', 'anomalie'],
        'remis_citoyen'      => [],
        'anomalie'           => ['en_stock', 'recu_ambassade', 'disponible_retrait'],
        'livre'              => [],
    ];

    // ── Colonnes ──────────────────────────────────────────────────────────────

    protected $fillable = [
        'numero', 'reference_demande',
        'nom_titulaire', 'prenom_titulaire', 'date_naissance',
        'email_citoyen', 'telephone',
        'pays_destination_id', 'ambassade_destination_id',
        'date_impression', 'date_reception_mae',
        'statut', 'lot_id',
        'enrolled_at', 'enrolled_by',
        'received_at', 'dispatched_at', 'disponible_at', 'delivered_at', 'email_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'date_naissance'    => 'date',
            'date_impression'   => 'date',
            'date_reception_mae'=> 'date',
            'enrolled_at'       => 'datetime',
            'received_at'       => 'datetime',
            'dispatched_at'     => 'datetime',
            'disponible_at'     => 'datetime',
            'delivered_at'      => 'datetime',
            'email_sent_at'     => 'datetime',
        ];
    }

    // ── Accesseurs ────────────────────────────────────────────────────────────

    public function getNomCompletAttribute(): string
    {
        return "{$this->prenom_titulaire} {$this->nom_titulaire}";
    }

    public function getStatutLabelAttribute(): string
    {
        return self::STATUT_LABELS[$this->statut] ?? strtoupper($this->statut);
    }

    public function getStatutColorAttribute(): string
    {
        return self::STATUT_COLORS[$this->statut] ?? 'gray';
    }

    // ── Machine d'état ────────────────────────────────────────────────────────

    public function canTransitionTo(string $newStatut): bool
    {
        $allowed = self::TRANSITIONS[$this->statut] ?? [];
        return in_array($newStatut, $allowed);
    }

    /**
     * Effectue la transition et enregistre l'événement de suivi.
     *
     * @throws ValidationException si la transition est interdite
     */
    public function transitionTo(string $newStatut, ?string $description = null, array $meta = []): void
    {
        if (! $this->canTransitionTo($newStatut)) {
            throw ValidationException::withMessages([
                'statut' => "Transition interdite : {$this->statut_label} → " . (self::STATUT_LABELS[$newStatut] ?? $newStatut),
            ]);
        }

        $old = $this->statut;
        $this->update(['statut' => $newStatut]);

        TrackingEvent::record(
            $this,
            "statut.{$newStatut}",
            $description ?? "Statut changé de «{$old}» à «{$newStatut}»",
            array_merge(['old_statut' => $old], $meta)
        );
    }

    // ── Helpers de statut ─────────────────────────────────────────────────────

    public function estEnrolee(): bool           { return $this->statut === self::STATUT_ENROLEE; }
    public function estImprime(): bool           { return $this->statut === self::STATUT_IMPRIME; }
    public function estRecuMAE(): bool           { return $this->statut === self::STATUT_RECU_MAE; }
    public function estEnStock(): bool           { return $this->statut === self::STATUT_EN_STOCK; }
    public function estEnLot(): bool             { return $this->statut === self::STATUT_EN_LOT; }
    public function estExpedie(): bool           { return $this->statut === self::STATUT_EXPEDIE; }
    public function estEnTransit(): bool         { return $this->statut === self::STATUT_EN_TRANSIT; }
    public function estRecuAmbassade(): bool     { return $this->statut === self::STATUT_RECU_AMBASSADE; }
    public function estDisponibleRetrait(): bool { return $this->statut === self::STATUT_DISPONIBLE_RETRAIT; }
    public function estRemisCitoyen(): bool      { return in_array($this->statut, [self::STATUT_REMIS_CITOYEN, 'livre']); }
    public function estAnomalie(): bool          { return $this->statut === self::STATUT_ANOMALIE; }

    /** Passeport modifiable (pas encore expédié ou anomalie) */
    public function estModifiable(): bool
    {
        return in_array($this->statut, [self::STATUT_ENROLEE, self::STATUT_IMPRIME, self::STATUT_RECU_MAE, self::STATUT_EN_STOCK]);
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function lot()
    {
        return $this->belongsTo(Lot::class);
    }

    public function paysDestination()
    {
        return $this->belongsTo(Pays::class, 'pays_destination_id');
    }

    public function ambassadeDestination()
    {
        return $this->belongsTo(Ambassade::class, 'ambassade_destination_id');
    }

    public function agentEnrolement()
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }

    public function anomalies()
    {
        return $this->hasMany(Anomalie::class);
    }

    public function emailLogs()
    {
        return $this->hasMany(EmailLog::class);
    }

    public function trackingEvents()
    {
        return $this->morphMany(TrackingEvent::class, 'trackable')->orderBy('created_at');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeEnStock($query)
    {
        return $query->where('statut', self::STATUT_EN_STOCK);
    }

    public function scopeDisponibles($query)
    {
        return $query->whereIn('statut', [self::STATUT_EN_STOCK, self::STATUT_IMPRIME, self::STATUT_RECU_MAE]);
    }

    public function scopePourAmbassade($query, int $ambassadeId)
    {
        return $query->where('ambassade_destination_id', $ambassadeId);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('numero', 'ilike', "%{$term}%")
              ->orWhere('reference_demande', 'ilike', "%{$term}%")
              ->orWhere('nom_titulaire', 'ilike', "%{$term}%")
              ->orWhere('prenom_titulaire', 'ilike', "%{$term}%")
              ->orWhere('email_citoyen', 'ilike', "%{$term}%");
        });
    }
}
