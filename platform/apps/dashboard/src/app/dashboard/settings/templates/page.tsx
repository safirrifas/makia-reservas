'use client';

import { useState } from 'react';
import { DashboardLayout } from '@/components/layout/dashboard-layout';
import { useTemplates, useUpdateTemplate, useRestoreTemplate } from '@/hooks/use-templates';
import {
  Mail,
  MessageSquare,
  Smartphone,
  Bell,
  Save,
  RotateCcw,
  Eye,
  Loader2,
  ChevronDown,
  ChevronRight,
  Check,
} from 'lucide-react';
import { cn } from '@/lib/utils';

type TemplateType = 'EMAIL' | 'SMS' | 'WHATSAPP' | 'PUSH';

const typeConfig: Record<TemplateType, { label: string; icon: typeof Mail; color: string }> = {
  EMAIL: { label: 'Email', icon: Mail, color: 'text-blue-600 bg-blue-50' },
  SMS: { label: 'SMS', icon: MessageSquare, color: 'text-green-600 bg-green-50' },
  WHATSAPP: { label: 'WhatsApp', icon: Smartphone, color: 'text-emerald-600 bg-emerald-50' },
  PUSH: { label: 'Push', icon: Bell, color: 'text-purple-600 bg-purple-50' },
};

const eventLabels: Record<string, string> = {
  BOOKING_RECEIVED: 'Reserva recibida',
  BOOKING_CONFIRMED: 'Reserva confirmada',
  BOOKING_CANCELLED: 'Reserva cancelada',
  BOOKING_REMINDER: 'Recordatorio',
  BOOKING_MODIFIED: 'Reserva modificada',
  ADMIN_NEW_BOOKING: 'Nueva reserva (Admin)',
};

