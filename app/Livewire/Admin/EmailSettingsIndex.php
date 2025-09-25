<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\EmailSetting;
use App\Models\User;
use App\Models\Upload;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

#[Layout('layouts.panel')]
#[Title('Configuración de Emails')]
class EmailSettingsIndex extends Component
{
    public array $settingsByCategory = [];
    public bool $showTestModal = false;
    public string $testEmail = '';
    public array $selectedEmailTypes = [
        'purchase_thank_you',
        'file_upload',
        'processing_started',
        'enhanced_upload_completed',
        'enhanced_upload_failed',
        'admin_sale'
    ];
    public bool $isLoading = false;
    public array $testResults = [];
    public bool $showResults = false;

    public function mount()
    {
        $this->loadSettings();
    }

    public function loadSettings()
    {
        $this->settingsByCategory = EmailSetting::getAllGrouped();
    }

    public function getEmailToggles()
    {
        // Get only the email enable/disable settings (not queues, templates, or general config)
        $allSettings = EmailSetting::where('key', 'like', '%_enabled')
            ->whereIn('category', ['features', 'user_notifications', 'admin_notifications'])
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        return $allSettings->map(function ($setting) {
            return $setting->toArray();
        })->toArray();
    }

    public function openTestModal()
    {
        \Log::info('EmailSettingsIndex openTestModal called');
        $this->showTestModal = true;
    }

    public function closeTestModal()
    {
        $this->showTestModal = false;
        $this->resetTestData();
    }

    private function resetTestData()
    {
        $this->testResults = [];
        $this->showResults = false;
        $this->isLoading = false;
    }

    public function resetTestForm()
    {
        $this->resetTestData();
        $this->testEmail = 'axldeth@gmail.com';
        $this->selectedEmailTypes = [
            'purchase_thank_you',
            'file_upload',
            'processing_started',
            'enhanced_upload_completed',
            'enhanced_upload_failed',
            'admin_sale'
        ];
    }

