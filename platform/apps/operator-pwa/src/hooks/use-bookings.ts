'use client';

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import type { Booking, BookingStatus } from '@makia/sdk';

interface UseBookingsOptions {
  date?: string;
  status?: BookingStatus;
}

const API_URL = process.env.NEXT_PUBLIC_API_URL || 'https://api.contacpro.app/v1';

function getToken(): string | null {
  if (typeof window === 'undefined') return null;
  return localStorage.getItem('makia_token');
}

export function useBookings(options: UseBookingsOptions = {}) {
  const queryClient = useQueryClient();
  const { date, status } = options;

  const { data: bookings = [], isLoading, refetch } = useQuery({
    queryKey: ['bookings', date, status],
    queryFn: async () => {
      const token = getToken();
      if (!token) return [];

      const params = new URLSearchParams();
      if (date) {
        params.set('dateFrom', date);
        params.set('dateTo', date);
      }
      if (status) {
        params.set('status', status);
      }

      const response = await fetch(`${API_URL}/bookings?${params.toString()}`, {
        headers: {
          Authorization: `Bearer ${token}`,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch bookings');
      }

      const data = await response.json();
      return data.data.bookings as Booking[];
    },
    refetchInterval: 30000, // Refetch every 30 seconds
  });

  const updateStatusMutation = useMutation({
    mutationFn: async ({ bookingId, status }: { bookingId: string; status: BookingStatus }) => {
      const token = getToken();
      if (!token) throw new Error('Not authenticated');

      const response = await fetch(`${API_URL}/bookings/${bookingId}`, {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({ status }),
      });

      if (!response.ok) {
        throw new Error('Failed to update booking status');
      }

      return response.json();
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['bookings'] });
    },
  });

  const updateStatus = async (bookingId: string, status: BookingStatus) => {
    await updateStatusMutation.mutateAsync({ bookingId, status });
  };

  return {
    bookings,
    isLoading,
    refetch,
    updateStatus,
    isUpdating: updateStatusMutation.isPending,
  };
}
