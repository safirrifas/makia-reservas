'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { DashboardLayout } from '@/components/layout/dashboard-layout';
import { StatsCards } from '@/components/dashboard/stats-cards';
import { RecentBookings } from '@/components/dashboard/recent-bookings';
import { TodayTimeline } from '@/components/dashboard/today-timeline';
import { QuickActions } from '@/components/dashboard/quick-actions';
import { useAuth } from '@/hooks/use-auth';
import { useStats } from '@/hooks/use-stats';
import { Loader2 } from 'lucide-react';

export default function DashboardPage() {
  const router = useRouter();
  const { user, isLoading: authLoading } = useAuth();
  const { stats, isLoading: statsLoading } = useStats();

  useEffect(() => {
    if (!authLoading && !user) {
      router.push('/auth/login');
    }
  }, [user, authLoading, router]);

  if (authLoading || !user) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <Loader2 className="h-8 w-8 animate-spin text-primary" />
      </div>
    );
  }

  return (
    <DashboardLayout>
      <div className="space-y-8">
        {/* Header */}
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Dashboard</h1>
          <p className="text-muted-foreground">
            Bienvenido de nuevo, {user.name || user.email}
          </p>
        </div>

        {/* Stats */}
        <StatsCards stats={stats} isLoading={statsLoading} />

        {/* Grid principal */}
        <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-7">
          {/* Timeline de hoy */}
          <div className="col-span-4">
            <TodayTimeline />
          </div>

          {/* Acciones rápidas */}
          <div className="col-span-3">
            <QuickActions />
          </div>
        </div>

        {/* Reservas recientes */}
        <RecentBookings />
      </div>
    </DashboardLayout>
  );
}
