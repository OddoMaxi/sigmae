'use client'

import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { AlertTriangle, CheckCircle, Clock } from 'lucide-react'
import toast from 'react-hot-toast'

const typeColors: Record<string, string> = {
  manquant:  'bg-red-100 text-red-700',
  endommage: 'bg-orange-100 text-orange-700',
  errone:    'bg-yellow-100 text-yellow-700',
  autre:     'bg-gray-100 text-gray-700',
}

const statutColors: Record<string, string> = {
  ouvert:        'bg-red-100 text-red-700',
  en_traitement: 'bg-blue-100 text-blue-700',
  resolu:        'bg-green-100 text-green-700',
}

export default function AnomaliesPage() {
  const [statut, setStatut] = useState('')
  const [page,   setPage]   = useState(1)
  const qc = useQueryClient()

  const { data, isLoading } = useQuery({
    queryKey: ['anomalies', statut, page],
    queryFn:  () => api.get('/anomalies', { params: { statut, page } }).then(r => r.data),
    keepPreviousData: true,
  })

  const resoudre = useMutation({
    mutationFn: (id: number) => api.post(`/anomalies/${id}/resoudre`),
    onSuccess: () => { toast.success('Anomalie résolue'); qc.invalidateQueries({ queryKey: ['anomalies'] }) },
    onError: () => toast.error('Erreur'),
  })

  const prendreEnCharge = useMutation({
    mutationFn: (id: number) => api.put(`/anomalies/${id}`, { statut: 'en_traitement' }),
    onSuccess: () => { toast.success('Prise en charge'); qc.invalidateQueries({ queryKey: ['anomalies'] }) },
    onError: () => toast.error('Erreur'),
  })

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-800">Anomalies</h1>
        <p className="text-sm text-gray-500">Suivi et résolution des anomalies signalées</p>
      </div>

      {/* Filtres */}
      <div className="bg-white rounded-xl border border-gray-100 shadow-sm p-4 flex gap-3">
        {['', 'ouvert', 'en_traitement', 'resolu'].map((s) => (
          <button key={s}
            onClick={() => { setStatut(s); setPage(1) }}
            className={`px-3 py-1.5 text-xs rounded-full border font-medium transition ${
              statut === s ? 'bg-[#1a5276] text-white border-[#1a5276]' : 'border-gray-200 text-gray-600 hover:border-[#1a5276]'
            }`}>
            {s || 'Toutes'}
          </button>
        ))}
      </div>

      {/* Table */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        {isLoading ? (
          <div className="flex items-center justify-center h-48">
            <div className="animate-spin rounded-full h-8 w-8 border-2 border-[#1a5276] border-t-transparent" />
          </div>
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="bg-gray-50 border-b">
                {['Type', 'Passeport', 'Lot / Ambassade', 'Description', 'Statut', 'Signalé par', 'Actions'].map(h => (
                  <th key={h} className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {data?.data?.map((a: any) => (
                <tr key={a.id} className="border-b hover:bg-gray-50 transition">
                  <td className="px-4 py-3">
                    <span className={`text-xs px-2 py-1 rounded-full font-medium ${typeColors[a.type] ?? 'bg-gray-100'}`}>
                      {a.type}
                    </span>
                  </td>
                  <td className="px-4 py-3 font-mono text-[#1a5276] font-semibold">
                    {a.passeport?.numero || '—'}
                  </td>
                  <td className="px-4 py-3">
                    <p className="text-xs font-medium">{a.lot?.reference}</p>
                    <p className="text-xs text-gray-400">{a.lot?.ambassade?.nom}</p>
                  </td>
                  <td className="px-4 py-3 text-gray-600 max-w-xs truncate">{a.description}</td>
                  <td className="px-4 py-3">
                    <span className={`text-xs px-2 py-1 rounded-full font-medium ${statutColors[a.statut] ?? ''}`}>
                      {a.statut}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-xs text-gray-500">{a.signale_par?.name}</td>
                  <td className="px-4 py-3">
                    <div className="flex gap-2">
                      {a.statut === 'ouvert' && (
                        <button onClick={() => prendreEnCharge.mutate(a.id)}
                          className="text-blue-600 hover:text-blue-800 flex items-center gap-1 text-xs">
                          <Clock size={13} /> Prendre en charge
                        </button>
                      )}
                      {a.statut === 'en_traitement' && (
                        <button onClick={() => resoudre.mutate(a.id)}
                          className="text-green-600 hover:text-green-800 flex items-center gap-1 text-xs">
                          <CheckCircle size={13} /> Résoudre
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
