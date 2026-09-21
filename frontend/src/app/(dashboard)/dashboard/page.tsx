'use client'

import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import Link from 'next/link'
import api from '@/lib/api'
import { useAuthStore, hasPermission } from '@/stores/authStore'
import { AreaChart, Area, XAxis, Tooltip, ResponsiveContainer } from 'recharts'
import {
  Package, FileText, AlertTriangle, CheckCircle,
  Fingerprint, ScanLine, Printer, Archive, ArrowUpRight,
} from 'lucide-react'
import { cn, formatDateTime } from '@/lib/utils'

interface DashboardData {
  stock: { enrolee: number; en_stock: number; en_lot: number; expedie: number; remis_citoyen: number; anomalie: number; total: number }
  lots:  { brouillon: number; en_transit: number; recus: number }
  anomalies: { ouvertes: number; en_cours: number; resolues: number }
  recent_lots: any[]
}

const NAVY = '#1a5276'

/* ─── Anneau de progression ──────────────────────────────────────────── */

function ProgressRing({ value, label, sub }: { value: number; label: string; sub: string }) {
  const r = 46
  const c = 2 * Math.PI * r
  const pct = Number.isFinite(value) ? Math.max(0, Math.min(100, value)) : 0
  return (
    <div className="flex items-center gap-5">
      <div className="relative h-[112px] w-[112px] shrink-0">
        <svg width="112" height="112" viewBox="0 0 112 112" className="-rotate-90">
          <circle cx="56" cy="56" r={r} fill="none" stroke="#eef1f4" strokeWidth="10" />
          <circle
            cx="56" cy="56" r={r} fill="none" stroke={NAVY} strokeWidth="10" strokeLinecap="round"
            strokeDasharray={c} strokeDashoffset={c - (pct / 100) * c}
            style={{ transition: 'stroke-dashoffset 0.6s ease' }}
          />
        </svg>
        <div className="absolute inset-0 flex items-center justify-center">
          <span className="text-2xl font-extrabold text-[color:var(--color-navy-900)] tabular-nums">{Math.round(pct)}%</span>
        </div>
      </div>
      <div>
        <p className="text-[13px] font-semibold text-slate-700">{label}</p>
        <p className="text-[11.5px] text-slate-400 mt-0.5">{sub}</p>
      </div>
    </div>
  )
}

/* ─── Carte KPI ───────────────────────────────────────────────────────── */

function Kpi({ label, value, dot, icon: Icon }: { label: string; value: number | undefined; dot: string; icon: any }) {
  return (
    <div className="bg-white rounded-[var(--radius-card)] border border-slate-200/70 p-4">
      <div className="flex items-center justify-between mb-3">
        <div className="flex items-center gap-1.5">
          <span className="h-1.5 w-1.5 rounded-full" style={{ background: dot }} />
          <span className="text-[11.5px] font-medium text-slate-500">{label}</span>
        </div>
        <Icon size={14} className="text-slate-300" />
      </div>
      <p className="text-[26px] font-extrabold text-[color:var(--color-navy-900)] tabular-nums leading-none">{value ?? '—'}</p>
    </div>
  )
}

const statutColors: Record<string, string> = {
  brouillon:    'bg-slate-100 text-slate-600',
  valide:       'bg-blue-50 text-blue-700',
  expedie:      'bg-amber-50 text-amber-700',
  recu:         'bg-emerald-50 text-emerald-700',
  recu_partiel: 'bg-orange-50 text-orange-700',
  anomalie:     'bg-red-50 text-red-700',
}

const AVATAR_HUES = ['#c99a2e', '#3d87b2', '#7c9885', '#b06b5f', '#8a7ab5']
function hue(name: string) {
  const sum = [...(name || '?')].reduce((acc, c) => acc + c.charCodeAt(0), 0)
  return AVATAR_HUES[sum % AVATAR_HUES.length]
}

