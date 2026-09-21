'use client'

import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'
import { downloadFile } from '@/lib/download'
import { useAuthStore, hasPermission } from '@/stores/authStore'
import { Download } from 'lucide-react'
import {
  BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer,
  PieChart, Pie, Cell, LineChart, Line, Legend
} from 'recharts'

const COLORS = ['#1a5276', '#2980b9', '#52be80', '#f39c12', '#e74c3c', '#8e44ad']

/* ─── Export ──────────────────────────────────────────────────────────── */

type ExportType = 'passeports' | 'lots' | 'anomalies'

const EXPORT_TYPES: { value: ExportType; label: string }[] = [
  { value: 'passeports', label: 'Passeports' },
  { value: 'lots',       label: 'Lots' },
  { value: 'anomalies',  label: 'Anomalies' },
]

const STATUT_OPTIONS: Record<ExportType, { value: string; label: string }[]> = {
  passeports: [
    { value: '', label: 'Tous les statuts' },
    { value: 'enrolee',            label: 'Enrôlé' },
    { value: 'imprime',            label: 'Imprimé' },
    { value: 'recu_mae',           label: 'Reçu MAE' },
    { value: 'en_stock',           label: 'En stock' },
    { value: 'en_lot',             label: 'En lot' },
    { value: 'expedie',            label: 'Expédié' },
    { value: 'en_transit',         label: 'En transit' },
    { value: 'recu_ambassade',     label: 'Reçu ambassade' },
    { value: 'disponible_retrait', label: 'Disponible retrait' },
    { value: 'remis_citoyen',      label: 'Remis citoyen' },
    { value: 'anomalie',           label: 'Anomalie' },
  ],
  lots: [
    { value: '', label: 'Tous les statuts' },
    { value: 'brouillon',    label: 'Brouillon' },
    { value: 'valide',       label: 'Validé' },
    { value: 'expedie',      label: 'Expédié' },
    { value: 'recu',         label: 'Reçu' },
    { value: 'recu_partiel', label: 'Reçu partiel' },
    { value: 'anomalie',     label: 'Anomalie' },
  ],
  anomalies: [
    { value: '', label: 'Tous les statuts' },
    { value: 'ouvert',        label: 'Ouvert' },
    { value: 'en_traitement', label: 'En traitement' },
    { value: 'resolu',        label: 'Résolu' },
  ],
}

function ExportPanel() {
  const [type, setType]     = useState<ExportType>('passeports')
  const [statut, setStatut] = useState('')
  const [ambassadeId, setAmbassadeId] = useState('')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo]     = useState('')
  const [loading, setLoading]   = useState(false)

  const { data: ambassades } = useQuery({
    queryKey: ['ambassades'],
    queryFn:  () => api.get('/ambassades').then(r => r.data),
  })

  const onTypeChange = (t: ExportType) => { setType(t); setStatut('') }

  const onExport = async () => {
    setLoading(true)
    await downloadFile(
      `/export/${type}`,
      {
        format: 'xlsx',
        statut: statut || undefined,
        ambassade_id: ambassadeId || undefined,
        date_from: dateFrom || undefined,
        date_to: dateTo || undefined,
      },
      `${type}.xlsx`
    )
    setLoading(false)
  }

  const inputClass = 'w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[color:var(--color-navy-600)]/30 focus:border-[color:var(--color-navy-600)]'

  return (
    <div className="bg-white rounded-[var(--radius-card)] border border-slate-200/70 p-6">
      <div className="flex items-center justify-between mb-4">
        <div>
          <h3 className="text-[13.5px] font-semibold text-[color:var(--color-navy-900)]">Exporter les données</h3>
          <p className="text-[12px] text-slate-500 mt-0.5">Filtrez par statut, ambassade et période, puis exportez en Excel.</p>
        </div>
      </div>

      <div className="grid grid-cols-2 md:grid-cols-5 gap-3 items-end">
        <div>
          <label className="block text-[11px] font-semibold text-slate-500 mb-1">Type de données</label>
          <select className={inputClass} value={type} onChange={(e) => onTypeChange(e.target.value as ExportType)}>
            {EXPORT_TYPES.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
          </select>
        </div>

        <div>
          <label className="block text-[11px] font-semibold text-slate-500 mb-1">Statut</label>
          <select className={inputClass} value={statut} onChange={(e) => setStatut(e.target.value)}>
            {STATUT_OPTIONS[type].map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
          </select>
        </div>

        <div>
          <label className="block text-[11px] font-semibold text-slate-500 mb-1">Ambassade</label>
          <select className={inputClass} value={ambassadeId} onChange={(e) => setAmbassadeId(e.target.value)}>
            <option value="">Toutes les ambassades</option>
            {ambassades?.map((a: any) => (
              <option key={a.id} value={a.id}>{a.nom} — {a.ville}</option>
            ))}
          </select>
        </div>

        <div>
          <label className="block text-[11px] font-semibold text-slate-500 mb-1">Du</label>
          <input type="date" className={inputClass} value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} />
        </div>

        <div>
          <label className="block text-[11px] font-semibold text-slate-500 mb-1">Au</label>
          <input type="date" className={inputClass} value={dateTo} onChange={(e) => setDateTo(e.target.value)} />
        </div>
      </div>

      <button
        onClick={onExport}
        disabled={loading}
        className="mt-4 flex items-center gap-2 text-white font-medium px-4 py-2.5 rounded-lg text-sm transition disabled:opacity-60"
        style={{ background: 'var(--color-navy-900)' }}
      >
        <Download size={15} /> {loading ? 'Génération…' : 'Exporter en Excel'}
      </button>
    </div>
  )
}

