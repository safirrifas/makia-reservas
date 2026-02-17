'use client';
import { Sidebar } from '@/components/Sidebar';
import { Header } from '@/components/Header';
import { AuthGuard } from '@/components/AuthGuard';
import { CreditCard, Download, CheckCircle } from 'lucide-react';

const invoices = [
  { id: 1, date: '2026-02-01', amount: 49.99, status: 'paid', plan: 'MASCANA' },
  { id: 2, date: '2026-01-01', amount: 49.99, status: 'paid', plan: 'MASCANA' },
  { id: 3, date: '2025-12-01', amount: 29.99, status: 'paid', plan: 'CANA' },
];

export default function BillingPage() {
  return (
    <AuthGuard>
      <div className="flex h-screen bg-gray-50">
        <Sidebar />
        <div className="flex flex-1 flex-col overflow-hidden">
          <Header />
          <main className="flex-1 overflow-y-auto p-6">
            <div className="mb-6">
              <h1 className="text-2xl font-bold">Facturacion</h1>
              <p className="text-gray-600">Gestiona tus facturas y metodos de pago</p>
            </div>
            <div className="grid gap-6 lg:grid-cols-3 mb-8">
              <div className="rounded-xl bg-white p-6 shadow-sm">
                <div className="flex items-center gap-3 mb-4">
                  <div className="p-3 bg-orange-100 rounded-xl"><CreditCard className="h-6 w-6 text-orange-600" /></div>
                  <div><p className="text-sm text-gray-600">Plan actual</p><p className="font-bold text-xl">MASCANA</p></div>
                </div>
                <p className="text-sm text-gray-500">Proximo cobro: 1 Mar 2026</p>
              </div>
              <div className="rounded-xl bg-white p-6 shadow-sm">
                <p className="text-sm text-gray-600 mb-1">Total este mes</p>
                <p className="font-bold text-3xl">49.99 EUR</p>
                <p className="text-sm text-green-600 mt-2 flex items-center gap-1"><CheckCircle className="h-4 w-4" />Pagado</p>
              </div>
              <div className="rounded-xl bg-white p-6 shadow-sm">
                <p className="text-sm text-gray-600 mb-1">Metodo de pago</p>
                <p className="font-medium">**** **** **** 4242</p>
                <button className="text-sm text-orange-600 mt-2 hover:underline">Cambiar</button>
              </div>
            </div>
            <div className="rounded-xl bg-white shadow-sm overflow-hidden">
              <div className="p-6 border-b"><h2 className="font-semibold">Historial de facturas</h2></div>
              <table className="w-full">
                <thead className="bg-gray-50"><tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Plan</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Importe</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                </tr></thead>
                <tbody className="divide-y">{invoices.map(inv => (
                  <tr key={inv.id} className="hover:bg-gray-50">
                    <td className="px-6 py-4">{inv.date}</td>
                    <td className="px-6 py-4"><span className="bg-purple-100 text-purple-800 px-2 py-1 rounded-full text-xs">{inv.plan}</span></td>
                    <td className="px-6 py-4 font-medium">{inv.amount} EUR</td>
                    <td className="px-6 py-4"><span className="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs flex items-center gap-1 w-fit"><CheckCircle className="h-3 w-3" />Pagado</span></td>
                    <td className="px-6 py-4"><button className="text-orange-600 hover:underline flex items-center gap-1"><Download className="h-4 w-4" />PDF</button></td>
                  </tr>
                ))}</tbody>
              </table>
            </div>
          </main>
        </div>
      </div>
    </AuthGuard>
  );
}
