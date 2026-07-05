<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // ── Constantes de rôles ────────────────────────────────────────────────────

    // Rôles nouveaux (SGP-GE v2)
    const ROLE_SUPER_ADMIN           = 'super_admin';
    const ROLE_ADMIN_CENTRAL         = 'admin_central';
    const ROLE_RESPONSABLE_CELLULE   = 'responsable_cellule';
    const ROLE_AGENT_RECEPTION       = 'agent_reception';
    const ROLE_AGENT_ENROLEMENT      = 'agent_enrolement';
    const ROLE_AGENT_IMPRESSION      = 'agent_impression';
    const ROLE_AGENT_LOGISTIQUE      = 'agent_logistique';
    const ROLE_UTILISATEUR_AMBASSADE = 'utilisateur_ambassade';
    const ROLE_AUDITEUR              = 'auditeur';

    // Rôles hérités (backward-compat)
    const ROLE_ADMIN_MAE      = 'admin_mae';
    const ROLE_GESTIONNAIRE   = 'gestionnaire';
    const ROLE_SUPERVISEUR    = 'superviseur';
    const ROLE_AGENT_AMBASSADE = 'agent_ambassade';

    // Groupes fonctionnels
    const ADMIN_ROLES    = [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN_CENTRAL, self::ROLE_ADMIN_MAE];
    const MANAGER_ROLES  = [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN_CENTRAL, self::ROLE_RESPONSABLE_CELLULE, self::ROLE_GESTIONNAIRE, self::ROLE_ADMIN_MAE];
    const LOGISTICS_ROLES = [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN_CENTRAL, self::ROLE_AGENT_LOGISTIQUE, self::ROLE_GESTIONNAIRE, self::ROLE_ADMIN_MAE];
    const EMBASSY_ROLES  = [self::ROLE_AGENT_RECEPTION, self::ROLE_AGENT_ENROLEMENT, self::ROLE_UTILISATEUR_AMBASSADE, self::ROLE_AGENT_AMBASSADE];
    const AUDIT_ROLES    = [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN_CENTRAL, self::ROLE_AUDITEUR, self::ROLE_SUPERVISEUR];

    protected $fillable = [
        'name', 'email', 'password', 'role', 'role_id',
        'ambassade_id', 'is_active', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password'      => 'hashed',
            'is_active'     => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    // ── Relations ──────────────────────────────────────────────────────────────

    public function roleModel()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function ambassade()
    {
        return $this->belongsTo(Ambassade::class);
    }

    public function appNotifications()
    {
        return $this->morphMany(AppNotification::class, 'notifiable')
                    ->orderByDesc('created_at');
    }

    // ── Permissions ────────────────────────────────────────────────────────────

    /**
     * Vérifie si l'utilisateur dispose d'une permission.
     * Charge roleModel.permissions une seule fois par instance.
     */
    public function hasPermission(string $permission): bool
    {
        if (! $this->relationLoaded('roleModel') || ! $this->roleModel?->relationLoaded('permissions')) {
            $this->load('roleModel.permissions');
        }

        return $this->roleModel?->permissions->contains('name', $permission) ?? false;
    }

    /**
     * Retourne la liste des permissions de l'utilisateur (noms).
     */
    public function getPermissionNames(): array
    {
        if (! $this->relationLoaded('roleModel') || ! $this->roleModel?->relationLoaded('permissions')) {
            $this->load('roleModel.permissions');
        }

        return $this->roleModel?->permissions->pluck('name')->all() ?? [];
    }

    // ── Helpers de rôle ────────────────────────────────────────────────────────

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isAdminCentral(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN_CENTRAL]);
    }

    public function isResponsableCellule(): bool
    {
        return in_array($this->role, self::MANAGER_ROLES);
    }

    public function isAgentLogistique(): bool
    {
        return in_array($this->role, self::LOGISTICS_ROLES);
    }

    public function isAgentReception(): bool
    {
        return $this->role === self::ROLE_AGENT_RECEPTION;
    }

    public function isUtilisateurAmbassade(): bool
    {
        return in_array($this->role, self::EMBASSY_ROLES);
    }

    public function isAuditeur(): bool
    {
        return in_array($this->role, self::AUDIT_ROLES);
    }

    /** L'utilisateur est scopé à une ambassade spécifique */
    public function isScopedToAmbassade(): bool
    {
        return in_array($this->role, self::EMBASSY_ROLES) && $this->ambassade_id !== null;
    }

    // ── Backward-compat ────────────────────────────────────────────────────────

    public function isAdmin(): bool
    {
        return in_array($this->role, self::ADMIN_ROLES);
    }

    public function isGestionnaire(): bool
    {
        return in_array($this->role, array_merge(self::MANAGER_ROLES, self::LOGISTICS_ROLES));
    }

    public function isAgentAmbassade(): bool
    {
        return $this->isUtilisateurAmbassade();
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    public function scopeScopedToAmbassade($query, int $ambassadeId)
    {
        return $query->whereIn('role', self::EMBASSY_ROLES)
                     ->where('ambassade_id', $ambassadeId);
    }
}
