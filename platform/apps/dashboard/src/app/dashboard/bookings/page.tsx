'use client';

import { useState } from 'react';
import { DashboardLayout } from '@/components/layout/dashboard-layout';
import { BookingsTable } from '@/components/bookings/bookings-table';
import { BookingFilters } from '@/components/bookings/booking-filters';
import { BookingDialog } from '@/components/bookings/booking-dialog';
import { useBookings } from '@/hooks/use-bookings';
import { Button } from '@/components/ui/button';
import { Plus, Download, RefreshCw } from 'lucide-react';
import type { BookingStatus } from '@makia/sdk';

export default function BookingsPage() {
  const [filters, setFilters] = useState({
    status: '' as BookingStatus | '',
    date: '',
    search: '',
  });
  const [isDialogOpen, setIsDialogOpen] = useState(false);
  const [selectedBooking, setSelectedBooking] = useState<string | null>(null);

  const { bookings, isLoading, refetch, updateStatus } = useBookings(filters);

  const handleStatusChange = async (bookingId: string, status: BookingStatus) => {
    await updateStatus(bookingId, status);
  };

  const handleEdit = (bookingId: string) => {
    setSelectedBooking(bookingId);
    setIsDialogOpen(true);
  };

  const handleExport = () => {
    // TODO: Implementar exportación CSV
    console.log('Exportar reservas');
  };

  return (
    <DashboardLayout>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">Reservas</h1>
            <p className="text-muted-foreground">
              Gestiona todas las reservas de tu restaurante
            </p>
          </div>
          <div className="flex items-center gap-2">
            <Button variant="outline" size="sm" onClick={() => refetch()}>
              <RefreshCw className="h-4 w-4 mr-2" />
              Actualizar
            </Button>
            <Button variant="outline" size="sm" onClick={handleExport}>
              <Download className="h-4 w-4 mr-2" />
              Exportar
            </Button>
            <Button size="sm" onClick={() => setIsDialogOpen(true)}>
              <Plus className="h-4 w-4 mr-2" />
              Nueva Reserva
            </Button>
          </div>
        </div>

        {/* Filters */}
        <BookingFilters filters={filters} onFiltersChange={setFilters} />

        {/* Table */}
        <BookingsTable
          bookings={bookings}
          isLoading={isLoading}
          onStatusChange={handleStatusChange}
          onEdit={handleEdit}
        />

        {/* Dialog */}
        <BookingDialog
          open={isDialogOpen}
          onOpenChange={setIsDialogOpen}
          bookingId={selectedBooking}
          onSuccess={() => {
            setIsDialogOpen(false);
            setSelectedBooking(null);
            refetch();
          }}
        />
      </div>
    </DashboardLayout>
  );
}
