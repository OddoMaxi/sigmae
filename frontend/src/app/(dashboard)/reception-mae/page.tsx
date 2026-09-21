'use client'

import { useState, useRef, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import toast from 'react-hot-toast'
import {
  Search, CheckCircle, AlertCircle, Clock, RefreshCw,
  Package, User, MapPin, Barcode, Archive, TrendingUp,
} from 'lucide-react'
import { formatDate, formatDateTime } from '@/lib/utils'

// ── Types ────────────────────────────────────────────────────────────────────

interface PasseportResult {
  id: number
  numero: string
  reference_demande?: string
  nom_complet: string
  statut: string
  statut_label: string
  statut_color: string
  date_impression?: string
  date_reception_mae?: string
  received_at?: string
  pays_destination?: { nom: string; code_iso: string }
  ambassade_destination?: { id: number; nom: string; code: string; ville: string }
}

interface ScanResult {
  trouve: boolean
  message?: string
  code?: string
  passeport?: PasseportResult
  action_recommandee?: string
  peut_receptionner?: boolean
}

const statutStyle: Record<string, string> = {
  imprime:              'bg-purple-100 text-purple-700',
  recu_mae:             'bg-blue-100 text-blue-700',
  en_stock:             'bg-green-100 text-green-700',
  en_lot:               'bg-yellow-100 text-yellow-700',
  expedie:              'bg-orange-100 text-orange-700',
  recu_ambassade:       'bg-teal-100 text-teal-700',
  disponible_retrait:   'bg-emerald-100 text-emerald-700',
  remis_citoyen:        'bg-slate-100 text-slate-700',
  anomalie:             'bg-red-100 text-red-700',
}

// ── Composant StatMini ────────────────────────────────────────────────────────

function StatMini({ label, value, color }: { label: string; value: number; color: string }) {
  return (
    <div className="bg-white rounded-xl border p-4 text-center">
      <p className={`text-3xl font-bold ${color}`}>{value}</p>
      <p className="text-xs text-slate-500 mt-1">{label}</p>
    </div>
  )
}

// ── Page principale ───────────────────────────────────────────────────────────

export default function ReceptionMAEPage() {
  const qc = useQueryClient()
  const inputRef   = useRef<HTMLInputElement>(null)
  const numeroRef  = useRef<HTMLInputElement>(null)
  const [identifiant,   setIdentifiant]   = useState('')
  const [scanResult,    setScanResult]    = useState<ScanResult | null>(null)
  const [allerEnStock,  setAllerEnStock]  = useState(true)
  const [dateReception, setDateReception] = useState(new Date().toISOString().split('T')[0])
  const [numeroInput,   setNumeroInput]   = useState('')

  // Focus auto sur le champ de scan
  useEffect(() => { inputRef.current?.focus() }, [])

  // ── Stats du jour ────────────────────────────────────────────────────────

  const { data: stats } = useQuery({
    queryKey: ['reception-mae-stats'],
    queryFn:  () => api.get('/reception-mae/statistiques').then(r => r.data),
    refetchInterval: 15000,
  })

  const { data: recap } = useQuery({
    queryKey: ['reception-mae-recap'],
    queryFn:  () => api.get('/reception-mae/recap').then(r => r.data),
  })

  // ── Passeports du jour ───────────────────────────────────────────────────

  const { data: todayData, isLoading: loadingToday } = useQuery({
    queryKey: ['reception-mae-aujourd-hui'],
    queryFn:  () => api.get('/reception-mae/aujourd-hui', { params: { per_page: 30 } }).then(r => r.data),
    refetchInterval: 10000,
  })

  // ── Scan ─────────────────────────────────────────────────────────────────

  const scanMutation = useMutation({
    mutationFn: (id: string) => api.post('/reception-mae/scanner', { identifiant: id }).then(r => r.data),
    onSuccess: (data: ScanResult) => setScanResult(data),
    onError: (e: any) => {
      if (e.response?.status === 404) {
        setScanResult({ trouve: false, message: 'Passeport introuvable.' })
      } else {
        toast.error('Erreur de scan')
      }
    },
  })

  // ── Validation ───────────────────────────────────────────────────────────

  const validerMutation = useMutation({
    mutationFn: () => api.post('/reception-mae/valider', {
      identifiant:        identifiant.trim(),
      numero:             numeroInput.trim().toUpperCase() || undefined,
      date_reception_mae: dateReception,
      aller_en_stock:     allerEnStock,
    }).then(r => r.data),
    onSuccess: (data) => {
      toast.success(data.message || 'Passeport réceptionné.')
      setScanResult(null)
      setIdentifiant('')
      setNumeroInput('')
      inputRef.current?.focus()
      qc.invalidateQueries({ queryKey: ['reception-mae-aujourd-hui'] })
      qc.invalidateQueries({ queryKey: ['reception-mae-stats'] })
      qc.invalidateQueries({ queryKey: ['reception-mae-recap'] })
    },
    onError: (e: any) => {
      const msg = e.response?.data?.message || 'Erreur lors de la validation.'
      const code = e.response?.data?.code
      if (code === 'deja_traite') {
        toast.error(`Doublon : ${msg}`)
      } else {
        toast.error(msg)
      }
    },
  })

  // ── Handlers ─────────────────────────────────────────────────────────────

  function handleSearch(e: React.FormEvent) {
    e.preventDefault()
    const val = identifiant.trim()
    if (!val) return
    setScanResult(null)
    setNumeroInput('')
    scanMutation.mutate(val)
  }

  function handleKeyDown(e: React.KeyboardEvent) {
    if (e.key === 'Enter') handleSearch(e as any)
  }

  const isEnrolee   = scanResult?.trouve && scanResult.passeport?.statut === 'enrolee'
  const isImprime   = scanResult?.trouve && scanResult.passeport?.statut === 'imprime'
  const canValidate = isImprime || (isEnrolee && numeroInput.trim().length > 0)

  return (
    <div className="space-y-6">
      {/* En-tête */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-[22px] font-bold text-[color:var(--color-navy-900)] tracking-tight">Réception MAE</h1>
          <p className="text-sm text-slate-500">Réception des passeports imprimés depuis l'imprimerie</p>
        </div>
        <div className="flex items-center gap-2 text-sm text-slate-500">
          <Clock size={15} />
          {new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}
        </div>
      </div>

      {/* Statistiques du jour */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <StatMini label="Reçus aujourd'hui"  value={stats?.recus_total ?? 0}           color="text-[#1a5276]" />
        <StatMini label="En attente (imprimé)" value={recap?.en_attente_imprime ?? 0}  color="text-purple-600" />
        <StatMini label="Reçu MAE (en attente stock)" value={recap?.en_attente_stock_mae ?? 0} color="text-blue-600" />
        <StatMini label="En stock"             value={recap?.en_stock ?? 0}            color="text-green-600" />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Zone de scan */}
        <div className="space-y-4">
          <div className="bg-white rounded-xl shadow-sm border p-5 space-y-4">
            <div className="flex items-center gap-2 text-slate-700">
              <Barcode size={18} className="text-[#1a5276]" />
              <h2 className="font-semibold">Scanner / Saisir un passeport</h2>
            </div>

            <form onSubmit={handleSearch} className="flex gap-2">
              <div className="flex-1 relative">
                <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                <input
                  ref={inputRef}
                  value={identifiant}
                  onChange={(e) => { setIdentifiant(e.target.value); setScanResult(null) }}
                  onKeyDown={handleKeyDown}
                  placeholder="N° passeport ou référence demande..."
                  className="w-full pl-9 pr-4 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#1a5276]"
                  autoComplete="off"
                />
              </div>
              <button
                type="submit"
                disabled={!identifiant.trim() || scanMutation.isPending}
                className="bg-[#1a5276] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-[#154360] disabled:opacity-50 flex items-center gap-2">
                {scanMutation.isPending ? <RefreshCw size={14} className="animate-spin" /> : <Search size={14} />}
                Chercher
              </button>
            </form>

            {/* Options de réception */}
            <div className="bg-slate-50 rounded-lg p-3 space-y-3">
              <div>
                <label className="text-xs text-slate-500 font-medium block mb-1">Date de réception</label>
                <input
                  type="date"
                  value={dateReception}
                  onChange={(e) => setDateReception(e.target.value)}
                  className="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#1a5276]"
                />
              </div>
              <label className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={allerEnStock}
                  onChange={(e) => setAllerEnStock(e.target.checked)}
                  className="accent-[#1a5276]"
                />
                <span className="text-sm text-slate-700">Passer directement en stock (REÇU_MAE → EN_STOCK)</span>
              </label>
            </div>
          </div>

          {/* Résultat du scan */}
          {scanResult && (
            <div className={`bg-white rounded-xl border shadow-sm p-5 ${
              !scanResult.trouve ? 'border-red-200 bg-red-50' : ''
            }`}>
              {!scanResult.trouve ? (
                <div className="flex items-center gap-3 text-red-600">
                  <AlertCircle size={20} />
                  <div>
                    <p className="font-semibold text-sm">Passeport introuvable</p>
                    <p className="text-xs text-red-500">{scanResult.message}</p>
                  </div>
                </div>
              ) : (
                <div className="space-y-4">
                  {/* Fiche passeport */}
                  <div className="flex items-start justify-between">
                    <div className="flex items-start gap-3">
                      <div className="w-10 h-10 bg-[#1a5276]/10 rounded-full flex items-center justify-center shrink-0">
                        <User size={18} className="text-[#1a5276]" />
                      </div>
                      <div>
                        <p className="font-bold text-slate-800">{scanResult.passeport?.nom_complet}</p>
                        <p className="font-mono text-sm text-[#1a5276] font-semibold">{scanResult.passeport?.numero}</p>
                        {scanResult.passeport?.reference_demande && (
                          <p className="text-xs text-slate-400">Réf: {scanResult.passeport.reference_demande}</p>
                        )}
                      </div>
                    </div>
                    <span className={`text-xs px-2.5 py-1 rounded-full font-semibold ${
                      statutStyle[scanResult.passeport?.statut ?? ''] ?? 'bg-slate-100 text-slate-600'
                    }`}>
                      {scanResult.passeport?.statut_label}
                    </span>
                  </div>

                  <div className="grid grid-cols-2 gap-3 text-xs">
                    {scanResult.passeport?.pays_destination && (
                      <div className="flex items-center gap-1.5 text-slate-600">
                        <MapPin size={12} className="text-[#1a5276]" />
                        <span>{scanResult.passeport.pays_destination.nom}</span>
                      </div>
                    )}
                    {scanResult.passeport?.ambassade_destination && (
                      <div className="flex items-center gap-1.5 text-slate-600">
                        <Package size={12} className="text-[#1a5276]" />
                        <span>{scanResult.passeport.ambassade_destination.nom}</span>
                      </div>
                    )}
                    {scanResult.passeport?.date_impression && (
                      <div className="text-slate-500">
                        <span className="font-medium">Imprimé : </span>
                        {formatDate(scanResult.passeport.date_impression)}
                      </div>
                    )}
                  </div>

                  {/* Cas enrôlé : numéro requis */}
                  {isEnrolee && (
                    <div className="bg-violet-50 border border-violet-200 rounded-lg p-3 space-y-2">
                      <p className="text-xs text-violet-800 font-semibold flex items-center gap-1.5">
                        <AlertCircle size={13} className="shrink-0" />
                        Dossier enrôlé — numéro de passeport requis
                      </p>
                      <p className="text-xs text-violet-600">
                        Le numéro n'a pas encore été assigné dans le système. Saisissez le numéro imprimé sur le passeport physique.
                      </p>
                      <input
                        ref={numeroRef}
                        value={numeroInput}
                        onChange={e => setNumeroInput(e.target.value.toUpperCase())}
                        onKeyDown={e => e.key === 'Enter' && canValidate && validerMutation.mutate()}
                        placeholder="Ex : PA123456"
                        className="w-full border border-violet-300 rounded-lg px-3 py-2 text-sm font-mono font-bold tracking-wider focus:outline-none focus:ring-2 focus:ring-violet-400"
                        autoFocus
                      />
                    </div>
                  )}

                  {/* Alerte doublon / statut incompatible */}
                  {!isImprime && !isEnrolee && (
                    <div className="bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 flex items-center gap-2">
                      <AlertCircle size={14} className="text-amber-600 shrink-0" />
                      <p className="text-xs text-amber-700">
                        <strong>Attention :</strong> Ce passeport a déjà été traité (statut : {scanResult.passeport?.statut_label}).
                      </p>
                    </div>
                  )}

                  {/* Bouton validation */}
                  {canValidate && (
                    <button
                      onClick={() => validerMutation.mutate()}
                      disabled={validerMutation.isPending}
                      className="w-full bg-green-600 hover:bg-green-700 text-white rounded-xl py-3 text-sm font-semibold flex items-center justify-center gap-2 transition disabled:opacity-50">
                      {validerMutation.isPending
                        ? <><RefreshCw size={15} className="animate-spin" /> Réception en cours…</>
                        : <><CheckCircle size={15} /> Confirmer la réception</>
                      }
                    </button>
                  )}
                </div>
              )}
            </div>
          )}
        </div>

        {/* Tableau passeports du jour */}
        <div className="bg-white rounded-xl shadow-sm border overflow-hidden">
          <div className="px-5 py-4 border-b flex items-center justify-between">
            <div className="flex items-center gap-2">
              <Archive size={16} className="text-[#1a5276]" />
              <h3 className="font-semibold text-slate-800 text-sm">Réceptionnés aujourd'hui</h3>
            </div>
            <span className="text-xs text-slate-400">
              {todayData?.total ?? 0} passeport(s)
            </span>
          </div>

          {loadingToday ? (
            <div className="flex justify-center p-8">
              <div className="w-7 h-7 border-2 border-[#1a5276] border-t-transparent rounded-full animate-spin" />
            </div>
          ) : todayData?.data?.length === 0 ? (
            <div className="flex flex-col items-center justify-center p-10 text-slate-400">
              <Package size={32} className="mb-2 opacity-50" />
              <p className="text-sm">Aucun passeport reçu aujourd'hui</p>
            </div>
          ) : (
            <div className="overflow-y-auto max-h-[500px]">
              <table className="w-full text-sm">
                <thead className="sticky top-0 bg-slate-50">
                  <tr className="border-b">
                    <th className="px-4 py-2 text-left text-xs font-semibold text-slate-500">N° Passeport</th>
                    <th className="px-4 py-2 text-left text-xs font-semibold text-slate-500">Titulaire</th>
                    <th className="px-4 py-2 text-left text-xs font-semibold text-slate-500">Statut</th>
                    <th className="px-4 py-2 text-left text-xs font-semibold text-slate-500">Heure</th>
                  </tr>
                </thead>
                <tbody>
                  {todayData?.data?.map((p: any) => (
                    <tr key={p.id} className="border-b hover:bg-slate-50 transition">
                      <td className="px-4 py-2.5 font-mono text-xs font-semibold text-[#1a5276]">{p.numero}</td>
                      <td className="px-4 py-2.5 text-xs">
                        <p className="font-medium text-slate-700">{p.nom_titulaire} {p.prenom_titulaire}</p>
                        <p className="text-slate-400">{p.ambassade_destination?.nom ?? '—'}</p>
                      </td>
                      <td className="px-4 py-2.5">
                        <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${
                          statutStyle[p.statut] ?? 'bg-slate-100'
                        }`}>
                          {p.statut_label}
                        </span>
                      </td>
                      <td className="px-4 py-2.5 text-xs text-slate-400">
                        {p.agent_reception?.heure ?? (p.received_at ? new Date(p.received_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '—')}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {/* Distribution horaire résumée */}
          {stats?.distribution_horaire?.length > 0 && (
            <div className="border-t px-5 py-3">
              <div className="flex items-center gap-1.5 text-xs text-slate-500 mb-2">
                <TrendingUp size={12} /> Distribution horaire
              </div>
              <div className="flex gap-1 items-end h-10">
                {stats.distribution_horaire
                  .filter((h: any) => h.heure >= 7 && h.heure <= 18)
                  .map((h: any) => {
                    const max = Math.max(...stats.distribution_horaire.map((x: any) => x.total), 1)
                    const pct = Math.round((h.total / max) * 100)
                    return (
                      <div key={h.heure} className="flex-1 flex flex-col items-center gap-0.5" title={`${h.label}: ${h.total}`}>
                        <div
                          className="w-full bg-[#1a5276]/70 rounded-sm"
                          style={{ height: `${Math.max(pct * 0.36, 2)}px` }}
                        />
                        <span className="text-slate-400" style={{ fontSize: '9px' }}>{h.heure}</span>
                      </div>
                    )
                  })}
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  )
}
