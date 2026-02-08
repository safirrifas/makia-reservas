import { create } from 'zustand';

interface License {
  id: number;
  domain: string;
  client: string;
  plan: string;
  usage: number;
  limit: number | null;
  status: string;
}

interface Client {
  id: number;
  name: string;
  email: string;
  phone: string;
  licenses: number;
  status: string;
}

interface Ticket {
  id: number;
  subject: string;
  client: string;
  priority: string;
  status: string;
  date: string;
}

interface Store {
  licenses: License[];
  clients: Client[];
  tickets: Ticket[];
  addLicense: (l: Omit<License, 'id'>) => void;
  updateLicense: (id: number, data: Partial<License>) => void;
  deleteLicense: (id: number) => void;
  addClient: (c: Omit<Client, 'id'>) => void;
  updateClient: (id: number, data: Partial<Client>) => void;
  deleteClient: (id: number) => void;
  addTicket: (t: Omit<Ticket, 'id'>) => void;
  updateTicket: (id: number, data: Partial<Ticket>) => void;
}

export const useStore = create<Store>((set) => ({
  licenses: [
    { id: 1, domain: 'brote.es', client: 'Brote Restaurante', plan: 'MASCANA', usage: 45, limit: 100, status: 'active' },
    { id: 2, domain: 'ejemplo.com', client: 'Bar Ejemplo', plan: 'CANA', usage: 12, limit: 15, status: 'active' },
    { id: 3, domain: 'tapas-bar.es', client: 'Tapas Bar', plan: 'CHUPITO', usage: 5, limit: 5, status: 'limit' },
  ],
  clients: [
    { id: 1, name: 'Brote Restaurante', email: 'info@brote.es', phone: '+34 612 345 678', licenses: 1, status: 'active' },
    { id: 2, name: 'Bar Ejemplo', email: 'contacto@ejemplo.com', phone: '+34 623 456 789', licenses: 1, status: 'active' },
    { id: 3, name: 'Tapas Bar', email: 'hola@tapas-bar.es', phone: '+34 634 567 890', licenses: 1, status: 'inactive' },
  ],
  tickets: [
    { id: 1, subject: 'Error al cargar reservas', client: 'Brote Restaurante', priority: 'high', status: 'open', date: '2026-02-08' },
    { id: 2, subject: 'Consulta sobre facturación', client: 'Bar Ejemplo', priority: 'medium', status: 'pending', date: '2026-02-07' },
  ],
  addLicense: (l) => set((s) => ({ licenses: [...s.licenses, { ...l, id: Date.now() }] })),
  updateLicense: (id, data) => set((s) => ({ licenses: s.licenses.map(l => l.id === id ? { ...l, ...data } : l) })),
  deleteLicense: (id) => set((s) => ({ licenses: s.licenses.filter(l => l.id !== id) })),
  addClient: (c) => set((s) => ({ clients: [...s.clients, { ...c, id: Date.now() }] })),
  updateClient: (id, data) => set((s) => ({ clients: s.clients.map(c => c.id === id ? { ...c, ...data } : c) })),
  deleteClient: (id) => set((s) => ({ clients: s.clients.filter(c => c.id !== id) })),
  addTicket: (t) => set((s) => ({ tickets: [...s.tickets, { ...t, id: Date.now() }] })),
  updateTicket: (id, data) => set((s) => ({ tickets: s.tickets.map(t => t.id === id ? { ...t, ...data } : t) })),
}));
