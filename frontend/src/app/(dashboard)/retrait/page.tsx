'use client'

import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import Link from 'next/link'
import api from '@/lib/api'
import { CheckCircle, UserCheck, Search, HandCoins, ExternalLink, User, Building2 } from 'lucide-react'
import toast from 'react-hot-toast'

type Tab = 'recu_ambassade' | 'disponible_retrait'

function StatBadge({ count, color }: { count: number; color: string }) {
  return (
    <span className={`ml-2 text-xs font-bold px-2 py-0.5 rounded-full ${color}`}>
      {count}
    </span>
  )
}

export default function RetraitPage() {
  const qc = useQueryClient()
  const [tab, setTab]       = useState<Tab>('recu_ambassade')
  const [search, setSearch] = useState('')

  const { data: recusData, isLoading: loadingRecus } = useQuery({
    queryKey: ['passeports-recu-ambassade'],
    queryFn: () => api.get('/passeports', { params: { statut: 'recu_ambassade', per_page: 100 } }).then(r => r.data),
    refetchInterval: 30000,
  })

  const { data: dispoData, isLoading: loadingDispo } = useQuery({
    queryKey: ['passeports-disponible-retrait'],
    queryFn: () => api.get('/passeports', { params: { statut: 'disponible_retrait', per_page: 100 } }).then(r => r.data),
    refetchInterval: 30000,
  })

  const recus = (recusData?.data ?? recusData ?? []) as any[]
  const dispos = (dispoData?.data ?? dispoData ?? []) as any[]

  const filter = (list: any[]) =>
    search.trim()
      ? list.filter(p =>
          `${p.nom_titulaire} ${p.prenom_titulaire} ${p.numero} ${p.reference_demande}`
            .toLowerCase().includes(search.toLowerCase())
        )
      : list

  const filteredRecus  = filter(recus)
  const filteredDispos = filter(dispos)
  const displayed      = tab === 'recu_ambassade' ? filteredRecus : filteredDispos
  const isLoading      = tab === 'recu_ambassade' ? loadingRecus : loadingDispo

  const disponible = useMutation({
    mutationFn: (id: number) => api.post(`/passeports/${id}/disponible-retrait`),
    onSuccess: () => {
      toast.success('Disponible au retrait — email envoyé au citoyen')
      qc.invalidateQueries({ queryKey: ['passeports-recu-ambassade'] })
      qc.invalidateQueries({ queryKey: ['passeports-disponible-retrait'] })
    },
    onError: (e: any) => toast.error(e.response?.data?.message || 'Erreur'),
  })

  const remettre = useMutation({
    mutationFn: (p: any) => api.post(`/passeports/${p.id}/remettre-citoyen`),
    onSuccess: () => {
      toast.success('Passeport remis au citoyen ✓')
      qc.invalidateQueries({ queryKey: ['passeports-disponible-retrait'] })
    },
    onError: (e: any) => toast.error(e.response?.data?.message || 'Erreur'),
  })

  return (
    <div className="space-y-6">
      {/* En-tête */}
      <div>
        <h1 className="text-2xl font-bold text-gray-800">Retrait des passeports</h1>
        <p className="text-sm text-gray-500 mt-1">Gérez la mise à disposition et la remise des passeports aux citoyens</p>
      </div>

      {/* KPIs */}
      <div className="grid grid-cols-2 gap-4">
        <div className="bg-teal-50 border border-teal-200 rounded-xl p-4 flex items-center gap-4">
          <div className="p-3 bg-teal-100 rounded-lg">
            <HandCoins size={22} className="text-teal-600" />
          </div>
          <div>
            <p className="text-2xl font-bold text-teal-700">{recus.length}</p>
            <p className="text-sm text-teal-600">Reçus — en attente de mise à disposition</p>
          </div>
        </div>
        <div className="bg-green-50 border border-green-200 rounded-xl p-4 flex items-center gap-4">
          <div className="p-3 bg-green-100 rounded-lg">
            <UserCheck size={22} className="text-green-600" />
          </div>
          <div>
            <p className="text-2xl font-bold text-green-700">{dispos.length}</p>
            <p className="text-sm text-green-600">Disponibles — en attente de retrait citoyen</p>
          </div>
        </div>
      </div>

      {/* Onglets + recherche */}
      <div className="flex items-center justify-between gap-4 flex-wrap">
        <div className="flex border border-gray-200 rounded-lg overflow-hidden bg-white">
          <button
            onClick={() => setTab('recu_ambassade')}
            className={`px-4 py-2 text-sm font-medium transition flex items-center ${
              tab === 'recu_ambassade'
                ? 'bg-teal-600 text-white'
                : 'text-gray-600 hover:bg-gray-50'
            }`}
          >
            <HandCoins size={15} className="mr-2" />
            Reçus ambassade
            <StatBadge count={filteredRecus.length} color={tab === 'recu_ambassade' ? 'bg-white/20 text-white' : 'bg-teal-100 text-teal-700'} />
          </button>
          <button
            onClick={() => setTab('disponible_retrait')}
            className={`px-4 py-2 text-sm font-medium transition flex items-center border-l border-gray-200 ${
              tab === 'disponible_retrait'
                ? 'bg-green-600 text-white'
                : 'text-gray-600 hover:bg-gray-50'
            }`}
          >
            <UserCheck size={15} className="mr-2" />
            Disponibles retrait
            <StatBadge count={filteredDispos.length} color={tab === 'disponible_retrait' ? 'bg-white/20 text-white' : 'bg-green-100 text-green-700'} />
          </button>
        </div>

        <div className="relative">
          <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
          <input
            value={search}
            onChange={e => setSearch(e.target.value)}
            placeholder="Nom, numéro, référence…"
            className="pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#1a5276] w-64"
          />
        </div>
      </div>

      {/* Tableau */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        {isLoading ? (
          <div className="flex items-center justify-center h-40">
            <div className="animate-spin rounded-full h-8 w-8 border-2 border-[#1a5276] border-t-transparent" />
          </div>
        ) : displayed.length === 0 ? (
          <div className="text-center py-16 text-gray-400">
            <CheckCircle size={36} className="mx-auto mb-3 opacity-30" />
            <p className="text-sm">
              {search ? 'Aucun résultat pour cette recherche.' : 'Aucun passeport dans cet état.'}
            </p>
          </div>
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="bg-gray-50 border-b">
                <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Titulaire</th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">N° Passeport</th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Contact</th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Ambassade</th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">
                  {tab === 'recu_ambassade' ? 'Réceptionné le' : 'Disponible depuis'}
                </th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Actions</th>
              </tr>
            </thead>
            <tbody>
              {displayed.map((p: any) => (
                <tr key={p.id} className="border-b hover:bg-gray-50 transition">
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-2">
                      <User size={16} className="text-gray-300 shrink-0" />
                      <div>
                        <p className="font-medium text-gray-800">{p.prenom_titulaire} {p.nom_titulaire}</p>
                        {p.reference_demande && (
                          <p className="text-xs text-violet-500 font-mono">{p.reference_demande}</p>
                        )}
                      </div>
                    </div>
                  </td>
                  <td className="px-4 py-3 font-mono text-sm text-[#1a5276] font-semibold">
                    {p.numero ?? '—'}
                  </td>
                  <td className="px-4 py-3">
                    <div className="text-xs text-gray-500 space-y-0.5">
                      {p.email_citoyen && <p>{p.email_citoyen}</p>}
                      {p.telephone    && <p>{p.telephone}</p>}
                    </div>
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-1 text-xs text-gray-500">
                      <Building2 size={13} className="shrink-0" />
                      {p.ambassade_destination?.nom ?? '—'}
                    </div>
                  </td>
                  <td className="px-4 py-3 text-xs text-gray-400">
                    {tab === 'recu_ambassade'
                      ? (p.received_at ? new Date(p.received_at).toLocaleDateString('fr-FR') : '—')
                      : (p.disponible_at ? new Date(p.disponible_at).toLocaleDateString('fr-FR') : '—')
                    }
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-2">
                      {tab === 'recu_ambassade' && (
                        <button
                          onClick={() => disponible.mutate(p.id)}
                          disabled={disponible.isPending}
                          className="flex items-center gap-1.5 bg-teal-600 hover:bg-teal-700 text-white text-xs px-3 py-1.5 rounded-lg disabled:opacity-60 transition"
                        >
                          <CheckCircle size={13} />
                          Disponible retrait
                        </button>
                      )}
                      {tab === 'disponible_retrait' && (
                        <button
                          onClick={() => {
                            if (confirm(`Confirmer la remise à ${p.prenom_titulaire} ${p.nom_titulaire} ?`))
                              remettre.mutate(p)
                          }}
                          disabled={remettre.isPending}
                          className="flex items-center gap-1.5 bg-green-600 hover:bg-green-700 text-white text-xs px-3 py-1.5 rounded-lg disabled:opacity-60 transition"
                        >
                          <UserCheck size={13} />
                          Remis au citoyen
                        </button>
                      )}
                      <Link href={`/passeports/${p.id}`}
                        className="text-gray-400 hover:text-[#1a5276] transition" title="Voir la fiche">
                        <ExternalLink size={15} />
                      </Link>
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
