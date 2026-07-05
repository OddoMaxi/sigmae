'use client'

import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'
import { Search, Download, AlertTriangle, Package, Archive } from 'lucide-react'
import { formatDate } from '@/lib/utils'

const STATUTS_STOCK = ['', 'en_stock', 'en_lot', 'expedie', 'anomalie']

const statutStyle: Record<string, string> = {
  en_stock: 'bg-green-100 text-green-700',
  en_lot:   'bg-blue-100 text-blue-700',
  expedie:  'bg-yellow-100 text-yellow-800',
  anomalie: 'bg-red-100 text-red-700',
}

const statutLabel: Record<string, string> = {
  en_stock: 'En stock',
  en_lot:   'En lot',
  expedie:  'Expédié',
  anomalie: 'Anomalie',
}

// ── Inventaire par ambassade ───────────────────────────────────────────────

function InventaireSection() {
  const { data, isLoading } = useQuery({
    queryKey: ['stock-inventaire'],
    queryFn: () => api.get('/stock-central/inventaire').then(r => r.data),
    refetchInterval: 30000,
  })

  const { data: alertes } = useQuery({
    queryKey: ['stock-alertes'],
    queryFn: () => api.get('/stock-central/alertes').then(r => r.data),
  })

  if (isLoading) return (
    <div className="bg-white rounded-xl border p-8 flex justify-center">
      <div className="w-7 h-7 border-2 border-[#1a5276] border-t-transparent rounded-full animate-spin" />
    </div>
  )

  return (
    <div className="space-y-4">
      {/* Alertes */}
      {(alertes?.count ?? 0) > 0 && (
        <div className="bg-amber-50 border border-amber-200 rounded-xl p-4">
          <div className="flex items-center gap-2 text-amber-700 font-semibold text-sm mb-2">
            <AlertTriangle size={16} />
            {alertes.count} ambassade(s) avec plus de {alertes.seuil} passeports en stock
          </div>
          <div className="flex flex-wrap gap-2">
            {alertes.alertes?.map((a: any) => (
              <span key={a.ambassade.id} className={`text-xs px-2.5 py-1 rounded-full font-medium ${
                a.niveau === 'critique' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'
              }`}>
                {a.ambassade.nom} — {a.total_en_stock} passeports
              </span>
            ))}
          </div>
        </div>
      )}

      {/* Totaux */}
      {data?.totaux && (
        <div className="grid grid-cols-5 gap-3">
          {[
            { k: 'en_stock', label: 'En stock',  color: 'text-green-600' },
            { k: 'en_lot',   label: 'En lot',    color: 'text-blue-600' },
            { k: 'expedie',  label: 'Expédiés',  color: 'text-yellow-600' },
            { k: 'anomalie', label: 'Anomalies', color: 'text-red-600' },
            { k: 'total',    label: 'Total',     color: 'text-gray-700' },
          ].map(({ k, label, color }) => (
            <div key={k} className="bg-white rounded-xl border p-3 text-center">
              <p className={`text-2xl font-bold ${color}`}>{data.totaux[k] ?? 0}</p>
              <p className="text-xs text-gray-500 mt-0.5">{label}</p>
            </div>
          ))}
        </div>
      )}

      {/* Table inventaire */}
      <div className="bg-white rounded-xl border overflow-hidden">
        <div className="px-5 py-3 border-b flex items-center gap-2">
          <Archive size={15} className="text-[#1a5276]" />
          <h3 className="font-semibold text-sm text-gray-800">Inventaire par ambassade</h3>
        </div>
        <table className="w-full text-sm">
          <thead>
            <tr className="bg-gray-50 border-b">
              {['Ambassade', 'Pays', 'En stock', 'En lot', 'Expédiés', 'Anomalies', 'Total'].map(h => (
                <th key={h} className="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">{h}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {data?.ambassades?.map((row: any) => (
              <tr key={row.ambassade.id} className={`border-b hover:bg-gray-50 ${row.alerte ? 'bg-amber-50/40' : ''}`}>
                <td className="px-4 py-2.5">
                  <div className="flex items-center gap-2">
                    {row.alerte && <AlertTriangle size={12} className="text-amber-500 shrink-0" />}
                    <div>
                      <p className="font-medium text-gray-800 text-xs">{row.ambassade.nom}</p>
                      <p className="text-gray-400 text-xs">{row.ambassade.ville}</p>
                    </div>
                  </div>
                </td>
                <td className="px-4 py-2.5 text-xs text-gray-500">{row.ambassade.pays?.nom}</td>
                <td className="px-4 py-2.5 text-center">
                  <span className={`text-xs font-bold ${row.en_stock >= 100 ? 'text-amber-600' : 'text-green-600'}`}>
                    {row.en_stock}
                  </span>
                </td>
                <td className="px-4 py-2.5 text-center text-xs text-blue-600 font-medium">{row.en_lot}</td>
                <td className="px-4 py-2.5 text-center text-xs text-yellow-700 font-medium">{row.expedie}</td>
                <td className="px-4 py-2.5 text-center text-xs text-red-600 font-medium">{row.anomalie}</td>
                <td className="px-4 py-2.5 text-center text-xs font-bold text-gray-700">{row.total}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}

// ── Page principale ───────────────────────────────────────────────────────────

export default function StockPage() {
  const [search,    setSearch]    = useState('')
  const [statut,    setStatut]    = useState('')
  const [page,      setPage]      = useState(1)
  const [tab,       setTab]       = useState<'liste' | 'inventaire'>('liste')

  const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api'

  const { data, isLoading } = useQuery({
    queryKey: ['stock-central', search, statut, page],
    queryFn:  () => api.get('/stock-central', { params: { search, statut, page, per_page: 50 } }).then(r => r.data),
    placeholderData: (prev: any) => prev,
    enabled: tab === 'liste',
  })

  return (
    <div className="space-y-6">
      {/* En-tête */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Stock Central</h1>
          <p className="text-sm text-gray-500">Passeports en stock, en lot, expédiés et en anomalie</p>
        </div>
        <a
          href={`${apiUrl}/stock-central/export?format=xlsx${statut ? `&statut=${statut}` : ''}${search ? `&search=${search}` : ''}`}
          target="_blank"
          className="flex items-center gap-2 border border-gray-300 text-gray-600 px-4 py-2 rounded-lg text-sm hover:bg-gray-50">
          <Download size={15} /> Export Excel
        </a>
      </div>

      {/* Onglets */}
      <div className="flex gap-1 bg-gray-100 rounded-lg p-1 w-fit">
        {([['liste', 'Liste passeports'], ['inventaire', 'Inventaire ambassades']] as [string, string][]).map(([t, l]) => (
          <button
            key={t}
            onClick={() => setTab(t as any)}
            className={`px-4 py-1.5 text-sm rounded-md font-medium transition ${
              tab === t ? 'bg-white shadow text-[#1a5276]' : 'text-gray-500 hover:text-gray-700'
            }`}>
            {l}
          </button>
        ))}
      </div>

      {tab === 'inventaire' ? (
        <InventaireSection />
      ) : (
        <>
          {/* Filtres */}
          <div className="bg-white rounded-xl shadow-sm border p-4 flex flex-wrap gap-3">
            <div className="flex-1 min-w-[200px] relative">
              <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
              <input
                value={search}
                onChange={(e) => { setSearch(e.target.value); setPage(1) }}
                placeholder="N° passeport, nom, référence..."
                className="w-full pl-9 pr-4 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#1a5276]"
              />
            </div>
            <select
              value={statut}
              onChange={(e) => { setStatut(e.target.value); setPage(1) }}
              className="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1a5276]">
              {STATUTS_STOCK.map(s => (
                <option key={s} value={s}>{s ? statutLabel[s] : 'Tous les statuts'}</option>
              ))}
            </select>
          </div>

          {/* Table */}
          <div className="bg-white rounded-xl shadow-sm border overflow-hidden">
            {isLoading ? (
              <div className="flex justify-center p-12">
                <div className="w-8 h-8 border-2 border-[#1a5276] border-t-transparent rounded-full animate-spin" />
              </div>
            ) : (
              <>
                <table className="w-full text-sm">
                  <thead>
                    <tr className="bg-gray-50 border-b">
                      {['N° Passeport', 'Titulaire', 'Ambassade', 'Pays', 'Statut', 'Lot', 'Réception MAE'].map(h => (
                        <th key={h} className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{h}</th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {data?.data?.length === 0 && (
                      <tr>
                        <td colSpan={7} className="text-center py-12 text-gray-400">
                          <Package size={32} className="mx-auto mb-2 opacity-40" />
                          Aucun passeport trouvé
                        </td>
                      </tr>
                    )}
                    {data?.data?.map((p: any) => (
                      <tr key={p.id} className="border-b hover:bg-gray-50 transition">
                        <td className="px-4 py-3 font-mono font-bold text-[#1a5276] text-xs">{p.numero}</td>
                        <td className="px-4 py-3">
                          <p className="font-medium text-gray-800 text-xs">{p.nom_titulaire} {p.prenom_titulaire}</p>
                          <p className="text-gray-400 text-xs">{p.email_citoyen ?? '—'}</p>
                        </td>
                        <td className="px-4 py-3 text-xs text-gray-600">
                          {p.ambassade_destination?.nom ?? '—'}
                          {p.ambassade_destination?.ville && (
                            <p className="text-gray-400">{p.ambassade_destination.ville}</p>
                          )}
                        </td>
                        <td className="px-4 py-3 text-xs text-gray-500">
                          {p.pays_destination?.nom ?? '—'}
                        </td>
                        <td className="px-4 py-3">
                          <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${statutStyle[p.statut] ?? 'bg-gray-100 text-gray-600'}`}>
                            {statutLabel[p.statut] ?? p.statut}
                          </span>
                        </td>
                        <td className="px-4 py-3 text-xs text-gray-500">
                          {p.lot ? (
                            <span className="font-mono text-[#1a5276]">{p.lot.reference}</span>
                          ) : '—'}
                        </td>
                        <td className="px-4 py-3 text-xs text-gray-500">
                          {formatDate(p.date_reception_mae)}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>

                {/* Pagination */}
                {(data?.last_page ?? 1) > 1 && (
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
        </>
      )}
    </div>
  )
}
