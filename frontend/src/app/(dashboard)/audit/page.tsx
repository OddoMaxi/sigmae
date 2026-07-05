'use client'

import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'
import { ClipboardList, Search } from 'lucide-react'

export default function AuditPage() {
  const [action, setAction] = useState('')
  const [page,   setPage]   = useState(1)

  const { data, isLoading } = useQuery({
    queryKey: ['audit', action, page],
    queryFn:  () => api.get('/audit', { params: { action, page } }).then(r => r.data),
    placeholderData: (prev: any) => prev,
  })

  const actionColor = (action: string) => {
    if (action.includes('delete')) return 'bg-red-100 text-red-700'
    if (action.includes('create') || action.includes('reception')) return 'bg-green-100 text-green-700'
    if (action.includes('update') || action.includes('valider') || action.includes('expedier')) return 'bg-blue-100 text-blue-700'
    if (action.includes('login') || action.includes('logout')) return 'bg-gray-100 text-gray-700'
    return 'bg-purple-100 text-purple-700'
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-800">Journal d'audit</h1>
        <p className="text-sm text-gray-500">Historique complet de toutes les actions du système</p>
      </div>

      <div className="bg-white rounded-xl border border-gray-100 shadow-sm p-4 flex gap-4">
        <div className="flex-1 relative">
          <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
          <input
            value={action}
            onChange={(e) => { setAction(e.target.value); setPage(1) }}
            placeholder="Filtrer par action (ex: lot.create, passeport...)"
            className="w-full pl-9 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#1a5276]"
          />
        </div>
      </div>

      <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        {isLoading ? (
          <div className="flex items-center justify-center h-48">
            <div className="animate-spin rounded-full h-8 w-8 border-2 border-[#1a5276] border-t-transparent" />
          </div>
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="bg-gray-50 border-b">
                {['Date/Heure', 'Utilisateur', 'Action', 'Entité', 'IP'].map(h => (
                  <th key={h} className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {data?.data?.map((log: any) => (
                <tr key={log.id} className="border-b hover:bg-gray-50 transition">
                  <td className="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                    {new Date(log.created_at).toLocaleDateString('fr-FR', {
                      day: '2-digit', month: '2-digit', year: 'numeric',
                      hour: '2-digit', minute: '2-digit'
                    })}
                  </td>
                  <td className="px-4 py-3">
                    <p className="font-medium text-gray-700">{log.user?.name || 'Système'}</p>
                    <p className="text-xs text-gray-400">{log.user?.role}</p>
                  </td>
                  <td className="px-4 py-3">
                    <span className={`text-xs px-2 py-1 rounded-full font-mono font-medium ${actionColor(log.action)}`}>
                      {log.action}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-xs text-gray-600">
                    {log.entity_type && (
                      <span>{log.entity_type} #{log.entity_id}</span>
                    )}
                  </td>
                  <td className="px-4 py-3 text-xs text-gray-400 font-mono">{log.ip_address}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}

        {data?.last_page > 1 && (
          <div className="flex items-center justify-between px-4 py-3 border-t">
            <p className="text-xs text-gray-500">{data.from}–{data.to} sur {data.total}</p>
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
      </div>
    </div>
  )
}
