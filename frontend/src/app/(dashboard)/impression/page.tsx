'use client'

import { useState, useRef, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { Printer, Search, Check, X, RefreshCw, Hash, User, Calendar, Building2 } from 'lucide-react'
import toast from 'react-hot-toast'

// ── Types ─────────────────────────────────────────────────────────────────

type Enrolement = {
  id: number
  reference_demande: string
  prenom_titulaire: string
  nom_titulaire: string
  date_naissance: string
  email_citoyen: string | null
  telephone: string | null
  enrolled_at: string
  ambassade_destination: { id: number; nom: string; code: string; ville: string } | null
}

// ── Page principale ───────────────────────────────────────────────────────

export default function ImpressionPage() {
  const qc = useQueryClient()

  // ── Zone scanner ──────────────────────────────────────────────────────
  const [refInput, setRefInput]         = useState('')
  const [dossierId, setDossierId]        = useState<number | null>(null)
  const [dossier, setDossier]           = useState<Enrolement | null>(null)
  const [numeroInput, setNumeroInput]   = useState('')
  const [searchError, setSearchError]   = useState('')
  const refInputRef   = useRef<HTMLInputElement>(null)
  const numeroRef     = useRef<HTMLInputElement>(null)

  // Auto-focus sur le champ de référence au chargement
  useEffect(() => { refInputRef.current?.focus() }, [])

  // ── File d'attente (tous les enrôlements en attente) ──────────────────
  const { data: fileAttente, isLoading: loadingFile } = useQuery({
    queryKey: ['enrolements-impression'],
    queryFn:  () => api.get('/enrolements', { params: { per_page: 100 } }).then(r => r.data),
    refetchInterval: 30_000,
  })

  const pending: Enrolement[] = fileAttente?.data ?? []

  // ── Rechercher un dossier par référence ───────────────────────────────
  const searchDossier = async (ref: string) => {
    const clean = ref.trim().toUpperCase()
    if (!clean) return
    setSearchError('')
    setDossier(null)
    setDossierId(null)
    setNumeroInput('')

    try {
      const res = await api.get('/enrolements', { params: { search: clean, per_page: 5 } })
      const items: Enrolement[] = res.data.data ?? []
      const found = items.find(
        (e) => e.reference_demande?.toUpperCase() === clean
      )
      if (found) {
        setDossier(found)
        setDossierId(found.id)
        setTimeout(() => numeroRef.current?.focus(), 50)
      } else {
        setSearchError(`Référence "${clean}" introuvable ou déjà traitée.`)
        refInputRef.current?.select()
      }
    } catch {
      setSearchError('Erreur de connexion au serveur.')
    }
  }

  // ── Assigner le numéro de passeport ───────────────────────────────────
  const assigner = useMutation({
    mutationFn: ({ id, numero }: { id: number; numero: string }) =>
      api.post(`/enrolements/${id}/assigner-numero`, { numero }),
    onSuccess: (res) => {
      const p = res.data
      toast.success(`✓ ${p.prenom_titulaire} ${p.nom_titulaire} — N° ${p.numero} assigné`)
      setDossier(null)
      setDossierId(null)
      setRefInput('')
      setNumeroInput('')
      setSearchError('')
      qc.invalidateQueries({ queryKey: ['enrolements-impression'] })
      setTimeout(() => refInputRef.current?.focus(), 50)
    },
    onError: (e: any) => {
      const msg = e.response?.data?.message ?? e.response?.data?.errors?.numero?.[0] ?? 'Erreur'
      toast.error(msg)
      numeroRef.current?.select()
    },
  })

  const handleAssigner = () => {
    if (!dossierId || !numeroInput.trim()) return
    assigner.mutate({ id: dossierId, numero: numeroInput.trim().toUpperCase() })
  }

  // Clic sur un dossier dans la file d'attente → pré-remplit le scanner
  const selectionnerDossier = (e: Enrolement) => {
    setRefInput(e.reference_demande)
    setDossier(e)
    setDossierId(e.id)
    setNumeroInput('')
    setSearchError('')
    setTimeout(() => numeroRef.current?.focus(), 50)
  }

  return (
    <div className="space-y-6">

      {/* ── En-tête ──────────────────────────────────────────────── */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <Printer className="text-[#1a5276]" size={26} />
            Unité d'impression
          </h1>
          <p className="text-sm text-gray-500 mt-0.5">
            Assignation des numéros de passeport aux dossiers enrôlés
          </p>
        </div>
        <div className="text-right">
          <p className="text-2xl font-bold text-[#1a5276]">{pending.length}</p>
          <p className="text-xs text-gray-500">dossier{pending.length > 1 ? 's' : ''} en attente</p>
        </div>
      </div>

      <div className="grid grid-cols-5 gap-6">

        {/* ── Colonne gauche : scanner + formulaire ─────────────────── */}
        <div className="col-span-3 space-y-4">

          {/* Saisie référence demande */}
          <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 className="font-semibold text-gray-700 mb-4 flex items-center gap-2">
              <Search size={16} className="text-[#1a5276]" />
              Rechercher le dossier
            </h3>
            <div className="flex gap-2">
              <input
                ref={refInputRef}
                value={refInput}
                onChange={e => { setRefInput(e.target.value); setSearchError('') }}
                onKeyDown={e => e.key === 'Enter' && searchDossier(refInput)}
                placeholder="DEM-FRPAR-20260601-X7K2P"
                className="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-[#1a5276]"
              />
              <button
                onClick={() => searchDossier(refInput)}
                className="px-4 py-2 bg-[#1a5276] text-white rounded-lg text-sm hover:bg-[#154360] transition"
              >
                Chercher
              </button>
            </div>
            {searchError && (
              <p className="text-red-500 text-xs mt-2 flex items-center gap-1">
                <X size={12} /> {searchError}
              </p>
            )}
          </div>

          {/* Fiche dossier trouvé */}
          {dossier && (
            <div className="bg-white rounded-xl shadow-sm border border-[#1a5276]/30 p-5 space-y-4">
              <div className="flex items-start justify-between">
                <h3 className="font-semibold text-[#1a5276] flex items-center gap-2">
                  <Check size={16} className="text-green-500" />
                  Dossier trouvé
                </h3>
                <button
                  onClick={() => { setDossier(null); setDossierId(null); setRefInput(''); setNumeroInput(''); refInputRef.current?.focus() }}
                  className="text-gray-400 hover:text-gray-600"
                >
                  <X size={16} />
                </button>
              </div>

              {/* Infos titulaire */}
              <div className="bg-gray-50 rounded-lg p-4 grid grid-cols-2 gap-3 text-sm">
                <div className="flex items-center gap-2 text-gray-700">
                  <User size={14} className="text-gray-400 shrink-0" />
                  <span className="font-semibold">
                    {dossier.prenom_titulaire} {dossier.nom_titulaire}
                  </span>
                </div>
                <div className="flex items-center gap-2 text-gray-500">
                  <Calendar size={14} className="text-gray-400 shrink-0" />
                  {dossier.date_naissance
                    ? new Date(dossier.date_naissance).toLocaleDateString('fr-FR')
                    : '—'}
                </div>
                <div className="flex items-center gap-2 text-gray-500 col-span-2">
                  <Building2 size={14} className="text-gray-400 shrink-0" />
                  {dossier.ambassade_destination?.nom ?? '—'}
                  <span className="text-xs text-gray-400">
                    ({dossier.ambassade_destination?.code})
                  </span>
                </div>
                <div className="col-span-2">
                  <span className="text-xs font-mono bg-[#1a5276]/10 text-[#1a5276] px-2 py-0.5 rounded">
                    {dossier.reference_demande}
                  </span>
                </div>
              </div>

              {/* Saisie numéro passeport */}
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1.5">
                  <Hash size={12} className="inline mr-1" />
                  Numéro de passeport imprimé *
                </label>
                <div className="flex gap-2">
                  <input
                    ref={numeroRef}
                    value={numeroInput}
                    onChange={e => setNumeroInput(e.target.value.toUpperCase())}
                    onKeyDown={e => e.key === 'Enter' && handleAssigner()}
                    placeholder="PA123456"
                    className="flex-1 border border-gray-200 rounded-lg px-3 py-2.5 text-sm font-mono font-bold tracking-wider focus:outline-none focus:ring-2 focus:ring-[#1a5276]"
                  />
                  <button
                    onClick={handleAssigner}
                    disabled={!numeroInput.trim() || assigner.isPending}
                    className="flex items-center gap-2 px-5 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700 disabled:opacity-50 transition font-medium"
                  >
                    <Check size={15} />
                    {assigner.isPending ? 'Assignation...' : 'Confirmer'}
                  </button>
                </div>
                <p className="text-xs text-gray-400 mt-1">
                  Appuyez sur Entrée pour confirmer rapidement.
                </p>
              </div>
            </div>
          )}

          {/* État vide — pas de dossier sélectionné */}
          {!dossier && !searchError && (
            <div className="bg-gray-50 rounded-xl border border-dashed border-gray-200 p-8 text-center text-gray-400">
              <Printer size={36} className="mx-auto mb-3 opacity-30" />
              <p className="text-sm">Saisissez une référence demande ou cliquez sur un dossier dans la file d'attente.</p>
            </div>
          )}
        </div>

        {/* ── Colonne droite : file d'attente ───────────────────────── */}
        <div className="col-span-2">
          <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div className="px-4 py-3 border-b bg-gray-50 flex items-center justify-between">
              <span className="text-sm font-semibold text-gray-700">File d'attente</span>
              <button
                onClick={() => qc.invalidateQueries({ queryKey: ['enrolements-impression'] })}
                className="text-gray-400 hover:text-gray-600 transition"
                title="Actualiser"
              >
                <RefreshCw size={14} />
              </button>
            </div>

            {loadingFile ? (
              <div className="flex items-center justify-center h-40">
                <div className="animate-spin rounded-full h-6 w-6 border-2 border-[#1a5276] border-t-transparent" />
              </div>
            ) : pending.length === 0 ? (
              <div className="flex flex-col items-center justify-center h-40 text-gray-400">
                <Check size={28} className="mb-2 text-green-400" />
                <p className="text-sm">Aucun dossier en attente</p>
              </div>
            ) : (
              <div className="divide-y max-h-[calc(100vh-280px)] overflow-y-auto">
                {pending.map((e) => (
                  <button
                    key={e.id}
                    onClick={() => selectionnerDossier(e)}
                    className={`w-full text-left px-4 py-3 hover:bg-blue-50 transition ${
                      dossierId === e.id ? 'bg-[#1a5276]/5 border-l-2 border-[#1a5276]' : ''
                    }`}
                  >
                    <p className="font-medium text-sm text-gray-800">
                      {e.prenom_titulaire} {e.nom_titulaire}
                    </p>
                    <p className="text-xs font-mono text-[#1a5276] mt-0.5">
                      {e.reference_demande}
                    </p>
                    <p className="text-xs text-gray-400 mt-0.5">
                      {e.ambassade_destination?.code ?? '—'} ·{' '}
                      {e.enrolled_at
                        ? new Date(e.enrolled_at).toLocaleDateString('fr-FR')
                        : '—'}
                    </p>
                  </button>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}
