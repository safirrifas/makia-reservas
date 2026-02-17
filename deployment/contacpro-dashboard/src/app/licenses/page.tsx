'use client';
import { useState } from 'react';
import { Sidebar } from '@/components/Sidebar';
import { Header } from '@/components/Header';
import { Modal } from '@/components/Modal';
import { AuthGuard } from '@/components/AuthGuard';
import { useStore } from '@/lib/store';
import { Plus, Search, Key, Check, X, RefreshCw, Trash2, Edit } from 'lucide-react';

const plans = [
  { name: 'CHUPITO', limit: 5 },
  { name: 'CANA', limit: 15 },
  { name: 'MASCANA', limit: 100 },
  { name: 'UNLIMITED', limit: null }
];
const colors: Record<string, string> = {
  CHUPITO: 'bg-gray-100 text-gray-800',
  CANA: 'bg-blue-100 text-blue-800',
  MASCANA: 'bg-purple-100 text-purple-800',
  UNLIMITED: 'bg-orange-100 text-orange-800'
};

export default function LicensesPage() {
  const { licenses, addLicense, updateLicense, deleteLicense } = useStore();
  const [showModal, setShowModal] = useState(false);
  const [editId, setEditId] = useState<number | null>(null);
  const [search, setSearch] = useState('');
  const [form, setForm] = useState({ domain: '', client: '', plan: 'CANA' });

  const filtered = licenses.filter(l => 
    l.domain.toLowerCase().includes(search.toLowerCase()) ||
    l.client.toLowerCase().includes(search.toLowerCase())
  );

  const openNew = () => { setForm({ domain: '', client: '', plan: 'CANA' }); setEditId(null); setShowModal(true); };
  const openEdit = (l: typeof licenses[0]) => { setForm({ domain: l.domain, client: l.client, plan: l.plan }); setEditId(l.id); setShowModal(true); };

  const handleSave = () => {
    const plan = plans.find(p => p.name === form.plan);
    if (editId) {
      updateLicense(editId, { ...form, limit: plan?.limit || null });
    } else {
      addLicense({ ...form, usage: 0, limit: plan?.limit || null, status: 'active' });
    }
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
              <div><h1 className="text-2xl font-bold">Licencias</h1><p className="text-gray-600">Gestiona las licencias de MakIA</p></div>
              <button onClick={openNew} className="flex items-center gap-2 rounded-lg bg-orange-500 px-4 py-2 text-white hover:bg-orange-600">
                <Plus className="h-5 w-5" />Nueva Licencia
              </button>
            </div>
            <div className="mb-6"><div className="relative"><Search className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" /><input placeholder="Buscar..." value={search} onChange={e => setSearch(e.target.value)} className="w-full rounded-lg border bg-white py-2 pl-10 pr-4" /></div></div>
            <div className="rounded-xl bg-white shadow-sm overflow-hidden">
              <table className="w-full">
                <thead className="bg-gray-50"><tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dominio</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Plan</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Uso</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                </tr></thead>
                <tbody className="divide-y">{filtered.map(l => (
                  <tr key={l.id} className="hover:bg-gray-50">
                    <td className="px-6 py-4"><div className="flex items-center gap-3"><div className="flex h-10 w-10 items-center justify-center rounded-lg bg-orange-50"><Key className="h-5 w-5 text-orange-600" /></div><p className="font-medium">{l.domain}</p></div></td>
                    <td className="px-6 py-4">{l.client}</td>
                    <td className="px-6 py-4"><span className={`rounded-full px-2 py-1 text-xs font-medium ${colors[l.plan]}`}>{l.plan}</span></td>
                    <td className="px-6 py-4"><div className="w-24"><div className="h-2 bg-gray-200 rounded-full"><div className={`h-2 rounded-full ${l.status === 'limit' ? 'bg-red-500' : 'bg-orange-500'}`} style={{ width: `${l.limit ? Math.min((l.usage / l.limit) * 100, 100) : 30}%` }} /></div><p className="text-xs text-gray-500 mt-1">{l.usage}/{l.limit || '∞'}</p></div></td>
                    <td className="px-6 py-4"><span className={`inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium ${l.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>{l.status === 'active' ? <Check className="h-3 w-3" /> : <X className="h-3 w-3" />}{l.status === 'active' ? 'Activa' : 'Límite'}</span></td>
                    <td className="px-6 py-4 flex gap-1">
                      <button onClick={() => updateLicense(l.id, { usage: 0, status: 'active' })} className="rounded p-1 hover:bg-gray-100" title="Resetear"><RefreshCw className="h-4 w-4 text-gray-500" /></button>
                      <button onClick={() => openEdit(l)} className="rounded p-1 hover:bg-gray-100" title="Editar"><Edit className="h-4 w-4 text-blue-500" /></button>
                      <button onClick={() => deleteLicense(l.id)} className="rounded p-1 hover:bg-gray-100" title="Eliminar"><Trash2 className="h-4 w-4 text-red-500" /></button>
                    </td>
                  </tr>
                ))}</tbody>
              </table>
            </div>
          </main>
        </div>
        <Modal isOpen={showModal} onClose={() => setShowModal(false)} title={editId ? 'Editar Licencia' : 'Nueva Licencia'}>
          <div className="space-y-4">
            <div><label className="block text-sm font-medium mb-1">Dominio</label><input value={form.domain} onChange={e => setForm({ ...form, domain: e.target.value })} placeholder="ejemplo.com" className="w-full rounded-lg border px-4 py-2" /></div>
            <div><label className="block text-sm font-medium mb-1">Cliente</label><input value={form.client} onChange={e => setForm({ ...form, client: e.target.value })} placeholder="Nombre" className="w-yll rounded-lg border px-4 py-2" /></div>
            <div><label className="block text-sm font-medium mb-1">Plan</label><select value={form.plan} onChange={e => setForm({ ...form, plan: e.target.value })} className="w-yll rounded-lg border px-4 py-2">{plans.map(p => <option key={p.name} value={p.name}>{p.name}</option>)}</select></div>
          </div>
          <div className="flex gap-3 mt-6">
            <button onClick={() => setShowModal(false)} className="flex-1 px-4 py-2 border rounded-lg hover:bg-gray-50">Cancelar</button>
            <button onClick={handleSave} disabled={!form.domain || !form.client} className="flex-1 px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 disabled:opacity-50">Guardar</button>
          </div>
        </Modal>
      </div>
    </AuthGuard>
  );
}
