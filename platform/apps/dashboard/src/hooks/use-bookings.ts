'use client';

import { useState, useEffect, useCallback } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useMakiaClient } from '@/lib/makia-client';
import type { Booking, BookingStatus, ListBookingsOptions } from '@makia/sdk';

interface UseBookingsOptions {
  status?: BookingStatus | '';
  date?: string;
  search?: string;
}

export function useBookings(options: UseBookingsOptions = {}) {
  const client = useMakiaClient();
  const queryClient = useQueryClient();

  const queryOptions: ListBookingsOptions = {};

  if (options.status) {
    queryOptions.status = options.status;
  }

  if (options.date) {
    queryOptions.dateFrom = options.date;
    queryOptions.dateTo = options.date;
  }

  if (options.search) {
    queryOptions.search = options.search;
  }

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ['bookings', options],
    queryFn: () => client.bookings.list(queryOptions),
    staleTime: 30 * 1000, // 30 segundos
  });

  const updateStatusMutation = useMutation({
    mutationFn: async ({ bookingId, status }: { bookingId: string; status: BookingStatus }) => {
      if (status === 'CONFIRMED') {
        return client.bookings.confirm(bookingId);
      } else if (status === 'CANCELLED') {
        return client.bookings.cancel(bookingId);
      } else {
        return client.bookings.update(bookingId, { status });
      }
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['bookings'] });
    },
  });

  const updateStatus = useCallback(
    async (bookingId: string, status: BookingStatus) => {
      await updateStatusMutation.mutateAsync({ bookingId, status });
    },
    [updateStatusMutation]
  );

  return {
    bookings: data?.data || [],
    pagination: data?.pagination,
    isLoading,
    error,
    refetch,
    updateStatus,
    isUpdating: updateStatusMutation.isPending,
  };
}

export function useBooking(bookingId: string | null) {
  const client = useMakiaClient();

  const { data, isLoading, error } = useQuery({
    queryKey: ['booking', bookingId],
    queryFn: () => (bookingId ? client.bookings.get(bookingId) : null),
    enabled: !!bookingId,
  });

  return {
    booking: data,
    isLoading,
    error,
  };
}

export function useTodayBookings() {
  const today = new Date().toISOString().split('T')[0];
  return useBookings({ date: today });
}

export function usePendingBookings() {
  return useBookings({ status: 'PENDING' });
}
