'use client'

import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import api from '@/lib/api'
import { Plus, UserCircle, ToggleLeft, ToggleRight, X, Check, Pencil } from 'lucide-react'
import toast from 'react-hot-toast'

/* ─── Schémas ────────────────────────────────────────────────────────────── */

const createSchema = z.object({
  name:         z.string().min(2, 'Minimum 2 caractères'),
  email:        z.string().email('Email invalide'),
  password:     z.string().min(8, 'Minimum 8 caractères'),
  role:         z.string().min(1, 'Rôle obligatoire'),
  ambassade_id: z.string().optional(),
})

const editSchema = z.object({
  name:         z.string().min(2, 'Minimum 2 caractères'),
  email:        z.string().email('Email invalide'),
  password:     z.string().min(8, 'Minimum 8 caractères').or(z.literal('')).optional(),
  role:         z.string().min(1, 'Rôle obligatoire'),
  ambassade_id: z.string().optional(),
})

type CreateData = z.infer<typeof createSchema>
type EditData   = z.infer<typeof editSchema>

/* ─── Constantes ─────────────────────────────────────────────────────────── */

const roleColors: Record<string, string> = {
  admin_mae:           'bg-purple-100 text-purple-700',
  admin_central:       'bg-purple-100 text-purple-700',
  super_admin:         'bg-red-100 text-red-700',
  gestionnaire:        'bg-blue-100 text-blue-700',
  superviseur:         'bg-indigo-100 text-indigo-700',
  agent_ambassade:     'bg-teal-100 text-teal-700',
  agent_enrolement:    'bg-violet-100 text-violet-700',
  agent_impression:    'bg-slate-100 text-slate-700',
  agent_reception:     'bg-cyan-100 text-cyan-700',
  agent_logistique:    'bg-orange-100 text-orange-700',
  responsable_cellule: 'bg-green-100 text-green-700',
  utilisateur_ambassade:'bg-sky-100 text-sky-700',
  auditeur:            'bg-yellow-100 text-yellow-700',
}

/* ─── Composant champ ────────────────────────────────────────────────────── */

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
  return (
    <div>
      <label className="block text-xs font-medium text-slate-600 mb-1">{label}</label>
      {children}
      {error && <p className="text-red-500 text-xs mt-0.5">{error}</p>}
    </div>
  )
}

const inputCls = 'w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1a5276]'

/* ─── Modal édition ──────────────────────────────────────────────────────── */

