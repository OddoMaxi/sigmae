'use client'

import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'
import {
  BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer,
  PieChart, Pie, Cell, LineChart, Line, Legend
} from 'recharts'

const COLORS = ['#1a5276', '#2980b9', '#52be80', '#f39c12', '#e74c3c', '#8e44ad']

export default function ReportingPage() {
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

  const { data: anomaliesStats } = useQuery({
    queryKey: ['anomalies-stats'],
    queryFn:  () => api.get('/reporting/anomalies').then(r => r.data),
  })

  const stockData = dashboard?.stock ? [
    { name: 'En stock',  value: dashboard.stock.en_stock  },
    { name: 'En lot',    value: dashboard.stock.en_lot    },
    { name: 'Expédiés',  value: dashboard.stock.expedie   },
    { name: 'Livrés',    value: dashboard.stock.livre     },
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
        <h1 className="text-2xl font-bold text-gray-800">Reporting</h1>
        <p className="text-sm text-gray-500">Statistiques et indicateurs de performance</p>
      </div>

      {/* Stock Pie */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
          <h3 className="font-semibold text-gray-700 mb-4">Répartition du stock</h3>
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
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
          <h3 className="font-semibold text-gray-700 mb-4">Délai moyen de livraison (jours)</h3>
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
      <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 className="font-semibold text-gray-700 mb-4">Réceptions par ambassade</h3>
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
