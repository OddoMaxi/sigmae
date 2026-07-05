'use client'

import { useParams, useRouter } from 'next/navigation'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import Link from 'next/link'
import {
  ArrowLeft, User, MapPin, Package, Clock,
  CheckCircle, AlertTriangle, Calendar, Hash,
  Phone, Mail, Building2, HandCoins, UserCheck,
} from 'lucide-react'
import toast from 'react-hot-toast'

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
  enrolee:            'bg-violet-100 text-violet-700 border-violet-200',
  imprime:            'bg-gray-100 text-gray-600 border-gray-200',
  recu_mae:           'bg-blue-100 text-blue-700 border-blue-200',
  en_stock:           'bg-indigo-100 text-indigo-700 border-indigo-200',
  en_lot:             'bg-yellow-100 text-yellow-800 border-yellow-200',
  expedie:            'bg-orange-100 text-orange-700 border-orange-200',
  en_transit:         'bg-purple-100 text-purple-700 border-purple-200',
  recu_ambassade:     'bg-teal-100 text-teal-700 border-teal-200',
  disponible_retrait: 'bg-green-100 text-green-700 border-green-200',
  remis_citoyen:      'bg-emerald-100 text-emerald-700 border-emerald-200',
  anomalie:           'bg-red-100 text-red-700 border-red-200',
}

function Field({ label, value }: { label: string; value?: string | null }) {
  return (
    <div>
      <p className="text-xs text-gray-400 uppercase tracking-wide mb-0.5">{label}</p>
      <p className="text-sm font-medium text-gray-800">{value || <span className="text-gray-300">—</span>}</p>
    </div>
  )
}