function ChartTooltip({ active, payload, label }: any) {
  if (!active || !payload?.length) return null
  return (
    <div className="bg-[color:var(--color-navy-900)] text-white text-[11.5px] rounded-lg px-3 py-2 shadow-lg">
      <p className="text-white/50 mb-0.5">{label}</p>
      <p className="font-semibold tabular-nums">{payload[0].value} j en moyenne</p>
    </div>
  )
}

const quickLinks = [
  { href: '/passeports',    label: 'Passeports',     desc: 'Consulter les passeports',   icon: FileText,    permission: 'passeports.view',  color: 'text-[#1a5276]' },
  { href: '/lots',          label: 'Lots',            desc: 'Réceptionner les lots',       icon: Package,     permission: 'lots.view',         color: 'text-blue-600' },
  { href: '/enrolements',   label: 'Enrôlements',     desc: 'Gérer les enrôlements',       icon: Fingerprint, permission: 'enrolement.view',   color: 'text-green-600' },
  { href: '/reception-mae', label: 'Réception MAE',   desc: 'Scanner les passeports',      icon: ScanLine,    permission: 'passeports.create', color: 'text-purple-600' },
  { href: '/impression',    label: 'Impression',      desc: 'Imprimer les passeports',     icon: Printer,     permission: 'passeports.create', color: 'text-indigo-600' },
  { href: '/stock',         label: 'Stock Central',   desc: 'Inventaire du stock',         icon: Archive,     permission: 'passeports.view',   color: 'text-orange-600' },
  { href: '/anomalies',     label: 'Anomalies',       desc: 'Signaler ou consulter',       icon: AlertTriangle, permission: 'anomalies.view',  color: 'text-red-500' },
]

function WelcomeDashboard() {
  const { user } = useAuthStore()
  const visible = quickLinks.filter((l) => hasPermission(user, l.permission))

  return (
    <div className="space-y-8">
      <div>
        <h1 className="text-[22px] font-bold text-[color:var(--color-navy-900)] tracking-tight">Bienvenue, {user?.name}</h1>
        <p className="text-[13px] text-slate-500 mt-1 capitalize">{user?.role_label ?? user?.role?.replace(/_/g, ' ')}</p>
      </div>
      <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
        {visible.map((l) => (
          <Link key={l.href} href={l.href}
            className="bg-white rounded-[var(--radius-card)] border border-slate-200/70 p-6 hover:border-slate-300 hover:shadow-sm transition group">
            <l.icon className={`${l.color} mb-3 group-hover:scale-110 transition-transform`} size={26} />
            <p className="font-semibold text-[color:var(--color-navy-900)] text-[14px]">{l.label}</p>
            <p className="text-[12px] text-slate-400 mt-1">{l.desc}</p>
          </Link>
        ))}
      </div>
    </div>
  )
}