function EditModal({ user, roles, ambassades, onClose }: {
  user: any; roles: any[]; ambassades: any[]; onClose: () => void
}) {
  const qc = useQueryClient()

  const { register, handleSubmit, watch, formState: { errors, isSubmitting } } = useForm<EditData>({
    resolver: zodResolver(editSchema),
    defaultValues: {
      name:         user.name,
      email:        user.email,
      password:     '',
      role:         user.role,
      ambassade_id: user.ambassade_id ? String(user.ambassade_id) : '',
    },
  })

  const role = watch('role')

  const update = useMutation({
    mutationFn: (d: EditData) => {
      const payload: any = { name: d.name, email: d.email, role: d.role, ambassade_id: d.ambassade_id || null }
      if (d.password) payload.password = d.password
      return api.put(`/users/${user.id}`, payload)
    },
    onSuccess: () => {
      toast.success('Utilisateur mis à jour')
      qc.invalidateQueries({ queryKey: ['users'] })
      onClose()
    },
    onError: (e: any) => toast.error(e.response?.data?.message || 'Erreur lors de la mise à jour'),
  })

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div className="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b">
          <div>
            <h3 className="font-semibold text-slate-800">Modifier l'utilisateur</h3>
            <p className="text-xs text-slate-400 mt-0.5">{user.email}</p>
          </div>
          <button onClick={onClose} className="text-slate-400 hover:text-slate-600 transition">
            <X size={20} />
          </button>
        </div>

        {/* Formulaire */}
        <form onSubmit={handleSubmit((d) => update.mutate(d))} className="p-6 space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <Field label="Nom complet *" error={errors.name?.message}>
              <input {...register('name')} className={inputCls} />
            </Field>
            <Field label="Email *" error={errors.email?.message}>
              <input {...register('email')} type="email" className={inputCls} />
            </Field>
          </div>

          <Field label="Nouveau mot de passe (laisser vide pour ne pas changer)" error={errors.password?.message}>
            <input {...register('password')} type="password" placeholder="••••••••" className={inputCls} autoComplete="new-password" />
          </Field>

          <div className="grid grid-cols-2 gap-4">
            <Field label="Rôle *" error={errors.role?.message}>
              <select {...register('role')} className={inputCls}>
                {roles.map((r: any) => (
                  <option key={r.name} value={r.name}>{r.display_name}</option>
                ))}
              </select>
            </Field>
            <Field label="Ambassade" error={errors.ambassade_id?.message}>
              <select {...register('ambassade_id')} className={inputCls}>
                <option value="">— Aucune —</option>
                {ambassades.map((a: any) => (
                  <option key={a.id} value={String(a.id)}>{a.nom} — {a.ville}</option>
                ))}
              </select>
            </Field>
          </div>

          {/* Actions */}
          <div className="flex justify-end gap-2 pt-2">
            <button type="button" onClick={onClose}
              className="flex items-center gap-1 px-4 py-2 text-sm border rounded-lg hover:bg-slate-50">
              <X size={14} /> Annuler
            </button>
            <button type="submit" disabled={isSubmitting}
              className="flex items-center gap-1 px-4 py-2 text-sm bg-[#1a5276] text-white rounded-lg hover:bg-[#154360] disabled:opacity-60">
              <Check size={14} /> {isSubmitting ? 'Enregistrement…' : 'Enregistrer'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

/* ─── Page principale ────────────────────────────────────────────────────── */

export default function UtilisateursPage() {
  const qc = useQueryClient()
  const [showCreate, setShowCreate] = useState(false)
  const [editingUser, setEditingUser] = useState<any>(null)

  const { data: users = [], isLoading } = useQuery({
    queryKey: ['users'],
    queryFn: () => api.get('/users').then(r => r.data.data ?? r.data),
  })

  const { data: roles = [] } = useQuery({
    queryKey: ['roles'],
    queryFn: () => api.get('/roles').then(r => r.data),
  })

  const { data: ambassades = [] } = useQuery({
    queryKey: ['ambassades'],
    queryFn: () => api.get('/ambassades').then(r => r.data),
  })

  const { register, handleSubmit, watch, reset, formState: { errors, isSubmitting } } = useForm<CreateData>({
    resolver: zodResolver(createSchema),
    defaultValues: { role: 'gestionnaire' },
  })

  const createRole = watch('role')

  const create = useMutation({
    mutationFn: (d: CreateData) => api.post('/users', { ...d, ambassade_id: d.ambassade_id || null }),
    onSuccess: () => {
      toast.success('Utilisateur créé')
      qc.invalidateQueries({ queryKey: ['users'] })
      setShowCreate(false)
      reset()
    },
    onError: (e: any) => toast.error(e.response?.data?.message || 'Erreur'),
  })

  const toggle = useMutation({
    mutationFn: (id: number) => api.patch(`/users/${id}/toggle-status`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['users'] }),
    onError: () => toast.error('Erreur'),
  })

  return (
    <div className="space-y-6">
      {/* Modal édition */}
      {editingUser && (
        <EditModal
          user={editingUser}
          roles={roles}
          ambassades={ambassades}
          onClose={() => setEditingUser(null)}
        />
      )}

      {/* En-tête */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-[22px] font-bold text-[color:var(--color-navy-900)] tracking-tight">Utilisateurs</h1>
          <p className="text-sm text-slate-500">Gestion des comptes et droits d'accès</p>
        </div>
        <button onClick={() => setShowCreate(!showCreate)}
          className="flex items-center gap-2 bg-[#1a5276] text-white px-4 py-2 rounded-lg text-sm hover:bg-[#154360]">
          <Plus size={15} /> Nouvel utilisateur
        </button>
      </div>

      {/* Formulaire création */}
      {showCreate && (
        <div className="bg-white rounded-xl shadow-sm border border-[#1a5276]/20 p-6">
          <h3 className="font-semibold text-slate-700 mb-4">Créer un utilisateur</h3>
          <form onSubmit={handleSubmit((d) => create.mutate(d))} className="grid grid-cols-2 gap-4">
            <Field label="Nom complet *" error={errors.name?.message}>
              <input {...register('name')} className={inputCls} />
            </Field>
            <Field label="Email *" error={errors.email?.message}>
              <input {...register('email')} type="email" className={inputCls} />
            </Field>
            <Field label="Mot de passe *" error={errors.password?.message}>
              <input {...register('password')} type="password" className={inputCls} />
            </Field>
            <Field label="Rôle *" error={errors.role?.message}>
              <select {...register('role')} className={inputCls}>
                {roles.map((r: any) => (
                  <option key={r.name} value={r.name}>{r.display_name}</option>
                ))}
              </select>
            </Field>
            <div className="col-span-2">
              <Field label="Ambassade" error={errors.ambassade_id?.message}>
                <select {...register('ambassade_id')} className={inputCls}>
                  <option value="">— Aucune —</option>
                  {ambassades.map((a: any) => (
                    <option key={a.id} value={a.id}>{a.nom} — {a.ville}, {a.pays}</option>
                  ))}
                </select>
              </Field>
            </div>
            <div className="col-span-2 flex justify-end gap-2">
              <button type="button" onClick={() => setShowCreate(false)}
                className="flex items-center gap-1 px-4 py-2 text-sm border rounded-lg hover:bg-slate-50">
                <X size={14} /> Annuler
              </button>
              <button type="submit" disabled={isSubmitting}
                className="flex items-center gap-1 px-4 py-2 text-sm bg-[#1a5276] text-white rounded-lg hover:bg-[#154360] disabled:opacity-60">
                <Check size={14} /> Créer
              </button>
            </div>
          </form>
        </div>
      )}

      {/* Tableau */}
      <div className="bg-white rounded-[var(--radius-card)] border border-slate-200/70 overflow-hidden">
        {isLoading ? (
          <div className="flex items-center justify-center h-48">
            <div className="animate-spin rounded-full h-8 w-8 border-2 border-[#1a5276] border-t-transparent" />
          </div>
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="bg-slate-50 border-b">
                {['Utilisateur', 'Email', 'Rôle', 'Ambassade', 'Dernière connexion', 'Statut', 'Actions'].map(h => (
                  <th key={h} className="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {users.map((u: any) => (
                <tr key={u.id} className="border-b hover:bg-slate-50 transition">
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-2">
                      <UserCircle size={20} className="text-slate-300 flex-shrink-0" />
                      <span className="font-medium text-slate-700">{u.name}</span>
                    </div>
                  </td>
                  <td className="px-4 py-3 text-slate-500">{u.email}</td>
                  <td className="px-4 py-3">
                    <span className={`text-xs px-2 py-1 rounded-full font-medium ${roleColors[u.role] ?? 'bg-slate-100 text-slate-600'}`}>
                      {u.role_label ?? u.role}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-xs text-slate-500">
                    {u.ambassade ? `${u.ambassade.nom} (${u.ambassade.pays})` : '—'}
                  </td>
                  <td className="px-4 py-3 text-xs text-slate-400">
                    {u.last_login_at ? new Date(u.last_login_at).toLocaleDateString('fr-FR') : 'Jamais'}
                  </td>
                  <td className="px-4 py-3">
                    <span className={`text-xs px-2 py-1 rounded-full font-medium ${u.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600'}`}>
                      {u.is_active ? 'Actif' : 'Inactif'}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-3">
                      <button
                        onClick={() => setEditingUser(u)}
                        className="text-slate-400 hover:text-[#1a5276] transition"
                        title="Modifier"
                      >
                        <Pencil size={16} />
                      </button>
                      <button
                        onClick={() => toggle.mutate(u.id)}
                        className="text-slate-400 hover:text-[#1a5276] transition"
                        title={u.is_active ? 'Désactiver' : 'Activer'}
                      >
                        {u.is_active
                          ? <ToggleRight size={20} className="text-green-500" />
                          : <ToggleLeft size={20} />}
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  )
}
