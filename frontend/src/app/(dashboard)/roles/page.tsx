'use client'

import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { Shield, Plus, Trash2, Save, ChevronDown, ChevronRight, Lock, Users, X } from 'lucide-react'
import toast from 'react-hot-toast'

// ── Types ─────────────────────────────────────────────────────────────────────

interface Permission {
  id: number
  name: string
  module: string
  action: string
  description?: string
}

interface Role {
  id: number
  name: string
  display_name: string
  description?: string
  is_system: boolean
  permissions_count: number
  permissions_by_module: Record<string, string[]>
}

interface PermissionsData {
  by_module: Record<string, Permission[]>
  flat: Permission[]
}

// ── Labels ────────────────────────────────────────────────────────────────────

const MODULE_LABELS: Record<string, string> = {
  enrolement:   'Enrôlement',
  passeports:   'Passeports',
  lots:         'Lots d\'expédition',
  ambassades:   'Ambassades',
  pays:         'Pays',
  transporteurs:'Transporteurs',
  anomalies:    'Anomalies',
  reporting:    'Reporting',
  audit:        'Audit',
  roles:        'Rôles & permissions',
  users:        'Utilisateurs',
}

const ACTION_LABELS: Record<string, string> = {
  view:           'Voir',
  create:         'Créer',
  update:         'Modifier',
  delete:         'Supprimer',
  import:         'Importer',
  export:         'Exporter',
  validate:       'Valider',
  ship:           'Expédier',
  receive:        'Réceptionner',
  resolve:        'Résoudre',
  manage:         'Gérer',
  toggle_status:  'Activer/Désactiver',
  assign_numero:  'Assigner numéro',
}

const ROLE_COLORS: Record<string, string> = {
  super_admin:        'bg-red-100 text-red-700 border-red-200',
  admin_mae:          'bg-purple-100 text-purple-700 border-purple-200',
  admin_central:      'bg-indigo-100 text-indigo-700 border-indigo-200',
  responsable_cellule:'bg-blue-100 text-blue-700 border-blue-200',
  gestionnaire:       'bg-cyan-100 text-cyan-700 border-cyan-200',
  superviseur:        'bg-teal-100 text-teal-700 border-teal-200',
  agent_logistique:   'bg-orange-100 text-orange-700 border-orange-200',
  agent_reception:    'bg-yellow-100 text-yellow-800 border-yellow-200',
  agent_enrolement:   'bg-violet-100 text-violet-700 border-violet-200',
  agent_impression:   'bg-gray-100 text-gray-700 border-gray-200',
  agent_ambassade:    'bg-green-100 text-green-700 border-green-200',
  utilisateur_ambassade:'bg-emerald-100 text-emerald-700 border-emerald-200',
  auditeur:           'bg-slate-100 text-slate-700 border-slate-200',
}

// ── Composant principal ────────────────────────────────────────────────────────

