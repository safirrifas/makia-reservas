'use client';
import { useState } from 'react';
import { Sidebar } from '@/components/Sidebar';
import { Header } from '@/components/Header';
import { AuthGuard } from '@/components/AuthGuard';
import { Save, Bell, Mail, Shield, Palette } from 'lucide-react';

export default function SettingsPage() {
  const [saved, setSaved] = useState(false);
  const [settings, setSettings] = useState({
    companyName: 'MakIA Restaurante',
    email: 'admin@contacpro.app',
    notifications: true,
    emailAlerts: true,
    darkMode: false,
    twoFactor: false
  });

  const handleSave = () => {
    setSaved(true);
    setTimeout(() => setSaved(false), 2000);
  };

  return (
    <AuthGuard>
      <div className="flex h-screen bg-gray-50">
        <Sidebar />
        <div className="flex flex-1 flex-col overflow-hidden">
          <Header />
          <main className="flex-1 overflow-y-auto p-6">
            <div className="mb-6">
              <h1 className="text-2xl font-bold">Configuracion</h1>
              <p className="text-gray-600">Ajusta las preferencias del sistema</p>
            </div>
            <div className="max-w-2xl space-y-6">
              <div className="rounded-xl bg-white p-6 shadow-sm">
                <h2 className="font-semibold mb-4 flex items-center gap-2"><Mail className="h-5 w-5 text-orange-500" />General</h2>
                <div className="space-y-4">
                  <div><label className="block text-sm font-medium mb-1">Nombre de la empresa</label><input value={settings.companyName} onChange={e => setSettings({...settings, companyName: e.target.value})} className="w-full rounded-lg border px-4 py-2" /></div>
                  <div><label className="block text-sm font-medium mb-1">Email de contacto</label><input type="email" value={settings.email} onChange={e => setSettings({...settings, email: e.target.value})} className="w-full rounded-lg border px-4 py-2" /></div>
                </div>
              </div>
              <div className="rounded-xl bg-white p-6 shadow-sm">
                <h2 className="font-semibold mb-4 flex items-center gap-2"><Bell className="h-5 w-5 text-orange-500" />Notificaciones</h2>
                <div className="space-y-4">
                  <label className="flex items-center justify-between"><span>Notificaciones push</span><input type="checkbox" checked={settings.notifications} onChange={e => setSettings({...settings, notifications: e.target.checked})} className="h-5 w-5 rounded text-orange-500" /></label>
                  <label className="flex items-center justify-between"><span>Alertas por email</span><input type="checkbox" checked={settings.emailAlerts} onChange={e => setSettings({...settings, emailAlerts: e.target.checked})} className="h-5 w-5 rounded text-orange-500" /></label>
                </div>
              </div>
              <div className="rounded-xl bg-white p-6 shadow-sm">
                <h2 className="font-semibold mb-4 flex items-center gap-2"><Palette className="h-5 w-5 text-orange-500" />Apariencia</h2>
                <label className="flex items-center justify-between"><span>Modo oscuro</span><input type="checkbox" checked={settings.darkMode} onChange={e => setSettings({...settings, darkMode: e.target.checked})} className="h-5 w-5 rounded text-orange-500" /></label>
              </div>
              <div className="rounded-xl bg-white p-6 shadow-sm">
                <h2 className="font-semibold mb-4 flex items-center gap-2"><Shield className="h-5 w-5 text-orange-500" />Seguridad</h2>
                <label className="flex items-center justify-between"><span>Autenticacion de dos factores</span><input type="checkbox" checked={settings.twoFactor} onChange={e => setSettings({...settings, twoFactor: e.target.checked})} className="h-5 w-5 rounded text-orange-500" /></label>
              </div>
              <button onClick={handleSave} className="flex items-center gap-2 rounded-lg bg-orange-500 px-6 py-3 text-white hover:bg-orange-600">
                <Save className="h-5 w-5" />{saved ? 'Guardado!' : 'Guardar cambios'}
              </button>
            </div>
          </main>
        </div>
      </div>
    </AuthGuard>
  );
}
