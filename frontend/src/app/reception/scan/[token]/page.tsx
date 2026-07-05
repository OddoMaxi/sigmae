'use client'

import { useEffect, useState } from 'react'
import { useParams } from 'next/navigation'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import axios from 'axios'
import toast from 'react-hot-toast'
import {
  Shield, Package, CheckCircle, AlertTriangle, XCircle,
  Eye, EyeOff, ChevronDown, ChevronUp, Clock, MapPin,
  Truck, Hash, User, RefreshCw,
} from 'lucide-react'
import { useAuthStore } from '@/stores/authStore'
import api from '@/lib/api'

// ── Types ────────────────────────────────────────────────────────────────────

interface LotSummary {
  id: number
  reference: string
  statut: string
  statut_label: string
  statut_color: string
  nb_passeports: number
  date_expedition: string | null
  date_prevue: string | null
  ambassade: { id: number; nom: string; code: string; ville: string } | null
  transporteur: { id: number; nom: string; type: string } | null
}

interface ScanPublicResponse {
  valid: boolean
  error_code?: string
  error_message?: string
  requires_auth?: boolean
  lot?: LotSummary
  token_expires_at?: string
  token_issued_at?: string
  lot_statut?: string
  lot_statut_label?: string
  ambassade?: { id: number; nom: string; code: string; ville: string }
}

interface Passeport {
  id: number
  numero: string
  nom_complet: string
  date_naissance: string | null
  statut: string
  statut_label: string
  email_citoyen: string | null
  pays_destination: { nom: string; code_iso: string } | null
  reception: {
    statut: 'en_attente' | 'confirme' | 'anomalie' | 'manquant'
    confirme_at: string | null
    notes: string | null
  }
}

interface DetailResponse {
  lot: LotSummary & {
    reference_suivi?: string
    notes?: string
    commentaire_ambassade?: string
    en_attente: number
    confirmes: number
    anomalies: number
    manquants: number
  }
  passeports: Passeport[]
  token_info: { issued_at: string; expires_at: string; version: number }
}

type PasseportAction = 'confirme' | 'anomalie' | 'manquant'

interface ConfirmationRow {
  passeport_id: number
  statut: PasseportAction
  notes?: string
}

// ── Schéma login ──────────────────────────────────────────────────────────────

const loginSchema = z.object({
  email:    z.string().email('Email invalide'),
  password: z.string().min(1, 'Mot de passe requis'),
})
type LoginForm = z.infer<typeof loginSchema>

// ── Helpers UI ────────────────────────────────────────────────────────────────

const STATUT_STYLE: Record<string, string> = {
  brouillon:    'bg-gray-100 text-gray-700',
  valide:       'bg-blue-100 text-blue-700',
  expedie:      'bg-yellow-100 text-yellow-800',
  en_transit:   'bg-amber-100 text-amber-800',
  recu:         'bg-green-100 text-green-700',
  recu_partiel: 'bg-orange-100 text-orange-700',
  anomalie:     'bg-red-100 text-red-700',
}

const ACTION_STYLE: Record<PasseportAction, string> = {
  confirme: 'border-green-500 bg-green-50 text-green-700',
  anomalie: 'border-orange-400 bg-orange-50 text-orange-700',
  manquant: 'border-red-400 bg-red-50 text-red-700',
}

function Badge({ statut, label }: { statut: string; label: string }) {
  return (
    <span className={`text-xs px-2.5 py-1 rounded-full font-semibold ${STATUT_STYLE[statut] ?? 'bg-gray-100 text-gray-600'}`}>
      {label}
    </span>
  )
}

function InfoRow({ icon, label, value }: { icon: React.ReactNode; label: string; value: string | number | null | undefined }) {
  if (!value && value !== 0) return null
  return (
    <div className="flex items-start gap-2 text-sm">
      <span className="text-[#1a5276] mt-0.5 shrink-0">{icon}</span>
      <div>
        <span className="text-gray-500 text-xs">{label}</span>
        <p className="font-medium text-gray-800">{value}</p>
      </div>
    </div>
  )
}

// ── Composant principal ───────────────────────────────────────────────────────

