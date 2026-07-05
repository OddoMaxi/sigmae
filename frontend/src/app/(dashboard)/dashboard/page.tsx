'use client'

import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect } from 'react'
import Link from 'next/link'
import api from '@/lib/api'
import { useAuthStore, hasPermission } from '@/stores/authStore'
import {
  Package, FileText, AlertTriangle, CheckCircle, Clock,
  TrendingUp, Fingerprint, ScanLine, Printer, Archive,
} from 'lucide-react'

interface DashboardData {
  stock: { enrolee: number; en_stock: number; en_lot: number; expedie: number; livre: number; anomalie: number; total: number }
  lots:  { brouillon: number; en_transit: number; recus: number }
  anomalies: { ouvertes: number; en_cours: number; resolues: number }
  recent_lots: any[]
}

const StatCard = ({ title, value, icon: Icon, color, sub }: any) => (
  <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <div className="flex items-center justify-between mb-3">
      <span className="text-sm text-gray-500 font-medium">{title}</span>
      <div className={`p-2 rounded-lg ${color}`}>
        <Icon size={18} className="text-white" />
      </div>
    </div>
    <p className="text-3xl font-bold text-gray-800">{value ?? '—'}</p>
    {sub && <p className="text-xs text-gray-400 mt-1">{sub}</p>}
  </div>
)

const statutColors: Record<string, string> = {
  brouillon:   'bg-gray-100 text-gray-700',
  valide:      'bg-blue-100 text-blue-700',
  expedie:     'bg-yellow-100 text-yellow-700',
  recu:        'bg-green-100 text-green-700',
  recu_partiel:'bg-orange-100 text-orange-700',
  anomalie:    'bg-red-100 text-red-700',
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
        <h1 className="text-2xl font-bold text-gray-800">Bienvenue, {user?.name}</h1>
        <p className="text-sm text-gray-500 mt-1">{user?.role_label ?? user?.role?.replace(/_/g, ' ')}</p>
      </div>
      <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
        {visible.map((l) => (
          <Link key={l.href} href={l.href}
            className="bg-white rounded-xl border border-gray-100 shadow-sm p-6 hover:shadow-md transition group">
            <l.icon className={`${l.color} mb-3 group-hover:scale-110 transition-transform`} size={28} />
            <p className="font-medium text-gray-800">{l.label}</p>
            <p className="text-xs text-gray-400 mt-1">{l.desc}</p>
          </Link>
        ))}
      </div>
    </div>
  )
}

export default function DashboardPage() {
  const qc = useQueryClient()
  const { user } = useAuthStore()

  const canViewReporting  = hasPermission(user, 'reporting.view')
  const canViewPasseports = hasPermission(user, 'passeports.view')
  const canViewLots       = hasPermission(user, 'lots.view')

  const { data, isLoading } = useQuery<DashboardData>({
    queryKey: ['dashboard'],
    queryFn:  () => api.get('/reporting/dashboard').then((r) => r.data),
    refetchInterval: 30000,
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

  if (!canViewReporting) return <WelcomeDashboard />

  if (isLoading) return (
    <div className="flex items-center justify-center h-64">
      <div className="animate-spin rounded-full h-10 w-10 border-2 border-[#1a5276] border-t-transparent" />
    </div>
  )

  return (
    <div className="space-y-8">
      <div>
        <h1 className="text-2xl font-bold text-gray-800">Tableau de bord</h1>
        <p className="text-sm text-gray-500 mt-1">Vue d'ensemble du système SGP-GE</p>
      </div>

      {/* Stock */}
      <section>
        <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Stock & Passeports</h2>
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-7 gap-4">
          <StatCard title="Enrôlés"   value={data?.stock.enrolee}   icon={Fingerprint}   color="bg-violet-500" />
          <StatCard title="En stock"  value={data?.stock.en_stock}  icon={FileText}      color="bg-[#1a5276]" />
          <StatCard title="En lot"    value={data?.stock.en_lot}    icon={Package}       color="bg-blue-500" />
          <StatCard title="Expédiés"  value={data?.stock.expedie}   icon={TrendingUp}    color="bg-yellow-500" />
          <StatCard title="Livrés"    value={data?.stock.livre}     icon={CheckCircle}   color="bg-green-500" />
          <StatCard title="Anomalies" value={data?.stock.anomalie}  icon={AlertTriangle} color="bg-red-500" />
          <StatCard title="Total"     value={data?.stock.total}     icon={FileText}      color="bg-gray-500" />
        </div>
      </section>

      {/* Lots */}
      <section>
        <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Lots d'expédition</h2>
        <div className="grid grid-cols-3 gap-4">
          <StatCard title="Brouillons"   value={data?.lots.brouillon}  icon={Package}     color="bg-gray-400"    sub="En préparation" />
          <StatCard title="En transit"   value={data?.lots.en_transit} icon={Clock}       color="bg-yellow-500"  sub="Validés / Expédiés" />
          <StatCard title="Réceptionnés" value={data?.lots.recus}      icon={CheckCircle} color="bg-green-500"   sub="Arrivés en ambassade" />
        </div>
      </section>

      {/* Anomalies + Lots récents */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
          <h3 className="font-semibold text-gray-700 mb-4">Anomalies</h3>
          <div className="space-y-3">
            {[
              { label: 'Ouvertes',      value: data?.anomalies.ouvertes, color: 'text-red-600' },
              { label: 'En traitement', value: data?.anomalies.en_cours, color: 'text-orange-600' },
              { label: 'Résolues',      value: data?.anomalies.resolues, color: 'text-green-600' },
            ].map((row) => (
              <div key={row.label} className="flex items-center justify-between py-2 border-b last:border-0">
                <span className="text-sm text-gray-600">{row.label}</span>
                <span className={`font-bold text-lg ${row.color}`}>{row.value ?? 0}</span>
              </div>
            ))}
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
          <h3 className="font-semibold text-gray-700 mb-4">Derniers lots</h3>
          <div className="space-y-3">
            {data?.recent_lots.map((lot: any) => (
              <div key={lot.id} className="flex items-center justify-between py-2 border-b last:border-0">
                <div>
                  <p className="text-sm font-medium text-gray-700">{lot.reference}</p>
                  <p className="text-xs text-gray-400">{lot.ambassade?.nom}</p>
                </div>
                <span className={`text-xs px-2 py-1 rounded-full font-medium ${statutColors[lot.statut] ?? 'bg-gray-100'}`}>
                  {lot.statut}
                </span>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  )
}
