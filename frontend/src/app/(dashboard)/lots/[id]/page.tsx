'use client'

import { useState } from 'react'
import { useParams, useRouter } from 'next/navigation'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import toast from 'react-hot-toast'
import Link from 'next/link'
import {
  ArrowLeft, Package, MapPin, Truck, QrCode, FileDown,
  CheckCircle, Send, AlertTriangle, Clock, History,
  RefreshCw, ChevronDown, ChevronUp,
} from 'lucide-react'
import { formatDate, formatDateTime } from '@/lib/utils'
import { useAuthStore, hasPermission } from '@/stores/authStore'

const statutStyle: Record<string, string> = {
  brouillon:    'bg-slate-100 text-slate-700',
  valide:       'bg-blue-100 text-blue-700',
  expedie:      'bg-yellow-100 text-yellow-800',
  en_transit:   'bg-amber-100 text-amber-800',
  recu:         'bg-green-100 text-green-700',
  recu_partiel: 'bg-orange-100 text-orange-700',
  anomalie:     'bg-red-100 text-red-700',
}

const receptionStyle: Record<string, string> = {
  en_attente: 'bg-slate-100 text-slate-500',
  confirme:   'bg-green-100 text-green-700',
  anomalie:   'bg-orange-100 text-orange-700',
  manquant:   'bg-red-100 text-red-700',
}

function InfoCard({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="bg-slate-50 rounded-lg p-3">
      <p className="text-xs text-slate-500 mb-0.5">{label}</p>
      <div className="text-sm font-medium text-slate-800">{children}</div>
    </div>
  )
}

// ── Onglet QR Code ────────────────────────────────────────────────────────────

function QrCodeTab({ lot }: { lot: any }) {
  const [imgSrc, setImgSrc] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)

  async function loadQr() {
    setLoading(true)
    try {
      const { data } = await api.get(`/lots/${lot.id}/qr-code?base64=true`)
      setImgSrc(`data:${data.mime_type};base64,${data.data}`)
    } catch (e: any) {
      toast.error(e.response?.data?.message || 'QR code non disponible.')
    } finally {
      setLoading(false)
    }
  }

  if (!lot.qr_token) {
    return (
      <div className="text-center py-8 text-slate-400">
        <QrCode size={40} className="mx-auto mb-2 opacity-40" />
        <p className="text-sm">QR code disponible après expédition</p>
      </div>
    )
  }

  return (
    <div className="flex flex-col items-center gap-4 py-4">
      {imgSrc ? (
        <img src={imgSrc} alt="QR Code" className="w-48 h-48 border rounded-xl shadow" />
      ) : (
        <div className="w-48 h-48 border rounded-xl bg-slate-50 flex items-center justify-center">
          {loading
            ? <div className="w-8 h-8 border-2 border-[#1a5276] border-t-transparent rounded-full animate-spin" />
            : <QrCode size={40} className="text-slate-300" />}
        </div>
      )}
      <div className="flex gap-2">
        <button
          onClick={loadQr}
          disabled={loading}
          className="flex items-center gap-2 bg-[#1a5276] text-white px-4 py-2 rounded-lg text-sm hover:bg-[#154360] disabled:opacity-50">
          <QrCode size={14} /> {imgSrc ? 'Rafraîchir' : 'Afficher le QR'}
        </button>
        <a
          href={`${process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api'}/lots/${lot.id}/qr-code`}
          target="_blank"
          className="flex items-center gap-2 border text-slate-600 px-4 py-2 rounded-lg text-sm hover:bg-slate-50">
          <FileDown size={14} /> Télécharger PNG
        </a>
      </div>
      <p className="text-xs text-slate-400 text-center max-w-xs">
        Scanner ce QR code depuis l'ambassade de réception pour confirmer la livraison.
      </p>
    </div>
  )
}

// ── Onglet Historique ─────────────────────────────────────────────────────────

