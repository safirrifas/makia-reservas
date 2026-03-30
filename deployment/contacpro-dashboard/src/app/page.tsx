'use client';
import { Sidebar } from '@/components/Sidebar';
import { Header } from '@/components/Header';
import { AuthGuard } from '@/components/AuthGuard';
import { useStore } from '@/lib/store';
import { Key, Users, HeadphonesIcon, TrendingUp } from 'lucide-react';

export default function DashboardPage() {
  const { licenses, clients, tickets } = useStore();

  const stats = [
    { label: 'Licencias Activas', value: licenses.filter(l => l.status === 'active').length, icon: Key, color: 'bg-orange-500' },
    { label: 'Clientes', value: clients.length, icon: Users, color: 'bg-blue-500' },
    { label: 'Tickets Abiertos', value: tickets.filter(t => t.status === 'open').length, icon: HeadphonesIcon, color: 'bg-purple-500' },
    { label: 'Reservas Totales', value: licenses.reduce((acc, l) => acc + l.usage, 0), icon: TrendingUp, color: 'bg-green-500' },
  ];

  return (
    <AuthGuard>
      <div className="flex h-screen bg-gray-50">
        <Sidebar />
        <div className="flex flex-1 flex-col overflow-hidden">
          <Header />
          <main className="flex-1 overflow-y-auto p-6">
            <div className="mb-6">
              <h1 className="text-2xl font-bold">Dashboard</h1>
              <p className="text-gray-600">Resumen general del sistema</p>
            </div>
            <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
              {stats.map((stat) => (
                <div key={stat.label} className="rounded-xl bg-white p-6 shadow-sm">
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="text-sm text-gray-600">{stat.label}</p>
                      <p className="text-3xl font-bold mt-1">{stat.value}</p>
                    </div>
                    <div className={`${stat.color} p-3 rounded-xl`}>
                      <stat.icon className="h-6 w-6 text-white" />
                    </div>
                  </div>
                </div>
              ))}
            </div>
            <div className="mt-8 grid gap-6 lg:grid-cols-2">
              <div className="rounded-xl bg-white p-6 shadow-sm">
                <h2 className="font-semibold mb-4">Últimas Licencias</h2>
                <div className="space-y-3">
                  {licenses.slice(0, 3).map((l) => (
                    <div key={l.id} className="flex items-center justify-between py-2 border-b last:border-0">
                      <div>
                        <p className="font-medium">{l.domain}</p>
                        <p className="text-sm text-gray-500">{l.client}</p>
                      </div>
                      <span className={`px-2 py-1 rounded-full text-xs ${l.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                        {l.plan}
                      </span>
                    </div>
                  ))}
                </div>
              </div>
              <div className="rounded-xl bg-white p-6 shadow-sm">
                <h2 className="font-semibold mb-4">Tickets Recientes</h2>
                <div className="space-y-3">
                  {tickets.slice(0, 3).map((t) => (
                    <div key={t.id} className="flex items-center justify-between py-2 border-b last:border-0">
                      <div>
                        <p className="font-medium">{t.subject}</p>
                        <p className="text-sm text-gray-500">{t.client}</p>
                      </div>
                      <span className={`px-2 py-1 rounded-full text-xs ${t.status === 'open' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800'}`}>
                        {t.status === 'open' ? 'Abierto' : 'Pendiente'}
                      </span>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </main>
        </div>
      </div>
    </AuthGuard>
  );
}
