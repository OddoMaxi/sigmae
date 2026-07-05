'use client'

import { useRouter } from 'next/navigation'
import { useQuery, useMutation } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import api from '@/lib/api'
import { ArrowLeft, Save } from 'lucide-react'
import Link from 'next/link'
import toast from 'react-hot-toast'

// ── Schéma ─────────────────────────────────────────────────────────────────

const schema = z.object({
  numero:                   z.string().min(1, 'Numéro obligatoire'),
  reference_demande:        z.string().optional(),
  nom_titulaire:            z.string().min(2, 'Nom obligatoire'),
  prenom_titulaire:         z.string().min(2, 'Prénom obligatoire'),
  date_naissance:           z.string().optional(),
  email_citoyen:            z.string().email('Email invalide').optional().or(z.literal('')),
  telephone:                z.string().optional(),
  ambassade_destination_id: z.number({ message: 'Ambassade requise' }).positive('Ambassade requise'),
  pays_destination_id:      z.number().positive().optional(),
  date_impression:          z.string().optional(),
})
type FormData = z.infer<typeof schema>

// ── Composant champ ─────────────────────────────────────────────────────────

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
  return (
    <div>
      <label className="block text-xs font-medium text-gray-600 mb-1">{label}</label>
      {children}
      {error && <p className="text-red-500 text-xs mt-0.5">{error}</p>}
    </div>
  )
}

const inputCls = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1a5276]'

// ── Page ───────────────────────────────────────────────────────────────────

export default function NouveauPasseportPage() {
  const router = useRouter()

  const { data: ambassades = [] } = useQuery({
    queryKey: ['ambassades-select'],
    queryFn:  () => api.get('/ambassades', { params: { per_page: 200 } }).then(r => r.data.data ?? r.data),
  })

  const { data: pays = [] } = useQuery({
    queryKey: ['pays-select'],
    queryFn:  () => api.get('/pays', { params: { per_page: 200 } }).then(r => r.data.data ?? r.data),
  })

  const { register, handleSubmit, watch, setValue, formState: { errors, isSubmitting } } = useForm<FormData>({
    resolver: zodResolver(schema),
  })

  // Pré-remplir pays quand ambassade change
  const ambassadeId = watch('ambassade_destination_id')
  const handleAmbassadeChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const id = Number(e.target.value)
    setValue('ambassade_destination_id', id)
    const amb = (ambassades as any[]).find((a: any) => a.id === id)
    if (amb?.pays_id) setValue('pays_destination_id', amb.pays_id)
  }

  const save = useMutation({
    mutationFn: (d: FormData) => api.post('/passeports', d),
    onSuccess: (res) => {
      toast.success('Passeport créé')
      router.push('/passeports')
    },
    onError: (e: any) => {
      const msg = e.response?.data?.message ?? 'Erreur lors de la création'
      toast.error(msg)
    },
  })

  return (
    <div className="space-y-6 max-w-3xl">

      {/* ── En-tête ──────────────────────────────────────────────────── */}
      <div className="flex items-center gap-3">
        <Link href="/passeports" className="text-gray-400 hover:text-gray-600 transition">
          <ArrowLeft size={20} />
        </Link>
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Nouveau passeport</h1>
          <p className="text-sm text-gray-500">Saisie manuelle depuis l'imprimerie (MAE)</p>
        </div>
      </div>

      {/* ── Formulaire ──────────────────────────────────────────────── */}
      <form onSubmit={handleSubmit((d) => save.mutate(d))}
        className="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-6">

        {/* Identification */}
        <div>
          <h3 className="text-sm font-semibold text-gray-700 mb-4 pb-2 border-b">Identification du passeport</h3>
          <div className="grid grid-cols-2 gap-4">
            <Field label="N° Passeport *" error={errors.numero?.message}>
              <input {...register('numero')} placeholder="PA123456" className={inputCls} />
            </Field>
            <Field label="Référence demande" error={errors.reference_demande?.message}>
              <input {...register('reference_demande')} placeholder="DEM-FRPAR-…" className={inputCls} />
            </Field>
            <Field label="Date d'impression">
              <input type="date" {...register('date_impression')}
                max={new Date().toISOString().split('T')[0]} className={inputCls} />
            </Field>
          </div>
        </div>

        {/* Titulaire */}
        <div>
          <h3 className="text-sm font-semibold text-gray-700 mb-4 pb-2 border-b">Titulaire</h3>
          <div className="grid grid-cols-2 gap-4">
            <Field label="Prénom *" error={errors.prenom_titulaire?.message}>
              <input {...register('prenom_titulaire')} placeholder="Alpha" className={inputCls} />
            </Field>
            <Field label="Nom *" error={errors.nom_titulaire?.message}>
              <input {...register('nom_titulaire')} placeholder="Diallo" className={inputCls} />
            </Field>
            <Field label="Date de naissance">
              <input type="date" {...register('date_naissance')}
                max={new Date().toISOString().split('T')[0]} className={inputCls} />
            </Field>
            <Field label="Téléphone">
              <input {...register('telephone')} placeholder="+224 620 000 000" className={inputCls} />
            </Field>
            <Field label="Email citoyen" error={errors.email_citoyen?.message}>
              <input {...register('email_citoyen')} type="email"
                placeholder="alpha.diallo@gmail.com" className={inputCls} />
            </Field>
          </div>
        </div>

        {/* Destination */}
        <div>
          <h3 className="text-sm font-semibold text-gray-700 mb-4 pb-2 border-b">Destination</h3>
          <div className="grid grid-cols-2 gap-4">
            <Field label="Ambassade destination *" error={errors.ambassade_destination_id?.message}>
              <select
                onChange={handleAmbassadeChange}
                defaultValue=""
                className={inputCls}
              >
                <option value="" disabled>Sélectionner une ambassade</option>
                {(ambassades as any[]).map((a: any) => (
                  <option key={a.id} value={a.id}>{a.nom} ({a.code})</option>
                ))}
              </select>
            </Field>
            <Field label="Pays">
              <select {...register('pays_destination_id', { setValueAs: (v) => v === '' ? undefined : Number(v) })} className={inputCls}>
                <option value="">— Automatique —</option>
                {(pays as any[]).map((p: any) => (
                  <option key={p.id} value={p.id}>{p.nom}</option>
                ))}
              </select>
            </Field>
          </div>
        </div>

        {/* Actions */}
        <div className="flex justify-end gap-3 pt-2 border-t border-gray-100">
          <Link href="/passeports"
            className="px-4 py-2 text-sm border rounded-lg hover:bg-gray-50">
            Annuler
          </Link>
          <button type="submit" disabled={isSubmitting}
            className="flex items-center gap-2 px-5 py-2 text-sm bg-[#1a5276] text-white rounded-lg hover:bg-[#154360] disabled:opacity-60">
            <Save size={14} />
            {isSubmitting ? 'Création...' : 'Créer le passeport'}
          </button>
        </div>
      </form>
    </div>
  )
}
