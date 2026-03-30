'use client';

import { useState, useEffect } from 'react';
import { format, isToday, isTomorrow, addDays } from 'date-fns';
import { es } from 'date-fns/locale';
import {
  CalendarDays,
  Clock,
  Users,
  CheckCircle,
  XCircle,
  ChevronLeft,
  ChevronRight,
  Loader2,
  RefreshCw,
  Phone,
  MessageSquare,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { useAuth } from '@/hooks/use-auth';
import { useBookings } from '@/hooks/use-bookings';
import type { Booking, BookingStatus } from '@makia/sdk';

const statusColors: Record<BookingStatus, string> = {
  PENDING: 'bg-yellow-500',
  CONFIRMED: 'bg-green-500',
  CANCELLED: 'bg-red-500',
  COMPLETED: 'bg-blue-500',
  NO_SHOW: 'bg-gray-500',
};

const statusLabels: Record<BookingStatus, string> = {
  PENDING: 'Pendiente',
  CONFIRMED: 'Confirmada',
  CANCELLED: 'Cancelada',
  COMPLETED: 'Completada',
  NO_SHOW: 'No show',
};

export default function OperatorHome() {
  const [selectedDate, setSelectedDate] = useState(new Date());
  const { user, isLoading: authLoading } = useAuth();
  const dateStr = format(selectedDate, 'yyyy-MM-dd');
  const { bookings, isLoading, refetch, updateStatus } = useBookings({ date: dateStr });

  const [expandedBooking, setExpandedBooking] = useState<string | null>(null);

  // Agrupar reservas por hora
  const bookingsByTime = bookings.reduce((acc, booking) => {
    const time = booking.bookingTime;
    if (!acc[time]) acc[time] = [];
    acc[time].push(booking);
    return acc;
  }, {} as Record<string, Booking[]>);

  const timeSlots = Object.keys(bookingsByTime).sort();

  const goToPrevDay = () => setSelectedDate((d) => addDays(d, -1));
  const goToNextDay = () => setSelectedDate((d) => addDays(d, 1));
  const goToToday = () => setSelectedDate(new Date());

  const getDateLabel = () => {
    if (isToday(selectedDate)) return 'Hoy';
    if (isTomorrow(selectedDate)) return 'Mañana';
    return format(selectedDate, 'EEEE', { locale: es });
  };

  const handleStatusChange = async (bookingId: string, newStatus: BookingStatus) => {
    await updateStatus(bookingId, newStatus);
    setExpandedBooking(null);
  };

  if (authLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-100">
        <Loader2 className="h-8 w-8 animate-spin text-primary" />
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-100 pb-20">
      {/* Header fijo */}
      <header className="sticky top-0 z-50 bg-white border-b shadow-sm">
        <div className="px-4 py-3">
          <div className="flex items-center justify-between">
            <div>
              <h1 className="text-lg font-bold text-gray-900">MakIA Operador</h1>
              <p className="text-xs text-gray-500">{user?.organization?.name}</p>
            </div>
            <button
              onClick={() => refetch()}
              className="p-2 rounded-full hover:bg-gray-100"
              disabled={isLoading}
            >
              <RefreshCw className={cn('h-5 w-5 text-gray-600', isLoading && 'animate-spin')} />
            </button>
          </div>
        </div>

        {/* Selector de fecha */}
        <div className="flex items-center justify-between px-4 py-2 bg-gray-50 border-t">
          <button onClick={goToPrevDay} className="p-2 rounded-full hover:bg-gray-200">
            <ChevronLeft className="h-5 w-5" />
          </button>

          <button onClick={goToToday} className="flex flex-col items-center">
            <span className="text-sm font-medium text-gray-900">{getDateLabel()}</span>
            <span className="text-xs text-gray-500">
              {format(selectedDate, 'd MMMM', { locale: es })}
            </span>
          </button>

          <button onClick={goToNextDay} className="p-2 rounded-full hover:bg-gray-200">
            <ChevronRight className="h-5 w-5" />
          </button>
        </div>
      </header>

      {/* Stats rápidas */}
      <div className="grid grid-cols-3 gap-3 p-4">
        <div className="bg-white rounded-xl p-3 text-center shadow-sm">
          <p className="text-2xl font-bold text-gray-900">{bookings.length}</p>
          <p className="text-xs text-gray-500">Total</p>
        </div>
        <div className="bg-white rounded-xl p-3 text-center shadow-sm">
          <p className="text-2xl font-bold text-yellow-600">
            {bookings.filter((b) => b.status === 'PENDING').length}
          </p>
          <p className="text-xs text-gray-500">Pendientes</p>
        </div>
        <div className="bg-white rounded-xl p-3 text-center shadow-sm">
          <p className="text-2xl font-bold text-green-600">
            {bookings.filter((b) => b.status === 'CONFIRMED').length}
          </p>
          <p className="text-xs text-gray-500">Confirmadas</p>
        </div>
      </div>

      {/* Lista de reservas por hora */}
      <div className="px-4 space-y-4">
        {isLoading ? (
          <div className="flex justify-center py-12">
            <Loader2 className="h-8 w-8 animate-spin text-primary" />
          </div>
        ) : timeSlots.length === 0 ? (
          <div className="bg-white rounded-xl p-8 text-center shadow-sm">
            <CalendarDays className="h-12 w-12 text-gray-300 mx-auto mb-3" />
            <p className="text-gray-500">No hay reservas para este día</p>
          </div>
        ) : (
          timeSlots.map((time) => (
            <div key={time} className="space-y-2">
              {/* Hora */}
              <div className="flex items-center gap-2">
                <Clock className="h-4 w-4 text-gray-400" />
                <span className="text-sm font-medium text-gray-600">{time}</span>
                <span className="text-xs text-gray-400">
                  ({bookingsByTime[time].length} reserva{bookingsByTime[time].length !== 1 ? 's' : ''})
                </span>
              </div>

              {/* Reservas de esta hora */}
              {bookingsByTime[time].map((booking) => (
                <div
                  key={booking.id}
                  className="bg-white rounded-xl shadow-sm overflow-hidden"
                >
                  {/* Card principal */}
                  <button
                    onClick={() =>
                      setExpandedBooking(expandedBooking === booking.id ? null : booking.id)
                    }
                    className="w-full p-4 text-left"
                  >
                    <div className="flex items-start justify-between">
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2">
                          <span
                            className={cn(
                              'w-2 h-2 rounded-full flex-shrink-0',
                              statusColors[booking.status]
                            )}
                          />
                          <p className="font-medium text-gray-900 truncate">
                            {booking.customerName}
                          </p>
                        </div>
                        <div className="mt-1 flex items-center gap-3 text-sm text-gray-500">
                          <span className="flex items-center gap-1">
                            <Users className="h-3.5 w-3.5" />
                            {booking.guests}
                          </span>
                          <span className="text-xs px-2 py-0.5 rounded-full bg-gray-100">
                            {statusLabels[booking.status]}
                          </span>
                        </div>
                      </div>
                    </div>
                  </button>

                  {/* Detalles expandidos */}
                  {expandedBooking === booking.id && (
                    <div className="border-t bg-gray-50 p-4 space-y-3">
                      {/* Info de contacto */}
                      <div className="space-y-2 text-sm">
                        <p className="text-gray-600">{booking.customerEmail}</p>
                        {booking.customerPhone && (
                          <div className="flex items-center gap-2">
                            <a
                              href={`tel:${booking.customerPhone}`}
                              className="flex items-center gap-1 text-primary"
                            >
                              <Phone className="h-4 w-4" />
                              {booking.customerPhone}
                            </a>
                            <a
                              href={`https://wa.me/${booking.customerPhone.replace(/\D/g, '')}`}
                              className="p-1.5 rounded-full bg-green-100 text-green-600"
                              target="_blank"
                              rel="noopener noreferrer"
                            >
                              <MessageSquare className="h-4 w-4" />
                            </a>
                          </div>
                        )}
                        {booking.specialRequests && (
                          <p className="text-gray-500 bg-white p-2 rounded-lg">
                            "{booking.specialRequests}"
                          </p>
                        )}
                      </div>

                      {/* Acciones rápidas */}
                      {booking.status === 'PENDING' && (
                        <div className="flex gap-2 pt-2">
                          <button
                            onClick={() => handleStatusChange(booking.id, 'CONFIRMED')}
                            className="flex-1 flex items-center justify-center gap-2 py-2.5 bg-green-500 text-white rounded-lg font-medium"
                          >
                            <CheckCircle className="h-4 w-4" />
                            Confirmar
                          </button>
                          <button
                            onClick={() => handleStatusChange(booking.id, 'CANCELLED')}
                            className="flex-1 flex items-center justify-center gap-2 py-2.5 bg-red-500 text-white rounded-lg font-medium"
                          >
                            <XCircle className="h-4 w-4" />
                            Cancelar
                          </button>
                        </div>
                      )}

                      {booking.status === 'CONFIRMED' && (
                        <div className="flex gap-2 pt-2">
                          <button
                            onClick={() => handleStatusChange(booking.id, 'COMPLETED')}
                            className="flex-1 flex items-center justify-center gap-2 py-2.5 bg-blue-500 text-white rounded-lg font-medium"
                          >
                            <CheckCircle className="h-4 w-4" />
                            Completada
                          </button>
                          <button
                            onClick={() => handleStatusChange(booking.id, 'NO_SHOW')}
                            className="flex-1 flex items-center justify-center gap-2 py-2.5 bg-gray-500 text-white rounded-lg font-medium"
                          >
                            <XCircle className="h-4 w-4" />
                            No show
                          </button>
                        </div>
                      )}
                    </div>
                  )}
                </div>
              ))}
            </div>
          ))
        )}
      </div>

      {/* Bottom nav */}
      <nav className="fixed bottom-0 left-0 right-0 bg-white border-t safe-area-inset-bottom">
        <div className="flex items-center justify-around py-2">
          <button className="flex flex-col items-center gap-1 py-2 px-4 text-primary">
            <CalendarDays className="h-5 w-5" />
            <span className="text-xs font-medium">Reservas</span>
          </button>
          <button className="flex flex-col items-center gap-1 py-2 px-4 text-gray-400">
            <Clock className="h-5 w-5" />
            <span className="text-xs">Historial</span>
          </button>
          <button className="flex flex-col items-center gap-1 py-2 px-4 text-gray-400">
            <Users className="h-5 w-5" />
            <span className="text-xs">Perfil</span>
          </button>
        </div>
      </nav>
    </div>
  );
}
