'use client';

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useMakiaClient } from '@/lib/makia-client';

interface Template {
  id: string;
  type: string;
  event: string;
  name: string;
  subject: string | null;
  body: string;
  variables: Record<string, string>;
  isActive: boolean;
  isDefault: boolean;
}

interface TemplatesResponse {
  EMAIL: Template[];
  SMS: Template[];
  WHATSAPP: Template[];
  PUSH: Template[];
}

export function useTemplates() {
  const client = useMakiaClient();

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ['notification-templates'],
    queryFn: async () => {
      const response = await client.get<{
        success: boolean;
        data: TemplatesResponse;
        meta: { availableVariables: Record<string, Record<string, string>> };
      }>('/notifications/templates');
      return response;
    },
  });

  return {
    templates: data?.data || { EMAIL: [], SMS: [], WHATSAPP: [], PUSH: [] },
    variables: data?.meta?.availableVariables || {},
    isLoading,
    error,
    refetch,
  };
}

export function useUpdateTemplate() {
  const client = useMakiaClient();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({
      id,
      subject,
      body,
    }: {
      id: string;
      subject?: string;
      body: string;
    }) => {
      return client.patch(`/notifications/templates/${id}`, { subject, body });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notification-templates'] });
    },
  });
}

export function useRestoreTemplate() {
  const client = useMakiaClient();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (templateId: string) => {
      return client.post(`/notifications/templates/${templateId}/restore`, {});
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notification-templates'] });
    },
  });
}

export function useRestoreAllTemplates() {
  const client = useMakiaClient();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async () => {
      return client.post('/notifications/templates/restore-all', {});
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notification-templates'] });
    },
  });
}

export function usePreviewTemplate() {
  const client = useMakiaClient();

  return useMutation({
    mutationFn: async (templateId: string) => {
      return client.post<{
        success: boolean;
        data: { subject: string; body: string; sampleData: Record<string, string> };
      }>(`/notifications/templates/${templateId}/preview`, {});
    },
  });
}