/* ─── Page principale ─────────────────────────────────────────────────── */

export default function ReportingPage() {
  const { user } = useAuthStore()

  const { data: dashboard } = useQuery({
    queryKey: ['dashboard'],
    queryFn:  () => api.get('/reporting/dashboard').then(r => r.data),
  })

  const { data: receptions } = useQuery({
    queryKey: ['receptions'],
    queryFn:  () => api.get('/reporting/receptions').then(r => r.data),
  })

  const { data: perf } = useQuery({
    queryKey: ['performance'],
    queryFn:  () => api.get('/reporting/performance').then(r => r.data),
  })

  const stockData = dashboard?.stock ? [
    { name: 'En stock',  value: dashboard.stock.en_stock  },
    { name: 'En lot',    value: dashboard.stock.en_lot    },
    { name: 'Expédiés',  value: dashboard.stock.expedie   },
    { name: 'Livrés',    value: dashboard.stock.remis_citoyen },
    { name: 'Anomalies', value: dashboard.stock.anomalie  },
  ] : []

  const perfChartData = perf?.map((p: any) => ({
    mois:   new Date(p.mois).toLocaleDateString('fr-FR', { month: 'short', year: '2-digit' }),
    delai:  Math.round(p.delai_moyen * 10) / 10,
    lots:   p.total_lots,
  })) || []

  const recepChartData = receptions?.map((r: any) => ({
    ambassade: `${r.nom} (${r.pays})`,
    recus:     Number(r.recus) || 0,
    partiels:  Number(r.partiels) || 0,
    attente:   Number(r.en_attente) || 0,
  })) || []

  return (
    <div className="space-y-8">
      <div>
        <h1 className="text-[22px] font-bold text-[color:var(--color-navy-900)] tracking-tight">Reporting</h1>
        <p className="text-sm text-slate-500">Statistiques et indicateurs de performance</p>
      </div>

      {hasPermission(user, 'reporting.export') && <ExportPanel />}

      {/* Stock Pie */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="bg-white rounded-[var(--radius-card)] border border-slate-200/70 p-6">
          <h3 className="font-semibold text-slate-700 mb-4">Répartition du stock</h3>
          <ResponsiveContainer width="100%" height={260}>
            <PieChart>
              <Pie data={stockData} dataKey="value" nameKey="name" cx="50%" cy="50%"
                outerRadius={90} label={({ name, percent }) => `${name} ${((percent ?? 0) * 100).toFixed(0)}%`}>
                {stockData.map((_, i) => <Cell key={i} fill={COLORS[i % COLORS.length]} />)}
              </Pie>
              <Tooltip />
            </PieChart>
          </ResponsiveContainer>
        </div>

        {/* Performance délais */}
        <div className="bg-white rounded-[var(--radius-card)] border border-slate-200/70 p-6">
          <h3 className="font-semibold text-slate-700 mb-4">Délai moyen de livraison (jours)</h3>
          <ResponsiveContainer width="100%" height={260}>
            <LineChart data={perfChartData}>
              <XAxis dataKey="mois" tick={{ fontSize: 11 }} />
              <YAxis tick={{ fontSize: 11 }} />
              <Tooltip />
              <Legend />
              <Line type="monotone" dataKey="delai" stroke="#1a5276" strokeWidth={2} dot />
            </LineChart>
          </ResponsiveContainer>
        </div>
      </div>

      {/* Réceptions par ambassade */}
      <div className="bg-white rounded-[var(--radius-card)] border border-slate-200/70 p-6">
        <h3 className="font-semibold text-slate-700 mb-4">Réceptions par ambassade</h3>
        <ResponsiveContainer width="100%" height={320}>
          <BarChart data={recepChartData} layout="vertical" margin={{ left: 20 }}>
            <XAxis type="number" tick={{ fontSize: 11 }} />
            <YAxis dataKey="ambassade" type="category" width={160} tick={{ fontSize: 11 }} />
            <Tooltip />
            <Legend />
            <Bar dataKey="recus"   fill="#52be80" name="Réceptionnés" stackId="a" />
            <Bar dataKey="partiels" fill="#f39c12" name="Partiels"     stackId="a" />
            <Bar dataKey="attente"  fill="#aab7b8"  name="En attente"  stackId="a" />
          </BarChart>
        </ResponsiveContainer>
      </div>
    </div>
  )
}
