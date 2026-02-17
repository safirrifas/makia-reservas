/**
 * MakIA Restaurante - Admin JavaScript
 *
 * Interacciones AJAX para el panel de administración de licencias
 */

(function($) {
    'use strict';

    // Configuración global
    const MakiaAdmin = {
        ajaxUrl: makiaAdmin.ajaxUrl,
        nonce: makiaAdmin.nonce,
        i18n: makiaAdmin.i18n,

        /**
         * Inicializar
         */
        init: function() {
            this.bindEvents();
            this.loadInitialData();
        },

        /**
         * Vincular eventos
         */
        bindEvents: function() {
            // Dashboard - cargar notificaciones recientes
            if ($('#makia-recent-notifications').length) {
                this.loadRecentNotifications();
            }

            // Página de facturación
            if ($('#makia-payment-method').length) {
                this.loadBillingData();
            }

            // Página de soporte
            if ($('#makia-support-form').length) {
                $('#makia-support-form').on('submit', this.handleSupportSubmit.bind(this));
                this.loadTickets();
            }

            // Página de novedades
            if ($('#makia-notifications-list').length) {
                this.loadNotifications();
                this.loadRoadmap();
                this.loadChangelog();

                $('#makia-notification-filter').on('change', this.handleNotificationFilter.bind(this));
                $('#makia-mark-all-read').on('click', this.markAllNotificationsRead.bind(this));
            }

            // Botones de upgrade/downgrade
            $(document).on('click', '.makia-upgrade-btn, .makia-downgrade-btn', this.handlePlanChange.bind(this));

            // Actualizar método de pago
            $(document).on('click', '#makia-update-payment', this.handleUpdatePayment.bind(this));

            // Editar datos de facturación
            $(document).on('click', '#makia-edit-billing', this.handleEditBilling.bind(this));

            // Marcar notificación como leída
            $(document).on('click', '.makia-notification-item', this.markNotificationRead.bind(this));
        },

        /**
         * Cargar datos iniciales según la página
         */
        loadInitialData: function() {
            // Detectar página actual y cargar datos correspondientes
        },

        /**
         * Realizar petición AJAX
         */
        ajax: function(action, data, successCallback, errorCallback) {
            data = data || {};
            data.action = action;
            data.nonce = this.nonce;

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        if (successCallback) successCallback(response.data);
                    } else {
                        if (errorCallback) {
                            errorCallback(response.data?.message || MakiaAdmin.i18n.error);
                        } else {
                            MakiaAdmin.showToast(response.data?.message || MakiaAdmin.i18n.error, 'error');
                        }
                    }
                },
                error: function(xhr, status, error) {
                    if (errorCallback) {
                        errorCallback(error);
                    } else {
                        MakiaAdmin.showToast(MakiaAdmin.i18n.error, 'error');
                    }
                }
            });
        },

        /**
         * Cargar notificaciones recientes (Dashboard)
         */
        loadRecentNotifications: function() {
            const container = $('#makia-recent-notifications');

            this.ajax('makia_get_notifications', { limit: 5 }, function(data) {
                if (!data || !data.length) {
                    container.html('<p class="makia-no-data">No hay notificaciones</p>');
                    return;
                }

                let html = '';
                data.forEach(function(notification) {
                    html += MakiaAdmin.renderNotificationItem(notification);
                });
                container.html(html);
            }, function() {
                container.html('<p class="makia-no-data">Error al cargar notificaciones</p>');
            });
        },

        /**
         * Renderizar item de notificación
         */
        renderNotificationItem: function(notification) {
            const iconClass = notification.type || 'announcement';
            const iconMap = {
                feature: 'star-filled',
                improvement: 'arrow-up-alt',
                maintenance: 'admin-tools',
                announcement: 'megaphone'
            };

            return `
                <div class="makia-notification-item ${notification.read ? '' : 'unread'}" data-id="${notification.id}">
                    <div class="makia-notification-icon ${iconClass}">
                        <span class="dashicons dashicons-${iconMap[iconClass] || 'megaphone'}"></span>
                    </div>
                    <div class="makia-notification-content">
                        <div class="makia-notification-title">${this.escapeHtml(notification.title)}</div>
                        <div class="makia-notification-date">${this.formatDate(notification.created_at)}</div>
                    </div>
                </div>
            `;
        },

        /**
         * Cargar datos de facturación
         */
        loadBillingData: function() {
            // Cargar método de pago
            this.ajax('makia_get_billing', {}, function(data) {
                MakiaAdmin.renderPaymentMethod(data.payment_method);
                MakiaAdmin.renderBillingInfo(data.billing_info);
                MakiaAdmin.renderNextInvoice(data.next_invoice);
            });

            // Cargar historial de facturas
            this.loadInvoices();
        },

        /**
         * Renderizar método de pago
         */
        renderPaymentMethod: function(paymentMethod) {
            const container = $('#makia-payment-method .makia-card-body');

            if (!paymentMethod) {
                container.html(`
                    <p>No hay método de pago configurado.</p>
                    <button id="makia-update-payment" class="button button-primary">Añadir método de pago</button>
                `);
                return;
            }

            const cardIcons = {
                visa: '💳',
                mastercard: '💳',
                amex: '💳',
                default: '💳'
            };

            container.html(`
                <div class="makia-payment-card">
                    <div class="makia-payment-card-icon">${cardIcons[paymentMethod.brand] || cardIcons.default}</div>
                    <div class="makia-payment-card-info">
                        <strong>${paymentMethod.brand?.toUpperCase() || 'Tarjeta'} •••• ${paymentMethod.last4}</strong>
                        <span>Expira: ${paymentMethod.exp_month}/${paymentMethod.exp_year}</span>
                    </div>
                    <button id="makia-update-payment" class="button">Cambiar</button>
                </div>
            `);
        },

        /**
         * Renderizar información de facturación
         */
        renderBillingInfo: function(billingInfo) {
            const container = $('#makia-billing-info .makia-card-body');

            if (!billingInfo || !billingInfo.name) {
                container.html(`
                    <p>No hay datos de facturación configurados.</p>
                    <button id="makia-edit-billing" class="button">Configurar datos</button>
                `);
                return;
            }

            container.html(`
                <table class="makia-info-table">
                    <tr>
                        <th>Nombre/Empresa:</th>
                        <td>${this.escapeHtml(billingInfo.name)}</td>
                    </tr>
                    <tr>
                        <th>NIF/CIF:</th>
                        <td>${this.escapeHtml(billingInfo.tax_id || '-')}</td>
                    </tr>
                    <tr>
                        <th>Dirección:</th>
                        <td>${this.escapeHtml(billingInfo.address || '-')}</td>
                    </tr>
                    <tr>
                        <th>Ciudad:</th>
                        <td>${this.escapeHtml(billingInfo.city || '-')} ${this.escapeHtml(billingInfo.postal_code || '')}</td>
                    </tr>
                    <tr>
                        <th>País:</th>
                        <td>${this.escapeHtml(billingInfo.country || '-')}</td>
                    </tr>
                    <tr>
                        <th>Email facturación:</th>
                        <td>${this.escapeHtml(billingInfo.email || '-')}</td>
                    </tr>
                </table>
                <p style="margin-top: 16px;">
                    <button id="makia-edit-billing" class="button">Editar datos</button>
                </p>
            `);
        },

        /**
         * Renderizar próxima factura
         */
        renderNextInvoice: function(nextInvoice) {
            const container = $('#makia-next-invoice .makia-card-body');

            if (!nextInvoice) {
                container.html('<p>No hay facturas pendientes.</p>');
                return;
            }

            container.html(`
                <div class="makia-next-invoice">
                    <div class="makia-next-invoice-info">
                        <span class="makia-next-invoice-amount">${nextInvoice.amount}€</span>
                        <span class="makia-next-invoice-date">Fecha: ${this.formatDate(nextInvoice.date)}</span>
                    </div>
                    <div class="makia-next-invoice-plan">
                        <strong>Plan ${nextInvoice.plan_name}</strong>
                    </div>
                </div>
            `);
        },

        /**
         * Cargar historial de facturas
         */
        loadInvoices: function() {
            const tbody = $('#makia-invoices-table tbody');

            this.ajax('makia_get_invoices', {}, function(data) {
                if (!data || !data.length) {
                    tbody.html('<tr><td colspan="5">No hay facturas disponibles</td></tr>');
                    return;
                }

                let html = '';
                data.forEach(function(invoice) {
                    const statusClass = invoice.status === 'paid' ? 'makia-badge-active' : 'makia-badge-expired';
                    const statusLabel = invoice.status === 'paid' ? 'Pagada' : 'Pendiente';

                    html += `
                        <tr>
                            <td><strong>${MakiaAdmin.escapeHtml(invoice.number)}</strong></td>
                            <td>${MakiaAdmin.formatDate(invoice.date)}</td>
                            <td>${invoice.amount}€</td>
                            <td><span class="makia-badge ${statusClass}">${statusLabel}</span></td>
                            <td>
                                <a href="${invoice.pdf_url}" target="_blank" class="button button-small">
                                    Descargar PDF
                                </a>
                            </td>
                        </tr>
                    `;
                });
                tbody.html(html);
            }, function() {
                tbody.html('<tr><td colspan="5">Error al cargar facturas</td></tr>');
            });
        },

        /**
         * Cargar tickets de soporte
         */
        loadTickets: function() {
            const tbody = $('#makia-tickets-table tbody');

            this.ajax('makia_get_tickets', {}, function(data) {
                if (!data || !data.length) {
                    tbody.html('<tr><td colspan="5">No hay tickets de soporte</td></tr>');
                    return;
                }

                let html = '';
                data.forEach(function(ticket) {
                    const statusClasses = {
                        open: 'makia-badge-trial',
                        pending: 'makia-badge-cancelled',
                        resolved: 'makia-badge-active',
                        closed: 'makia-badge-inactive'
                    };
                    const statusLabels = {
                        open: 'Abierto',
                        pending: 'Pendiente',
                        resolved: 'Resuelto',
                        closed: 'Cerrado'
                    };

                    html += `
                        <tr>
                            <td><strong>#${ticket.id}</strong></td>
                            <td>${MakiaAdmin.escapeHtml(ticket.subject)}</td>
                            <td><span class="makia-badge ${statusClasses[ticket.status] || ''}">${statusLabels[ticket.status] || ticket.status}</span></td>
                            <td>${MakiaAdmin.formatDate(ticket.updated_at)}</td>
                            <td>
                                <a href="https://dashboard.contacpro.app/support/tickets/${ticket.id}" target="_blank" class="button button-small">
                                    Ver
                                </a>
                            </td>
                        </tr>
                    `;
                });
                tbody.html(html);
            }, function() {
                tbody.html('<tr><td colspan="5">Error al cargar tickets</td></tr>');
            });
        },

        /**
         * Enviar ticket de soporte
         */
        handleSupportSubmit: function(e) {
            e.preventDefault();

            const form = $(e.target);
            const submitBtn = form.find('button[type="submit"]');
            const originalText = submitBtn.text();

            submitBtn.prop('disabled', true).text(this.i18n.loading);

            this.ajax('makia_submit_ticket', {
                category: form.find('#ticket-category').val(),
                priority: form.find('#ticket-priority').val(),
                subject: form.find('#ticket-subject').val(),
                message: form.find('#ticket-message').val()
            }, function(data) {
                MakiaAdmin.showToast('Ticket enviado correctamente. ID: #' + data.id, 'success');
                form[0].reset();
                MakiaAdmin.loadTickets();
            }, function(error) {
                MakiaAdmin.showToast(error, 'error');
            });

            submitBtn.prop('disabled', false).text(originalText);
        },

        /**
         * Cargar notificaciones (página de novedades)
         */
        loadNotifications: function(filter) {
            const container = $('#makia-notifications-list');
            filter = filter || 'all';

            container.html('<div class="makia-loading">' + this.i18n.loading + '</div>');

            this.ajax('makia_get_notifications', { filter: filter }, function(data) {
                if (!data || !data.length) {
                    container.html('<p class="makia-no-data">No hay notificaciones</p>');
                    return;
                }

                let html = '';
                data.forEach(function(notification) {
                    html += MakiaAdmin.renderNotificationItem(notification);
                });
                container.html(html);
            }, function() {
                container.html('<p class="makia-no-data">Error al cargar notificaciones</p>');
            });
        },

        /**
         * Filtrar notificaciones
         */
        handleNotificationFilter: function(e) {
            const filter = $(e.target).val();
            this.loadNotifications(filter);
        },

        /**
         * Marcar notificación como leída
         */
        markNotificationRead: function(e) {
            const item = $(e.currentTarget);
            const id = item.data('id');

            if (!item.hasClass('unread')) return;

            this.ajax('makia_mark_notification_read', { notification_id: id }, function() {
                item.removeClass('unread');
            });
        },

        /**
         * Marcar todas las notificaciones como leídas
         */
        markAllNotificationsRead: function(e) {
            e.preventDefault();

            this.ajax('makia_mark_notification_read', { notification_id: 'all' }, function() {
                $('.makia-notification-item').removeClass('unread');
                MakiaAdmin.showToast('Todas las notificaciones marcadas como leídas', 'success');
            });
        },

        /**
         * Cargar roadmap
         */
        loadRoadmap: function() {
            const container = $('#makia-roadmap');

            // Datos de ejemplo - en producción vendrían de la API
            const roadmapItems = [
                {
                    status: 'in-progress',
                    title: 'Integración con Google Calendar',
                    description: 'Sincronización bidireccional de reservas'
                },
                {
                    status: 'planned',
                    title: 'App móvil para restaurantes',
                    description: 'Gestiona tus reservas desde tu smartphone'
                },
                {
                    status: 'beta',
                    title: 'Sistema de lista de espera',
                    description: 'Permite a los clientes apuntarse cuando no hay disponibilidad'
                },
                {
                    status: 'planned',
                    title: 'Inteligencia artificial para predicciones',
                    description: 'Predicción de demanda y optimización de mesas'
                }
            ];

            let html = '';
            roadmapItems.forEach(function(item) {
                const statusLabels = {
                    planned: 'Planificado',
                    'in-progress': 'En desarrollo',
                    beta: 'Beta'
                };

                html += `
                    <div class="makia-roadmap-item">
                        <div class="makia-roadmap-status">
                            <span class="${item.status}">${statusLabels[item.status]}</span>
                        </div>
                        <div class="makia-roadmap-content">
                            <h4>${MakiaAdmin.escapeHtml(item.title)}</h4>
                            <p>${MakiaAdmin.escapeHtml(item.description)}</p>
                        </div>
                    </div>
                `;
            });

            container.html(html);
        },

        /**
         * Cargar changelog
         */
        loadChangelog: function() {
            const container = $('#makia-changelog');

            // Datos de ejemplo - en producción vendrían de la API
            const changelog = [
                {
                    version: '3.4.0',
                    date: '2026-02-05',
                    changes: [
                        { type: 'feature', text: 'Panel de administración completo (Dashboard, Licencia, Facturación, Soporte, Novedades)' },
                        { type: 'feature', text: 'Integración con API tRPC de contacpro.app' },
                        { type: 'feature', text: 'Mostrar motivo de cierre en formulario (Cerrado, Completo, Festivo)' },
                        { type: 'improvement', text: 'Sistema de planes y upgrade/downgrade' }
                    ]
                },
                {
                    version: '3.3.0',
                    date: '2026-02-04',
                    changes: [
                        { type: 'feature', text: 'Sistema de sincronización automática' },
                        { type: 'feature', text: 'Integración con WhatsApp Business' },
                        { type: 'feature', text: 'Bot de Telegram para reservas' }
                    ]
                },
                {
                    version: '1.0.0',
                    date: '2024-01-15',
                    changes: [
                        { type: 'feature', text: 'Lanzamiento inicial' },
                        { type: 'feature', text: 'Widget de reservas embebible' },
                        { type: 'feature', text: 'Sistema de notificaciones por email' },
                        { type: 'feature', text: 'Dashboard de gestión' }
                    ]
                }
            ];

            let html = '';
            changelog.forEach(function(release) {
                let changesHtml = '';
                release.changes.forEach(function(change) {
                    changesHtml += `
                        <li>
                            <span class="makia-change-type ${change.type}">${change.type}</span>
                            ${MakiaAdmin.escapeHtml(change.text)}
                        </li>
                    `;
                });

                html += `
                    <div class="makia-changelog-item">
                        <div class="makia-changelog-version">
                            <strong>v${release.version}</strong>
                            <span>${MakiaAdmin.formatDate(release.date)}</span>
                        </div>
                        <ul class="makia-changelog-changes">
                            ${changesHtml}
                        </ul>
                    </div>
                `;
            });

            container.html(html);
        },

        /**
         * Cambiar de plan
         */
        handlePlanChange: function(e) {
            e.preventDefault();

            const btn = $(e.currentTarget);
            const plan = btn.data('plan');
            const isUpgrade = btn.hasClass('makia-upgrade-btn');

            const confirmMsg = isUpgrade ? this.i18n.confirmUpgrade : this.i18n.confirmCancel;

            if (!confirm(confirmMsg)) {
                return;
            }

            btn.prop('disabled', true).text(this.i18n.loading);

            this.ajax('makia_upgrade_plan', { plan: plan }, function(data) {
                if (data.checkout_url) {
                    // Redirigir a página de pago
                    window.location.href = data.checkout_url;
                } else {
                    MakiaAdmin.showToast('Plan actualizado correctamente', 'success');
                    window.location.reload();
                }
            }, function(error) {
                btn.prop('disabled', false).text(isUpgrade ? 'Mejorar Plan' : 'Cambiar a este plan');
                MakiaAdmin.showToast(error, 'error');
            });
        },

        /**
         * Actualizar método de pago
         */
        handleUpdatePayment: function(e) {
            e.preventDefault();

            const btn = $(e.currentTarget);
            btn.prop('disabled', true).text(this.i18n.loading);

            this.ajax('makia_update_payment_method', {}, function(data) {
                if (data.url) {
                    window.location.href = data.url;
                }
            }, function(error) {
                btn.prop('disabled', false).text('Cambiar');
                MakiaAdmin.showToast(error, 'error');
            });
        },

        /**
         * Editar datos de facturación
         */
        handleEditBilling: function(e) {
            e.preventDefault();
            // Redirigir al portal de facturación
            window.open('https://dashboard.contacpro.app/settings/billing', '_blank');
        },

        /**
         * Mostrar toast
         */
        showToast: function(message, type) {
            type = type || 'info';

            const toast = $('<div class="makia-toast ' + type + '">' + this.escapeHtml(message) + '</div>');
            $('body').append(toast);

            setTimeout(function() {
                toast.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 4000);
        },

        /**
         * Escapar HTML
         */
        escapeHtml: function(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * Formatear fecha
         */
        formatDate: function(dateStr) {
            if (!dateStr) return '-';
            const date = new Date(dateStr);
            return date.toLocaleDateString('es-ES', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }
    };

    // Inicializar cuando el DOM esté listo
    $(document).ready(function() {
        MakiaAdmin.init();
    });

})(jQuery);