export default function PasseportDetailPage() {
  const { id } = useParams<{ id: string }>()
  const router = useRouter()
  const qc = useQueryClient()

  const { data, isLoading, isError } = useQuery({
    queryKey: ['passeport', id],
    queryFn: () => api.get(`/passeports/${id}`).then((r) => r.data),
  })

  const { data: histData } = useQuery({
    queryKey: ['passeport-historique', id],
    queryFn: () => api.get(`/passeports/${id}/historique`).then((r) => r.data),
    enabled: !!data,
  })

  const passeport = data?.passeport
  const timeline  = histData?.timeline ?? []

  const disponible = useMutation({
    mutationFn: () => api.post(`/passeports/${id}/disponible-retrait`),
    onSuccess: () => {
      toast.success('Passeport marqué disponible au retrait — email envoyé au citoyen')
      qc.invalidateQueries({ queryKey: ['passeport', id] })
    },
    onError: (e: any) => toast.error(e.response?.data?.message || 'Erreur'),
  })

  const remettre = useMutation({
    mutationFn: () => api.post(`/passeports/${id}/remettre-citoyen`),
    onSuccess: () => {
      toast.success('Passeport remis au citoyen ✓')
      qc.invalidateQueries({ queryKey: ['passeport', id] })
    },
    onError: (e: any) => toast.error(e.response?.data?.message || 'Erreur'),
  })

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-2 border-[#1a5276] border-t-transparent" />
      </div>
    )
  }

  if (isError || !passeport) {
    return (
      <div className="text-center py-16">
        <AlertTriangle size={40} className="mx-auto text-red-400 mb-3" />
        <p className="text-gray-500">Passeport introuvable.</p>
        <Link href="/passeports" className="mt-4 inline-block text-sm text-[#1a5276] hover:underline">
          Retour à la liste
        </Link>
      </div>
    )
  }

  const statut = passeport.statut as string

  return (
    <div className="space-y-6 max-w-4xl">
      {/* Header */}
      <div className="flex items-start justify-between">
        <div className="flex items-center gap-3">
          <Link href="/passeports"
            className="text-gray-400 hover:text-gray-600 transition">
            <ArrowLeft size={20} />
          </Link>
          <div>
            <div className="flex items-center gap-3">
              <h1 className="text-2xl font-bold text-gray-800">
                {passeport.numero
                  ? <span className="font-mono">{passeport.numero}</span>
                  : <span className="text-violet-600 font-mono text-lg">{passeport.reference_demande ?? '—'}</span>
                }
              </h1>
              <span className={`text-xs px-2.5 py-1 rounded-full font-medium border ${statutStyle[statut] ?? 'bg-gray-100 text-gray-600 border-gray-200'}`}>
                {STATUT_LABELS[statut] ?? statut}
              </span>
            </div>
            <p className="text-sm text-gray-500 mt-0.5">
              {passeport.prenom_titulaire} {passeport.nom_titulaire}
            </p>
          </div>
        </div>
      </div>

      {/* Actions retrait */}
      {statut === 'recu_ambassade' && (
        <div className="bg-teal-50 border border-teal-200 rounded-xl p-4 flex items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <HandCoins size={20} className="text-teal-600 shrink-0" />
            <div>
              <p className="text-sm font-medium text-teal-800">Passeport reçu en ambassade</p>
              <p className="text-xs text-teal-600 mt-0.5">Marquez-le disponible pour prévenir le citoyen par email.</p>
            </div>
          </div>
          <button
            onClick={() => disponible.mutate()}
            disabled={disponible.isPending}
            className="shrink-0 flex items-center gap-2 bg-teal-600 hover:bg-teal-700 text-white text-sm px-4 py-2 rounded-lg disabled:opacity-60 transition"
          >
            <CheckCircle size={15} />
            {disponible.isPending ? 'Traitement…' : 'Disponible au retrait'}
          </button>
        </div>
      )}

      {statut === 'disponible_retrait' && (
        <div className="bg-green-50 border border-green-200 rounded-xl p-4 flex items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <UserCheck size={20} className="text-green-600 shrink-0" />
            <div>
              <p className="text-sm font-medium text-green-800">En attente de retrait par le citoyen</p>
              <p className="text-xs text-green-600 mt-0.5">Cliquez après remise physique du document au guichet.</p>
            </div>
          </div>
          <button
            onClick={() => {
              if (confirm(`Confirmer la remise du passeport à ${passeport.prenom_titulaire} ${passeport.nom_titulaire} ?`))
                remettre.mutate()
            }}
            disabled={remettre.isPending}
            className="shrink-0 flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white text-sm px-4 py-2 rounded-lg disabled:opacity-60 transition"
          >
            <UserCheck size={15} />
            {remettre.isPending ? 'Traitement…' : 'Remis au citoyen'}
          </button>
        </div>
      )}

      {statut === 'remis_citoyen' && (
        <div className="bg-emerald-50 border border-emerald-200 rounded-xl p-4 flex items-center gap-3">
          <CheckCircle size={20} className="text-emerald-500 shrink-0" />
          <div>
            <p className="text-sm font-medium text-emerald-800">Passeport remis au citoyen</p>
            {passeport.delivered_at && (
              <p className="text-xs text-emerald-600 mt-0.5">
                Le {new Date(passeport.delivered_at).toLocaleDateString('fr-FR', { dateStyle: 'long' })}
              </p>
            )}
          </div>
        </div>
      )}

      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        {/* Titulaire */}
        <div className="md:col-span-2 space-y-4">
          <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div className="flex items-center gap-2 mb-4">
              <User size={16} className="text-[#1a5276]" />
              <h2 className="font-semibold text-gray-700 text-sm">Titulaire</h2>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <Field label="Prénom" value={passeport.prenom_titulaire} />
              <Field label="Nom" value={passeport.nom_titulaire} />
              <Field label="Date de naissance"
                value={passeport.date_naissance
                  ? new Date(passeport.date_naissance).toLocaleDateString('fr-FR')
                  : null}
              />
              <Field label="Téléphone" value={passeport.telephone} />
              <Field label="Email" value={passeport.email_citoyen} />
            </div>
          </div>

          {/* Destination */}
          <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div className="flex items-center gap-2 mb-4">
              <MapPin size={16} className="text-[#1a5276]" />
              <h2 className="font-semibold text-gray-700 text-sm">Destination</h2>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <Field label="Pays"
                value={passeport.pays_destination
                  ? `${passeport.pays_destination.nom} (${passeport.pays_destination.code_iso})`
                  : null}
              />
              <Field label="Ambassade"
                value={passeport.ambassade_destination
                  ? `${passeport.ambassade_destination.nom}${passeport.ambassade_destination.ville ? ` — ${passeport.ambassade_destination.ville}` : ''}`
                  : null}
              />
            </div>
          </div>

          {/* Lot */}
          {passeport.lot && (
            <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
              <div className="flex items-center gap-2 mb-4">
                <Package size={16} className="text-[#1a5276]" />
                <h2 className="font-semibold text-gray-700 text-sm">Lot d'expédition</h2>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <Field label="Référence lot" value={passeport.lot.reference} />
                <Field label="Statut lot" value={passeport.lot.statut} />
                {passeport.lot.transporteur && (
                  <Field label="Transporteur" value={passeport.lot.transporteur.nom} />
                )}
              </div>
            </div>
          )}
        </div>

        {/* Dates & Statut */}
        <div className="space-y-4">
          <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div className="flex items-center gap-2 mb-4">
              <Calendar size={16} className="text-[#1a5276]" />
              <h2 className="font-semibold text-gray-700 text-sm">Dates clés</h2>
            </div>
            <div className="space-y-3">
              {passeport.enrolled_at && (
                <div className="flex items-start gap-2">
                  <div className="w-1.5 h-1.5 rounded-full bg-violet-400 mt-1.5 shrink-0" />
                  <div>
                    <p className="text-xs text-gray-400">Enrôlement</p>
                    <p className="text-xs font-medium">{new Date(passeport.enrolled_at).toLocaleDateString('fr-FR')}</p>
                  </div>
                </div>
              )}
              {passeport.date_impression && (
                <div className="flex items-start gap-2">
                  <div className="w-1.5 h-1.5 rounded-full bg-gray-400 mt-1.5 shrink-0" />
                  <div>
                    <p className="text-xs text-gray-400">Impression</p>
                    <p className="text-xs font-medium">{new Date(passeport.date_impression).toLocaleDateString('fr-FR')}</p>
                  </div>
                </div>
              )}
              {passeport.date_reception_mae && (
                <div className="flex items-start gap-2">
                  <div className="w-1.5 h-1.5 rounded-full bg-blue-400 mt-1.5 shrink-0" />
                  <div>
                    <p className="text-xs text-gray-400">Réception MAE</p>
                    <p className="text-xs font-medium">{new Date(passeport.date_reception_mae).toLocaleDateString('fr-FR')}</p>
                  </div>
                </div>
              )}
              {passeport.dispatched_at && (
                <div className="flex items-start gap-2">
                  <div className="w-1.5 h-1.5 rounded-full bg-orange-400 mt-1.5 shrink-0" />
                  <div>
                    <p className="text-xs text-gray-400">Expédition</p>
                    <p className="text-xs font-medium">{new Date(passeport.dispatched_at).toLocaleDateString('fr-FR')}</p>
                  </div>
                </div>
              )}
              {passeport.disponible_at && (
                <div className="flex items-start gap-2">
                  <div className="w-1.5 h-1.5 rounded-full bg-green-400 mt-1.5 shrink-0" />
                  <div>
                    <p className="text-xs text-gray-400">Disponible retrait</p>
                    <p className="text-xs font-medium">{new Date(passeport.disponible_at).toLocaleDateString('fr-FR')}</p>
                  </div>
                </div>
              )}
              {passeport.delivered_at && (
                <div className="flex items-start gap-2">
                  <div className="w-1.5 h-1.5 rounded-full bg-emerald-400 mt-1.5 shrink-0" />
                  <div>
                    <p className="text-xs text-gray-400">Remis citoyen</p>
                    <p className="text-xs font-medium">{new Date(passeport.delivered_at).toLocaleDateString('fr-FR')}</p>
                  </div>
                </div>
              )}
            </div>
          </div>

          {/* Identifiants */}
          <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div className="flex items-center gap-2 mb-4">
              <Hash size={16} className="text-[#1a5276]" />
              <h2 className="font-semibold text-gray-700 text-sm">Identifiants</h2>
            </div>
            <div className="space-y-3">
              {passeport.numero && (
                <div>
                  <p className="text-xs text-gray-400">N° passeport</p>
                  <p className="font-mono text-sm font-bold text-[#1a5276]">{passeport.numero}</p>
                </div>
              )}
              {passeport.reference_demande && (
                <div>
                  <p className="text-xs text-gray-400">Réf. demande</p>
                  <p className="font-mono text-xs text-violet-600 bg-violet-50 px-2 py-1 rounded">
                    {passeport.reference_demande}
                  </p>
                </div>
              )}
              <div>
                <p className="text-xs text-gray-400">ID système</p>
                <p className="text-xs text-gray-500">#{passeport.id}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Timeline */}
      {timeline.length > 0 && (
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
          <div className="flex items-center gap-2 mb-4">
            <Clock size={16} className="text-[#1a5276]" />
            <h2 className="font-semibold text-gray-700 text-sm">Historique</h2>
          </div>
          <ol className="relative border-l border-gray-200 ml-3 space-y-4">
            {timeline.map((e: any) => (
              <li key={e.id} className="ml-4">
                <div className="absolute -left-1.5 w-3 h-3 rounded-full bg-[#1a5276] border-2 border-white" />
                <div className="flex items-start justify-between">
                  <div>
                    <p className="text-sm font-medium text-gray-700">{e.description}</p>
                    {e.agent && (
                      <p className="text-xs text-gray-400 mt-0.5">
                        par {e.agent.name}
                      </p>
                    )}
                  </div>
                  <span className="text-xs text-gray-400 shrink-0 ml-4">
                    {new Date(e.date).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' })}
                  </span>
                </div>
              </li>
            ))}
          </ol>
        </div>
      )}
    </div>
  )
}