export default function RolesPage() {
  const qc = useQueryClient()
  const [selectedRole, setSelectedRole] = useState<Role | null>(null)
  const [showCreate,   setShowCreate]   = useState(false)
  const [dirty,        setDirty]        = useState<Set<number>>(new Set())
  const [localPerms,   setLocalPerms]   = useState<Set<number>>(new Set())

  // ── Données ────────────────────────────────────────────────────────────────

  const { data: roles = [], isLoading: rolesLoading } = useQuery<Role[]>({
    queryKey: ['roles'],
    queryFn:  () => api.get('/roles').then(r => r.data),
  })

  const { data: permData } = useQuery<PermissionsData>({
    queryKey: ['permissions'],
    queryFn:  () => api.get('/permissions').then(r => r.data),
  })

  const { data: roleDetail, isLoading: detailLoading } = useQuery({
    queryKey: ['role-detail', selectedRole?.id],
    queryFn:  () => api.get(`/roles/${selectedRole!.id}`).then(r => r.data),
    enabled:  !!selectedRole,
    onSuccess: (d: any) => {
      const ids = new Set<number>(d.permissions.map((p: Permission) => p.id))
      setLocalPerms(ids)
      setDirty(new Set())
    },
  } as any)

  // ── Mutations ──────────────────────────────────────────────────────────────

  const savePerms = useMutation({
    mutationFn: (ids: number[]) =>
      api.put(`/roles/${selectedRole!.id}/permissions`, { permission_ids: ids }),
    onSuccess: () => {
      toast.success('Permissions sauvegardées')
      setDirty(new Set())
      qc.invalidateQueries({ queryKey: ['roles'] })
      qc.invalidateQueries({ queryKey: ['role-detail', selectedRole?.id] })
    },
    onError: (e: any) => toast.error(e.response?.data?.message || 'Erreur'),
  })

  const deleteRole = useMutation({
    mutationFn: (id: number) => api.delete(`/roles/${id}`),
    onSuccess: () => {
      toast.success('Rôle supprimé')
      setSelectedRole(null)
      qc.invalidateQueries({ queryKey: ['roles'] })
    },
    onError: (e: any) => toast.error(e.response?.data?.message || 'Impossible de supprimer ce rôle'),
  })

  // ── Handlers ──────────────────────────────────────────────────────────────

  const togglePerm = (permId: number) => {
    setLocalPerms(prev => {
      const next = new Set(prev)
      next.has(permId) ? next.delete(permId) : next.add(permId)
      return next
    })
    setDirty(prev => new Set(prev).add(permId))
  }

  const toggleModule = (perms: Permission[]) => {
    const ids  = perms.map(p => p.id)
    const allOn = ids.every(id => localPerms.has(id))
    setLocalPerms(prev => {
      const next = new Set(prev)
      ids.forEach(id => allOn ? next.delete(id) : next.add(id))
      return next
    })
    setDirty(prev => { const n = new Set(prev); ids.forEach(id => n.add(id)); return n })
  }

  const selectRole = (role: Role) => {
    if (dirty.size > 0 && !confirm('Vous avez des modifications non sauvegardées. Continuer ?')) return
    setSelectedRole(role)
    setDirty(new Set())
  }

  const allPermissions: Permission[] = permData?.flat ?? []
  const byModule = permData?.by_module ?? {}

  return (
    <div className="space-y-6">
      {/* En-tête */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Rôles &amp; Permissions</h1>
          <p className="text-sm text-gray-500">Gestion des rôles et de leurs droits d'accès</p>
        </div>
        <button onClick={() => setShowCreate(true)}
          className="flex items-center gap-2 bg-[#1a5276] text-white px-4 py-2 rounded-lg text-sm hover:bg-[#154360]">
          <Plus size={15} /> Nouveau rôle
        </button>
      </div>

      <div className="flex gap-6 items-start">

        {/* ── Liste des rôles ─────────────────────────────────────────────── */}
        <div className="w-72 shrink-0 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
          <div className="px-4 py-3 border-b bg-gray-50">
            <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide">
              {roles.length} rôles configurés
            </p>
          </div>
          {rolesLoading ? (
            <div className="flex justify-center py-8">
              <div className="animate-spin rounded-full h-6 w-6 border-2 border-[#1a5276] border-t-transparent" />
            </div>
          ) : (
            <ul className="divide-y divide-gray-100">
              {roles.map(role => (
                <li key={role.id}>
                  <button
                    onClick={() => selectRole(role)}
                    className={`w-full text-left px-4 py-3 hover:bg-gray-50 transition flex items-start justify-between gap-2 ${selectedRole?.id === role.id ? 'bg-blue-50 border-l-2 border-[#1a5276]' : ''}`}
                  >
                    <div className="min-w-0">
                      <div className="flex items-center gap-1.5 flex-wrap">
                        <span className="text-sm font-medium text-gray-800 truncate">
                          {role.display_name}
                        </span>
                        {role.is_system && (
                          <span title="Rôle système"><Lock size={11} className="text-gray-400 shrink-0" /></span>
                        )}
                      </div>
                      <p className="text-xs text-gray-400 font-mono mt-0.5">{role.name}</p>
                    </div>
                    <span className={`text-xs px-2 py-0.5 rounded-full border font-medium shrink-0 ${ROLE_COLORS[role.name] ?? 'bg-gray-100 text-gray-600 border-gray-200'}`}>
                      {role.permissions_count}
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>

        {/* ── Détail / matrice permissions ────────────────────────────────── */}
        {selectedRole ? (
          <div className="flex-1 min-w-0 space-y-4">
            {/* Header rôle sélectionné */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
              <div className="flex items-start justify-between">
                <div>
                  <div className="flex items-center gap-2">
                    <Shield size={18} className="text-[#1a5276]" />
                    <h2 className="text-lg font-bold text-gray-800">{selectedRole.display_name}</h2>
                    {selectedRole.is_system && (
                      <span className="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full border border-amber-200 flex items-center gap-1">
                        <Lock size={10} /> Système
                      </span>
                    )}
                  </div>
                  <p className="text-xs text-gray-400 font-mono mt-0.5">{selectedRole.name}</p>
                  {selectedRole.description && (
                    <p className="text-sm text-gray-500 mt-1">{selectedRole.description}</p>
                  )}
                </div>
                <div className="flex gap-2">
                  {dirty.size > 0 && (
                    <button
                      onClick={() => savePerms.mutate(Array.from(localPerms))}
                      disabled={savePerms.isPending}
                      className="flex items-center gap-1.5 bg-[#1a5276] text-white px-3 py-1.5 rounded-lg text-sm hover:bg-[#154360] disabled:opacity-60">
                      <Save size={14} />
                      {savePerms.isPending ? 'Sauvegarde...' : `Sauvegarder (${dirty.size} modif.)`}
                    </button>
                  )}
                  {!selectedRole.is_system && (
                    <button
                      onClick={() => {
                        if (confirm(`Supprimer le rôle « ${selectedRole.display_name} » ?`))
                          deleteRole.mutate(selectedRole.id)
                      }}
                      className="flex items-center gap-1.5 border border-red-200 text-red-600 px-3 py-1.5 rounded-lg text-sm hover:bg-red-50">
                      <Trash2 size={14} /> Supprimer
                    </button>
                  )}
                </div>
              </div>
            </div>

            {/* Matrice permissions */}
            {detailLoading ? (
              <div className="flex justify-center py-8">
                <div className="animate-spin rounded-full h-6 w-6 border-2 border-[#1a5276] border-t-transparent" />
              </div>
            ) : (
              <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div className="px-5 py-3 border-b bg-gray-50 flex items-center justify-between">
                  <p className="text-sm font-semibold text-gray-700">Permissions par module</p>
                  <p className="text-xs text-gray-400">
                    {localPerms.size} / {allPermissions.length} permissions activées
                  </p>
                </div>

                <div className="divide-y divide-gray-100">
                  {Object.entries(byModule).map(([module, perms]) => {
                    const ids   = perms.map(p => p.id)
                    const total = ids.length
                    const active= ids.filter(id => localPerms.has(id)).length
                    const allOn = active === total
                    const someOn= active > 0 && !allOn

                    return (
                      <div key={module} className="px-5 py-4">
                        {/* En-tête module */}
                        <div className="flex items-center justify-between mb-3">
                          <div className="flex items-center gap-2">
                            <button
                              onClick={() => toggleModule(perms)}
                              className={`w-4 h-4 rounded border flex items-center justify-center transition ${
                                allOn  ? 'bg-[#1a5276] border-[#1a5276]' :
                                someOn ? 'bg-[#1a5276]/30 border-[#1a5276]' :
                                         'border-gray-300 hover:border-[#1a5276]'
                              }`}
                            >
                              {(allOn || someOn) && (
                                <svg viewBox="0 0 10 10" className="w-2.5 h-2.5 text-white fill-current">
                                  {allOn
                                    ? <path d="M1.5 5.5L4 8l4.5-5" stroke="currentColor" strokeWidth="1.5" fill="none" />
                                    : <rect x="1.5" y="4.25" width="7" height="1.5" />
                                  }
                                </svg>
                              )}
                            </button>
                            <span className="text-sm font-semibold text-gray-700">
                              {MODULE_LABELS[module] ?? module}
                            </span>
                          </div>
                          <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${active > 0 ? 'bg-blue-50 text-blue-700' : 'bg-gray-100 text-gray-400'}`}>
                            {active}/{total}
                          </span>
                        </div>

                        {/* Cases à cocher des actions */}
                        <div className="flex flex-wrap gap-2 ml-6">
                          {perms.map(perm => (
                            <label key={perm.id}
                              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg border cursor-pointer text-sm transition select-none ${
                                localPerms.has(perm.id)
                                  ? 'bg-[#1a5276] border-[#1a5276] text-white'
                                  : 'border-gray-200 text-gray-600 hover:border-[#1a5276] hover:bg-blue-50'
                              }`}
                            >
                              <input
                                type="checkbox"
                                className="sr-only"
                                checked={localPerms.has(perm.id)}
                                onChange={() => togglePerm(perm.id)}
                              />
                              {ACTION_LABELS[perm.action] ?? perm.action}
                            </label>
                          ))}
                        </div>
                      </div>
                    )
                  })}
                </div>
              </div>
            )}
          </div>
        ) : (
          <div className="flex-1 flex items-center justify-center h-64 bg-white rounded-xl border border-gray-100 border-dashed">
            <div className="text-center">
              <Shield size={40} className="mx-auto text-gray-200 mb-3" />
              <p className="text-gray-400 text-sm">Sélectionnez un rôle pour gérer ses permissions</p>
            </div>
          </div>
        )}
      </div>

      {/* ── Modal création rôle ──────────────────────────────────────────────── */}
      {showCreate && (
        <CreateRoleModal
          permissions={byModule}
          onClose={() => setShowCreate(false)}
          onCreated={() => {
            setShowCreate(false)
            qc.invalidateQueries({ queryKey: ['roles'] })
          }}
        />
      )}
    </div>
  )
}

// ── Modal création ─────────────────────────────────────────────────────────────

function CreateRoleModal({
  permissions,
  onClose,
  onCreated,
}: {
  permissions: Record<string, Permission[]>
  onClose: () => void
  onCreated: () => void
}) {
  const [name,        setName]        = useState('')
  const [displayName, setDisplayName] = useState('')
  const [description, setDescription] = useState('')
  const [selected,    setSelected]    = useState<Set<number>>(new Set())

  const create = useMutation({
    mutationFn: () => api.post('/roles', {
      name,
      display_name: displayName,
      description:  description || undefined,
      permission_ids: Array.from(selected),
    }),
    onSuccess: () => {
      toast.success('Rôle créé')
      onCreated()
    },
    onError: (e: any) => toast.error(e.response?.data?.message || e.response?.data?.errors?.name?.[0] || 'Erreur'),
  })

  const toggle = (id: number) => setSelected(prev => {
    const n = new Set(prev)
    n.has(id) ? n.delete(id) : n.add(id)
    return n
  })

  return (
    <div className="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
      <div className="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col">
        <div className="flex items-center justify-between p-5 border-b">
          <h2 className="font-bold text-gray-800 flex items-center gap-2">
            <Shield size={18} className="text-[#1a5276]" /> Nouveau rôle
          </h2>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
            <X size={20} />
          </button>
        </div>

        <div className="overflow-y-auto flex-1 p-5 space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1">
                Identifiant <span className="text-red-500">*</span>
              </label>
              <input value={name} onChange={e => setName(e.target.value.toLowerCase().replace(/[^a-z_]/g, ''))}
                placeholder="ex: agent_douane"
                className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-[#1a5276]" />
              <p className="text-xs text-gray-400 mt-0.5">Lettres minuscules et _ uniquement</p>
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1">
                Nom affiché <span className="text-red-500">*</span>
              </label>
              <input value={displayName} onChange={e => setDisplayName(e.target.value)}
                placeholder="ex: Agent Douane"
                className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1a5276]" />
            </div>
          </div>
          <div>
            <label className="block text-xs font-medium text-gray-600 mb-1">Description</label>
            <input value={description} onChange={e => setDescription(e.target.value)}
              placeholder="Rôle pour..."
              className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1a5276]" />
          </div>

          {/* Permissions initiales */}
          <div>
            <p className="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-3">
              Permissions initiales ({selected.size} sélectionnées)
            </p>
            <div className="space-y-3 border border-gray-100 rounded-lg p-4 bg-gray-50">
              {Object.entries(permissions).map(([module, perms]) => (
                <div key={module}>
                  <p className="text-xs font-medium text-gray-500 mb-1.5">
                    {MODULE_LABELS[module] ?? module}
                  </p>
                  <div className="flex flex-wrap gap-1.5">
                    {perms.map(p => (
                      <label key={p.id}
                        className={`flex items-center gap-1 px-2.5 py-1 rounded-md border cursor-pointer text-xs transition select-none ${
                          selected.has(p.id)
                            ? 'bg-[#1a5276] border-[#1a5276] text-white'
                            : 'border-gray-200 text-gray-500 hover:border-[#1a5276] bg-white'
                        }`}>
                        <input type="checkbox" className="sr-only"
                          checked={selected.has(p.id)} onChange={() => toggle(p.id)} />
                        {ACTION_LABELS[p.action] ?? p.action}
                      </label>
                    ))}
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>

        <div className="flex justify-end gap-3 p-5 border-t">
          <button onClick={onClose} className="px-4 py-2 text-sm text-gray-600 border rounded-lg hover:bg-gray-50">
            Annuler
          </button>
          <button
            onClick={() => create.mutate()}
            disabled={!name || !displayName || create.isPending}
            className="flex items-center gap-2 bg-[#1a5276] text-white px-4 py-2 rounded-lg text-sm hover:bg-[#154360] disabled:opacity-50">
            <Plus size={14} />
            {create.isPending ? 'Création...' : 'Créer le rôle'}
          </button>
        </div>
      </div>
    </div>
  )
}
