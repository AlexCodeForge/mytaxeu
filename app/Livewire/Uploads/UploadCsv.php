<?php

declare(strict_types=1);

namespace App\Livewire\Uploads;

use App\Models\Upload;
use App\Services\CreditService;
use App\Services\CsvLineCountService;
use App\Services\CsvPeriodAnalyzer;
use App\Services\UploadLimitValidator;
use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class UploadCsv extends Component
{
    use WithFileUploads, AuthorizesRequests;

    public ?TemporaryUploadedFile $csvFile = null;
    public bool $uploading = false;
    public string $uploadProgress = '';
    public int $userCredits = 0;
    public array $periodAnalysis = [];
    public bool $showPeriodConfirmation = false;
    public bool $processingConfirmation = false;
    public bool $uploadSuccess = false;

    public function __construct()
    {
        // Force emergency logs to ensure logging is working
        \Log::emergency('🚨 CONSTRUCTOR EMERGENCY LOG - UPLOAD CSV');
        error_log('CONSTRUCTOR ERROR LOG - UPLOAD CSV');

        logger()->emergency('🚀 UploadCsv component constructor called');
        logger()->info('🚀 UploadCsv component constructor called', [
            'user_id' => auth()->id(),
            'timestamp' => now()->toISOString(),
        ]);

        $this->userCredits = auth()->user()?->credits ?? 0;

        logger()->info('✅ UploadCsv component constructor completed', [
            'user_credits' => $this->userCredits,
        ]);

        \Log::emergency('🚨 CONSTRUCTOR COMPLETED');
    }

    protected array $rules = [
        'csvFile' => 'required|file|mimes:csv|max:102400', // 100MB max
    ];

    protected array $messages = [
        'csvFile.required' => 'Por favor seleccione un archivo CSV.',
        'csvFile.file' => 'El archivo debe ser un archivo válido.',
        'csvFile.mimes' => 'El archivo debe ser un CSV (.csv).',
        'csvFile.max' => 'El archivo no puede ser mayor a 100MB.',
    ];

    public function mount()
    {
        // Force log write to test if logging is working AT ALL
        \Log::emergency('🚨 EMERGENCY TEST LOG FROM UPLOAD CSV MOUNT');
        error_log('ERROR LOG TEST FROM UPLOAD CSV MOUNT');

        logger()->emergency('🎯 UploadCsv component mount method called');
        logger()->info('🎯 UploadCsv component mount method called', [
            'user_id' => auth()->id(),
            'timestamp' => now()->toISOString(),
        ]);

        $this->userCredits = auth()->user()?->credits ?? 0;

        logger()->info('✅ UploadCsv component mount completed', [
            'user_credits' => $this->userCredits,
        ]);

        \Log::emergency('🚨 MOUNT COMPLETED - EMERGENCY LOG');
    }

    public function updatedCsvFile(): void
    {
        logger()->info('🚨 FILE UPLOAD TRIGGERED');

        logger()->info('🔄 CSV file updated in Livewire', [
            'user_id' => auth()->id(),
            'file_present' => !is_null($this->csvFile),
            'file_name' => $this->csvFile?->getClientOriginalName(),
            'file_size' => $this->csvFile?->getSize(),
            'file_mime' => $this->csvFile?->getMimeType(),
            'file_path' => $this->csvFile?->getRealPath(),
        ]);

        // Reset previous analysis
        $this->periodAnalysis = [];
        $this->showPeriodConfirmation = false;

        if (!$this->csvFile) {
            logger()->warning('❌ CSV file is null after update');
            return;
        }

        // Validate basic file properties
        try {
            $this->validateOnly('csvFile');
            logger()->info('✅ File validation passed');
        } catch (\Exception $e) {
            logger()->error('❌ File validation failed', [
                'error' => $e->getMessage(),
            ]);
            return;
        }

        if ($this->csvFile && $this->getErrorBag()->isEmpty()) {
            logger()->info('🔬 About to start period analysis');

            // First, quickly check file size/line count before detailed analysis
            $csvService = app(CsvLineCountService::class);
            $quickAnalysis = $csvService->analyzeFile($this->csvFile);
            $lineCount = $quickAnalysis['line_count'];

                        // Check monthly usage limits ONLY for free tier users (no credits)
            $user = auth()->user();
            $usageMeteringService = app(\App\Services\UsageMeteringService::class);

            if (!$usageMeteringService->canProcessLines($user, $lineCount)) {
                $currentUsage = $usageMeteringService->getCurrentMonthUsage($user);
                $monthlyLimit = $usageMeteringService->getMonthlyLimit($user);

                logger()->warning('❌ Free tier usage limit exceeded during file analysis', [
                    'user_id' => auth()->id(),
                    'user_credits' => $user->credits,
                    'current_usage' => $currentUsage,
                    'requested_lines' => $lineCount,
                    'monthly_limit' => $monthlyLimit,
                ]);

                $errorMessage = "Tu archivo tiene {$lineCount} líneas, pero el límite para usuarios sin créditos es de {$monthlyLimit} líneas por mes. Has usado {$currentUsage} líneas este mes. Compra créditos para procesar archivos más grandes.";
                $this->addError('csvFile', $errorMessage);
                return;
            }

            // If monthly limit check passes, proceed with period analysis
            $this->analyzeFilePeriods();
        } else {
            logger()->warning('⚠️ Skipping period analysis', [
                'has_file' => !is_null($this->csvFile),
                'has_errors' => !$this->getErrorBag()->isEmpty(),
                'errors' => $this->getErrorBag()->toArray(),
            ]);
        }

        logger()->info('✅ CSV file validation completed', [
            'user_id' => auth()->id(),
            'errors' => $this->getErrorBag()->toArray(),
            'analysis_result' => $this->periodAnalysis,
        ]);
    }

    /**
     * Analyze file for ACTIVITY_PERIOD information
     */
    public function analyzeFilePeriods(): void
    {
        try {
            logger()->info('🔍 Starting period analysis');

            $periodAnalyzer = app(CsvPeriodAnalyzer::class);
            $this->periodAnalysis = $periodAnalyzer->analyzePeriods($this->csvFile);

            logger()->info('📊 Period analysis completed', [
                'analysis_result' => $this->periodAnalysis,
            ]);

            if (!$this->periodAnalysis['is_valid']) {
                $this->addError('csvFile', $this->periodAnalysis['error_message']);
                return;
            }

                        // Show confirmation dialog if file is valid and has periods
            if ($this->periodAnalysis['period_count'] > 0) {
                $this->showPeriodConfirmation = true;

                logger()->info('🎯 Showing period confirmation', [
                    'periods' => $this->periodAnalysis['periods'],
                    'period_count' => $this->periodAnalysis['period_count'],
                    'required_credits' => $this->periodAnalysis['required_credits'],
                    'user_credits' => $this->userCredits,
                ]);

                // Dispatch event to frontend to show confirmation
                $this->dispatch('show-period-confirmation', [
                    'periods' => $this->periodAnalysis['periods'],
                    'periodCount' => $this->periodAnalysis['period_count'],
                    'requiredCredits' => $this->periodAnalysis['required_credits'],
                    'userCredits' => $this->userCredits,
                ]);

                // Also dispatch a generic info message
                $periodsText = implode(', ', $this->periodAnalysis['periods']);
                $this->dispatch('upload-info', [
                    'message' => "Archivo analizado: {$this->periodAnalysis['period_count']} período(s) detectado(s) ({$periodsText}). Se requerirán {$this->periodAnalysis['required_credits']} crédito(s) para procesar este archivo."
                ]);
            }

        } catch (\Exception $e) {
            logger()->error('❌ Period analysis failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addError('csvFile', 'Error al analizar el archivo: ' . $e->getMessage());
        }
    }

    public function processUpload(bool $isConfirmed = false): void
    {
        logger()->emergency('🚨 PROCESS UPLOAD METHOD STARTED');
        logger()->info('🔍 Process upload params', [
            'is_confirmed' => $isConfirmed,
            'show_period_confirmation' => $this->showPeriodConfirmation,
            'processing_confirmation' => $this->processingConfirmation,
        ]);

        // If we're showing confirmation and this isn't a confirmed upload, user needs to confirm first
        if ($this->showPeriodConfirmation && !$isConfirmed) {
            logger()->warning('⚠️ Upload attempted while confirmation pending - redirecting to confirmation', [
                'periods' => $this->periodAnalysis['periods'] ?? [],
                'required_credits' => $this->periodAnalysis['required_credits'] ?? 0,
                'show_period_confirmation' => $this->showPeriodConfirmation,
                'processing_confirmation' => $this->processingConfirmation,
            ]);

            $this->dispatch('upload-error', [
                'message' => 'Por favor confirma el procesamiento del archivo primero. Verifica la información de períodos y créditos.'
            ]);
            return;
        }

        logger()->emergency('🚀 STARTING UPLOAD PROCESS');
        logger()->info('🚀 Upload method called', [
            'user_id' => auth()->id(),
            'file_present' => !is_null($this->csvFile),
            'uploading_state' => $this->uploading,
            'csv_file_name' => $this->csvFile?->getClientOriginalName(),
            'is_confirmed' => $isConfirmed,
        ]);

        try {
            $this->authorize('create', Upload::class);
            logger()->info('✅ Authorization passed');
        } catch (\Exception $e) {
            logger()->error('❌ Authorization failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        logger()->emergency('🔍 STARTING VALIDATION');
        $this->validate();
        logger()->emergency('✅ VALIDATION COMPLETED', [
            'errors' => $this->getErrorBag()->toArray(),
            'has_errors' => !$this->getErrorBag()->isEmpty(),
        ]);

        if (! $this->csvFile) {
            logger()->emergency('❌ NO CSV FILE PRESENT AFTER VALIDATION');
            $errorMessage = 'No se ha seleccionado ningún archivo.';
            $this->dispatch('upload-error', [
                'message' => $errorMessage
            ]);
            $this->addError('csvFile', $errorMessage);
            return;
        }

        logger()->emergency('✅ CSV FILE IS PRESENT, CONTINUING...');

        logger()->info('📋 File details before processing', [
            'original_name' => $this->csvFile->getClientOriginalName(),
            'size' => $this->csvFile->getSize(),
            'mime_type' => $this->csvFile->getMimeType(),
            'real_path' => $this->csvFile->getRealPath(),
        ]);

        // Check if we have period analysis from file selection
        if (empty($this->periodAnalysis) || !$this->periodAnalysis['is_valid']) {
            // Re-analyze if not done already
            $this->analyzeFilePeriods();
            if (!$this->periodAnalysis['is_valid']) {
                return; // Error already added by analyzeFilePeriods
            }
        }

        $requiredCredits = $this->periodAnalysis['required_credits'];

        // Check if user has enough credits for the detected periods
        logger()->info('💰 Checking user credits for periods');
        $creditService = app(CreditService::class);
        $user = auth()->user();

        logger()->info('👤 User details', [
            'user_id' => $user->id,
            'current_credits' => $user->credits,
            'required_credits' => $requiredCredits,
            'detected_periods' => $this->periodAnalysis['periods'],
        ]);

        if (!$creditService->hasEnoughCredits($user, $requiredCredits)) {
            logger()->warning('❌ Insufficient credits', [
                'user_id' => $user->id,
                'current_credits' => $user->credits,
                'required_credits' => $requiredCredits,
            ]);

            $periodsText = implode(', ', $this->periodAnalysis['periods']);
            $errorMessage = "No tienes suficientes créditos para procesar este archivo. Necesitas {$requiredCredits} crédito(s) para los {$this->periodAnalysis['period_count']} período(s) detectado(s) ({$periodsText}), pero solo tienes {$user->credits} crédito(s).";

            $this->dispatch('upload-error', [
                'message' => $errorMessage
            ]);
            $this->addError('csvFile', $errorMessage);
            return;
        }

        logger()->info('✅ Credit check passed');

        try {
            logger()->info('🔄 Starting upload process');
            $this->uploading = true;
            $this->uploadProgress = 'Validando archivo...';

            logger()->info('📊 Upload state changed', [
                'uploading' => $this->uploading,
                'progress' => $this->uploadProgress,
            ]);

            // Initialize services
            logger()->info('🛠️ Initializing services');
            $csvService = app(CsvLineCountService::class);
            $limitValidator = app(UploadLimitValidator::class);
            $usageMeteringService = app(\App\Services\UsageMeteringService::class);

            // Validate file content and count lines accurately
            try {
                logger()->info('🔍 Starting file analysis');
                $analysisResult = $csvService->analyzeFile($this->csvFile);
                $csvLineCount = $analysisResult['line_count'];

                logger()->info('📊 File analysis completed', [
                    'line_count' => $csvLineCount,
                    'analysis_result' => $analysisResult,
                ]);

                // Check if file is empty
                if ($csvLineCount === 0) {
                    logger()->warning('❌ Empty CSV file detected');
                    $errorMessage = 'El archivo CSV no es válido: archivo vacío';
                    $this->dispatch('upload-error', [
                        'message' => $errorMessage
                    ]);
                    $this->addError('csvFile', $errorMessage);
                    return;
                }

                                // Check usage limits ONLY for free tier users (no credits)
                logger()->info('🔍 Checking free tier usage limits');
                if (!$usageMeteringService->canProcessLines(auth()->user(), $csvLineCount)) {
                    logger()->warning('❌ Free tier usage limit exceeded', [
                        'user_id' => auth()->id(),
                        'user_credits' => auth()->user()->credits,
                        'current_usage' => $usageMeteringService->getCurrentMonthUsage(auth()->user()),
                        'requested_lines' => $csvLineCount,
                    ]);

                    $currentUsage = $usageMeteringService->getCurrentMonthUsage(auth()->user());
                    $monthlyLimit = $usageMeteringService->getMonthlyLimit(auth()->user());
                    $errorMessage = "Tu archivo tiene {$csvLineCount} líneas, pero el límite para usuarios sin créditos es de {$monthlyLimit} líneas por mes. Has usado {$currentUsage} líneas este mes. Compra créditos para procesar archivos más grandes.";

                    // Reset upload state
                    $this->uploading = false;
                    $this->uploadProgress = '';
                    $this->processingConfirmation = false;
                    $this->uploadSuccess = false;

                    $this->dispatch('upload-error', [
                        'message' => $errorMessage
                    ]);
                    $this->addError('csvFile', $errorMessage);
                    return;
                }

                // Validate upload limits (legacy system)
                logger()->info('🔍 Validating upload limits');
                $ipAddress = request()->ip();
                $validationResult = $limitValidator->validateUpload(auth()->user(), $csvLineCount, $ipAddress);

                logger()->info('📊 Limit validation result', [
                    'validation_result' => $validationResult,
                    'ip_address' => $ipAddress,
                    'line_count' => $csvLineCount,
                ]);

                if (!$validationResult['allowed']) {
                    logger()->warning('❌ Upload limit exceeded', $validationResult);
                    $errorMessage = $this->getLocalizedLimitError($validationResult, $csvLineCount);
                    $this->dispatch('upload-error', [
                        'message' => $errorMessage
                    ]);
                    $this->addError('csvFile', $errorMessage);
                    return;
                }

                logger()->info('✅ All validations passed');

            } catch (\InvalidArgumentException $e) {
                logger()->error('❌ File analysis failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $errorMessage = 'El archivo CSV no es válido: ' . $e->getMessage();
                $this->dispatch('upload-error', [
                    'message' => $errorMessage
                ]);
                $this->addError('csvFile', $errorMessage);
                return;
            }

            // Legacy row count for backward compatibility (will be deprecated)
            $content = file_get_contents($this->csvFile->getRealPath());
            $rowsCount = substr_count($content, "\n") + 1;

            $this->uploadProgress = 'Guardando archivo...';

            // Generate unique filename
            $uuid = Str::uuid();
            $extension = $this->csvFile->getClientOriginalExtension() ?: 'csv';
            $filename = $uuid . '.' . $extension;

            // Storage path pattern: uploads/{user_id}/input/{uuid}.csv
            $storagePath = 'uploads/' . auth()->id() . '/input/' . $filename;

            // Store file with error handling
            try {
                logger()->info('💾 Attempting to store file', [
                    'original_name' => $this->csvFile->getClientOriginalName(),
                    'temp_path' => $this->csvFile->getRealPath(),
                    'target_directory' => dirname($storagePath),
                    'target_filename' => basename($storagePath),
                    'storage_disk' => 'local'
                ]);

                $path = $this->csvFile->storeAs(
                    dirname($storagePath),
                    basename($storagePath),
                    'local'
                );

                if (!$path) {
                    throw new Exception('File storage returned null/false');
                }

                // Verify file was actually stored
                $fullPath = storage_path('app/' . $path);

                // Check if file exists in standard location or private subdirectory
                $privateFullPath = storage_path('app/private/' . $path);

                if (!file_exists($fullPath) && !file_exists($privateFullPath)) {
                    logger()->error('🔍 File not found in either location', [
                        'standard_path' => $fullPath,
                        'private_path' => $privateFullPath,
                        'returned_path' => $path
                    ]);
                    throw new Exception('File was not found after storage');
                }

                // File should be in the standard location now
                if (!file_exists($fullPath) && file_exists($privateFullPath)) {
                    logger()->info('📂 File found in private directory, but will use standard path', ['path' => $path]);
                }

                logger()->info('✅ File stored successfully', [
                    'stored_path' => $path,
                    'full_path' => $fullPath,
                    'file_size' => filesize($fullPath)
                ]);

            } catch (Exception $e) {
                logger()->error('❌ File storage failed', [
                    'error' => $e->getMessage(),
                    'original_name' => $this->csvFile?->getClientOriginalName(),
                    'temp_path' => $this->csvFile?->getRealPath(),
                    'target_storage_path' => $storagePath
                ]);

                $this->addError('csvFile', 'Error al guardar el archivo: ' . $e->getMessage());
                $this->uploading = false;
                $this->processingConfirmation = false;
                $this->uploadSuccess = false;
                return;
            }

            $this->uploadProgress = 'Registrando en base de datos...';

            // Create upload record with period information
            $upload = Upload::create([
                'user_id' => auth()->id(),
                'original_name' => $this->csvFile->getClientOriginalName(),
                'disk' => 'local',
                'path' => $path,
                'size_bytes' => $this->csvFile->getSize(),
                'csv_line_count' => $csvLineCount,
                'detected_periods' => $this->periodAnalysis['periods'],
                'period_count' => $this->periodAnalysis['period_count'],
                'credits_required' => $requiredCredits,
                'credits_consumed' => 0, // Will be updated when processing completes
                'rows_count' => $rowsCount,
                'status' => Upload::STATUS_RECEIVED,
            ]);

            $this->uploadProgress = 'Completado!';

            // Dispatch upload-created event for real-time updates
            $this->dispatch('upload-created', [
                'uploadId' => $upload->id,
                'filename' => $upload->original_name,
                'userId' => $upload->user_id,
                'status' => $upload->status,
            ]);

            // Record the upload for tracking
            $limitValidator->recordUpload(auth()->user(), $csvLineCount, request()->ip());

            // Dispatch the processing job
            logger()->info('🚀 Dispatching processing job', ['upload_id' => $upload->id]);
            \App\Jobs\ProcessUploadJob::dispatch($upload->id);

            // Update status to queued
            logger()->info('📝 Updating upload status to queued');
            $upload->update(['status' => Upload::STATUS_QUEUED]);

            // Dispatch events
            logger()->info('📡 Dispatching events');
            $this->dispatch('upload-created', uploadId: $upload->id);

            // Reset processing confirmation before dispatching success
            $this->processingConfirmation = false;

            $this->dispatch('upload-success', [
                'uploadId' => $upload->id,
                'fileName' => $upload->original_name,
                'message' => 'Archivo subido exitosamente. El procesamiento ha comenzado.'
            ]);

            logger()->emergency('🚨 SUCCESS EVENT DISPATCHED', [
                'upload_id' => $upload->id,
                'file_name' => $upload->original_name,
                'event_data' => [
                    'uploadId' => $upload->id,
                    'fileName' => $upload->original_name,
                    'message' => 'Archivo subido exitosamente. El procesamiento ha comenzado.'
                ]
            ]);

            // Set success state for frontend handling
            $this->uploadSuccess = true;
            logger()->emergency('✅ UPLOAD SUCCESS STATE SET TO TRUE');

            // Update user credits display
            logger()->info('💰 Updating user credits display');
            $this->userCredits = auth()->user()->fresh()->credits;

            logger()->info('📊 Updated credits', [
                'new_credits' => $this->userCredits,
            ]);

            // Reset form (but keep processingConfirmation until after redirect)
            logger()->info('🔄 Resetting form state');
            $this->reset(['csvFile', 'uploading', 'uploadProgress', 'periodAnalysis', 'showPeriodConfirmation']);

            logger()->emergency('🎉 UPLOAD PROCESS COMPLETED SUCCESSFULLY!', [
                'upload_id' => $upload->id,
                'file_name' => $upload->original_name,
                'line_count' => $csvLineCount,
                'is_confirmed' => $isConfirmed,
            ]);

        } catch (\Exception $e) {
            logger()->error('❌ Upload process failed with exception', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file_name' => $this->csvFile?->getClientOriginalName(),
            ]);

            $this->uploading = false;
            $this->uploadProgress = '';
            $this->processingConfirmation = false;
            $this->uploadSuccess = false;

            logger()->error('Upload failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Dispatch error event for toast notification
            $this->dispatch('upload-error', [
                'message' => 'Error al subir el archivo: ' . $e->getMessage()
            ]);

            $this->addError('csvFile', 'Error al subir el archivo: ' . $e->getMessage());
        }
    }

    public function cancelUpload(): void
    {
        logger()->info('🚫 Upload cancelled by user', [
            'user_id' => auth()->id(),
            'had_file' => !is_null($this->csvFile),
            'was_uploading' => $this->uploading,
        ]);

        $this->reset(['csvFile', 'uploading', 'uploadProgress', 'periodAnalysis', 'showPeriodConfirmation', 'processingConfirmation', 'uploadSuccess']);
        $this->resetErrorBag();

        logger()->info('✅ Upload state reset after cancellation');
    }

    /**
     * User confirmed they want to proceed with the upload after seeing period analysis
     */
    public function confirmUpload(): void
    {
        logger()->emergency('🚨 CONFIRM UPLOAD METHOD CALLED');
        logger()->info('✅ User confirmed upload after period analysis', [
            'user_id' => auth()->id(),
            'periods' => $this->periodAnalysis['periods'] ?? [],
            'required_credits' => $this->periodAnalysis['required_credits'] ?? 0,
            'csv_file_present' => !is_null($this->csvFile),
            'period_analysis_valid' => $this->periodAnalysis['is_valid'] ?? false,
        ]);

        $this->processingConfirmation = true;
        logger()->info('🔄 Processing confirmation set to true');

        try {
            $this->showPeriodConfirmation = false;
            logger()->info('🔄 Modal closed, calling processUpload with confirmation');

            $this->processUpload(true); // Pass true to indicate this is a confirmed upload

            logger()->info('✅ processUpload completed successfully');
        } catch (\Exception $e) {
            logger()->emergency('🚨 EXCEPTION IN CONFIRM UPLOAD: ' . $e->getMessage());
            logger()->error('❌ Error during confirmed upload', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            // Reset processing state
            $this->processingConfirmation = false;
            $this->uploadSuccess = false;
            $this->showPeriodConfirmation = true; // Show modal again

            // Dispatch error event
            $this->dispatch('upload-error', [
                'message' => 'Error durante el procesamiento: ' . $e->getMessage()
            ]);

            logger()->info('🔄 Processing confirmation reset due to error');
        }
    }

    /**
     * User cancelled the upload after seeing period analysis
     */
    public function cancelConfirmation(): void
    {
        logger()->info('🚫 User cancelled upload after seeing period analysis', [
            'user_id' => auth()->id(),
            'periods' => $this->periodAnalysis['periods'] ?? [],
        ]);

        $this->showPeriodConfirmation = false;
        $this->processingConfirmation = false;
        $this->uploadSuccess = false;
        $this->reset(['csvFile', 'periodAnalysis']);
        $this->resetErrorBag();

        // Dispatch event to clear file from frontend
        $this->dispatch('file-cancelled');
    }



    public function render()
    {
        logger()->info('🎨 UploadCsv component render method called', [
            'user_id' => auth()->id(),
            'timestamp' => now()->toISOString(),
            'has_csv_file' => !is_null($this->csvFile),
            'uploading' => $this->uploading,
            'user_credits' => $this->userCredits,
        ]);

        $result = view('livewire.uploads.upload-csv')->layout('layouts.panel');

        logger()->info('✅ UploadCsv component render completed', [
            'user_id' => auth()->id(),
        ]);

        return $result;
    }

    /**
     * Get localized error message for limit violations
     */
    private function getLocalizedLimitError(array $validationResult, int $lineCount): string
    {
        $limit = $validationResult['limit'];
        $isCustomLimit = $validationResult['is_custom_limit'] ?? false;

        if (auth()->user()) {
            // Authenticated user message
            return $isCustomLimit
                ? "El archivo tiene {$lineCount} líneas, pero su límite actual es de {$limit} líneas."
                : "El archivo tiene {$lineCount} líneas, pero el plan gratuito está limitado a {$limit} líneas. Considere actualizar su cuenta o reducir el tamaño del archivo.";
        } else {
            // Anonymous user message
            return "El archivo tiene {$lineCount} líneas, pero los usuarios anónimos están limitados a {$limit} líneas. Regístrese para obtener más funciones.";
        }
    }
}
