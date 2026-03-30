'use client';
import { useState } from 'react';
import { Sidebar } from '@/components/Sidebar';
import { Header } from '@/components/Header';
import { Modal } from '@/components/Modal';
import { AuthGuard } from '@/components/AuthGuard';
import { useStore } from '@/lib/store';
import { Plus, Search, MessageSquare, Clock, CheckCircle, AlertCircle } from 'lucide-react';

const priorityColors: Record<string, string> = { high: 'bg-red-100 text-red-800', medium: 'bg-yellow-100 text-yellow-800', low: 'bg-green-100 text-green-800' };
const statusColors: Record<string, string> = { open: 'bg-blue-100 text-blue-800', pending: 'bg-yellow-100 text-yellow-800', closed: 'bg-gray-100 text-gray-800' };

export default function SupportPage() {
  const { tickets, clients, addTicket, updateTicket } = useStore();
  const [showModal, setShowModal] = useState(false);
  const [search, setSearch] = useState('');
  const [form, setForm] = useState({ subject: '', client: '', priority: 'medium' });

  const filtered = tickets.filter(t => 
    t.subject.toLowerCase().includes(search.toLowerCase()) ||
    t.client.toLowerCase().includes(search.toLowerCase())
  );

  const openNew = () => { setForm({ subject: '', client: clients[0]?.name || '', priority: 'medium' }); setShowModal(true); };

  const handleSave = () => {
    addTicket({ ...form, status: 'open', date: new Date().toISOString().split('T')[0] });
    setShowModal(false);
  };

  return (
    <AuthGuard>
      <div className="flex h-screen bg-gray-50">
        <Sidebar />
        <div className="flex flex-1 flex-col overflow-hidden">
          <Header />
          <main className="flex-1 overflow-y-auto p-6">
            <div className="mb-6 flex items-center justify-between">
              <div><h1 className="text-2xl font-bold">Soporte</h1><p className="text-gray-600">Gestiona los tickets de soporte</p></div>
              <button onClick={openNew} className="flex items-center gap-2 rounded-lg bg-orange-500 px-4 py-2 text-white hover:bg-orange-600">
                <Plus className="h-5 w-5" />Nuevo Ticket
              </button>
            </div>
            <div className="mb-6"><div className="relative"><Search className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" /><input placeholder="Buscar..." value={search} onChange={e => setSearch(e.target.value)} className="w-full rounded-lg border bg-white py-2 pl-10 pr-4" /></div></div>
            <div className="space-y-4">
              {filtered.map(t => (
                <div key={t.id} className="rounded-xl bg-white p-6 shadow-sm">
                  <div className="flex items-start justify-between">
                    <div className="flex items-start gap-4">
                      <div className={`flex h-10 w-10 items-center justify-center rounded-lg ${t.status === 'open' ? 'bg-blue-50' : t.status === 'pending' ? 'bg-yellow-50' : 'bg-gray-50'}`}>
                        {t.status === 'open' ? <AlertCircle className="h-5 w-5 text-blue-600" /> : t.status === 'pending' ? <Clock className="h-5 w-5 text-yellow-600" /> : <CheckCircle className="h-5 w-5 text-gray-600" />}
                      </div>
                      <div>
                        <h3 className="font-semibold">{t.subject}</h3>
                        <p className="text-sm text-gray-600">{t.client}</p>
                      </div>
                    </div>
                    <div className="flex items-center gap-2">
                      <span className={`rounded-full px-2 py-1 text-xs font-medium ${priorityColors[t.priority]}`}>{t.priority === 'high' ? 'Alta' : t.priority === 'medium' ? 'Media' : 'Baja'}</span>
                      <span className={`rounded-full px-2 py-1 text-xs font-medium ${statusColors[t.status]}`}>{t.status === 'open' ? 'Abierto' : t.status === 'pending' ? 'Pendiente' : 'Cerrado'}</span>
                    </div>
                  </div>
                  <div className="mt-4 flex items-center justify-between border-t pt-4">
                    <span className="text-sm text-gray-500">{t.date}</span>
                    <div className="flex gap-2">
                      {t.status !== 'closed' && <button onClick={() => updateTicket(t.id, { status: 'pending' })} className="text-sm text-yellow-600 hover:underline">Pendiente</button>}
                      {t.status !== 'closed' && <button onClick={() => updateTicket(t.id, { status: 'closed' })} className="text-sm text-green-600 hover:underline">Cerrar</button>}
                      {t.status === 'closed' && <button onClick={() => updateTicket(t.id, { status: 'open' })} className="text-sm text-blue-600 hover:underline">Reabrir</button>}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </main>
        </div>
        <Modal isOpen={showModal} onClose={() => setShowModal(false)} title="Nuevo Ticket">
          <div className="space-y-4">
            <div><label className="block text-sm font-medium mb-1">Asunto</label><input value={form.subject} onChange={e => setForm({ ...form, subject: e.target.value })} placeholder="Describe el problema" className="w-full rounded-lg border px-4 py-2" /></div>
            <div><label className="block text-sm font-medium mb-1">Cliente</label><select value={form.client} onChange={e => setForm({ ...form, client: e.target.value })} className="w-full rounded-lg border px-4 py-2">{clients.map(c => <option key={c.id} value={c.name}>{c.name}</option>)}</select></div>
            <div><label className="block text-sm font-medium mb-1">Prioridad</label><select value={form.priority} onChange={e => setForm({ ...form, priority: e.target.value })} className="w-yll rounded-lg border px-4 py-2"><option value="low">Baja</option><option value="medium">Media</option><option value="high">Alta</option></select></div>
          </div>
          <div className="flex gap-3 mt-6">
            <button onClick={() => setShowModal(false)} className="flex-1 px-4 py-2 border rounded-lg hover:bg-gray-50">Cancelar</button>
            <button onClick={handleSave} disabled={!form.subject} className="flex-1 px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 disabled:opacity-50">Crear</button>
          </div>
        </Modal>
      </div>
    </AuthGuard>
  );
}
