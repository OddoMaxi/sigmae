'use client'

import { useState } from 'react'
import { useQuery, useMutation } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import api from '@/lib/api'
import toast from 'react-hot-toast'
import { useRouter } from 'next/navigation'
import {
  ArrowLeft, Search, Plus, X, Package, Building2, Truck, CheckCircle, RefreshCw,
} from 'lucide-react'

// ── Schéma ────────────────────────────────────────────────────────────────────

const schema = z.object({
  ambassade_id:           z.number({ message: 'Ambassade obligatoire' }).min(1, 'Ambassade obligatoire'),
  transporteur_id:        z.number({ message: 'Transporteur obligatoire' }).min(1, 'Transporteur obligatoire'),
  date_expedition:        z.string().optional(),
  date_reception_prevue:  z.string().optional(),
  reference_suivi:        z.string().max(100).optional(),
  notes:                  z.string().max(1000).optional(),
})

type FormData = z.infer<typeof schema>

// ── Recherche passeports ──────────────────────────────────────────────────────

interface PasseportStock {
  id: number
  numero: string
  nom_titulaire: string
  prenom_titulaire: string
  email_citoyen?: string
  statut: string
  date_reception_mae?: string
}

function PasseportSelector({
  ambassadeId,
  selected,
  onAdd,
  onRemove,
}: {
  ambassadeId: number | null
  selected: PasseportStock[]
  onAdd: (p: PasseportStock) => void
  onRemove: (id: number) => void
}) {
  const [search, setSearch] = useState('')

  const { data, isLoading } = useQuery({
    queryKey: ['stock-en_stock', ambassadeId, search],
    queryFn:  () => api.get('/stock-central', {
      params: { statut: 'en_stock', ambassade_id: ambassadeId ?? undefined, search, per_page: 30 }
    }).then(r => r.data),
    enabled: !!ambassadeId,
  })

  const selectedIds = new Set(selected.map(p => p.id))

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <label className="text-sm font-medium text-gray-700">Passeports à ajouter</label>
        <span className="text-xs text-gray-400">{selected.length} sélectionné(s)</span>
      </div>

      {!ambassadeId && (
        <p className="text-xs text-amber-600 bg-amber-50 px-3 py-2 rounded-lg">
          Sélectionnez d'abord une ambassade pour voir les passeports disponibles.
        </p>
      )}

      {ambassadeId && (
        <div className="border rounded-xl overflow-hidden">
          <div className="p-3 border-b bg-gray-50">
            <div className="relative">
              <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
              <input
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="N° passeport, nom..."
                className="w-full pl-9 pr-4 py-2 text-sm border rounded-lg focus:outline-none focus:ring-1 focus:ring-[#1a5276]"
              />
            </div>
          </div>

          <div className="max-h-60 overflow-y-auto">
            {isLoading ? (
              <div className="flex justify-center p-4">
                <div className="w-5 h-5 border-2 border-[#1a5276] border-t-transparent rounded-full animate-spin" />
              </div>
            ) : data?.data?.length === 0 ? (
              <p className="text-center text-xs text-gray-400 py-6">Aucun passeport en stock pour cette ambassade.</p>
            ) : (
              data?.data?.map((p: PasseportStock) => (
                <div key={p.id}
                  className={`flex items-center justify-between px-4 py-2.5 border-b hover:bg-gray-50 transition ${
                    selectedIds.has(p.id) ? 'bg-green-50' : ''
                  }`}>
                  <div>
                    <p className="text-xs font-bold font-mono text-[#1a5276]">{p.numero}</p>
                    <p className="text-xs text-gray-600">{p.nom_titulaire} {p.prenom_titulaire}</p>
                  </div>
                  {selectedIds.has(p.id) ? (
                    <button onClick={() => onRemove(p.id)}
                      className="text-red-400 hover:text-red-600 transition">
                      <X size={16} />
                    </button>
                  ) : (
                    <button onClick={() => onAdd(p)}
                      className="text-[#1a5276] hover:text-[#154360] transition">
                      <Plus size={16} />
                    </button>
                  )}
                </div>
              ))
            )}
          </div>
        </div>
      )}

      {/* Passeports sélectionnés */}
      {selected.length > 0 && (
        <div className="bg-green-50 border border-green-200 rounded-xl p-3 space-y-1">
          <p className="text-xs font-semibold text-green-700 mb-2">
            <CheckCircle size={12} className="inline mr-1" />
            {selected.length} passeport(s) sélectionné(s)
          </p>
          <div className="flex flex-wrap gap-1.5">
            {selected.map(p => (
              <span key={p.id}
                className="inline-flex items-center gap-1 bg-white border border-green-200 rounded-full px-2.5 py-1 text-xs font-mono text-[#1a5276]">
                {p.numero}
                <button onClick={() => onRemove(p.id)} className="text-gray-400 hover:text-red-500 ml-0.5">
                  <X size={10} />
                </button>
              </span>
            ))}
          </div>
        </div>
      )}
    </div>
  )
}

// ── Page principale ───────────────────────────────────────────────────────────

