'use client'

import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import api from '@/lib/api'
import { UserPlus, Search, X, Check, Fingerprint, FileText, Calendar, Phone, Mail, User } from 'lucide-react'
import toast from 'react-hot-toast'

// ── Schéma ────────────────────────────────────────────────────────────────────

const schema = z.object({
  prenom_titulaire: z.string().min(2, 'Minimum 2 caractères'),
  nom_titulaire:    z.string().min(2, 'Minimum 2 caractères'),
  date_naissance:   z.string().min(1, 'Date de naissance requise'),
  email_citoyen:    z.string().email('Email invalide').optional().or(z.literal('')),
  telephone:        z.string().optional(),
})
type FormData = z.infer<typeof schema>

// ── Badges statut ─────────────────────────────────────────────────────────────

const STATUT_STYLE: Record<string, string> = {
  enrolee:            'bg-violet-100 text-violet-700',
  imprime:            'bg-slate-100 text-slate-600',
  recu_mae:           'bg-blue-100 text-blue-700',
  en_stock:           'bg-indigo-100 text-indigo-700',
}

function StatutBadge({ statut }: { statut: string }) {
  const labels: Record<string, string> = {
    enrolee:  'Enrôlé',
    imprime:  'Imprimé',
    recu_mae: 'Reçu MAE',
    en_stock: 'En stock',
  }
  return (
    <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${STATUT_STYLE[statut] ?? 'bg-slate-100 text-slate-500'}`}>
      {labels[statut] ?? statut}
    </span>
  )
}

// ── Page principale ───────────────────────────────────────────────────────────

export default function EnrolementPage() {
  const qc = useQueryClient()
  const [showForm, setShowForm]     = useState(false)
  const [search, setSearch]         = useState('')
  const [lastCreated, setLastCreated] = useState<any>(null)

  // ── Liste des enrôlements ──────────────────────────────────────────────────

  const { data, isLoading } = useQuery({
    queryKey: ['enrolements', search],
    queryFn:  () => api.get('/enrolements', { params: { search: search || undefined, per_page: 50 } })
                       .then(r => r.data),
  })

  const enrolements = data?.data ?? []

  // ── Formulaire ────────────────────────────────────────────────────────────

  const { register, handleSubmit, reset, formState: { errors, isSubmitting } } = useForm<FormData>({
    resolver: zodResolver(schema),
  })

  const create = useMutation({
    mutationFn: (d: FormData) => api.post('/enrolements', d),
    onSuccess: (res) => {
      toast.success('Enrôlement enregistré avec succès')
      setLastCreated(res.data)
      setShowForm(false)
      reset()
      qc.invalidateQueries({ queryKey: ['enrolements'] })
    },
    onError: (e: any) => toast.error(e.response?.data?.message || 'Erreur lors de l\'enregistrement'),
  })

  return (
    <div className="space-y-6">

      {/* ── En-tête ─────────────────────────────────────────────────────── */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-[22px] font-bold text-[color:var(--color-navy-900)] tracking-tight flex items-center gap-2">
            <Fingerprint className="text-[#1a5276]" size={26} />
            Enrôlements biométriques
          </h1>
          <p className="text-sm text-slate-500 mt-0.5">
            Saisie des demandes de passeport après enrôlement biométrique
          </p>
        </div>
        <button
          onClick={() => { setShowForm(!showForm); setLastCreated(null) }}
          className="flex items-center gap-2 bg-[#1a5276] text-white px-4 py-2 rounded-lg text-sm hover:bg-[#154360] transition"
        >
          <UserPlus size={15} />
          Nouvel enrôlement
        </button>
      </div>

      {/* ── Confirmation dernier enrôlement ────────────────────────────── */}
      {lastCreated && (
        <div className="bg-green-50 border border-green-200 rounded-xl p-4 flex items-start gap-3">
          <Check className="text-green-600 mt-0.5 shrink-0" size={20} />
          <div className="flex-1">
            <p className="font-semibold text-green-800">
              Enrôlement enregistré — {lastCreated.prenom_titulaire} {lastCreated.nom_titulaire}
            </p>
            <p className="text-sm text-green-700 mt-0.5">
              Référence demande : <span className="font-mono font-bold">{lastCreated.reference_demande}</span>
            </p>
            <p className="text-xs text-green-600 mt-1">
              Transmettez cette référence à l'imprimerie. Elle servira à lier le passeport imprimé à ce dossier.
            </p>
          </div>
          <button onClick={() => setLastCreated(null)} className="text-green-400 hover:text-green-600">
            <X size={16} />
          </button>
        </div>
      )}

      {/* ── Formulaire d'enrôlement ──────────────────────────────────────── */}
      {showForm && (
        <div className="bg-white rounded-xl shadow-sm border border-[#1a5276]/20 p-6">
          <h3 className="font-semibold text-slate-700 mb-5 flex items-center gap-2">
            <FileText size={16} className="text-[#1a5276]" />
            Informations du demandeur
          </h3>
          <form onSubmit={handleSubmit((d) => create.mutate(d))} className="space-y-4">
            <div className="grid grid-cols-2 gap-4">

              {/* Prénom */}
              <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">
                  <User size={12} className="inline mr-1" />Prénom *
                </label>
                <input
                  {...register('prenom_titulaire')}
                  placeholder="Alpha"
                  className="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1a5276]"
                />
                {errors.prenom_titulaire && (
                  <p className="text-red-500 text-xs mt-0.5">{errors.prenom_titulaire.message}</p>
                )}
              </div>

              {/* Nom */}
              <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">
                  <User size={12} className="inline mr-1" />Nom *
                </label>
                <input
                  {...register('nom_titulaire')}
                  placeholder="Diallo"
                  className="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1a5276]"
                />
                {errors.nom_titulaire && (
                  <p className="text-red-500 text-xs mt-0.5">{errors.nom_titulaire.message}</p>
                )}
              </div>

              {/* Date de naissance */}
              <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">
                  <Calendar size={12} className="inline mr-1" />Date de naissance *
                </label>
                <input
                  type="date"
                  {...register('date_naissance')}
                  max={new Date().toISOString().split('T')[0]}
                  className="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1a5276]"
                />
                {errors.date_naissance && (
                  <p className="text-red-500 text-xs mt-0.5">{errors.date_naissance.message}</p>
                )}
              </div>

              {/* Téléphone */}
              <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">
                  <Phone size={12} className="inline mr-1" />Téléphone
                </label>
                <input
                  {...register('telephone')}
                  placeholder="+224 620 000 000"
                  className="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1a5276]"
                />
              </div>

              {/* Email */}
              <div className="col-span-2">
                <label className="block text-xs font-medium text-slate-600 mb-1">
                  <Mail size={12} className="inline mr-1" />Email du citoyen
                </label>
                <input
                  {...register('email_citoyen')}
                  type="email"
                  placeholder="alpha.diallo@gmail.com"
                  className="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1a5276]"
                />
                {errors.email_citoyen && (
                  <p className="text-red-500 text-xs mt-0.5">{errors.email_citoyen.message}</p>
                )}
                <p className="text-xs text-slate-400 mt-0.5">
                  Utilisé pour notifier le citoyen quand son passeport est prêt au retrait.
                </p>
              </div>
            </div>

            <div className="flex justify-end gap-2 pt-2 border-t border-slate-100">
              <button
                type="button"
                onClick={() => { setShowForm(false); reset() }}
                className="flex items-center gap-1 px-4 py-2 text-sm border rounded-lg hover:bg-slate-50"
              >
                <X size={14} /> Annuler
              </button>
              <button
                type="submit"
                disabled={isSubmitting}
                className="flex items-center gap-1 px-5 py-2 text-sm bg-[#1a5276] text-white rounded-lg hover:bg-[#154360] disabled:opacity-60"
              >
                <Fingerprint size={14} />
                {isSubmitting ? 'Enregistrement...' : 'Enregistrer l\'enrôlement'}
              </button>
            </div>
          </form>
        </div>
      )}

      {/* ── Barre de recherche ──────────────────────────────────────────── */}
      <div className="relative">
        <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
        <input
          value={search}
          onChange={e => setSearch(e.target.value)}
          placeholder="Rechercher par nom, prénom, référence ou email..."
          className="w-full pl-9 pr-4 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#1a5276]"
        />
      </div>

      {/* ── Tableau des enrôlements ──────────────────────────────────────── */}
      <div className="bg-white rounded-[var(--radius-card)] border border-slate-200/70 overflow-hidden">
        {isLoading ? (
          <div className="flex items-center justify-center h-48">
            <div className="animate-spin rounded-full h-8 w-8 border-2 border-[#1a5276] border-t-transparent" />
          </div>
        ) : enrolements.length === 0 ? (
          <div className="flex flex-col items-center justify-center h-48 text-slate-400">
            <Fingerprint size={36} className="mb-3 opacity-30" />
            <p className="text-sm">Aucun enrôlement{search ? ' correspondant à la recherche' : ' enregistré'}</p>
          </div>
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="bg-slate-50 border-b">
                {['Demandeur', 'Date naissance', 'Référence demande', 'Contact', 'Enrôlé le', 'Statut'].map(h => (
                  <th key={h} className="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {enrolements.map((e: any) => (
                <tr key={e.id} className="border-b hover:bg-slate-50 transition">
                  <td className="px-4 py-3">
                    <div className="font-medium text-slate-800">
                      {e.prenom_titulaire} {e.nom_titulaire}
                    </div>
                  </td>
                  <td className="px-4 py-3 text-slate-500">
                    {e.date_naissance
                      ? new Date(e.date_naissance).toLocaleDateString('fr-FR')
                      : '—'}
                  </td>
                  <td className="px-4 py-3">
                    <span className="font-mono text-xs bg-slate-100 px-2 py-0.5 rounded text-slate-700">
                      {e.reference_demande}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-slate-500 text-xs">
                    <div>{e.email_citoyen || '—'}</div>
                    <div>{e.telephone || ''}</div>
                  </td>
                  <td className="px-4 py-3 text-slate-500 text-xs">
                    {e.enrolled_at
                      ? new Date(e.enrolled_at).toLocaleDateString('fr-FR', {
                          day: '2-digit', month: 'short', year: 'numeric',
                          hour: '2-digit', minute: '2-digit',
                        })
                      : '—'}
                  </td>
                  <td className="px-4 py-3">
                    <StatutBadge statut={e.statut} />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}

        {/* Pagination info */}
        {data && data.total > 0 && (
          <div className="px-4 py-3 border-t bg-slate-50 text-xs text-slate-500">
            {data.total} enrôlement{data.total > 1 ? 's' : ''} au total
          </div>
        )}
      </div>
    </div>
  )
}