function HistoriqueTab({ lotId }: { lotId: number }) {
  const { data, isLoading } = useQuery({
    queryKey: ['lot-historique', lotId],
    queryFn:  () => api.get(`/lots/${lotId}/historique`).then(r => r.data),
  })

  if (isLoading) return (
    <div className="flex justify-center p-6">
      <div className="w-7 h-7 border-2 border-[#1a5276] border-t-transparent rounded-full animate-spin" />
    </div>
  )

  return (
    <div className="space-y-3 py-2">
      {data?.events?.length === 0 && (
        <p className="text-center text-slate-400 text-sm py-6">Aucun événement enregistré.</p>
      )}
      {data?.events?.map((e: any, i: number) => (
        <div key={i} className="flex gap-3">
          <div className="flex flex-col items-center">
            <div className="w-2.5 h-2.5 rounded-full bg-[#1a5276] mt-1 shrink-0" />
            {i < data.events.length - 1 && <div className="w-0.5 flex-1 bg-slate-200 mt-1" />}
          </div>
          <div className="pb-3 flex-1">
            <p className="text-xs font-semibold text-slate-700">{e.event}</p>
            {e.description && <p className="text-xs text-slate-500 mt-0.5">{e.description}</p>}
            <div className="flex items-center gap-2 mt-1">
              {e.agent && <span className="text-xs text-slate-400">par {e.agent.nom}</span>}
              <span className="text-xs text-slate-400">{formatDateTime(e.date)}</span>
            </div>
          </div>
        </div>
      ))}
    </div>
  )
}

// ── Page principale ───────────────────────────────────────────────────────────

