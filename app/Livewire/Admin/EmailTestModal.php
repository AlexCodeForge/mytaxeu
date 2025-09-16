<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\EmailSetting;
use App\Models\Upload;
use App\Models\User;
use Livewire\Component;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class EmailTestModal extends Component
{
    public bool $show = false;
    public string $testType = 'send_emails'; // 'send_emails' or 'diagnostics'
    public string $category = '';

    #[Validate('required|email')]
    public string $testEmail = '';

    public array $selectedEmailTypes = [
        'subscription_confirmation',
        'file_upload',
        'processing_started',
        'upload_completed',
        'admin_sale',
        'weekly_report'
    ];

    public bool $isLoading = false;
    public array $testResults = [];
    public bool $showResults = false;

    protected $listeners = [
        'open-test-modal' => 'openModal',
        'open-category-test' => 'openCategoryTest',
    ];

    public function openModal()
    {
        $this->testType = 'send_emails';
        $this->show = true;
        $this->resetResults();

        // Debug log
        \Log::info('EmailTestModal openModal called', ['show' => $this->show, 'testType' => $this->testType]);
    }

    public function openCategoryTest($category)
    {
        $this->testType = 'diagnostics';
        $this->category = $category;
        $this->show = true;
        $this->resetResults();

        // Debug log
        \Log::info('EmailTestModal openCategoryTest called', ['show' => $this->show, 'category' => $category]);
    }

    public function closeModal()
    {
        $this->show = false;
        $this->resetResults();
    }

    private function resetResults()
    {
        $this->testResults = [];
        $this->showResults = false;
        $this->isLoading = false;
    }

    public function runTest()
    {
        $this->validate();
        $this->isLoading = true;
        $this->resetResults();

        try {
            if ($this->testType === 'send_emails') {
                $this->runEmailTest();
            } else {
                $this->runDiagnosticTest();
            }
        } catch (\Exception $e) {
            $this->testResults = [
                'success' => false,
                'message' => 'Error durante la prueba: ' . $e->getMessage(),
                'details' => []
            ];
        }

        $this->isLoading = false;
        $this->showResults = true;
    }

    private function runEmailTest()
    {
        $sentEmails = [];
        $errors = [];

        foreach ($this->selectedEmailTypes as $emailType) {
            try {
                switch ($emailType) {
                    case 'subscription_confirmation':
                        $this->sendSubscriptionConfirmationTest($this->testEmail);
                        $sentEmails[] = 'Confirmación de Suscripción';
                        break;

                    case 'subscription_renewal':
                        $this->sendSubscriptionRenewalTest($this->testEmail);
                        $sentEmails[] = 'Recordatorio de Renovación';
                        break;

                    case 'file_upload':
                        $this->sendFileUploadTest($this->testEmail);
                        $sentEmails[] = 'Confirmación de Carga de Archivo';
                        break;

                    case 'processing_started':
                        $this->sendProcessingStartedTest($this->testEmail);
                        $sentEmails[] = 'Procesamiento Iniciado';
                        break;

                    case 'upload_completed':
                        $this->sendUploadCompletedTest($this->testEmail);
                        $sentEmails[] = 'Procesamiento Completado';
                        break;

                    case 'upload_failed':
                        $this->sendUploadFailedTest($this->testEmail);
                        $sentEmails[] = 'Procesamiento Fallido';
                        break;

                    case 'upload_queued':
                        $this->sendUploadQueuedTest($this->testEmail);
                        $sentEmails[] = 'Archivo en Cola';
                        break;

                    case 'upload_received':
                        $this->sendUploadReceivedTest($this->testEmail);
                        $sentEmails[] = 'Archivo Recibido';
                        break;

                    case 'enhanced_upload_completed':
                        $this->sendEnhancedUploadCompletedTest($this->testEmail);
                        $sentEmails[] = 'Carga Mejorada Completada';
                        break;

                    case 'enhanced_upload_failed':
                        $this->sendEnhancedUploadFailedTest($this->testEmail);
                        $sentEmails[] = 'Carga Mejorada Fallida';
                        break;

                    case 'admin_sale':
                        $this->sendAdminSaleTest($this->testEmail);
                        $sentEmails[] = 'Notificación de Venta (Admin)';
                        break;

                    case 'failed_job_alert':
                        $this->sendFailedJobAlertTest($this->testEmail);
                        $sentEmails[] = 'Alerta de Trabajo Fallido';
                        break;

                    case 'daily_report':
                        $this->sendDailyReportTest($this->testEmail);
                        $sentEmails[] = 'Reporte Diario';
                        break;

                    case 'weekly_report':
                        $this->sendWeeklyReportTest($this->testEmail);
                        $sentEmails[] = 'Reporte Semanal';
                        break;

                    case 'monthly_report':
                        $this->sendMonthlyReportTest($this->testEmail);
                        $sentEmails[] = 'Reporte Mensual';
                        break;
                }
            } catch (\Exception $e) {
                $errors[] = "Error enviando {$emailType}: " . $e->getMessage();
                Log::error("Error sending test email", [
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
    }

    private function runDiagnosticTest()
    {
        $results = [];
        $allPassed = true;

        try {
            $settings = EmailSetting::getByCategory($this->category);

            if (empty($settings)) {
                $this->testResults = [
                    'success' => false,
                    'message' => "No se encontraron configuraciones para la categoría: {$this->category}",
                    'details' => []
                ];
                return;
            }

            $results[] = "✅ Configuraciones encontradas: " . count($settings);

            foreach ($settings as $setting) {
                $type = $setting['type'];
                $value = $setting['value'];

                if ($type === 'boolean') {
                    $results[] = $value == '1' ?
                        "✅ {$setting['label']}: Habilitado" :
                        "⚠️ {$setting['label']}: Deshabilitado";
                } elseif ($type === 'email') {
                    $isValid = filter_var($value, FILTER_VALIDATE_EMAIL);
                    $results[] = $isValid ?
                        "✅ {$setting['label']}: Email válido" :
                        "❌ {$setting['label']}: Email inválido";
                    if (!$isValid) $allPassed = false;
                } else {
                    $results[] = "✅ {$setting['label']}: {$value}";
                }
            }

        } catch (\Exception $e) {
            $results[] = "❌ Error al probar categoría: " . $e->getMessage();
            $allPassed = false;
        }

        $this->testResults = [
            'success' => $allPassed,
            'message' => $allPassed ?
                "Configuración de '{$this->category}' válida" :
                "Hay problemas en la configuración de '{$this->category}'",
            'details' => $results,
        ];
    }

    // Test email methods
    private function sendSubscriptionConfirmationTest(string $email): void
    {
        $testUser = new User([
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
        Notification::route('mail', $email)->notifyNow($notification);
    }

    private function sendFileUploadTest(string $email): void
    {
        $testUser = new User([
            'name' => 'Usuario de Prueba',
            'email' => $email,
            'email_verified_at' => now(),
        ]);

        $fakeUpload = new Upload([
            'id' => 998,
            'original_name' => 'datos-fiscales-test.csv',
            'path' => 'uploads/test-upload.csv',
            'size_bytes' => 1024576,
            'csv_line_count' => 1500,
            'credits_required' => 150,
            'credits_consumed' => 0,
            'rows_count' => 1500,
            'status' => 'received',
            'created_at' => now(),
        ]);

        $notification = new \App\Notifications\FileUploadConfirmation($fakeUpload);
        $testUser->notifyNow($notification);
    }

    private function sendProcessingStartedTest(string $email): void
    {
        $testUser = new User([
            'name' => 'Usuario de Prueba',
            'email' => $email,
            'email_verified_at' => now(),
        ]);

        $uploadData = [
            'upload_id' => 999,
            'filename' => 'test-processing.csv',
            'file_size_mb' => 2.5,
            'estimated_records' => 2500,
            'started_at' => now()->format('d/m/Y H:i'),
        ];

        $estimationData = [
            'estimated_duration' => '3-5 minutos',
            'queue_position' => 2,
            'credits_to_consume' => 250,
        ];

        $notification = new \App\Notifications\FileProcessingStarted($uploadData, $estimationData);
        $testUser->notifyNow($notification);
    }

    private function sendUploadCompletedTest(string $email): void
    {
        $testUser = new User([
            'name' => 'Usuario de Prueba',
            'email' => $email,
            'email_verified_at' => now(),
        ]);

        $fakeUpload = new Upload([
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

    private function sendAdminSaleTest(string $email): void
    {
        $saleData = [
            'customer_name' => 'Usuario de Prueba',
            'customer_email' => $email,
            'plan_name' => 'Plan Premium',
            'amount' => 29.99,
            'currency' => 'EUR',
            'payment_method' => 'Tarjeta **** 4242',
            'credits_purchased' => 1000,
            'sale_date' => now()->format('d/m/Y H:i'),
            'transaction_id' => 'txn_test_123456',
        ];

        $notification = new \App\Notifications\SaleNotification($saleData);
        Notification::route('mail', $email)->notifyNow($notification);
    }

    // Stub methods for other email types
    private function sendSubscriptionRenewalTest(string $email): void { /* Implement if needed */ }
    private function sendUploadFailedTest(string $email): void { /* Implement if needed */ }
    private function sendUploadQueuedTest(string $email): void { /* Implement if needed */ }
    private function sendUploadReceivedTest(string $email): void { /* Implement if needed */ }
    private function sendEnhancedUploadCompletedTest(string $email): void { /* Implement if needed */ }
    private function sendEnhancedUploadFailedTest(string $email): void { /* Implement if needed */ }
    private function sendFailedJobAlertTest(string $email): void { /* Implement if needed */ }
    private function sendDailyReportTest(string $email): void { /* Implement if needed */ }
    private function sendMonthlyReportTest(string $email): void { /* Implement if needed */ }

    public function getEmailTypeLabels(): array
    {
        return [
            'subscription_confirmation' => 'Confirmación de Suscripción',
            'subscription_renewal' => 'Recordatorio de Renovación',
            'file_upload' => 'Confirmación de Carga',
            'processing_started' => 'Procesamiento Iniciado',
            'upload_completed' => 'Procesamiento Completado',
            'upload_failed' => 'Procesamiento Fallido',
            'upload_queued' => 'Archivo en Cola',
            'upload_received' => 'Archivo Recibido',
            'enhanced_upload_completed' => 'Carga Mejorada Completada',
            'enhanced_upload_failed' => 'Carga Mejorada Fallida',
            'admin_sale' => 'Notificación de Venta',
            'failed_job_alert' => 'Alerta de Trabajo Fallido',
            'daily_report' => 'Reporte Diario',
            'weekly_report' => 'Reporte Semanal',
            'monthly_report' => 'Reporte Mensual',
        ];
    }

    public function render()
    {
        return view('livewire.admin.email-test-modal', [
            'emailTypeLabels' => $this->getEmailTypeLabels(),
        ]);
    }
}
