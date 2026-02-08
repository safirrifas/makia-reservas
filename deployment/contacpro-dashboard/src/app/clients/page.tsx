'use client';
import { useState } from 'react';
import { Sidebar } from '@/components/Sidebar';
import { Header } from '@/components/Header';
import { Modal } from '@/components/Modal';
import { AuthGuard } from '@/components/AuthGuard';
import { useStore } from '@/lib/store';
import { Plus, Search, User, Check, X, Trash2, Edit, Mail, Phone } from 'lucide-react';

export default function ClientsPage() {
  const { clients, addClient, updateClient, deleteClient } = useStore();
  const [showModal, setShowModal] = useState(false);
  const [editId, setEditId] = useState<number | null>(null);
  const [search, setSearch] = useState('');
  const [form, setForm] = useState({ name: '', email: '', phone: '', status: 'active' });

  const filtered = clients.filter(c => 
    c.name.toLowerCase().includes(search.toLowerCase()) ||
    c.email.toLowerCase().includes(search.toLowerCase())
  );

  const openNew = () => { setForm({ name: '', email: '', phone: '', status: 'active' }); setEditId(null); setShowModal(true); };
  const openEdit = (c: typeof clients[0]) => { setForm({ name: c.name, email: c.email, phone: c.phone, status: c.status }); setEditId(c.id); setShowModal(true); };

  const handleSave = () => {
    if (editId) {
      updateClient(editId, form);
    } else {
      addClient({ ...form, licenses: 0 });
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
              <div><h1 className="text-2xl font-bold">Clientes</h1><p className="text-gray-600">Gestiona los clientes de MakIA</p></div>
              <button onClick={openNew} className="flex items-center gap-2 rounded-lg bg-orange-500 px-4 py-2 text-white hover:bg-orange-600">
                <Plus className="h-5 w-5" />Nuevo Cliente
              </button>
            </div>
            <div className="mb-6"><div className="relative"><Search className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" /><input placeholder="Buscar..." value={search} onChange={e => setSearch(e.target.value)} className="w-full rounded-lg border bg-white py-2 pl-10 pr-4" /></div></div>
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
              {filtered.map(c => (
                <div key={c.id} className="rounded-xl bg-white p-6 shadow-sm">
                  <div className="flex items-start justify-between mb-4">
                    <div className="flex items-center gap-3">
                      <div className="flex h-12 w-12 items-center justify-center rounded-full bg-orange-100">
                        <User className="h-6 w-6 text-orange-600" />
                      </div>
                      <div>
                        <h3 className="font-semibold">{c.name}</h3>
                        <span className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${c.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}`}>
                          {c.status === 'active' ? <Check className="h-3 w-3" /> : <X className="h-3 w-3" />}
                          {c.status === 'active' ? 'Activo' : 'Inactivo'}
                        </span>
                      </div>
                    </div>
                  </div>
                  <div className="space-y-2 text-sm text-gray-600 mb-4">
                    <div className="flex items-center gap-2"><Mail className="h-4 w-4" />{c.email}</div>
                    <div className="flex items-center gap-2"><Phone className="h-4 w-4" />{c.phone}</div>
                  </div>
                  <div className="flex items-center justify-between pt-4 border-t">
                    <span className="text-sm text-gray-500">{c.licenses} licencia(s)</span>
                    <div className="flex gap-1">
                      <button onClick={() => openEdit(c)} className="rounded p-1 hover:bg-gray-100"><Edit className="h-4 w-4 text-blue-500" /></button>
                      <button onClick={() => deleteClient(c.id)} className="rounded p-1 hover:bg-gray-100"><Trash2 className="h-4 w-4 text-red-500" /></button>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </main>
        </div>
        <Modal isOpen={showModal} onClose={() => setShowModal(false)} title={editId ? 'Editar Cliente' : 'Nuevo Cliente'}>
          <div className="space-y-4">
            <div><label className="block text-sm font-medium mb-1">Nombre</label><input value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} placeholder="Restaurante" className="w-full rounded-lg border px-4 py-2" /></div>
            <div><label className="block text-sm font-medium mb-1">Email</label><input type="email" value={form.email} onChange={e => setForm({ ...form, email: e.target.value })} placeholder="email@ejemplo.com" className="w-full rounded-lg border px-4 py-2" /></div>
            <div><label className="block text-sm font-medium mb-1">Teléfono</label><input value={form.phone} onChange={e => setForm({ ...form, phone: e.target.value })} placeholder="+34 600 000 000" className="w-yll rounded-lg border px-4 py-2" /></div>
            <div><label className="block text-sm font-medium mb-1">Estado</label><select value={form.status} onChange={e => setForm({ ...form, status: e.target.value })} className="w-yll rounded-lg border px-4 py-2"><option value="active">Activo</option><option value="inactive">Inactivo</option></select></div>
          </div>
          <div className="flex gap-3 mt-6">
            <button onClick={() => setShowModal(false)} className="flex-1 px-4 py-2 border rounded-lg hover:bg-gray-50">Cancelar</button>
            <button onClick={handleSave} disabled={!form.name || !form.email} className="flex-1 px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 disabled:opacity-50">Guardar</button>
          </div>
        </Modal>
      </div>
    </AuthGuard>
  );
}