export default function LotDetailPage() {
  const params = useParams()
  const router = useRouter()
  const qc     = useQueryClient()
  const lotId  = params.id as string
  const { user } = useAuthStore()
  const canValidate = hasPermission(user, 'lots.validate')
  const canShip     = hasPermission(user, 'lots.ship')
  const canUpdate   = hasPermission(user, 'lots.update')

  const [tab,         setTab]         = useState<'passeports' | 'qr' | 'historique'>('passeports')
  const [showDetails, setShowDetails] = useState(false)

  const { data: lot, isLoading } = useQuery({
    queryKey: ['lot', lotId],
    queryFn:  () => api.get(`/lots/${lotId}`).then(r => r.data),
  })

  const { data: passeports, isLoading: loadingPasseports } = useQuery({
    queryKey: ['lot-passeports', lotId],
    queryFn:  () => api.get(`/lots/${lotId}/passeports`, { params: { per_page: 100 } }).then(r => r.data),
    enabled:  tab === 'passeports',
  })

  const valider = useMutation({
    mutationFn: () => api.post(`/lots/${lotId}/valider`),
    onSuccess:  () => { toast.success('Lot validé'); qc.invalidateQueries({ queryKey: ['lot', lotId] }) },
    onError:    (e: any) => toast.error(e.response?.data?.message || 'Erreur'),
  })

  const expedier = useMutation({
    mutationFn: () => api.post(`/lots/${lotId}/expedier`),
    onSuccess:  () => { toast.success('Lot expédié — QR code et bordereau générés'); qc.invalidateQueries({ queryKey: ['lot', lotId] }) },
    onError:    (e: any) => toast.error(e.response?.data?.message || 'Erreur'),
  })

  if (isLoading) return (
    <div className="flex items-center justify-center h-64">
      <div className="w-10 h-10 border-2 border-[#1a5276] border-t-transparent rounded-full animate-spin" />
    </div>
  )

  if (!lot) return (
    <div className="text-center text-slate-400 py-16">Lot introuvable.</div>
  )

  const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api'

  return (
    <div className="space-y-6">
      {/* Navigation */}
      <div className="flex items-center gap-3">
        <button onClick={() => router.push('/lots')}
          className="flex items-center gap-1.5 text-sm text-slate-500 hover:text-[#1a5276] transition">
          <ArrowLeft size={15} /> Retour aux lots
        </button>
        <span className="text-slate-300">/</span>
        <span className="font-mono text-sm text-[#1a5276] font-semibold">{lot.reference}</span>
      </div>

      {/* En-tête lot */}
      <div className="bg-white rounded-2xl shadow-sm border overflow-hidden">
        <div className="bg-[#1a5276]/5 border-b px-6 py-5 flex items-start justify-between">
          <div className="flex items-start gap-4">
            <div className="w-12 h-12 bg-[#1a5276]/10 rounded-xl flex items-center justify-center">
              <Package size={22} className="text-[#1a5276]" />
            </div>
            <div>
              <h1 className="text-xl font-bold text-slate-800 font-mono">{lot.reference}</h1>
              <p className="text-sm text-slate-500">Lot d'expédition</p>
            </div>
          </div>
          <div className="flex items-center gap-3">
            <span className={`text-sm px-3 py-1.5 rounded-full font-semibold ${statutStyle[lot.statut] ?? 'bg-slate-100 text-slate-600'}`}>
              {lot.statut_label ?? lot.statut}
            </span>
            {/* Actions */}
            {lot.statut === 'brouillon' && canValidate && (
              <button onClick={() => valider.mutate()} disabled={valider.isPending}
                className="flex items-center gap-1.5 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50">
                {valider.isPending ? <RefreshCw size={13} className="animate-spin" /> : <CheckCircle size={13} />}
                Valider
              </button>
            )}
            {lot.statut === 'valide' && canShip && (
              <button onClick={() => expedier.mutate()} disabled={expedier.isPending}
                className="flex items-center gap-1.5 bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 disabled:opacity-50">
                {expedier.isPending ? <RefreshCw size={13} className="animate-spin" /> : <Send size={13} />}
                Expédier
              </button>
            )}
            {lot.bordereau_path && (
              <button
                onClick={async () => {
                  const res    = await api.get(`/lots/${lot.id}/bordereau`)
                  const bytes  = atob(res.data.data)
                  const buf    = new Uint8Array(bytes.length)
                  for (let i = 0; i < bytes.length; i++) buf[i] = bytes.charCodeAt(i)
                  const blob   = new Blob([buf], { type: 'application/pdf' })
                  const url    = URL.createObjectURL(blob)
                  const a      = document.createElement('a')
                  a.href       = url
                  a.download   = res.data.filename
                  a.click()
                  URL.revokeObjectURL(url)
                }}
                className="flex items-center gap-1.5 border text-slate-600 px-4 py-2 rounded-lg text-sm hover:bg-slate-50">
                <FileDown size={13} /> Bordereau
              </button>
            )}
          </div>
        </div>

        {/* Informations */}
        <div className="px-6 py-4 grid grid-cols-2 md:grid-cols-4 gap-4">
          <InfoCard label="Ambassade">
            <div className="flex items-center gap-1.5">
              <MapPin size={13} className="text-[#1a5276]" />
              {lot.ambassade?.nom ?? '—'}
            </div>
            {lot.ambassade?.ville && <p className="text-xs text-slate-400">{lot.ambassade.ville}</p>}
          </InfoCard>
          <InfoCard label="Transporteur">
            <div className="flex items-center gap-1.5">
              <Truck size={13} className="text-[#1a5276]" />
              {lot.transporteur?.nom ?? '—'}
            </div>
          </InfoCard>
          <InfoCard label="Passeports">
            <span className="text-[#1a5276] font-bold text-xl">{lot.passeports_count ?? 0}</span>
          </InfoCard>
          <InfoCard label="Date expédition">
            {formatDate(lot.date_expedition)}
          </InfoCard>
        </div>

        {/* Détails supplémentaires */}
        <div className="border-t px-6 py-2">
          <button
            onClick={() => setShowDetails(!showDetails)}
            className="flex items-center gap-1.5 text-xs text-slate-500 hover:text-slate-700">
            {showDetails ? <ChevronUp size={13} /> : <ChevronDown size={13} />}
            {showDetails ? 'Masquer' : 'Plus de détails'}
          </button>
          {showDetails && (
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 py-4">
              <InfoCard label="Réception prévue">{formatDate(lot.date_reception_prevue)}</InfoCard>
              <InfoCard label="Réception effective">{formatDateTime(lot.date_reception_effective)}</InfoCard>
              <InfoCard label="Réf. suivi">{lot.reference_suivi ?? '—'}</InfoCard>
              <InfoCard label="Créé par">{lot.cree_par?.name ?? '—'}</InfoCard>
              {lot.notes && (
                <div className="col-span-4 bg-yellow-50 rounded-lg p-3 text-xs text-yellow-800">
                  <strong>Notes :</strong> {lot.notes}
                </div>
              )}
              {lot.commentaire_ambassade && (
                <div className="col-span-4 bg-blue-50 rounded-lg p-3 text-xs text-blue-800">
                  <strong>Commentaire ambassade :</strong> {lot.commentaire_ambassade}
                </div>
              )}
            </div>
          )}
        </div>
      </div>

      {/* Onglets */}
      <div className="flex gap-1 bg-slate-100 rounded-lg p-1 w-fit">
        {([
          ['passeports', `Passeports (${lot.passeports_count ?? 0})`],
          ['qr', 'QR Code'],
          ['historique', 'Historique'],
        ] as [string, string][]).map(([t, l]) => (
          <button key={t} onClick={() => setTab(t as any)}
            className={`px-4 py-1.5 text-sm rounded-md font-medium transition ${
              tab === t ? 'bg-white shadow text-[#1a5276]' : 'text-slate-500 hover:text-slate-700'
            }`}>
            {l}
          </button>
        ))}
      </div>

      {/* Contenu onglet */}
      <div className="bg-white rounded-2xl shadow-sm border p-5">
        {/* Passeports */}
        {tab === 'passeports' && (
          <>
            {loadingPasseports ? (
              <div className="flex justify-center p-8">
                <div className="w-7 h-7 border-2 border-[#1a5276] border-t-transparent rounded-full animate-spin" />
              </div>
            ) : passeports?.data?.length === 0 ? (
              <div className="text-center py-8 text-slate-400">
                <Package size={32} className="mx-auto mb-2 opacity-40" />
                <p className="text-sm">Aucun passeport dans ce lot</p>
                {lot.statut === 'brouillon' && canUpdate && (
                  <Link href={`/lots/${lot.id}/ajouter`} className="mt-2 inline-block text-[#1a5276] text-sm hover:underline">
                    Ajouter des passeports →
                  </Link>
                )}
              </div>
            ) : (
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b bg-slate-50">
                    {['N° Passeport', 'Titulaire', 'Date naissance', 'Statut', 'Réception', 'Note'].map(h => (
                      <th key={h} className="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase">{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {passeports?.data?.map((p: any) => (
                    <tr key={p.id} className="border-b hover:bg-slate-50">
                      <td className="px-4 py-2.5 font-mono text-xs font-bold text-[#1a5276]">{p.numero}</td>
                      <td className="px-4 py-2.5 text-xs font-medium text-slate-700">{p.nom_titulaire} {p.prenom_titulaire}</td>
                      <td className="px-4 py-2.5 text-xs text-slate-500">{formatDate(p.date_naissance)}</td>
                      <td className="px-4 py-2.5">
                        <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${statutStyle[p.statut] ?? 'bg-slate-100'}`}>
                          {p.statut_label ?? p.statut}
                        </span>
                      </td>
                      <td className="px-4 py-2.5">
                        {p.pivot?.statut_reception && (
                          <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${receptionStyle[p.pivot.statut_reception] ?? ''}`}>
                            {p.pivot.statut_reception}
                          </span>
                        )}
                      </td>
                      <td className="px-4 py-2.5 text-xs text-slate-400 max-w-[200px] truncate">
                        {p.pivot?.notes ?? '—'}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </>
        )}

        {/* QR Code */}
        {tab === 'qr' && <QrCodeTab lot={lot} />}

        {/* Historique */}
        {tab === 'historique' && <HistoriqueTab lotId={Number(lotId)} />}
      </div>
    </div>
  )
}
