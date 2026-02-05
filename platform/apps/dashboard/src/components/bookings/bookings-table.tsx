'use client';

import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import {
  MoreHorizontal,
  CheckCircle,
  XCircle,
  Clock,
  AlertTriangle,
  User,
  Mail,
  Phone,
  Users,
  Loader2,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import type { Booking, BookingStatus } from '@makia/sdk';

interface BookingsTableProps {
  bookings: Booking[];
  isLoading: boolean;
  onStatusChange: (bookingId: string, status: BookingStatus) => Promise<void>;
  onEdit: (bookingId: string) => void;
}

const statusConfig: Record<BookingStatus, { label: string; color: string; icon: typeof Clock }> = {
  PENDING: { label: 'Pendiente', color: 'bg-yellow-100 text-yellow-800', icon: Clock },
  CONFIRMED: { label: 'Confirmada', color: 'bg-green-100 text-green-800', icon: CheckCircle },
  CANCELLED: { label: 'Cancelada', color: 'bg-red-100 text-red-800', icon: XCircle },
  COMPLETED: { label: 'Completada', color: 'bg-blue-100 text-blue-800', icon: CheckCircle },
  NO_SHOW: { label: 'No show', color: 'bg-gray-100 text-gray-800', icon: AlertTriangle },
};

export function BookingsTable({
  bookings,
  isLoading,
  onStatusChange,
  onEdit,
}: BookingsTableProps) {
  if (isLoading) {
    return (
      <div className="bg-white rounded-xl border p-8 flex items-center justify-center">
        <Loader2 className="h-8 w-8 animate-spin text-primary" />
      </div>
    );
  }

  if (bookings.length === 0) {
    return (
      <div className="bg-white rounded-xl border p-8 text-center">
        <div className="text-gray-400 mb-2">
          <Clock className="h-12 w-12 mx-auto" />
        </div>
        <h3 className="text-lg font-medium text-gray-900">No hay reservas</h3>
        <p className="text-gray-500">Las reservas aparecerán aquí cuando se creen.</p>
      </div>
    );
  }

  return (
    <div className="bg-white rounded-xl border overflow-hidden">
      <div className="overflow-x-auto">
        <table className="w-full">
          <thead>
            <tr className="border-b bg-gray-50">
              <th className="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Cliente
              </th>
              <th className="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Fecha y hora
              </th>
              <th className="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Comensales
              </th>
              <th className="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Estado
              </th>
              <th className="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Origen
              </th>
              <th className="px-6 py-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                Acciones
              </th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {bookings.map((booking) => {
              const status = statusConfig[booking.status];
              const StatusIcon = status.icon;

              return (
                <tr key={booking.id} className="hover:bg-gray-50 transition-colors">
                  {/* Cliente */}
                  <td className="px-6 py-4">
                    <div className="flex items-center gap-3">
                      <div className="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                        <User className="h-5 w-5 text-primary" />
                      </div>
                      <div className="min-w-0">
                        <p className="font-medium text-gray-900 truncate">
                          {booking.customerName}
                        </p>
                        <div className="flex items-center gap-3 text-sm text-gray-500">
                          <span className="flex items-center gap-1 truncate">
                            <Mail className="h-3 w-3" />
                            {booking.customerEmail}
                          </span>
                          {booking.customerPhone && (
                            <span className="flex items-center gap-1">
                              <Phone className="h-3 w-3" />
                              {booking.customerPhone}
                            </span>
                          )}
                        </div>
                      </div>
                    </div>
                  </td>

                  {/* Fecha y hora */}
                  <td className="px-6 py-4">
                    <div>
                      <p className="font-medium text-gray-900">
                        {format(new Date(booking.bookingDate), 'EEEE d MMM', { locale: es })}
                      </p>
                      <p className="text-sm text-gray-500">{booking.bookingTime}</p>
                    </div>
                  </td>

                  {/* Comensales */}
                  <td className="px-6 py-4">
                    <div className="flex items-center gap-2">
                      <Users className="h-4 w-4 text-gray-400" />
                      <span className="font-medium">{booking.guests}</span>
                    </div>
                  </td>

                  {/* Estado */}
                  <td className="px-6 py-4">
                    <span
                      className={cn(
                        'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium',
                        status.color
                      )}
                    >
                      <StatusIcon className="h-3.5 w-3.5" />
                      {status.label}
                    </span>
                  </td>

                  {/* Origen */}
                  <td className="px-6 py-4">
                    <span className="text-sm text-gray-500 capitalize">{booking.source}</span>
                  </td>

                  {/* Acciones */}
                  <td className="px-6 py-4 text-right">
                    <div className="flex items-center justify-end gap-2">
                      {booking.status === 'PENDING' && (
                        <>
                          <button
                            onClick={() => onStatusChange(booking.id, 'CONFIRMED')}
                            className="p-2 rounded-lg hover:bg-green-50 text-green-600 transition-colors"
                            title="Confirmar"
                          >
                            <CheckCircle className="h-4 w-4" />
                          </button>
                          <button
                            onClick={() => onStatusChange(booking.id, 'CANCELLED')}
                            className="p-2 rounded-lg hover:bg-red-50 text-red-600 transition-colors"
                            title="Cancelar"
                          >
                            <XCircle className="h-4 w-4" />
                          </button>
                        </>
                      )}
                      <button
                        onClick={() => onEdit(booking.id)}
                        className="p-2 rounded-lg hover:bg-gray-100 text-gray-600 transition-colors"
                        title="Más opciones"
                      >
                        <MoreHorizontal className="h-4 w-4" />
                      </button>
                    </div>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </div>
  );
}