export default function NouveauLotPage() {
  const router = useRouter()
  const [selectedPasseports, setSelectedPasseports] = useState<PasseportStock[]>([])

  const { register, handleSubmit, watch, setValue, formState: { errors } } = useForm<FormData>({
    resolver: zodResolver(schema),
  })

  const ambassadeId    = watch('ambassade_id')
  const transporteurId = watch('transporteur_id')

  // ── Données de référence ─────────────────────────────────────────────────

  const { data: ambassades } = useQuery({
    queryKey: ['ambassades-select'],
    queryFn:  () => api.get('/ambassades', { params: { per_page: 200 } }).then(r => r.data?.data ?? r.data),
  })

  const { data: transporteurs } = useQuery({
    queryKey: ['transporteurs-select'],
    queryFn:  () => api.get('/transporteurs').then(r => r.data?.data ?? r.data),
  })

  // ── Création du lot ──────────────────────────────────────────────────────

  const createLot = useMutation({
    mutationFn: async (data: FormData) => {
      // 1. Créer le lot
      const { data: lot } = await api.post('/lots', data)
      // 2. Ajouter les passeports si sélectionnés
      if (selectedPasseports.length > 0) {
        await api.post(`/lots/${lot.id}/passeports`, {
          passeport_ids: selectedPasseports.map(p => p.id),
        })
      }
      return lot
    },
    onSuccess: (lot) => {
      toast.success('Lot créé avec succès.')
      router.push(`/lots/${lot.id}`)
    },
    onError: (e: any) => {
      const msg = e.response?.data?.message || Object.values(e.response?.data?.errors ?? {})[0] || 'Erreur lors de la création.'
      toast.error(String(msg))
    },
  })

  function onSubmit(data: FormData) {
    createLot.mutate(data)
  }

  return (
    <div className="space-y-6 max-w-3xl">
      {/* Navigation */}
      <div className="flex items-center gap-3">
        <button onClick={() => router.push('/lots')}
          className="flex items-center gap-1.5 text-sm text-gray-500 hover:text-[#1a5276] transition">
          <ArrowLeft size={15} /> Retour aux lots
        </button>
      </div>

      <div>
        <h1 className="text-2xl font-bold text-gray-800">Nouveau lot d'expédition</h1>
        <p className="text-sm text-gray-500">Un lot doit être destiné à une seule ambassade.</p>
      </div>

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
        {/* Informations générales */}
        <div className="bg-white rounded-2xl shadow-sm border p-6 space-y-5">
          <h2 className="font-semibold text-gray-800 flex items-center gap-2">
            <Package size={16} className="text-[#1a5276]" /> Informations générales
          </h2>

          {/* Ambassade */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1.5">
              <Building2 size={14} className="inline mr-1 text-[#1a5276]" />
              Ambassade de destination <span className="text-red-500">*</span>
            </label>
            <select
              className="w-full border rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1a5276]"
              onChange={(e) => setValue('ambassade_id', Number(e.target.value))}
              defaultValue="">
              <option value="" disabled>Sélectionner une ambassade...</option>
              {ambassades?.map((a: any) => (
                <option key={a.id} value={a.id}>{a.nom} — {a.ville}, {a.pays}</option>
              ))}
            </select>
            {errors.ambassade_id && <p className="text-red-500 text-xs mt-1">{errors.ambassade_id.message}</p>}
          </div>

          {/* Transporteur */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1.5">
              <Truck size={14} className="inline mr-1 text-[#1a5276]" />
              Transporteur <span className="text-red-500">*</span>
            </label>
            <select
              className="w-full border rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1a5276]"
              onChange={(e) => setValue('transporteur_id', Number(e.target.value))}
              defaultValue="">
              <option value="" disabled>Sélectionner un transporteur...</option>
              {transporteurs
                ?.filter((t: any) => t.is_active !== false)
                ?.map((t: any) => (
                  <option key={t.id} value={t.id}>{t.nom} ({t.type ?? 'Standard'})</option>
                ))}
            </select>
            {errors.transporteur_id && <p className="text-red-500 text-xs mt-1">{errors.transporteur_id.message}</p>}
          </div>

          {/* Dates */}
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1.5">Date d'expédition</label>
              <input
                type="date"
                {...register('date_expedition')}
                className="w-full border rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1a5276]"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1.5">Réception prévue</label>
              <input
                type="date"
                {...register('date_reception_prevue')}
                className="w-full border rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1a5276]"
              />
            </div>
          </div>

          {/* Référence suivi */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1.5">Référence de suivi transporteur</label>
            <input
              type="text"
              {...register('reference_suivi')}
              placeholder="ex: DHL-2024-001234"
              className="w-full border rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1a5276]"
            />
          </div>

          {/* Notes */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1.5">Notes internes (optionnel)</label>
            <textarea
              {...register('notes')}
              rows={3}
              placeholder="Observations, instructions particulières..."
              className="w-full border rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1a5276] resize-none"
            />
          </div>
        </div>

        {/* Sélection passeports */}
        <div className="bg-white rounded-2xl shadow-sm border p-6">
          <PasseportSelector
            ambassadeId={ambassadeId ?? null}
            selected={selectedPasseports}
            onAdd={(p) => setSelectedPasseports(prev => [...prev, p])}
            onRemove={(id) => setSelectedPasseports(prev => prev.filter(p => p.id !== id))}
          />
        </div>

        {/* Soumission */}
        <div className="flex gap-3">
          <button
            type="button"
            onClick={() => router.push('/lots')}
            className="flex-1 border border-gray-300 text-gray-600 py-3 rounded-xl text-sm font-medium hover:bg-gray-50">
            Annuler
          </button>
          <button
            type="submit"
            disabled={createLot.isPending}
            className="flex-1 bg-[#1a5276] text-white py-3 rounded-xl text-sm font-semibold hover:bg-[#154360] disabled:opacity-50 flex items-center justify-center gap-2">
            {createLot.isPending
              ? <><RefreshCw size={15} className="animate-spin" /> Création…</>
              : <><Package size={15} /> Créer le lot</>}
          </button>
        </div>
      </form>
    </div>
  )
}