export default function DashboardPage() {
  const qc = useQueryClient()
  const { user } = useAuthStore()
  const [now, setNow] = useState<Date | null>(null)

  const canViewReporting  = hasPermission(user, 'reporting.view')
  const canViewPasseports = hasPermission(user, 'passeports.view')
  const canViewLots       = hasPermission(user, 'lots.view')

  const { data, isLoading, dataUpdatedAt } = useQuery<DashboardData>({
    queryKey: ['dashboard'],
    queryFn:  () => api.get('/reporting/dashboard').then((r) => r.data),
    refetchInterval: 30000,
    enabled: canViewReporting,
  })

  const { data: perf } = useQuery({
    queryKey: ['performance'],
    queryFn:  () => api.get('/reporting/performance').then((r) => r.data),
    enabled: canViewReporting,
  })

  const { data: receptions } = useQuery({
    queryKey: ['receptions'],
    queryFn:  () => api.get('/reporting/receptions').then((r) => r.data),
    enabled: canViewReporting,
  })

  useEffect(() => {
    if (canViewPasseports) {
      qc.prefetchQuery({ queryKey: ['passeports', '', '', 1], queryFn: () => api.get('/passeports', { params: { page: 1, per_page: 50 } }).then(r => r.data) })
    }
    if (canViewLots) {
      qc.prefetchQuery({ queryKey: ['lots', undefined, 1], queryFn: () => api.get('/lots', { params: { page: 1 } }).then(r => r.data) })
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  useEffect(() => { setNow(new Date()) }, [dataUpdatedAt])

  if (!canViewReporting) return <WelcomeDashboard />

  if (isLoading) return (
    <div className="flex items-center justify-center h-64">
      <div className="animate-spin rounded-full h-8 w-8 border-2 border-[color:var(--color-navy-600)] border-t-transparent" />
    </div>
  )

  const total = data?.stock.total ?? 0
  const remisRate = total > 0 ? (data!.stock.remis_citoyen / total) * 100 : 0

  const perfChartData = (perf ?? []).map((p: any) => ({
    mois:  new Date(p.mois).toLocaleDateString('fr-FR', { month: 'short' }),
    delai: Math.round((p.delai_moyen ?? 0) * 10) / 10,
  }))

  const ambassadesRanked = [...(receptions ?? [])]
    .sort((a: any, b: any) => (Number(b.recus) + Number(b.en_attente)) - (Number(a.recus) + Number(a.en_attente)))
    .slice(0, 6)

  return (
    <div className="space-y-6">
      <div className="flex items-end justify-between">
        <div>
          <h1 className="text-[22px] font-bold text-[color:var(--color-navy-900)] tracking-tight">Tableau de bord</h1>
          <p className="text-[13px] text-slate-500 mt-1">Vue d&apos;ensemble du système SGP-GE</p>
        </div>
        {now && (
          <p className="text-[11px] text-slate-400">
            Actualisé à {now.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}
          </p>
        )}
      </div>

      {/* Hero : anneau + KPIs */}
      <div className="grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-4">
        <div className="bg-white rounded-[var(--radius-card)] border border-slate-200/70 p-5 flex items-center">
          <ProgressRing value={remisRate} label="Taux de remise" sub={`${data?.stock.remis_citoyen ?? 0} sur ${total} passeports`} />
        </div>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <Kpi label="Enrôlés"   value={data?.stock.enrolee}  dot="#8a7ab5" icon={Fingerprint} />
          <Kpi label="En stock"  value={data?.stock.en_stock} dot={NAVY}    icon={Archive} />
          <Kpi label="En lot"    value={data?.stock.en_lot}   dot="#3d87b2" icon={Package} />
          <Kpi label="Anomalies" value={data?.stock.anomalie} dot="#dc2626" icon={AlertTriangle} />
        </div>
      </div>

      {/* Chart + Ambassades */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div className="lg:col-span-2 bg-white rounded-[var(--radius-card)] border border-slate-200/70 p-5">
          <div className="flex items-center justify-between mb-1">
            <h3 className="text-[13.5px] font-semibold text-[color:var(--color-navy-900)]">Délai moyen de traitement</h3>
            <span className="text-[11px] text-slate-400">par mois, en jours</span>
          </div>
          {perfChartData.length < 2 ? (
            <div className="h-[220px] flex items-center justify-center text-[12.5px] text-slate-400">
              Historique en cours de constitution — revenez après le prochain lot réceptionné.
            </div>
          ) : (
            <ResponsiveContainer width="100%" height={220}>
              <AreaChart data={perfChartData} margin={{ top: 16, right: 8, left: -20, bottom: 0 }}>
                <defs>
                  <linearGradient id="perfFill" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor={NAVY} stopOpacity={0.28} />
                    <stop offset="100%" stopColor={NAVY} stopOpacity={0} />
                  </linearGradient>
                </defs>
                <XAxis dataKey="mois" tick={{ fontSize: 11, fill: '#94a3b8' }} axisLine={false} tickLine={false} />
                <Tooltip content={<ChartTooltip />} cursor={{ stroke: '#e2e8f0', strokeWidth: 1 }} />
                <Area type="monotone" dataKey="delai" stroke={NAVY} strokeWidth={2} fill="url(#perfFill)" />
              </AreaChart>
            </ResponsiveContainer>
          )}
        </div>

        <div className="bg-white rounded-[var(--radius-card)] border border-slate-200/70 p-5">
          <div className="flex items-center justify-between mb-3">
            <h3 className="text-[13.5px] font-semibold text-[color:var(--color-navy-900)]">Ambassades actives</h3>
            <Link href="/ambassades" className="text-[11px] text-slate-400 hover:text-[color:var(--color-navy-600)]">Tout voir</Link>
          </div>
          <div className="space-y-1">
            {ambassadesRanked.length === 0 && <p className="text-[12.5px] text-slate-400 py-4 text-center">Aucune donnée</p>}
            {ambassadesRanked.map((a: any) => {
              const enAttente = Number(a.en_attente) || 0
              const status = enAttente > 3 ? '#dc2626' : enAttente > 0 ? '#d97706' : '#16a34a'
              return (
                <div key={a.id} className="flex items-center gap-3 py-2 border-b border-slate-50 last:border-0">
                  <div
                    className="h-7 w-7 shrink-0 rounded-full flex items-center justify-center text-[10px] font-bold text-white"
                    style={{ background: hue(a.nom) }}
                  >
                    {a.nom?.slice(0, 2).toUpperCase()}
                  </div>
                  <div className="min-w-0 flex-1">
                    <p className="text-[12.5px] font-medium text-slate-700 truncate">{a.nom}</p>
                    <p className="text-[10.5px] text-slate-400 truncate">{a.pays}</p>
                  </div>
                  <span className="h-1.5 w-1.5 rounded-full shrink-0" style={{ background: status }} title={`${enAttente} lot(s) en attente`} />
                </div>
              )
            })}
          </div>
        </div>
      </div>

      {/* Anomalies + Derniers lots */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div className="bg-white rounded-[var(--radius-card)] border border-slate-200/70 p-5">
          <h3 className="text-[13.5px] font-semibold text-[color:var(--color-navy-900)] mb-4">Anomalies</h3>
          <div className="space-y-3">
            {[
              { label: 'Ouvertes',      value: data?.anomalies.ouvertes, color: '#dc2626' },
              { label: 'En traitement', value: data?.anomalies.en_cours, color: '#d97706' },
              { label: 'Résolues',      value: data?.anomalies.resolues, color: '#16a34a' },
            ].map((row) => (
              <div key={row.label} className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <span className="h-1.5 w-1.5 rounded-full" style={{ background: row.color }} />
                  <span className="text-[12.5px] text-slate-600">{row.label}</span>
                </div>
                <span className="font-bold text-[15px] tabular-nums text-[color:var(--color-navy-900)]">{row.value ?? 0}</span>
              </div>
            ))}
          </div>
        </div>

        <div className="lg:col-span-2 bg-white rounded-[var(--radius-card)] border border-slate-200/70 p-5">
          <div className="flex items-center justify-between mb-3">
            <h3 className="text-[13.5px] font-semibold text-[color:var(--color-navy-900)]">Derniers lots</h3>
            <Link href="/lots" className="text-[11px] text-slate-400 hover:text-[color:var(--color-navy-600)] flex items-center gap-0.5">
              Tout voir <ArrowUpRight size={11} />
            </Link>
          </div>
          <div>
            {(!data?.recent_lots || data.recent_lots.length === 0) && (
              <p className="text-[12.5px] text-slate-400 py-6 text-center">Aucun lot pour le moment</p>
            )}
            {data?.recent_lots.map((lot: any) => (
              <Link key={lot.id} href={`/lots/${lot.id}`}
                className="flex items-center justify-between py-2.5 border-b border-slate-50 last:border-0 hover:bg-slate-50/60 -mx-2 px-2 rounded-lg transition-colors">
                <div className="min-w-0">
                  <p className="text-[12.5px] font-semibold text-slate-700 font-mono">{lot.reference}</p>
                  <p className="text-[11px] text-slate-400">{lot.ambassade?.nom} · {formatDateTime(lot.created_at)}</p>
                </div>
                <span className={cn('text-[10.5px] px-2 py-1 rounded-full font-medium shrink-0', statutColors[lot.statut] ?? 'bg-slate-100 text-slate-600')}>
                  {lot.statut}
                </span>
              </Link>
            ))}
          </div>
        </div>
      </div>
    </div>
  )
}
