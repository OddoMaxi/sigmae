'use client'

import { useState } from 'react'
import { useParams } from 'next/navigation'
import { useQuery, useMutation } from '@tanstack/react-query'
import api from '@/lib/api'
import toast from 'react-hot-toast'
import { CheckCircle, AlertTriangle, Package, Send } from 'lucide-react'

type ReceptionStatut = 'en_attente' | 'confirme' | 'anomalie'

interface PasseportState {
  passeport_id: number
  statut: ReceptionStatut
  note: string
}

export default function ScanPage() {
  const { token } = useParams<{ token: string }>()
  const [states, setStates] = useState<Record<number, PasseportState>>({})
  const [submitted, setSubmitted] = useState(false)

  const { data, isLoading, error } = useQuery({
    queryKey: ['scan', token],
    queryFn:  () => api.get(`/reception/scan/${token}`).then(r => r.data),
    retry: false,
  })

  const confirm = useMutation({
    mutationFn: (payload: any) => api.post(`/reception/lot/${token}`, payload),
    onSuccess: () => {
      toast.success('Réception enregistrée avec succès')
      setSubmitted(true)
    },
    onError: (e: any) => toast.error(e.response?.data?.message || 'Erreur'),
  })

  const toggle = (passeportId: number, statut: ReceptionStatut) => {
    setStates(prev => ({
      ...prev,
      [passeportId]: { passeport_id: passeportId, statut, note: prev[passeportId]?.note || '' }
    }))
  }

  const setNote = (passeportId: number, note: string) => {
    setStates(prev => ({
      ...prev,
      [passeportId]: { ...prev[passeportId], note }
    }))
  }

  const handleSubmit = () => {
    const passeports = data?.lot?.passeports || []
    const confirmations = passeports.map((p: any) => ({
      passeport_id: p.id,
      statut:       states[p.id]?.statut || 'confirme',
      note:         states[p.id]?.note   || '',
    }))
    confirm.mutate({ confirmations })
  }

  const allConfirmed = () => {
    const passeports = data?.lot?.passeports || []
    return passeports.every((p: any) => states[p.id]?.statut !== undefined)
  }

  if (isLoading) return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center">
      <div className="animate-spin rounded-full h-12 w-12 border-2 border-[#1a5276] border-t-transparent" />
    </div>
  )

  if (error || !data) return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
      <div className="bg-white rounded-2xl p-8 text-center max-w-sm shadow-lg">
        <AlertTriangle className="mx-auto text-red-500 mb-3" size={48} />
        <h2 className="font-bold text-gray-800 text-lg">QR Code invalide</h2>
        <p className="text-gray-500 text-sm mt-2">Ce code est expiré ou invalide.</p>
      </div>
    </div>
  )

  if (submitted) return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
      <div className="bg-white rounded-2xl p-8 text-center max-w-sm shadow-lg">
        <CheckCircle className="mx-auto text-green-500 mb-3" size={48} />
        <h2 className="font-bold text-gray-800 text-lg">Réception confirmée</h2>
        <p className="text-gray-500 text-sm mt-2">
          Les notifications ont été envoyées aux citoyens.
        </p>
      </div>
    </div>
  )

  const lot = data.lot

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-[#1a5276] text-white p-6">
        <p className="text-xs opacity-70 mb-1">Réception ambassade — SGP-GE</p>
        <h1 className="text-xl font-bold">{lot.reference}</h1>
        <p className="text-sm opacity-80 mt-1">
          {lot.ambassade?.nom} — {lot.ambassade?.ville}, {lot.ambassade?.pays}
        </p>
      </div>

      {/* Infos lot */}
      <div className="p-4 bg-white border-b">
        <div className="flex gap-4 text-sm">
          <div>
            <span className="text-gray-400">Transporteur</span>
            <p className="font-medium">{lot.transporteur?.nom}</p>
          </div>
          <div>
            <span className="text-gray-400">Total</span>
            <p className="font-bold text-[#1a5276] flex items-center gap-1">
              <Package size={14} /> {lot.passeports_count} passeports
            </p>
          </div>
          <div>
            <span className="text-gray-400">Confirmés</span>
            <p className="font-bold text-green-600">{data.confirmed_count}</p>
          </div>
        </div>
      </div>

      {/* Liste passeports */}
      <div className="p-4 space-y-3 pb-28">
        <p className="text-sm font-semibold text-gray-600">
          Confirmez la réception de chaque passeport :
        </p>

        {lot.passeports?.map((p: any) => {
          const st = states[p.id]?.statut
          return (
            <div key={p.id} className="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
              <div className="flex justify-between items-start">
                <div>
                  <p className="font-mono font-semibold text-[#1a5276]">{p.numero}</p>
                  <p className="text-sm font-medium text-gray-700">{p.prenom_titulaire} {p.nom_titulaire}</p>
                </div>
                {st && (
                  <span className={`text-xs px-2 py-1 rounded-full font-medium ${
                    st === 'confirme' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                  }`}>
                    {st === 'confirme' ? '✓ Reçu' : '⚠ Anomalie'}
                  </span>
                )}
              </div>

              <div className="flex gap-2 mt-3">
                <button
                  onClick={() => toggle(p.id, 'confirme')}
                  className={`flex-1 flex items-center justify-center gap-2 py-2.5 rounded-lg text-sm font-medium border transition ${
                    st === 'confirme'
                      ? 'bg-green-500 text-white border-green-500'
                      : 'border-gray-200 text-gray-600 hover:border-green-400'
                  }`}>
                  <CheckCircle size={16} /> Reçu
                </button>
                <button
                  onClick={() => toggle(p.id, 'anomalie')}
                  className={`flex-1 flex items-center justify-center gap-2 py-2.5 rounded-lg text-sm font-medium border transition ${
                    st === 'anomalie'
                      ? 'bg-red-500 text-white border-red-500'
                      : 'border-gray-200 text-gray-600 hover:border-red-400'
                  }`}>
                  <AlertTriangle size={16} /> Anomalie
                </button>
              </div>

              {st === 'anomalie' && (
                <textarea
                  placeholder="Décrire l'anomalie..."
                  value={states[p.id]?.note || ''}
                  onChange={(e) => setNote(p.id, e.target.value)}
                  className="mt-2 w-full border border-red-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300 resize-none"
                  rows={2}
                />
              )}
            </div>
          )
        })}
      </div>

      {/* Bouton flottant */}
      <div className="fixed bottom-0 left-0 right-0 p-4 bg-white border-t shadow-xl">
        <button
          onClick={handleSubmit}
          disabled={confirm.isPending || !allConfirmed()}
          className="w-full flex items-center justify-center gap-2 bg-[#1a5276] text-white py-3.5 rounded-xl font-semibold text-sm disabled:opacity-50 hover:bg-[#154360] transition"
        >
          <Send size={18} />
          {confirm.isPending ? 'Envoi...' : `Confirmer la réception (${lot.passeports?.length} passeports)`}
        </button>
        {!allConfirmed() && (
          <p className="text-center text-xs text-gray-400 mt-2">
            Veuillez traiter tous les passeports avant de confirmer
          </p>
        )}
      </div>
    </div>
  )
}