export default function TemplatesPage() {
  const { templates, variables, isLoading, refetch } = useTemplates();
  const updateTemplate = useUpdateTemplate();
  const restoreTemplate = useRestoreTemplate();

  const [activeType, setActiveType] = useState<TemplateType>('EMAIL');
  const [expandedTemplate, setExpandedTemplate] = useState<string | null>(null);
  const [editedTemplates, setEditedTemplates] = useState<Record<string, { subject?: string; body: string }>>({});
  const [previewData, setPreviewData] = useState<{ subject: string; body: string } | null>(null);
  const [saving, setSaving] = useState<string | null>(null);

  const currentTemplates = templates[activeType] || [];

  const handleEdit = (id: string, field: 'subject' | 'body', value: string) => {
    setEditedTemplates((prev) => ({
      ...prev,
      [id]: {
        ...prev[id],
        [field]: value,
      },
    }));
  };

  const handleSave = async (template: any) => {
    setSaving(template.id);
    try {
      const edited = editedTemplates[template.id];
      await updateTemplate.mutateAsync({
        id: template.id,
        subject: edited?.subject ?? template.subject,
        body: edited?.body ?? template.body,
      });
      // Limpiar ediciones guardadas
      setEditedTemplates((prev) => {
        const next = { ...prev };
        delete next[template.id];
        return next;
      });
      refetch();
    } finally {
      setSaving(null);
    }
  };

  const handleRestore = async (templateId: string) => {
    if (!confirm('¿Restaurar esta plantilla a los valores por defecto?')) return;

    setSaving(templateId);
    try {
      await restoreTemplate.mutateAsync(templateId);
      setEditedTemplates((prev) => {
        const next = { ...prev };
        delete next[templateId];
        return next;
      });
      refetch();
    } finally {
      setSaving(null);
    }
  };

  const getTemplateValue = (template: any, field: 'subject' | 'body') => {
    return editedTemplates[template.id]?.[field] ?? template[field] ?? '';
  };

  const hasChanges = (templateId: string) => {
    return !!editedTemplates[templateId];
  };

  return (
    <DashboardLayout>
      <div className="space-y-6">
        {/* Header */}
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Plantillas de Notificaciones</h1>
          <p className="text-muted-foreground">
            Personaliza los mensajes que se envían a tus clientes
          </p>
        </div>

        {/* Tabs de tipo */}
        <div className="flex gap-2 border-b">
          {(Object.keys(typeConfig) as TemplateType[]).map((type) => {
            const config = typeConfig[type];
            const Icon = config.icon;
            const count = templates[type]?.length || 0;

            return (
              <button
                key={type}
                onClick={() => setActiveType(type)}
                className={cn(
                  'flex items-center gap-2 px-4 py-3 border-b-2 transition-colors',
                  activeType === type
                    ? 'border-primary text-primary'
                    : 'border-transparent text-gray-500 hover:text-gray-700'
                )}
              >
                <Icon className="h-4 w-4" />
                <span className="font-medium">{config.label}</span>
                {count > 0 && (
                  <span className="text-xs bg-gray-100 px-2 py-0.5 rounded-full">
                    {count}
                  </span>
                )}
              </button>
            );
          })}
        </div>

        {/* Lista de plantillas */}
        {isLoading ? (
          <div className="flex justify-center py-12">
            <Loader2 className="h-8 w-8 animate-spin text-primary" />
          </div>
        ) : currentTemplates.length === 0 ? (
          <div className="bg-white rounded-xl border p-8 text-center">
            <p className="text-gray-500">No hay plantillas de {typeConfig[activeType].label}</p>
          </div>
        ) : (
          <div className="space-y-4">
            {currentTemplates.map((template: any) => {
              const isExpanded = expandedTemplate === template.id;
              const eventVars = variables[template.event] || {};

              return (
                <div
                  key={template.id}
                  className="bg-white rounded-xl border overflow-hidden"
                >
                  {/* Header de la plantilla */}
                  <button
                    onClick={() => setExpandedTemplate(isExpanded ? null : template.id)}
                    className="w-full flex items-center justify-between p-4 hover:bg-gray-50"
                  >
                    <div className="flex items-center gap-3">
                      <div className={cn('p-2 rounded-lg', typeConfig[activeType].color)}>
                        {(() => {
                          const Icon = typeConfig[activeType].icon;
                          return <Icon className="h-4 w-4" />;
                        })()}
                      </div>
                      <div className="text-left">
                        <p className="font-medium">{template.name}</p>
                        <p className="text-sm text-gray-500">
                          {eventLabels[template.event] || template.event}
                        </p>
                      </div>
                    </div>
                    <div className="flex items-center gap-2">
                      {hasChanges(template.id) && (
                        <span className="text-xs bg-yellow-100 text-yellow-700 px-2 py-1 rounded">
                          Sin guardar
                        </span>
                      )}
                      {template.isDefault && (
                        <span className="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded">
                          Por defecto
                        </span>
                      )}
                      {isExpanded ? (
                        <ChevronDown className="h-5 w-5 text-gray-400" />
                      ) : (
                        <ChevronRight className="h-5 w-5 text-gray-400" />
                      )}
                    </div>
                  </button>

                  {/* Editor expandido */}
                  {isExpanded && (
                    <div className="border-t p-4 space-y-4">
                      {/* Asunto (solo para email) */}
                      {activeType === 'EMAIL' && (
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">
                            Asunto
                          </label>
                          <input
                            type="text"
                            value={getTemplateValue(template, 'subject')}
                            onChange={(e) => handleEdit(template.id, 'subject', e.target.value)}
                            className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                            placeholder="Asunto del email..."
                          />
                        </div>
                      )}

                      {/* Cuerpo */}
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Mensaje
                          {activeType === 'SMS' && (
                            <span className="text-gray-400 ml-2">
                              ({getTemplateValue(template, 'body').length}/160 caracteres)
                            </span>
                          )}
                        </label>
                        <textarea
                          value={getTemplateValue(template, 'body')}
                          onChange={(e) => handleEdit(template.id, 'body', e.target.value)}
                          rows={activeType === 'SMS' ? 4 : 10}
                          maxLength={activeType === 'SMS' ? 160 : undefined}
                          className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary font-mono text-sm"
                          placeholder="Contenido del mensaje..."
                        />
                      </div>

                      {/* Variables disponibles */}
                      <div className="bg-gray-50 p-4 rounded-lg">
                        <p className="text-sm font-medium text-gray-700 mb-2">
                          Variables disponibles:
                        </p>
                        <div className="flex flex-wrap gap-2">
                          {Object.entries(eventVars).map(([key, description]) => (
                            <button
                              key={key}
                              type="button"
                              onClick={() => {
                                const textarea = document.querySelector(
                                  `textarea[value="${getTemplateValue(template, 'body')}"]`
                                ) as HTMLTextAreaElement;
                                if (textarea) {
                                  const start = textarea.selectionStart;
                                  const end = textarea.selectionEnd;
                                  const currentValue = getTemplateValue(template, 'body');
                                  const newValue =
                                    currentValue.substring(0, start) +
                                    `{${key}}` +
                                    currentValue.substring(end);
                                  handleEdit(template.id, 'body', newValue);
                                } else {
                                  handleEdit(
                                    template.id,
                                    'body',
                                    getTemplateValue(template, 'body') + `{${key}}`
                                  );
                                }
                              }}
                              className="text-xs bg-white border px-2 py-1 rounded hover:bg-gray-100 transition-colors"
                              title={description as string}
                            >
                              {`{${key}}`}
                            </button>
                          ))}
                        </div>
                      </div>

                      {/* Acciones */}
                      <div className="flex items-center justify-between pt-2">
                        <button
                          onClick={() => handleRestore(template.id)}
                          disabled={saving === template.id || template.isDefault}
                          className="flex items-center gap-2 px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg disabled:opacity-50"
                        >
                          <RotateCcw className="h-4 w-4" />
                          Restaurar por defecto
                        </button>

                        <div className="flex items-center gap-2">
                          <button
                            onClick={() => {
                              // TODO: Implementar preview con API
                              setPreviewData({
                                subject: getTemplateValue(template, 'subject'),
                                body: getTemplateValue(template, 'body'),
                              });
                            }}
                            className="flex items-center gap-2 px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg"
                          >
                            <Eye className="h-4 w-4" />
                            Vista previa
                          </button>

                          <button
                            onClick={() => handleSave(template)}
                            disabled={saving === template.id || !hasChanges(template.id)}
                            className={cn(
                              'flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors',
                              hasChanges(template.id)
                                ? 'bg-primary text-white hover:bg-primary/90'
                                : 'bg-gray-100 text-gray-400 cursor-not-allowed'
                            )}
                          >
                            {saving === template.id ? (
                              <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                              <Save className="h-4 w-4" />
                            )}
                            Guardar
                          </button>
                        </div>
                      </div>
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        )}

        {/* Modal de vista previa */}
        {previewData && (
          <div
            className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
            onClick={() => setPreviewData(null)}
          >
            <div
              className="bg-white rounded-xl max-w-2xl w-full max-h-[80vh] overflow-auto"
              onClick={(e) => e.stopPropagation()}
            >
              <div className="p-4 border-b flex items-center justify-between">
                <h3 className="font-semibold">Vista previa</h3>
                <button
                  onClick={() => setPreviewData(null)}
                  className="text-gray-400 hover:text-gray-600"
                >
                  ✕
                </button>
              </div>
              <div className="p-4 space-y-4">
                {previewData.subject && (
                  <div>
                    <p className="text-sm text-gray-500 mb-1">Asunto:</p>
                    <p className="font-medium">{previewData.subject}</p>
                  </div>
                )}
                <div>
                  <p className="text-sm text-gray-500 mb-1">Mensaje:</p>
                  <div className="bg-gray-50 p-4 rounded-lg whitespace-pre-wrap font-mono text-sm">
                    {previewData.body}
                  </div>
                </div>
              </div>
            </div>
          </div>
        )}
      </div>
    </DashboardLayout>
  );
}
