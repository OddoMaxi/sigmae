'use client'

import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'
import { Search, Plus, Upload, Download, Eye } from 'lucide-react'
import Link from 'next/link'

const STATUTS = [
  '', 'enrolee', 'imprime', 'recu_mae', 'en_stock',
  'en_lot', 'expedie', 'en_transit', 'recu_ambassade',
  'disponible_retrait', 'remis_citoyen', 'anomalie',
]

const STATUT_LABELS: Record<string, string> = {
  enrolee:            'Enrôlé',
  imprime:            'Imprimé',
  recu_mae:           'Reçu MAE',
  en_stock:           'En stock',
  en_lot:             'En lot',
  expedie:            'Expédié',
  en_transit:         'En transit',
  recu_ambassade:     'Reçu ambassade',
  disponible_retrait: 'Disponible retrait',
  remis_citoyen:      'Remis citoyen',
  anomalie:           'Anomalie',
}

const statutStyle: Record<string, string> = {
  enrolee:            'bg-violet-100 text-violet-700',
  imprime:            'bg-gray-100 text-gray-600',
  recu_mae:           'bg-blue-100 text-blue-700',
  en_stock:           'bg-indigo-100 text-indigo-700',
  en_lot:             'bg-yellow-100 text-yellow-800',
  expedie:            'bg-orange-100 text-orange-700',
  en_transit:         'bg-purple-100 text-purple-700',
  recu_ambassade:     'bg-teal-100 text-teal-700',
  disponible_retrait: 'bg-green-100 text-green-700',
  remis_citoyen:      'bg-emerald-100 text-emerald-700',
  anomalie:           'bg-red-100 text-red-700',
}

export default function PasseportsPage() {
  const [search,  setSearch]  = useState('')
  const [statut,  setStatut]  = useState('')
  const [page,    setPage]    = useState(1)

  const { data, isLoading } = useQuery({
    queryKey: ['passeports', search, statut, page],
    queryFn:  () => api.get('/passeports', {
      params: {
        search:  search  || undefined,
        statut:  statut  || undefined,
        page,
        per_page: 50,
      },
    }).then((r) => r.data),
    placeholderData: (prev: any) => prev,
  })

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Passeports</h1>
          <p className="text-sm text-gray-500">Gestion du stock et suivi des passeports</p>
        </div>
        <div className="flex gap-2">
          <button className="flex items-center gap-2 border border-gray-300 text-gray-600 px-4 py-2 rounded-lg text-sm hover:bg-gray-50">
            <Upload size={15} /> Importer
          </button>
          <Link href="/passeports/nouveau"
            className="flex items-center gap-2 bg-[#1a5276] text-white px-4 py-2 rounded-lg text-sm hover:bg-[#154360]">
            <Plus size={15} /> Nouveau
          </Link>
        </div>
      </div>

      {/* Filtres */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex gap-4">
        <div className="flex-1 relative">
          <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
          <input
            value={search}
            onChange={(e) => { setSearch(e.target.value); setPage(1) }}
            placeholder="Rechercher par N°, nom, prénom..."
            className="w-full pl-9 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#1a5276]"
          />
        </div>
        <select
          value={statut}
          onChange={(e) => { setStatut(e.target.value); setPage(1) }}
          className="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1a5276]"
        >
          {STATUTS.map((s) => (
            <option key={s} value={s}>{s ? (STATUT_LABELS[s] ?? s) : 'Tous les statuts'}</option>
          ))}
        </select>
        <a href="/api/export/passeports?format=xlsx" target="_blank"
          className="flex items-center gap-2 border border-gray-300 text-gray-600 px-4 py-2 rounded-lg text-sm hover:bg-gray-50">
          <Download size={15} /> Export
        </a>
      </div>

      {/* Table */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        {isLoading ? (
          <div className="flex items-center justify-center h-48">
            <div className="animate-spin rounded-full h-8 w-8 border-2 border-[#1a5276] border-t-transparent" />
          </div>
        ) : (
          <>
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-gray-50 border-b">
                  {['N° / Référence', 'Titulaire', 'Email citoyen', 'Statut', 'Ambassade destination', 'Date', 'Actions'].map((h) => (
                    <th key={h} className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {(data?.data ?? []).map((p: any) => (
                  <tr key={p.id} className="border-b hover:bg-gray-50 transition">
                    <td className="px-4 py-3">
                      {p.numero ? (
                        <span className="font-mono font-semibold text-[#1a5276]">{p.numero}</span>
                      ) : (
                        <span className="font-mono text-xs text-violet-600 bg-violet-50 px-1.5 py-0.5 rounded">
                          {p.reference_demande ?? '—'}
                        </span>
                      )}
                    </td>
                    <td className="px-4 py-3">
                      <p className="font-medium">{p.nom_titulaire} {p.prenom_titulaire}</p>
                      <p className="text-xs text-gray-400">{p.telephone || p.email_citoyen || ''}</p>
                    </td>
                    <td className="px-4 py-3 text-gray-500 text-sm">{p.email_citoyen || '—'}</td>
                    <td className="px-4 py-3">
                      <span className={`text-xs px-2 py-1 rounded-full font-medium ${statutStyle[p.statut] ?? 'bg-gray-100 text-gray-600'}`}>
                        {STATUT_LABELS[p.statut] ?? p.statut}
                      </span>
                    </td>
                    <td className="px-4 py-3">
                      {p.ambassade_destination ? (
                        <div>
                          <p className="text-xs font-medium">{p.ambassade_destination.nom}</p>
                          <p className="text-xs text-gray-400">{p.ambassade_destination.code}</p>
                        </div>
                      ) : p.lot ? (
                        <div>
                          <p className="text-xs font-medium">{p.lot.reference}</p>
                        </div>
                      ) : <span className="text-gray-300">—</span>}
                    </td>
                    <td className="px-4 py-3 text-gray-500 text-xs">
                      {p.enrolled_at
                        ? new Date(p.enrolled_at).toLocaleDateString('fr-FR')
                        : p.received_at
                        ? new Date(p.received_at).toLocaleDateString('fr-FR')
                        : '—'}
                    </td>
                    <td className="px-4 py-3">
                      <Link href={`/passeports/${p.id}`}
                        className="text-[#1a5276] hover:underline flex items-center gap-1 text-sm">
                        <Eye size={14} /> Voir
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>

            {/* Pagination */}
            {data?.last_page > 1 && (
              <div className="flex items-center justify-between px-4 py-3 border-t">
                <p className="text-xs text-gray-500">
                  {data.from}–{data.to} sur {data.total} passeports
                </p>
                <div className="flex gap-2">
                  <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1}
                    className="px-3 py-1 text-xs border rounded hover:bg-gray-50 disabled:opacity-40">
                    Précédent
                  </button>
                  <button onClick={() => setPage(p => Math.min(data.last_page, p + 1))} disabled={page === data.last_page}
                    className="px-3 py-1 text-xs border rounded hover:bg-gray-50 disabled:opacity-40">
                    Suivant
                  </button>
                </div>
              </div>
            )}
          </>
        )}
      </div>
    </div>
  )
}
