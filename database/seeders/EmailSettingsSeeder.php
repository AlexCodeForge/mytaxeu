<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\EmailSetting;
use Illuminate\Database\Seeder;

class EmailSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing settings
        EmailSetting::truncate();

        $this->createGeneralSettings();
        $this->createFeatureToggles();
        $this->createUserNotificationSettings();
        $this->createAdminNotificationSettings();
        $this->createScheduleSettings();
    }

    /**
     * Create general email settings.
     */
    protected function createGeneralSettings(): void
    {
        $settings = [
            [
                'key' => 'admin_email_primary',
                'category' => 'general',
                'value' => 'admin@mytaxeu.com',
                'type' => 'email',
                'label' => 'Email Administrador Principal',
                'description' => 'Dirección de email principal para notificaciones administrativas',
                'sort_order' => 10,
            ],
            [
                'key' => 'support_email',
                'category' => 'general',
                'value' => 'support@mytaxeu.com',
                'type' => 'email',
                'label' => 'Email de Soporte',
                'description' => 'Dirección de email para soporte técnico',
                'sort_order' => 20,
            ],
            [
                'key' => 'sender_name',
                'category' => 'general',
                'value' => 'MyTaxEU',
                'type' => 'string',
                'label' => 'Nombre del Remitente',
                'description' => 'Nombre que aparecerá como remitente en todos los emails',
                'sort_order' => 30,
            ],
        ];

        foreach ($settings as $setting) {
            EmailSetting::create($setting);
        }
    }

    /**
     * Create feature toggle settings.
     */
    protected function createFeatureToggles(): void
    {
        $features = [
            [
                'key' => 'subscription_emails_enabled',
                'label' => 'Emails de Suscripción',
                'description' => 'Habilitar/deshabilitar todos los emails relacionados con suscripciones',
                'sort_order' => 10,
            ],
            [
                'key' => 'file_processing_emails_enabled',
                'label' => 'Emails de Procesamiento',
                'description' => 'Habilitar/deshabilitar emails de carga y procesamiento de archivos',
                'sort_order' => 20,
            ],
            [
                'key' => 'admin_notifications_enabled',
                'label' => 'Notificaciones Administrativas',
                'description' => 'Habilitar/deshabilitar todas las notificaciones para administradores',
                'sort_order' => 30,
            ],
            [
                'key' => 'weekly_reports_enabled',
                'label' => 'Reportes Semanales',
                'description' => 'Habilitar/deshabilitar envío automático de reportes semanales',
                'sort_order' => 40,
            ],
            [
                'key' => 'monthly_reports_enabled',
                'label' => 'Reportes Mensuales',
                'description' => 'Habilitar/deshabilitar envío automático de reportes mensuales',
                'sort_order' => 50,
            ],
        ];

        foreach ($features as $feature) {
            EmailSetting::create([
                'key' => $feature['key'],
                'category' => 'features',
                'value' => '1', // true
                'type' => 'boolean',
                'label' => $feature['label'],
                'description' => $feature['description'],
                'sort_order' => $feature['sort_order'],
            ]);
        }
    }

    /**
     * Create user notification settings.
     */
    protected function createUserNotificationSettings(): void
    {
        $notifications = [
            'subscription_payment_confirmation' => [
                'label' => 'Confirmación de Pago',
                'description' => 'Email enviado tras confirmar un pago de suscripción',
                'template' => 'emails.users.subscription-payment-confirmation',
            ],
            'subscription_renewal_reminder' => [
                'label' => 'Recordatorio de Renovación',
                'description' => 'Email enviado 7 días antes de la renovación',
                'template' => 'emails.users.subscription-renewal-reminder',
            ],
            'file_upload_confirmation' => [
                'label' => 'Confirmación de Carga',
                'description' => 'Email enviado al confirmar la carga de un archivo',
                'template' => 'emails.users.file-upload-confirmation',
            ],
            'file_processing_started' => [
                'label' => 'Procesamiento Iniciado',
                'description' => 'Email enviado al iniciar el procesamiento',
                'template' => 'emails.users.file-processing-started',
            ],
            'file_processing_completed' => [
                'label' => 'Procesamiento Completado',
                'description' => 'Email enviado al completar el procesamiento',
                'template' => 'emails.users.file-processing-completed',
            ],
            'file_processing_failed' => [
                'label' => 'Procesamiento Fallido',
                'description' => 'Email enviado cuando falla el procesamiento',
                'template' => 'emails.users.file-processing-failed',
            ],
        ];

        $order = 10;
        foreach ($notifications as $key => $config) {
            // Enabled setting
            EmailSetting::create([
                'key' => $key . '_enabled',
                'category' => 'user_notifications',
                'subcategory' => $key,
                'value' => '1',
                'type' => 'boolean',
                'label' => $config['label'] . ' - Habilitado',
                'description' => $config['description'],
                'sort_order' => $order,
            ]);

            // Queue setting
            EmailSetting::create([
                'key' => $key . '_queue',
                'category' => 'user_notifications',
                'subcategory' => $key,
                'value' => 'emails',
                'type' => 'string',
                'label' => $config['label'] . ' - Cola',
                'description' => 'Cola de procesamiento para este tipo de email',
                'sort_order' => $order + 1,
            ]);

            // Template setting
            EmailSetting::create([
                'key' => $key . '_template',
                'category' => 'user_notifications',
                'subcategory' => $key,
                'value' => $config['template'],
                'type' => 'string',
                'label' => $config['label'] . ' - Plantilla',
                'description' => 'Plantilla Blade a usar para este email',
                'sort_order' => $order + 2,
            ]);

            $order += 10;
        }
    }

    /**
     * Create admin notification settings.
     */
    protected function createAdminNotificationSettings(): void
    {
        $notifications = [
            'sale_notification' => [
                'label' => 'Notificación de Venta',
                'description' => 'Email enviado inmediatamente tras una venta',
                'queue' => 'priority-emails',
                'template' => 'emails.admin.sale-notification',
            ],
            'weekly_sales_report' => [
                'label' => 'Reporte Semanal',
                'description' => 'Reporte enviado cada lunes con datos de la semana',
                'queue' => 'report-emails',
                'template' => 'emails.admin.weekly-sales-report',
            ],
            'monthly_sales_report' => [
                'label' => 'Reporte Mensual',
                'description' => 'Reporte ejecutivo enviado el primer día de cada mes',
                'queue' => 'report-emails',
                'template' => 'emails.admin.monthly-sales-report',
            ],
            'daily_job_status_report' => [
                'label' => 'Reporte Diario',
                'description' => 'Reporte operacional enviado cada mañana',
                'queue' => 'report-emails',
                'template' => 'emails.admin.daily-job-status-report',
            ],
            'failed_job_alert' => [
                'label' => 'Alerta de Trabajo Fallido',
                'description' => 'Alerta enviada cuando falla un trabajo crítico',
                'queue' => 'priority-emails',
                'template' => 'emails.admin.failed-job-alert',
            ],
        ];

        $order = 10;
        foreach ($notifications as $key => $config) {
            // Enabled setting
            EmailSetting::create([
                'key' => $key . '_enabled',
                'category' => 'admin_notifications',
                'subcategory' => $key,
                'value' => '1',
                'type' => 'boolean',
                'label' => $config['label'] . ' - Habilitado',
                'description' => $config['description'],
                'sort_order' => $order,
            ]);

            // Queue setting
            EmailSetting::create([
                'key' => $key . '_queue',
                'category' => 'admin_notifications',
                'subcategory' => $key,
                'value' => $config['queue'],
                'type' => 'string',
                'label' => $config['label'] . ' - Cola',
                'description' => 'Cola de procesamiento para este tipo de email',
                'sort_order' => $order + 1,
            ]);

            // Template setting
            EmailSetting::create([
                'key' => $key . '_template',
                'category' => 'admin_notifications',
                'subcategory' => $key,
                'value' => $config['template'],
                'type' => 'string',
                'label' => $config['label'] . ' - Plantilla',
                'description' => 'Plantilla Blade a usar para este email',
                'sort_order' => $order + 2,
            ]);

            $order += 10;
        }
    }

    /**
     * Create schedule settings.
     */
    protected function createScheduleSettings(): void
    {
        $schedules = [
            [
                'key' => 'daily_report_time',
                'category' => 'schedules',
                'value' => '08:00',
                'type' => 'string',
                'label' => 'Hora Reporte Diario',
                'description' => 'Hora para enviar el reporte diario (formato HH:MM)',
                'sort_order' => 10,
            ],
            [
                'key' => 'weekly_report_time',
                'category' => 'schedules',
                'value' => '09:00',
                'type' => 'string',
                'label' => 'Hora Reporte Semanal',
                'description' => 'Hora para enviar el reporte semanal los lunes',
                'sort_order' => 20,
            ],
            [
                'key' => 'monthly_report_time',
                'category' => 'schedules',
                'value' => '09:00',
                'type' => 'string',
                'label' => 'Hora Reporte Mensual',
                'description' => 'Hora para enviar el reporte mensual el día 1',
                'sort_order' => 30,
            ],
            [
                'key' => 'renewal_reminder_days',
                'category' => 'schedules',
                'value' => '7',
                'type' => 'integer',
                'label' => 'Días Recordatorio Renovación',
                'description' => 'Días antes de la renovación para enviar recordatorio',
                'sort_order' => 40,
            ],
        ];

        foreach ($schedules as $setting) {
            EmailSetting::create($setting);
        }
    }
}
