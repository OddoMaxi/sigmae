'use client'

import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import api from '@/lib/api'
import { Plus, Building2, Edit2, X, Check } from 'lucide-react'
import toast from 'react-hot-toast'
import { useAuthStore, hasPermission } from '@/stores/authStore'

const schema = z.object({
  code:          z.string().min(2).max(10).toUpperCase(),
  nom:           z.string().min(2),
  pays_id:       z.number({ message: 'Le pays est obligatoire' }).min(1, 'Le pays est obligatoire'),
  ville:         z.string().min(2),
  email_contact: z.string().email().optional().or(z.literal('')),
  responsable:   z.string().optional(),
})
type FormData = z.infer<typeof schema>

function AmbassadeForm({ initial, onSave, onCancel }: {
  initial?: Partial<FormData>
  onSave: (data: FormData) => void
  onCancel: () => void
}) {
  const { register, handleSubmit, setValue, formState: { errors, isSubmitting } } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: initial,
  })

  const { data: pays } = useQuery({
    queryKey: ['pays-select'],
    queryFn:  () => api.get('/pays').then(r => r.data),
  })

  return (
    <form onSubmit={handleSubmit(onSave)} className="grid grid-cols-2 gap-4">
      {[
        { name: 'code',  label: 'Code (ex: FR-PAR)', col: 1 },
        { name: 'nom',   label: 'Nom officiel',      col: 1 },
        { name: 'ville', label: 'Ville',             col: 1 },
        { name: 'email_contact', label: 'Email contact', col: 2 },
        { name: 'responsable',   label: 'Responsable',   col: 2 },
      ].map(({ name, label }) => (
        <div key={name}>
          <label className="block text-xs font-medium text-slate-600 mb-1">{label}</label>
          <input
            {...register(name as keyof FormData)}
            className="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1a5276]"
          />
          {errors[name as keyof FormData] && (
            <p className="text-red-500 text-xs mt-0.5">{errors[name as keyof FormData]?.message}</p>
          )}
        </div>
      ))}

      <div>
        <label className="block text-xs font-medium text-slate-600 mb-1">Pays</label>
        <select
          defaultValue={initial?.pays_id ?? ''}
          onChange={(e) => setValue('pays_id', Number(e.target.value))}
          className="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1a5276]">
          <option value="" disabled>Sélectionner un pays...</option>
          {pays?.map((p: any) => (
            <option key={p.id} value={p.id}>{p.nom}</option>
          ))}
        </select>
        {errors.pays_id && <p className="text-red-500 text-xs mt-0.5">{errors.pays_id.message}</p>}
      </div>

      <div className="col-span-2 flex justify-end gap-2 pt-2">
        <button type="button" onClick={onCancel}
          className="flex items-center gap-1 px-4 py-2 text-sm border rounded-lg hover:bg-slate-50">
          <X size={14} /> Annuler
        </button>
        <button type="submit" disabled={isSubmitting}
          className="flex items-center gap-1 px-4 py-2 text-sm bg-[#1a5276] text-white rounded-lg hover:bg-[#154360] disabled:opacity-60">
          <Check size={14} /> {isSubmitting ? 'Enregistrement...' : 'Enregistrer'}
        </button>
      </div>
    </form>
  )
}

export default function AmbassadesPage() {
  const qc = useQueryClient()
  const { user } = useAuthStore()
  const [showForm, setShowForm] = useState(false)
  const [editing, setEditing] = useState<number | null>(null)

  const canCreate = hasPermission(user, 'ambassades.create')
  const canUpdate = hasPermission(user, 'ambassades.update')

  const { data = [], isLoading } = useQuery({
    queryKey: ['ambassades'],
    queryFn: () => api.get('/ambassades').then(r => r.data),
  })

  const create = useMutation({
    mutationFn: (d: FormData) => api.post('/ambassades', d),
    onSuccess: () => { toast.success('Ambassade créée'); qc.invalidateQueries({ queryKey: ['ambassades'] }); setShowForm(false) },
    onError: (e: any) => toast.error(e.response?.data?.message || 'Erreur'),
  })

  const update = useMutation({
    mutationFn: ({ id, ...d }: FormData & { id: number }) => api.put(`/ambassades/${id}`, d),
    onSuccess: () => { toast.success('Ambassade mise à jour'); qc.invalidateQueries({ queryKey: ['ambassades'] }); setEditing(null) },
    onError: (e: any) => toast.error(e.response?.data?.message || 'Erreur'),
  })

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-[22px] font-bold text-[color:var(--color-navy-900)] tracking-tight">Ambassades & Consulats</h1>
          <p className="text-sm text-slate-500">Gestion des représentations diplomatiques</p>
        </div>
        {canCreate && (
          <button onClick={() => { setShowForm(true); setEditing(null) }}
            className="flex items-center gap-2 bg-[#1a5276] text-white px-4 py-2 rounded-lg text-sm hover:bg-[#154360]">
            <Plus size={15} /> Nouvelle ambassade
          </button>
        )}
      </div>

      {showForm && canCreate && (
        <div className="bg-white rounded-xl shadow-sm border border-[#1a5276]/20 p-6">
          <h3 className="font-semibold text-slate-700 mb-4">Nouvelle ambassade</h3>
          <AmbassadeForm onSave={(d) => create.mutate(d)} onCancel={() => setShowForm(false)} />
        </div>
      )}

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {isLoading ? (
          <div className="col-span-3 flex items-center justify-center h-48">
            <div className="animate-spin rounded-full h-8 w-8 border-2 border-[#1a5276] border-t-transparent" />
          </div>
        ) : data.map((a: any) => (
          <div key={a.id} className="bg-white rounded-[var(--radius-card)] border border-slate-200/70 p-5">
            {editing === a.id && canUpdate ? (
              <AmbassadeForm
                initial={{ ...a, pays_id: a.pays_id }}
                onSave={(d) => update.mutate({ ...d, id: a.id })}
                onCancel={() => setEditing(null)}
              />
            ) : (
              <>
                <div className="flex items-start justify-between mb-3">
                  <div className="flex items-center gap-2">
                    <div className="p-2 bg-[#1a5276]/10 rounded-lg">
                      <Building2 size={18} className="text-[#1a5276]" />
                    </div>
                    <div>
                      <span className="text-xs font-mono bg-slate-100 px-1.5 py-0.5 rounded text-slate-600">{a.code}</span>
                    </div>
                  </div>
                  <div className="flex items-center gap-1">
                    <span className={`text-xs px-2 py-0.5 rounded-full ${a.is_active ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'}`}>
                      {a.is_active ? 'Active' : 'Inactive'}
                    </span>
                    {canUpdate && (
                      <button onClick={() => setEditing(a.id)}
                        className="p-1.5 text-slate-400 hover:text-[#1a5276] hover:bg-slate-100 rounded-lg transition">
                        <Edit2 size={14} />
                      </button>
                    )}
                  </div>
                </div>
                <h3 className="font-semibold text-slate-800">{a.nom}</h3>
                <p className="text-sm text-slate-500">{a.ville}, {a.pays}</p>
                {a.responsable && <p className="text-xs text-slate-400 mt-2">Responsable : {a.responsable}</p>}
                {a.email_contact && <p className="text-xs text-[#1a5276] mt-0.5">{a.email_contact}</p>}
              </>
            )}
          </div>
        ))}
      </div>
    </div>
  )
}
