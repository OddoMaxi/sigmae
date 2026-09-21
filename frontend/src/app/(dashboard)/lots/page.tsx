'use client'

import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { Plus, Package, Eye, FileDown, CheckCircle, Send } from 'lucide-react'
import Link from 'next/link'
import toast from 'react-hot-toast'
import { useAuthStore, hasPermission } from '@/stores/authStore'

const statutStyle: Record<string, string> = {
  brouillon:   'bg-slate-100 text-slate-700',
  valide:      'bg-blue-100 text-blue-700',
  expedie:     'bg-yellow-100 text-yellow-800',
  recu:        'bg-green-100 text-green-700',
  recu_partiel:'bg-orange-100 text-orange-700',
  anomalie:    'bg-red-100 text-red-700',
}

async function downloadBordereau(lotId: number) {
  const res   = await api.get(`/lots/${lotId}/bordereau`)
  const bytes = atob(res.data.data)
  const buf   = new Uint8Array(bytes.length)
  for (let i = 0; i < bytes.length; i++) buf[i] = bytes.charCodeAt(i)
  const blob  = new Blob([buf], { type: 'application/pdf' })
  const url   = URL.createObjectURL(blob)
  const a     = document.createElement('a')
  a.href      = url
  a.download  = res.data.filename
  a.click()
  URL.revokeObjectURL(url)
}

export default function LotsPage() {
  const [statut, setStatut] = useState('')
  const [page,   setPage]   = useState(1)
  const qc = useQueryClient()
  const { user } = useAuthStore()
  const canCreate   = hasPermission(user, 'lots.create')
  const canValidate = hasPermission(user, 'lots.validate')
  const canShip     = hasPermission(user, 'lots.ship')

  const { data, isLoading } = useQuery({
    queryKey: ['lots', statut, page],
    queryFn:  () => api.get('/lots', { params: { statut: statut || undefined, page } }).then(r => r.data),
    placeholderData: (prev: any) => prev,
  })

  const valider = useMutation({
    mutationFn: (id: number) => api.post(`/lots/${id}/valider`),
    onSuccess: () => { toast.success('Lot validé'); qc.invalidateQueries({ queryKey: ['lots'] }) },
    onError: (e: any) => toast.error(e.response?.data?.message || 'Erreur'),
  })

  const expedier = useMutation({
    mutationFn: (id: number) => api.post(`/lots/${id}/expedier`),
    onSuccess: () => { toast.success('Lot expédié — bordereau généré'); qc.invalidateQueries({ queryKey: ['lots'] }) },
    onError: (e: any) => toast.error(e.response?.data?.message || 'Erreur'),
  })

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-[22px] font-bold text-[color:var(--color-navy-900)] tracking-tight">Lots d'expédition</h1>
          <p className="text-sm text-slate-500">Création et suivi des lots vers les ambassades</p>
        </div>
        {canCreate && (
          <Link href="/lots/nouveau"
            className="flex items-center gap-2 bg-[#1a5276] text-white px-4 py-2 rounded-lg text-sm hover:bg-[#154360]">
            <Plus size={15} /> Nouveau lot
          </Link>
        )}
      </div>

      {/* Filtre statut */}
      <div className="bg-white rounded-xl border border-slate-100 shadow-sm p-4 flex gap-3">
        {['', 'brouillon', 'valide', 'expedie', 'recu', 'recu_partiel', 'anomalie'].map((s) => (
          <button key={s}
            onClick={() => { setStatut(s); setPage(1) }}
            className={`px-3 py-1.5 text-xs rounded-full border font-medium transition ${
              statut === s ? 'bg-[#1a5276] text-white border-[#1a5276]' : 'border-slate-200 text-slate-600 hover:border-[#1a5276]'
            }`}>
            {s || 'Tous'}
          </button>
        ))}
      </div>

      {/* Table */}
      <div className="bg-white rounded-[var(--radius-card)] border border-slate-200/70 overflow-hidden">
        {isLoading ? (
          <div className="flex items-center justify-center h-48">
            <div className="animate-spin rounded-full h-8 w-8 border-2 border-[#1a5276] border-t-transparent" />
          </div>
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="bg-slate-50 border-b">
                {['Référence', 'Ambassade', 'Transporteur', 'Passeports', 'Statut', 'Expédition', 'Actions'].map(h => (
                  <th key={h} className="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {data?.data?.map((lot: any) => (
                <tr key={lot.id} className="border-b hover:bg-slate-50 transition">
                  <td className="px-4 py-3 font-mono font-semibold text-[#1a5276]">{lot.reference}</td>
                  <td className="px-4 py-3">
                    <p className="font-medium">{lot.ambassade?.nom}</p>
                    <p className="text-xs text-slate-400">{lot.ambassade?.pays}</p>
                  </td>
                  <td className="px-4 py-3 text-slate-600">{lot.transporteur?.nom}</td>
                  <td className="px-4 py-3 text-center">
                    <span className="inline-flex items-center gap-1 font-bold text-[#1a5276]">
                      <Package size={14} /> {lot.passeports_count}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <span className={`text-xs px-2 py-1 rounded-full font-medium ${statutStyle[lot.statut] ?? ''}`}>
                      {lot.statut}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-slate-500 text-xs">
                    {lot.date_expedition ? new Date(lot.date_expedition).toLocaleDateString('fr-FR') : '—'}
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-2">
                      <Link href={`/lots/${lot.id}`} className="text-[#1a5276] hover:underline">
                        <Eye size={15} />
                      </Link>
                      {lot.statut === 'brouillon' && canValidate && (
                        <button onClick={() => valider.mutate(lot.id)}
                          title="Valider"
                          className="text-blue-600 hover:text-blue-800">
                          <CheckCircle size={15} />
                        </button>
                      )}
                      {lot.statut === 'valide' && canShip && (
                        <button onClick={() => expedier.mutate(lot.id)}
                          title="Expédier"
                          className="text-green-600 hover:text-green-800">
                          <Send size={15} />
                        </button>
                      )}
                      {lot.bordereau_path && (
                        <button onClick={() => downloadBordereau(lot.id)}
                          className="text-slate-500 hover:text-slate-700" title="Bordereau PDF">
                          <FileDown size={15} />
                        </button>
                      )}
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