export default function ScanPage() {
  const params  = useParams()
  const token   = decodeURIComponent(params.token as string)

  const { user, isAuthenticated, login } = useAuthStore()

  // États principaux
  const [phase, setPhase]             = useState<'loading' | 'error' | 'summary' | 'login' | 'detail' | 'done'>('loading')
  const [scanData, setScanData]       = useState<ScanPublicResponse | null>(null)
  const [detail, setDetail]           = useState<DetailResponse | null>(null)
  const [loadingDetail, setLoadingDetail] = useState(false)

  // États de confirmation
  const [confirmations, setConfirmations] = useState<Record<number, ConfirmationRow>>({})
  const [notes, setNotes]             = useState<Record<number, string>>({})
  const [showNoteFor, setShowNoteFor] = useState<number | null>(null)
  const [commentaire, setCommentaire] = useState('')
  const [allerDisponible, setAllerDisponible] = useState(false)
  const [submitting, setSubmitting]   = useState(false)

  // Login inline
  const [showPw, setShowPw]           = useState(false)
  const { register, handleSubmit, formState: { errors, isSubmitting: loginLoading } } = useForm<LoginForm>({
    resolver: zodResolver(loginSchema),
  })

  // ── Étape 1 : scan public ────────────────────────────────────────────────

  useEffect(() => {
    const baseUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api'
    axios.get<ScanPublicResponse>(`${baseUrl}/reception/scan/${encodeURIComponent(token)}`)
      .then(({ data }) => {
        setScanData(data)
        if (!data.valid) {
          setPhase('error')
        } else if (data.error_code === 'ALREADY_RECEIVED') {
          setPhase('error')
        } else {
          setPhase('summary')
        }
      })
      .catch(() => {
        setScanData({ valid: false, error_code: 'NETWORK_ERROR', error_message: 'Impossible de joindre le serveur.' })
        setPhase('error')
      })
  }, [token])

  // ── Étape 2 : chargement détail si déjà connecté ─────────────────────────

  useEffect(() => {
    if (phase === 'summary' && isAuthenticated) {
      loadDetail()
    }
  }, [phase, isAuthenticated])

  async function loadDetail() {
    setLoadingDetail(true)
    try {
      const { data } = await api.get<DetailResponse>(`/reception/detail/${encodeURIComponent(token)}`)
      setDetail(data)
      // Pré-initialiser les confirmations avec le statut actuel du pivot
      const init: Record<number, ConfirmationRow> = {}
      data.passeports.forEach((p) => {
        if (p.reception.statut !== 'en_attente') {
          init[p.id] = { passeport_id: p.id, statut: p.reception.statut as PasseportAction, notes: p.reception.notes ?? '' }
        }
      })
      setConfirmations(init)
      setPhase('detail')
    } catch (err: any) {
      const code = err.response?.data?.error_code
      if (err.response?.status === 403 || code === 'AMBASSADE_MISMATCH') {
        toast.error(err.response?.data?.message || "Vous n'êtes pas autorisé pour ce lot.")
        setPhase('summary')
      } else if (err.response?.status === 401) {
        setPhase('login')
      } else {
        toast.error(err.response?.data?.message || 'Erreur lors du chargement des détails.')
        setPhase('summary')
      }
    } finally {
      setLoadingDetail(false)
    }
  }

  // ── Login inline ─────────────────────────────────────────────────────────

  async function onLogin(data: LoginForm) {
    try {
      await login(data.email, data.password)
      toast.success('Connecté')
      await loadDetail()
    } catch {
      toast.error('Identifiants incorrects')
    }
  }

  // ── Gestion des confirmations ─────────────────────────────────────────────

  function setAction(passeportId: number, action: PasseportAction) {
    setConfirmations((prev) => ({
      ...prev,
      [passeportId]: { passeport_id: passeportId, statut: action, notes: notes[passeportId] ?? '' },
    }))
  }

  function removeAction(passeportId: number) {
    setConfirmations((prev) => {
      const next = { ...prev }
      delete next[passeportId]
      return next
    })
  }

  function updateNote(passeportId: number, note: string) {
    setNotes((prev) => ({ ...prev, [passeportId]: note }))
    setConfirmations((prev) => {
      if (!prev[passeportId]) return prev
      return { ...prev, [passeportId]: { ...prev[passeportId], notes: note } }
    })
  }

  function selectAll(action: PasseportAction) {
    if (!detail) return
    const eligible = detail.passeports.filter((p) => p.reception.statut === 'en_attente')
    const init: Record<number, ConfirmationRow> = { ...confirmations }
    eligible.forEach((p) => { init[p.id] = { passeport_id: p.id, statut: action, notes: '' } })
    setConfirmations(init)
  }

  // ── Soumission ────────────────────────────────────────────────────────────

  async function submitConfirmation() {
    const rows = Object.values(confirmations)
    if (rows.length === 0) {
      toast.error('Sélectionnez au moins un passeport.')
      return
    }
    setSubmitting(true)
    try {
      await api.post(`/reception/lot/${encodeURIComponent(token)}`, {
        confirmations: rows,
        commentaire: commentaire || undefined,
        aller_disponible_retrait: allerDisponible,
      })
      toast.success('Réception enregistrée avec succès.')
      setPhase('done')
    } catch (err: any) {
      toast.error(err.response?.data?.error_message || err.response?.data?.message || 'Erreur lors de la soumission.')
    } finally {
      setSubmitting(false)
    }
  }

  // ── Rendu ─────────────────────────────────────────────────────────────────

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50">
      {/* En-tête */}
      <div className="bg-[#1a5276] text-white px-4 py-4">
        <div className="max-w-2xl mx-auto flex items-center gap-3">
          <Shield size={22} className="opacity-80" />
          <div>
            <p className="font-bold text-sm">SGP-GE — Réception Ambassade</p>
            <p className="text-xs opacity-60">Système de Gestion des Passeports</p>
          </div>
        </div>
      </div>

      <div className="max-w-2xl mx-auto px-4 py-8 space-y-6">

        {/* ── Phase : chargement ── */}
        {phase === 'loading' && (
          <div className="bg-white rounded-2xl shadow-sm border p-10 flex flex-col items-center gap-3">
            <div className="w-10 h-10 border-4 border-[#1a5276] border-t-transparent rounded-full animate-spin" />
            <p className="text-gray-500 text-sm">Vérification du QR code…</p>
          </div>
        )}

        {/* ── Phase : erreur ── */}
        {phase === 'error' && scanData && (
          <div className="bg-white rounded-2xl shadow-sm border p-8 text-center space-y-4">
            {scanData.error_code === 'ALREADY_RECEIVED' ? (
              <>
                <CheckCircle size={48} className="mx-auto text-green-500" />
                <h2 className="text-lg font-bold text-gray-800">Lot déjà réceptionné</h2>
                <p className="text-sm text-gray-500">
                  Le lot <strong>{scanData.lot?.reference ?? ''}</strong>{' '}
                  ({scanData.lot_statut_label ?? ''}) a déjà été traité.
                </p>
                {scanData.ambassade && (
                  <p className="text-xs text-gray-400">{scanData.ambassade.nom}</p>
                )}
              </>
            ) : (
              <>
                <XCircle size={48} className="mx-auto text-red-400" />
                <h2 className="text-lg font-bold text-gray-800">QR code invalide</h2>
                <p className="text-sm text-gray-500">{scanData.error_message}</p>
                <span className="inline-block bg-red-50 text-red-600 text-xs px-3 py-1 rounded-full font-mono">
                  {scanData.error_code}
                </span>
              </>
            )}
          </div>
        )}

        {/* ── Phase : résumé public ── */}
        {(phase === 'summary' || phase === 'login') && scanData?.lot && (
          <div className="bg-white rounded-2xl shadow-sm border overflow-hidden">
            <div className="bg-[#1a5276]/5 border-b px-6 py-4 flex items-center justify-between">
              <div className="flex items-center gap-3">
                <Package size={20} className="text-[#1a5276]" />
                <div>
                  <p className="font-bold text-gray-800 font-mono">{scanData.lot.reference}</p>
                  <p className="text-xs text-gray-500">Lot d'expédition</p>
                </div>
              </div>
              <Badge statut={scanData.lot.statut} label={scanData.lot.statut_label} />
            </div>

            <div className="px-6 py-5 grid grid-cols-2 gap-4">
              <InfoRow icon={<MapPin size={14} />} label="Ambassade" value={scanData.lot.ambassade?.nom} />
              <InfoRow icon={<Hash size={14} />} label="Passeports" value={scanData.lot.nb_passeports} />
              <InfoRow icon={<Truck size={14} />} label="Transporteur" value={scanData.lot.transporteur?.nom} />
              <InfoRow icon={<Clock size={14} />} label="Réception prévue" value={
                scanData.lot.date_prevue ? new Date(scanData.lot.date_prevue).toLocaleDateString('fr-FR') : null
              } />
            </div>

            {scanData.token_issued_at && (
              <p className="px-6 pb-4 text-xs text-gray-400">
                QR généré le {new Date(scanData.token_issued_at).toLocaleString('fr-FR')} •{' '}
                expire le {new Date(scanData.token_expires_at!).toLocaleDateString('fr-FR')}
              </p>
            )}
          </div>
        )}

        {/* ── Phase : connexion ── */}
        {phase === 'login' && (
          <div className="bg-white rounded-2xl shadow-sm border p-6 space-y-4">
            <div className="flex items-center gap-2 text-[#1a5276]">
              <User size={18} />
              <h3 className="font-semibold">Connexion requise pour réceptionner</h3>
            </div>
            <p className="text-sm text-gray-500">
              Identifiez-vous avec votre compte ambassade pour accéder au contenu du lot.
            </p>

            <form onSubmit={handleSubmit(onLogin)} className="space-y-3">
              <div>
                <input
                  {...register('email')}
                  type="email"
                  placeholder="email@ambassade.gov"
                  className="w-full border rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1a5276]"
                />
                {errors.email && <p className="text-red-500 text-xs mt-1">{errors.email.message}</p>}
              </div>
              <div className="relative">
                <input
                  {...register('password')}
                  type={showPw ? 'text' : 'password'}
                  placeholder="Mot de passe"
                  className="w-full border rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1a5276] pr-10"
                />
                <button type="button" onClick={() => setShowPw(!showPw)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">
                  {showPw ? <EyeOff size={15} /> : <Eye size={15} />}
                </button>
                {errors.password && <p className="text-red-500 text-xs mt-1">{errors.password.message}</p>}
              </div>
              <button
                type="submit"
                disabled={loginLoading || loadingDetail}
                className="w-full bg-[#1a5276] text-white rounded-lg py-2.5 text-sm font-medium hover:bg-[#154360] disabled:opacity-50 flex items-center justify-center gap-2">
                {(loginLoading || loadingDetail) && (
                  <RefreshCw size={14} className="animate-spin" />
                )}
                {loginLoading ? 'Connexion…' : loadingDetail ? 'Chargement…' : 'Se connecter'}
              </button>
            </form>
          </div>
        )}

        {/* ── Phase : "connecté mais charge le détail" ── */}
        {phase === 'summary' && loadingDetail && (
          <div className="bg-white rounded-2xl shadow-sm border p-6 flex items-center gap-3 text-sm text-gray-500">
            <RefreshCw size={16} className="animate-spin text-[#1a5276]" />
            Chargement du contenu du lot…
          </div>
        )}

        {/* ── Phase : "connecté mais pas encore chargé, montrer le bouton" ── */}
        {phase === 'summary' && !loadingDetail && !isAuthenticated && (
          <div className="bg-white rounded-2xl shadow-sm border p-6 text-center space-y-3">
            <User size={32} className="mx-auto text-gray-400" />
            <p className="text-sm text-gray-600 font-medium">Authentification requise</p>
            <p className="text-xs text-gray-400">Connectez-vous pour confirmer la réception de ce lot.</p>
            <button
              onClick={() => setPhase('login')}
              className="bg-[#1a5276] text-white rounded-lg px-6 py-2 text-sm font-medium hover:bg-[#154360]">
              Se connecter
            </button>
          </div>
        )}

        {/* ── Phase : détail authentifié ── */}
        {phase === 'detail' && detail && (
          <>
            {/* Résumé compteurs */}
            <div className="grid grid-cols-4 gap-3">
              {[
                { label: 'En attente', value: detail.lot.en_attente,  color: 'text-yellow-600', bg: 'bg-yellow-50' },
                { label: 'Confirmés',  value: detail.lot.confirmes,   color: 'text-green-600',  bg: 'bg-green-50' },
                { label: 'Anomalies',  value: detail.lot.anomalies,   color: 'text-orange-600', bg: 'bg-orange-50' },
                { label: 'Manquants',  value: detail.lot.manquants,   color: 'text-red-600',    bg: 'bg-red-50' },
              ].map(({ label, value, color, bg }) => (
                <div key={label} className={`${bg} rounded-xl p-3 text-center`}>
                  <p className={`text-2xl font-bold ${color}`}>{value}</p>
                  <p className="text-xs text-gray-500 mt-0.5">{label}</p>
                </div>
              ))}
            </div>

            {/* Sélection rapide */}
            {detail.lot.en_attente > 0 && (
              <div className="bg-white rounded-xl shadow-sm border px-4 py-3 flex flex-wrap gap-2 items-center">
                <span className="text-xs text-gray-500 font-medium">Sélectionner tout comme :</span>
                <button onClick={() => selectAll('confirme')}
                  className="flex items-center gap-1 text-xs bg-green-100 text-green-700 px-3 py-1.5 rounded-full hover:bg-green-200 font-medium">
                  <CheckCircle size={12} /> Reçu
                </button>
                <button onClick={() => selectAll('anomalie')}
                  className="flex items-center gap-1 text-xs bg-orange-100 text-orange-700 px-3 py-1.5 rounded-full hover:bg-orange-200 font-medium">
                  <AlertTriangle size={12} /> Anomalie
                </button>
                <button onClick={() => selectAll('manquant')}
                  className="flex items-center gap-1 text-xs bg-red-100 text-red-700 px-3 py-1.5 rounded-full hover:bg-red-200 font-medium">
                  <XCircle size={12} /> Manquant
                </button>
              </div>
            )}

            {/* Liste des passeports */}
            <div className="bg-white rounded-2xl shadow-sm border overflow-hidden">
              <div className="border-b px-5 py-3 flex items-center justify-between">
                <h3 className="font-semibold text-gray-800 text-sm">
                  Passeports ({detail.passeports.length})
                </h3>
                <span className="text-xs text-gray-400">
                  {Object.keys(confirmations).length} sélectionné(s)
                </span>
              </div>

              <div className="divide-y">
                {detail.passeports.map((p) => {
                  const selected = confirmations[p.id]
                  const alreadyDone = p.reception.statut !== 'en_attente'

                  return (
                    <div key={p.id} className={`px-5 py-4 transition ${selected ? ACTION_STYLE[selected.statut] + ' border-l-4' : ''}`}>
                      <div className="flex items-start justify-between gap-3">
                        <div className="flex-1 min-w-0">
                          <div className="flex items-center gap-2 flex-wrap">
                            <span className="font-mono text-sm font-bold text-[#1a5276]">{p.numero}</span>
                            {alreadyDone && (
                              <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${
                                p.reception.statut === 'confirme'  ? 'bg-green-100 text-green-700' :
                                p.reception.statut === 'anomalie'  ? 'bg-orange-100 text-orange-700' :
                                'bg-red-100 text-red-700'
                              }`}>
                                {p.reception.statut === 'confirme' ? 'Confirmé' :
                                 p.reception.statut === 'anomalie' ? 'Anomalie' : 'Manquant'}
                              </span>
                            )}
                          </div>
                          <p className="text-sm text-gray-700 mt-0.5">{p.nom_complet}</p>
                          <p className="text-xs text-gray-400">
                            {p.date_naissance} {p.pays_destination ? `• ${p.pays_destination.nom}` : ''}
                          </p>
                        </div>

                        {!alreadyDone && (
                          <div className="flex gap-1.5 shrink-0">
                            <button
                              onClick={() => selected?.statut === 'confirme' ? removeAction(p.id) : setAction(p.id, 'confirme')}
                              title="Reçu"
                              className={`p-1.5 rounded-lg border transition ${
                                selected?.statut === 'confirme'
                                  ? 'bg-green-500 border-green-500 text-white'
                                  : 'border-gray-200 text-gray-400 hover:border-green-400 hover:text-green-600'
                              }`}>
                              <CheckCircle size={16} />
                            </button>
                            <button
                              onClick={() => selected?.statut === 'anomalie' ? removeAction(p.id) : setAction(p.id, 'anomalie')}
                              title="Anomalie"
                              className={`p-1.5 rounded-lg border transition ${
                                selected?.statut === 'anomalie'
                                  ? 'bg-orange-400 border-orange-400 text-white'
                                  : 'border-gray-200 text-gray-400 hover:border-orange-400 hover:text-orange-600'
                              }`}>
                              <AlertTriangle size={16} />
                            </button>
                            <button
                              onClick={() => selected?.statut === 'manquant' ? removeAction(p.id) : setAction(p.id, 'manquant')}
                              title="Manquant"
                              className={`p-1.5 rounded-lg border transition ${
                                selected?.statut === 'manquant'
                                  ? 'bg-red-400 border-red-400 text-white'
                                  : 'border-gray-200 text-gray-400 hover:border-red-400 hover:text-red-600'
                              }`}>
                              <XCircle size={16} />
                            </button>
                            <button
                              onClick={() => setShowNoteFor(showNoteFor === p.id ? null : p.id)}
                              title="Note"
                              className="p-1.5 rounded-lg border border-gray-200 text-gray-400 hover:border-gray-400 hover:text-gray-600 transition">
                              {showNoteFor === p.id ? <ChevronUp size={16} /> : <ChevronDown size={16} />}
                            </button>
                          </div>
                        )}
                      </div>

                      {/* Note annexe */}
                      {showNoteFor === p.id && (
                        <div className="mt-2">
                          <input
                            type="text"
                            value={notes[p.id] ?? ''}
                            onChange={(e) => updateNote(p.id, e.target.value)}
                            placeholder="Note ou observation (optionnel)"
                            className="w-full text-xs border rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#1a5276]"
                          />
                        </div>
                      )}

                      {/* Note déjà enregistrée */}
                      {alreadyDone && p.reception.notes && (
                        <p className="mt-1 text-xs text-gray-500 italic">Note : {p.reception.notes}</p>
                      )}
                    </div>
                  )
                })}
              </div>
            </div>

            {/* Options de confirmation */}
            <div className="bg-white rounded-2xl shadow-sm border p-5 space-y-4">
              <h3 className="font-semibold text-gray-800 text-sm">Options de confirmation</h3>

              <div>
                <label className="block text-xs text-gray-500 mb-1">Commentaire de l'ambassade (optionnel)</label>
                <textarea
                  value={commentaire}
                  onChange={(e) => setCommentaire(e.target.value)}
                  rows={3}
                  placeholder="Remarques générales sur cette réception…"
                  className="w-full border rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1a5276] resize-none"
                />
              </div>

              <label className="flex items-start gap-3 cursor-pointer">
                <input
                  type="checkbox"
                  checked={allerDisponible}
                  onChange={(e) => setAllerDisponible(e.target.checked)}
                  className="mt-0.5 accent-[#1a5276]"
                />
                <div>
                  <p className="text-sm font-medium text-gray-700">Passer directement en « Disponible retrait »</p>
                  <p className="text-xs text-gray-400">Les passeports confirmés seront immédiatement disponibles et les citoyens notifiés par email.</p>
                </div>
              </label>

              <div className="bg-blue-50 rounded-lg px-4 py-3 flex items-center justify-between text-sm">
                <span className="text-gray-600">
                  <strong>{Object.keys(confirmations).length}</strong> passeport(s) dans la confirmation
                </span>
                <div className="flex gap-2 text-xs">
                  {Object.values(confirmations).filter(c => c.statut === 'confirme').length > 0 && (
                    <span className="text-green-600 font-medium">
                      ✓ {Object.values(confirmations).filter(c => c.statut === 'confirme').length} reçu(s)
                    </span>
                  )}
                  {Object.values(confirmations).filter(c => c.statut === 'anomalie').length > 0 && (
                    <span className="text-orange-600 font-medium">
                      ⚠ {Object.values(confirmations).filter(c => c.statut === 'anomalie').length} anomalie(s)
                    </span>
                  )}
                  {Object.values(confirmations).filter(c => c.statut === 'manquant').length > 0 && (
                    <span className="text-red-600 font-medium">
                      ✗ {Object.values(confirmations).filter(c => c.statut === 'manquant').length} manquant(s)
                    </span>
                  )}
                </div>
              </div>

              <button
                onClick={submitConfirmation}
                disabled={submitting || Object.keys(confirmations).length === 0}
                className="w-full bg-[#1a5276] text-white rounded-xl py-3 text-sm font-semibold hover:bg-[#154360] disabled:opacity-50 flex items-center justify-center gap-2 transition">
                {submitting && <RefreshCw size={15} className="animate-spin" />}
                {submitting ? 'Enregistrement…' : 'Confirmer la réception'}
              </button>
            </div>
          </>
        )}

        {/* ── Phase : succès ── */}
        {phase === 'done' && (
          <div className="bg-white rounded-2xl shadow-sm border p-10 text-center space-y-4">
            <div className="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto">
              <CheckCircle size={36} className="text-green-500" />
            </div>
            <h2 className="text-xl font-bold text-gray-800">Réception enregistrée</h2>
            <p className="text-sm text-gray-500 max-w-xs mx-auto">
              La réception du lot a été confirmée avec succès.
              {allerDisponible && ' Les citoyens ont été notifiés par email.'}
            </p>
            {detail?.lot.reference && (
              <p className="font-mono text-[#1a5276] font-semibold">{detail.lot.reference}</p>
            )}
          </div>
        )}

      </div>
    </div>
  )
}
