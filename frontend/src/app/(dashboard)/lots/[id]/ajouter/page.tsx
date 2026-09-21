'use client'

import { useState } from 'react'
import { useParams, useRouter } from 'next/navigation'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import toast from 'react-hot-toast'
import { ArrowLeft, Search, Plus, X, Package, CheckCircle, RefreshCw } from 'lucide-react'

interface PasseportStock {
  id: number
  numero: string
  nom_titulaire: string
  prenom_titulaire: string
  statut: string
}

export default function AjouterPasseportsPage() {
  const params = useParams()
  const router = useRouter()
  const qc = useQueryClient()
  const lotId = params.id as string

  const [search, setSearch] = useState('')
  const [selected, setSelected] = useState<PasseportStock[]>([])

  const { data: lot, isLoading: loadingLot } = useQuery({
    queryKey: ['lot', lotId],
    queryFn:  () => api.get(`/lots/${lotId}`).then(r => r.data),
  })

  const { data, isLoading } = useQuery({
    queryKey: ['stock-en_stock', lot?.ambassade_id, search],
    queryFn:  () => api.get('/stock-central', {
      params: { statut: 'en_stock', ambassade_id: lot?.ambassade_id, search, per_page: 30 }
    }).then(r => r.data),
    enabled: !!lot?.ambassade_id,
  })

  const selectedIds = new Set(selected.map(p => p.id))

  const addPasseports = useMutation({
    mutationFn: () => api.post(`/lots/${lotId}/passeports`, {
      passeport_ids: selected.map(p => p.id),
    }),
    onSuccess: () => {
      toast.success('Passeport(s) ajouté(s) au lot.')
      qc.invalidateQueries({ queryKey: ['lot', lotId] })
      qc.invalidateQueries({ queryKey: ['lot-passeports', lotId] })
      router.push(`/lots/${lotId}`)
    },
    onError: (e: any) => {
      const msg = e.response?.data?.message || Object.values(e.response?.data?.errors ?? {})[0] || "Erreur lors de l'ajout."
      toast.error(String(msg))
    },
  })

  if (loadingLot) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-2 border-[color:var(--color-navy-600)] border-t-transparent" />
      </div>
    )
  }

  if (lot && lot.statut !== 'brouillon') {
    return (
      <div className="max-w-2xl">
        <p className="text-sm text-slate-500 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">
          Ce lot n&apos;est plus en brouillon (statut : {lot.statut}) — son contenu ne peut plus être modifié.
        </p>
        <button onClick={() => router.push(`/lots/${lotId}`)}
          className="mt-4 flex items-center gap-1.5 text-sm text-slate-500 hover:text-[color:var(--color-navy-600)] transition">
          <ArrowLeft size={15} /> Retour au lot
        </button>
      </div>
    )
  }

  return (
    <div className="space-y-6 max-w-2xl">
      <div className="flex items-center gap-3">
        <button onClick={() => router.push(`/lots/${lotId}`)}
          className="flex items-center gap-1.5 text-sm text-slate-500 hover:text-[color:var(--color-navy-600)] transition">
          <ArrowLeft size={15} /> Retour au lot
        </button>
      </div>

      <div>
        <h1 className="text-[22px] font-bold text-[color:var(--color-navy-900)] tracking-tight">
          Ajouter des passeports — {lot?.reference}
        </h1>
        <p className="text-sm text-slate-500">
          Destination : {lot?.ambassade?.nom} ({lot?.ambassade?.ville})
        </p>
      </div>

      <div className="bg-white rounded-[var(--radius-card)] border border-slate-200/70 p-6 space-y-3">
        <div className="flex items-center justify-between">
          <label className="text-sm font-medium text-slate-700">Passeports à ajouter</label>
          <span className="text-xs text-slate-400">{selected.length} sélectionné(s)</span>
        </div>

        <div className="border rounded-xl overflow-hidden">
          <div className="p-3 border-b bg-slate-50">
            <div className="relative">
              <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
              <input
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="N° passeport, nom..."
                className="w-full pl-9 pr-4 py-2 text-sm border rounded-lg focus:outline-none focus:ring-1 focus:ring-[color:var(--color-navy-600)]"
              />
            </div>
          </div>

          <div className="max-h-72 overflow-y-auto">
            {isLoading ? (
              <div className="flex justify-center p-4">
                <div className="w-5 h-5 border-2 border-[color:var(--color-navy-600)] border-t-transparent rounded-full animate-spin" />
              </div>
            ) : data?.data?.length === 0 ? (
              <p className="text-center text-xs text-slate-400 py-6">Aucun passeport en stock pour cette ambassade.</p>
            ) : (
              data?.data?.map((p: PasseportStock) => (
                <div key={p.id}
                  className={`flex items-center justify-between px-4 py-2.5 border-b hover:bg-slate-50 transition ${
                    selectedIds.has(p.id) ? 'bg-green-50' : ''
                  }`}>
                  <div>
                    <p className="text-xs font-bold font-mono text-[color:var(--color-navy-600)]">{p.numero}</p>
                    <p className="text-xs text-slate-600">{p.nom_titulaire} {p.prenom_titulaire}</p>
                  </div>
                  {selectedIds.has(p.id) ? (
                    <button type="button" onClick={() => setSelected(prev => prev.filter(x => x.id !== p.id))}
                      className="text-red-400 hover:text-red-600 transition">
                      <X size={16} />
                    </button>
                  ) : (
                    <button type="button" onClick={() => setSelected(prev => [...prev, p])}
                      className="text-[color:var(--color-navy-600)] hover:text-[color:var(--color-navy-900)] transition">
                      <Plus size={16} />
                    </button>
                  )}
                </div>
              ))
            )}
          </div>
        </div>

        {selected.length > 0 && (
          <div className="bg-green-50 border border-green-200 rounded-xl p-3 space-y-1">
            <p className="text-xs font-semibold text-green-700 mb-2">
              <CheckCircle size={12} className="inline mr-1" />
              {selected.length} passeport(s) sélectionné(s)
            </p>
            <div className="flex flex-wrap gap-1.5">
              {selected.map(p => (
                <span key={p.id}
                  className="inline-flex items-center gap-1 bg-white border border-green-200 rounded-full px-2.5 py-1 text-xs font-mono text-[color:var(--color-navy-600)]">
                  {p.numero}
                  <button type="button" onClick={() => setSelected(prev => prev.filter(x => x.id !== p.id))}
                    className="text-slate-400 hover:text-red-500 ml-0.5">
                    <X size={10} />
                  </button>
                </span>
              ))}
            </div>
          </div>
        )}
      </div>

      <div className="flex gap-3">
        <button
          type="button"
          onClick={() => router.push(`/lots/${lotId}`)}
          className="flex-1 border border-slate-300 text-slate-600 py-3 rounded-xl text-sm font-medium hover:bg-slate-50">
          Annuler
        </button>
        <button
          type="button"
          disabled={selected.length === 0 || addPasseports.isPending}
          onClick={() => addPasseports.mutate()}
          className="flex-1 text-white py-3 rounded-xl text-sm font-semibold disabled:opacity-50 flex items-center justify-center gap-2"
          style={{ background: 'var(--color-navy-900)' }}>
          {addPasseports.isPending
            ? <><RefreshCw size={15} className="animate-spin" /> Ajout…</>
            : <><Package size={15} /> Ajouter au lot</>}
        </button>
      </div>
    </div>
  )
}
