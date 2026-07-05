'use client'

import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { useAuthStore, hasRole } from '@/stores/authStore'
import {
  LayoutDashboard, FileText, Package, Building2,
  Truck, AlertTriangle, BarChart3, ClipboardList,
  Users, LogOut, Shield
} from 'lucide-react'
import { cn } from '@/lib/utils'

const navItems = [
  { href: '/dashboard',     label: 'Tableau de bord', icon: LayoutDashboard, roles: ['*'] },
  { href: '/passeports',    label: 'Passeports',       icon: FileText,         roles: ['admin_mae', 'gestionnaire', 'superviseur'] },
  { href: '/lots',          label: 'Lots',             icon: Package,          roles: ['admin_mae', 'gestionnaire', 'superviseur'] },
  { href: '/ambassades',    label: 'Ambassades',       icon: Building2,        roles: ['admin_mae', 'gestionnaire', 'superviseur'] },
  { href: '/transporteurs', label: 'Transporteurs',    icon: Truck,            roles: ['admin_mae', 'gestionnaire'] },
  { href: '/anomalies',     label: 'Anomalies',        icon: AlertTriangle,    roles: ['*'] },
  { href: '/reporting',     label: 'Reporting',        icon: BarChart3,        roles: ['admin_mae', 'superviseur', 'gestionnaire'] },
  { href: '/audit',         label: 'Audit',            icon: ClipboardList,    roles: ['admin_mae', 'superviseur'] },
  { href: '/utilisateurs',  label: 'Utilisateurs',     icon: Users,            roles: ['admin_mae'] },
]

export default function Sidebar() {
  const pathname  = usePathname()
  const { user, logout } = useAuthStore()

  const visible = navItems.filter(
    (item) => item.roles.includes('*') || hasRole(user, ...item.roles)
  )

  return (
    <aside className="flex flex-col w-64 bg-[#1a5276] text-white min-h-screen">
      <div className="p-6 border-b border-white/10">
        <div className="flex items-center gap-2 mb-1">
          <Shield size={20} />
          <span className="font-bold text-lg">SGP-GE</span>
        </div>
        <p className="text-xs text-white/60">Gestion des Passeports</p>
      </div>

      <nav className="flex-1 p-4 space-y-1 overflow-y-auto">
        {visible.map((item) => {
          const active = pathname.startsWith(item.href)
          return (
            <Link key={item.href} href={item.href}
              className={cn(
                'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition',
                active
                  ? 'bg-white/20 font-medium'
                  : 'hover:bg-white/10 text-white/80 hover:text-white'
              )}>
              <item.icon size={18} />
              {item.label}
            </Link>
          )
        })}
      </nav>

      <div className="p-4 border-t border-white/10">
        <div className="text-xs text-white/60 mb-3">
          <p className="font-medium text-white/90 truncate">{user?.name}</p>
          <p className="capitalize">{user?.role?.replace('_', ' ')}</p>
        </div>
        <button
          onClick={logout}
          className="flex items-center gap-2 text-xs text-white/70 hover:text-white transition w-full"
        >
          <LogOut size={14} />
          Déconnexion
        </button>
      </div>
    </aside>
  )
}
