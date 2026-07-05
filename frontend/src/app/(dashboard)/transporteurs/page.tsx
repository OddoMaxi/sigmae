'use client'

import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import api from '@/lib/api'
import { Plus, Truck, Edit2, X, Check } from 'lucide-react'
import toast from 'react-hot-toast'

const schema = z.object({
  nom:       z.string().min(2),
  contact:   z.string().optional(),
  telephone: z.string().optional(),
  email:     z.string().email().optional().or(z.literal('')),
  adresse:   z.string().optional(),
})
type FormData = z.infer<typeof schema>

export default function TransporteursPage() {
  const qc = useQueryClient()
  const [showForm, setShowForm] = useState(false)
  const [editing, setEditing]   = useState<number | null>(null)

  const { data = [], isLoading } = useQuery({
    queryKey: ['transporteurs'],
    queryFn: () => api.get('/transporteurs').then(r => r.data.data ?? r.data),
  })

  const { register, handleSubmit, reset, formState: { errors, isSubmitting } } = useForm<FormData>({
    resolver: zodResolver(schema),
  })

  const create = useMutation({
    mutationFn: (d: FormData) => api.post('/transporteurs', d),
    onSuccess: () => { toast.success('Transporteur créé'); qc.invalidateQueries({ queryKey: ['transporteurs'] }); setShowForm(false); reset() },
    onError: (e: any) => toast.error(e.response?.data?.message || 'Erreur'),
  })

  const toggle = useMutation({
    mutationFn: ({ id, is_active }: { id: number; is_active: boolean }) =>
      api.put(`/transporteurs/${id}`, { is_active: !is_active }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['transporteurs'] }),
  })

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Transporteurs</h1>
          <p className="text-sm text-gray-500">Sociétés de transport des lots</p>
        </div>
        <button onClick={() => setShowForm(!showForm)}
          className="flex items-center gap-2 bg-[#1a5276] text-white px-4 py-2 rounded-lg text-sm hover:bg-[#154360]">
          <Plus size={15} /> Nouveau transporteur
        </button>
      </div>

      {showForm && (
        <div className="bg-white rounded-xl shadow-sm border border-[#1a5276]/20 p-6">
          <h3 className="font-semibold text-gray-700 mb-4">Nouveau transporteur</h3>
          <form onSubmit={handleSubmit((d) => create.mutate(d))} className="grid grid-cols-2 gap-4">
            {[
              { name: 'nom',       label: 'Nom*' },
              { name: 'contact',   label: 'Contact' },
              { name: 'telephone', label: 'Téléphone' },
              { name: 'email',     label: 'Email' },
            ].map(({ name, label }) => (
              <div key={name}>
                <label className="block text-xs font-medium text-gray-600 mb-1">{label}</label>
                <input {...register(name as keyof FormData)}
                  className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1a5276]" />
                {errors[name as keyof FormData] && (
                  <p className="text-red-500 text-xs mt-0.5">{errors[name as keyof FormData]?.message}</p>
                )}
              </div>
            ))}
            <div className="col-span-2">
              <label className="block text-xs font-medium text-gray-600 mb-1">Adresse</label>
              <textarea {...register('adresse')} rows={2}
                className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1a5276] resize-none" />
            </div>
            <div className="col-span-2 flex justify-end gap-2">
              <button type="button" onClick={() => setShowForm(false)}
                className="flex items-center gap-1 px-4 py-2 text-sm border rounded-lg hover:bg-gray-50">
                <X size={14} /> Annuler
              </button>
              <button type="submit" disabled={isSubmitting}
                className="flex items-center gap-1 px-4 py-2 text-sm bg-[#1a5276] text-white rounded-lg hover:bg-[#154360] disabled:opacity-60">
                <Check size={14} /> Enregistrer
              </button>
            </div>
          </form>
        </div>
      )}

      <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        {isLoading ? (
          <div className="flex items-center justify-center h-48">
            <div className="animate-spin rounded-full h-8 w-8 border-2 border-[#1a5276] border-t-transparent" />
          </div>
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="bg-gray-50 border-b">
                {['Nom', 'Contact', 'Téléphone', 'Email', 'Statut', 'Actions'].map(h => (
                  <th key={h} className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {data.map((t: any) => (
                <tr key={t.id} className="border-b hover:bg-gray-50 transition">
                  <td className="px-4 py-3 font-medium flex items-center gap-2">
                    <Truck size={15} className="text-gray-400" /> {t.nom}
                  </td>
                  <td className="px-4 py-3 text-gray-500">{t.contact || '—'}</td>
                  <td className="px-4 py-3 text-gray-500">{t.telephone || '—'}</td>
                  <td className="px-4 py-3 text-gray-500">{t.email || '—'}</td>
                  <td className="px-4 py-3">
                    <span className={`text-xs px-2 py-1 rounded-full font-medium ${t.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                      {t.is_active ? 'Actif' : 'Inactif'}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <button onClick={() => toggle.mutate({ id: t.id, is_active: t.is_active })}
                      className="text-xs text-gray-500 hover:text-[#1a5276] underline">
                      {t.is_active ? 'Désactiver' : 'Activer'}
                    </button>
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