    public function sendTestEmails()
    {
        $this->validate([
            'testEmail' => 'required|email',
            'selectedEmailTypes' => 'required|array|min:1',
        ]);

        $this->isLoading = true;
        $this->resetTestData();

        $sentEmails = [];
        $errors = [];

        \Log::info('Starting email tests', ['email' => $this->testEmail, 'types' => $this->selectedEmailTypes]);

        foreach ($this->selectedEmailTypes as $emailType) {
            try {
                switch ($emailType) {
                    case 'purchase_thank_you':
                        $this->sendPurchaseThankYouTest($this->testEmail);
                        $sentEmails[] = 'Agradecimiento por Compra';
                        break;

                    case 'file_upload':
                        $this->sendFileUploadTest($this->testEmail);
                        $sentEmails[] = 'Confirmación de Carga de Archivo';
                        break;

                    case 'processing_started':
                        $this->sendProcessingStartedTest($this->testEmail);
                        $sentEmails[] = 'Procesamiento Iniciado';
                        break;

                    case 'enhanced_upload_completed':
                        $this->sendEnhancedUploadCompletedTest($this->testEmail);
                        $sentEmails[] = 'Procesamiento Completado (Mejorado)';
                        break;

                    case 'enhanced_upload_failed':
                        $this->sendEnhancedUploadFailedTest($this->testEmail);
                        $sentEmails[] = 'Procesamiento Fallido (Mejorado)';
                        break;

                    case 'admin_sale':
                        $this->sendAdminSaleTest($this->testEmail);
                        $sentEmails[] = 'Notificación de Venta (Admin)';
                        break;
                }
            } catch (\Exception $e) {
                $errors[] = "Error enviando {$emailType}: " . $e->getMessage();
                \Log::error("Error sending test email", [
                    'type' => $emailType,
                    'email' => $this->testEmail,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->testResults = [
            'success' => count($sentEmails) > 0,
            'message' => count($sentEmails) === count($this->selectedEmailTypes) ?
                'Todos los emails de prueba fueron enviados exitosamente' :
                'Algunos emails fueron enviados, pero hubo errores',
            'sent_emails' => $sentEmails,
            'errors' => $errors,
        ];

        $this->isLoading = false;
        $this->showResults = true;

        \Log::info('Email test completed', ['results' => $this->testResults]);
    }

    public function toggleSetting(string $key)
    {
        try {
            $setting = EmailSetting::where('key', $key)->first();

            if (!$setting) {
                session()->flash('error', 'Configuración no encontrada.');
                return;
            }

            $setting->is_active = !$setting->is_active;
            $setting->save();

            // Clear cache to ensure fresh data
            EmailSetting::clearCache();

            $status = $setting->is_active ? 'activada' : 'desactivada';
            session()->flash('success', "Configuración '{$setting->label}' {$status} exitosamente.");

        } catch (\Exception $e) {
            \Log::error('Error toggling email setting', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            session()->flash('error', 'Error al cambiar la configuración. Inténtalo de nuevo.');
        }
    }


    // Test email methods
    private function sendSubscriptionConfirmationTest(string $email): void
    {
        $testUser = new \App\Models\User([
            'name' => 'Usuario de Prueba',
            'email' => $email,
            'email_verified_at' => now(),
        ]);

        $paymentData = [
            'payment_intent_id' => 'pi_test_123456',
            'amount' => 2999,
            'currency' => 'eur',
            'status' => 'succeeded',
            'payment_method' => 'Tarjeta terminada en 4242',
            'created' => now()->timestamp,
        ];

        $subscriptionData = [
            'subscription_id' => 'sub_test_789012',
            'plan_name' => 'Plan Premium',
            'status' => 'active',
            'current_period_start' => now()->format('d/m/Y'),
            'current_period_end' => now()->addMonth()->format('d/m/Y'),
            'billing_cycle' => 'monthly',
        ];

        $creditsData = [
            'credits_purchased' => 1000,
            'credits_bonus' => 100,
            'credits_total' => 1100,
            'previous_balance' => 50,
            'new_balance' => 1150,
        ];

        $notification = new \App\Notifications\SubscriptionPaymentConfirmation($paymentData, $subscriptionData, $creditsData);
        $testUser->notifyNow($notification);
    }

    private function sendFileUploadTest(string $email): void
    {
        $testUser = new \App\Models\User([
            'name' => 'Usuario de Prueba',
            'email' => $email,
            'email_verified_at' => now(),
        ]);

        $uploadData = [
            'upload_id' => 998,
            'user_id' => $testUser->id ?? 1,
            'status' => 'received',
            'submitted_at' => now()->format('d/m/Y H:i'),
        ];

        $fileData = [
            'original_name' => 'datos-fiscales-test.csv',
            'file_size' => '1.0 MB',
            'estimated_lines' => 1500,
            'file_type' => 'CSV',
        ];

        $processingData = [
            'credits_required' => 150,
            'queue_position' => 3,
            'estimated_processing_time' => '2-3 minutos',
            'next_steps' => 'El archivo será procesado automáticamente.',
        ];

        $notification = new \App\Notifications\FileUploadConfirmation($uploadData, $fileData, $processingData);
        $testUser->notifyNow($notification);
    }

    private function sendProcessingStartedTest(string $email): void
    {
        $testUser = new \App\Models\User([
            'name' => 'Usuario de Prueba',
            'email' => $email,
            'email_verified_at' => now(),
        ]);

        $uploadData = [
            'upload_id' => 999,
            'user_id' => $testUser->id ?? 1,
            'status' => 'processing',
            'started_at' => now()->format('d/m/Y H:i'),
        ];

        $fileData = [
            'original_name' => 'test-processing.csv',
            'file_size' => '2.5 MB',
            'estimated_lines' => 2500,
            'file_type' => 'CSV',
        ];

        $processingData = [
            'estimated_duration' => '3-5 minutos',
            'queue_position' => 2,
            'credits_to_consume' => 250,
            'worker_id' => 'worker-01',
            'priority' => 'normal',
        ];

        $notification = new \App\Notifications\FileProcessingStarted($uploadData, $fileData, $processingData);
        $testUser->notifyNow($notification);
    }

    private function sendUploadCompletedTest(string $email): void
    {
        $testUser = new \App\Models\User([
            'name' => 'Usuario de Prueba',
            'email' => $email,
            'email_verified_at' => now(),
        ]);

        $fakeUpload = new \App\Models\Upload([
            'id' => 999,
            'original_name' => 'datos-fiscales-completado.csv',
            'path' => 'uploads/test-completed.csv',
            'size_bytes' => 1024576,
            'csv_line_count' => 2500,
            'credits_required' => 250,
            'credits_consumed' => 250,
            'rows_count' => 2500,
            'status' => 'completed',
            'processed_at' => now(),
        ]);

        $notification = new \App\Notifications\UploadCompleted($fakeUpload);
        $testUser->notifyNow($notification);
    }

    private function sendUploadFailedTest(string $email): void
    {
        $testUser = new \App\Models\User([
            'name' => 'Usuario de Prueba',
            'email' => $email,
            'email_verified_at' => now(),
        ]);

        $fakeUpload = new \App\Models\Upload([
            'id' => 997,
            'original_name' => 'datos-fiscales-fallido.csv',
            'path' => 'uploads/test-failed.csv',
            'size_bytes' => 1024576,
            'csv_line_count' => 2500,
            'credits_required' => 250,
            'failure_reason' => 'Formato de archivo incorrecto',
            'status' => 'failed',
            'created_at' => now(),
        ]);

        $notification = new \App\Notifications\UploadFailed($fakeUpload);
        $testUser->notifyNow($notification);
    }

    private function sendUploadQueuedTest(string $email): void
    {
        $testUser = new \App\Models\User([
            'name' => 'Usuario de Prueba',
            'email' => $email,
            'email_verified_at' => now(),
        ]);

        $fakeUpload = new \App\Models\Upload([
            'id' => 996,
            'original_name' => 'datos-fiscales-cola.csv',
            'path' => 'uploads/test-queued.csv',
            'size_bytes' => 1024576,
            'csv_line_count' => 1800,
            'credits_required' => 180,
            'status' => 'queued',
            'created_at' => now(),
        ]);

        $notification = new \App\Notifications\UploadQueued($fakeUpload);
        $testUser->notifyNow($notification);
    }

    private function sendUploadReceivedTest(string $email): void
    {
        $testUser = new \App\Models\User([
            'name' => 'Usuario de Prueba',
            'email' => $email,
            'email_verified_at' => now(),
        ]);

        $fakeUpload = new \App\Models\Upload([
            'id' => 995,
            'original_name' => 'datos-fiscales-recibido.csv',
            'path' => 'uploads/test-received.csv',
            'size_bytes' => 1024576,
            'csv_line_count' => 2200,
            'credits_required' => 220,
            'status' => 'received',
            'created_at' => now(),
        ]);

        $notification = new \App\Notifications\UploadReceived($fakeUpload);
        $testUser->notifyNow($notification);
    }

    private function sendAdminSaleTest(string $email): void
    {
        $customerData = [
            'customer_id' => 'cust_test_123',
            'customer_name' => 'Usuario de Prueba',
            'customer_email' => $email,
            'registration_date' => now()->subDays(30)->format('d/m/Y'),
            'total_previous_purchases' => 2,
        ];

        $saleData = [
            'transaction_id' => 'txn_test_123456',
            'plan_name' => 'Plan Premium',
            'amount' => 29.99,
            'currency' => 'EUR',
            'payment_method' => 'Tarjeta **** 4242',
            'credits_purchased' => 1000,
            'sale_date' => now()->format('d/m/Y H:i'),
            'status' => 'completed',
        ];

        $revenueData = [
            'gross_revenue' => 29.99,
            'net_revenue' => 26.99,
            'fees' => 3.00,
            'commission_rate' => 10.0,
            'daily_total' => 89.97,
            'monthly_total' => 1799.40,
        ];

        $notification = new \App\Notifications\SaleNotification($customerData, $saleData, $revenueData);
        \Illuminate\Support\Facades\Notification::route('mail', $email)->notifyNow($notification);
    }

    private function sendWeeklyReportTest(string $email): void
    {
        $reportData = [
            'sales' => [
                'total_sales' => 45,
                'total_revenue' => 1347.55,
                'average_transaction_value' => 29.95,
                'returning_customers' => 12
            ],
            'customers' => ['new_users' => 8],
            'jobs' => [
                'completed_jobs' => 156,
                'success_rate' => 98.7,
                'failed_jobs' => 2
            ]
        ];

        $weekPeriod = now()->startOfWeek()->format('d/m/Y') . ' - ' . now()->endOfWeek()->format('d/m/Y');

        $notification = new \App\Notifications\WeeklySalesReport($reportData, $weekPeriod);
        \Illuminate\Support\Facades\Notification::route('mail', $email)->notifyNow($notification);
    }

    private function sendDailyReportTest(string $email): void
    {
        $reportDate = now()->format('Y-m-d');
        $reportData = [
            'jobs' => [
                'completed_jobs' => 87,
                'failed_jobs' => 3,
                'success_rate' => 96.7,
                'total_processing_time' => '2h 34m'
            ],
            'uploads' => [
                'total_uploads' => 45,
                'successful_uploads' => 42,
                'failed_uploads' => 3
            ]
        ];

        $notification = new \App\Notifications\DailyJobStatusReport($reportData, $reportDate);
        \Illuminate\Support\Facades\Notification::route('mail', $email)->notifyNow($notification);
    }

    private function sendMonthlyReportTest(string $email): void
    {
        $monthPeriod = now()->format('F Y');
        $reportData = [
            'sales' => [
                'total_sales' => 156,
                'total_revenue' => 4673.44,
                'average_transaction_value' => 29.95,
                'returning_customers' => 45
            ],
            'customers' => [
                'new_users' => 32,
                'total_active_users' => 287
            ]
        ];

        $notification = new \App\Notifications\MonthlySalesReport($reportData, $monthPeriod);
        \Illuminate\Support\Facades\Notification::route('mail', $email)->notifyNow($notification);
    }

    public function getEmailTypeLabels(): array
    {
        return [
            'purchase_thank_you' => 'Agradecimiento por Compra',
            'file_upload' => 'Confirmación de Carga',
            'processing_started' => 'Procesamiento Iniciado',
            'enhanced_upload_completed' => 'Procesamiento Completado (Mejorado)',
            'enhanced_upload_failed' => 'Procesamiento Fallido (Mejorado)',
            'admin_sale' => 'Notificación de Venta',
        ];
    }

    private function sendPurchaseThankYouTest(string $email): void
    {
        $testUser = new \App\Models\User([
            'name' => 'Usuario de Prueba',
            'email' => $email,
            'email_verified_at' => now(),
        ]);

        $customerData = [
            'name' => 'Usuario de Prueba',
            'email' => $email,
            'customer_id' => 'cust_test_123456',
            'registration_date' => now()->subDays(1)->format('d/m/Y'),
        ];

        $purchaseData = [
            'date' => now()->format('d/m/Y H:i'),
            'amount' => 2999, // 29.99 EUR in cents
            'amount_formatted' => '€29.99',
            'payment_method' => 'Tarjeta de crédito',
            'transaction_id' => 'pi_test_' . time(),
            'invoice_id' => 'in_test_' . time(),
        ];

        $subscriptionData = [
            'plan_name' => 'Plan Premium',
            'billing_cycle' => 'mensual',
            'next_billing_date' => now()->addMonth()->format('d/m/Y'),
            'status' => 'active',
        ];

        $creditsData = [
            'credits_added' => 100,
            'total_credits' => 250,
            'credits_remaining' => 250,
        ];

        $notification = new \App\Notifications\PurchaseThankYou($customerData, $purchaseData, $subscriptionData, $creditsData);
        $testUser->notifyNow($notification);
    }

    private function sendEnhancedUploadCompletedTest(string $email): void
    {
        $testUser = new \App\Models\User([
            'name' => 'Usuario de Prueba',
            'email' => $email,
            'email_verified_at' => now(),
        ]);

        $fakeUpload = new \App\Models\Upload([
            'id' => 1001,
            'original_name' => 'datos-fiscales-mejorado.csv',
            'user_id' => $testUser->id ?? 1,
            'status' => 'completed',
            'size_bytes' => 2048576,
            'rows_count' => 1234,
            'processed_at' => now(),
            'created_at' => now()->subMinutes(5),
        ]);

        $fakeUploadMetric = new \App\Models\UploadMetric([
            'id' => 501,
            'upload_id' => $fakeUpload->id,
            'user_id' => $testUser->id ?? 1,
            'file_name' => $fakeUpload->original_name,
            'file_size_bytes' => $fakeUpload->size_bytes,
            'line_count' => $fakeUpload->rows_count,
            'processing_duration_seconds' => 287,
            'credits_consumed' => 1,
            'status' => 'completed',
        ]);

        $notification = new \App\Notifications\EnhancedUploadCompleted($fakeUpload, $fakeUploadMetric);
        $testUser->notifyNow($notification);
    }

    private function sendEnhancedUploadFailedTest(string $email): void
    {
        $testUser = new \App\Models\User([
            'name' => 'Usuario de Prueba',
            'email' => $email,
            'email_verified_at' => now(),
        ]);

        $fakeUpload = new \App\Models\Upload([
            'id' => 1002,
            'original_name' => 'datos-fiscales-error.csv',
            'user_id' => $testUser->id ?? 1,
            'status' => 'failed',
            'size_bytes' => 1024768,
            'rows_count' => 567,
            'failure_reason' => 'Error de formato en línea 45: formato de fecha inválido',
            'created_at' => now()->subMinutes(3),
        ]);

        $fakeUploadMetric = new \App\Models\UploadMetric([
            'id' => 502,
            'upload_id' => $fakeUpload->id,
            'user_id' => $testUser->id ?? 1,
            'file_name' => $fakeUpload->original_name,
            'file_size_bytes' => $fakeUpload->size_bytes,
            'line_count' => $fakeUpload->rows_count,
            'processing_duration_seconds' => 78,
            'credits_consumed' => 0,
            'status' => 'failed',
            'error_message' => $fakeUpload->failure_reason,
        ]);

        $notification = new \App\Notifications\EnhancedUploadFailed($fakeUpload, $fakeUploadMetric);
        $testUser->notifyNow($notification);
    }

    public function render()
    {
        return view('livewire.admin.email-settings-index', [
            'emailTypeLabels' => $this->getEmailTypeLabels(),
            'emailToggles' => $this->getEmailToggles(),
        ]);
    }
}
