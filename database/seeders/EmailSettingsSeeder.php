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
                'description' => 'Habilitar/deshabilitar emails relacionados con compras y suscripciones',
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
                'description' => 'Habilitar/deshabilitar notificaciones de ventas para administradores',
                'sort_order' => 30,
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
            'purchase_thank_you' => [
                'label' => 'Agradecimiento por Compra',
                'description' => 'Email de agradecimiento enviado tras una compra exitosa',
                'template' => 'emails.users.purchase-thank-you',
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
            'enhanced_upload_completed' => [
                'label' => 'Procesamiento Completado (Mejorado)',
                'description' => 'Email enviado al completar exitosamente el procesamiento',
                'template' => 'emails.users.enhanced-upload-completed',
            ],
            'enhanced_upload_failed' => [
                'label' => 'Procesamiento Fallido (Mejorado)',
                'description' => 'Email enviado cuando falla el procesamiento',
                'template' => 'emails.users.enhanced-upload-failed',
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
