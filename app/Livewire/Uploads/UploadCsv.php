<?php

declare(strict_types=1);

namespace App\Livewire\Uploads;

use App\Models\Upload;
use App\Services\CreditService;
use App\Services\CsvLineCountService;
use App\Services\UploadLimitValidator;
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
        'csvFile' => 'required|file|mimes:csv|max:10240', // 10MB max
    ];

    protected array $messages = [
        'csvFile.required' => 'Por favor seleccione un archivo CSV.',
        'csvFile.file' => 'El archivo debe ser un archivo válido.',
        'csvFile.mimes' => 'El archivo debe ser un CSV (.csv).',
        'csvFile.max' => 'El archivo no puede ser mayor a 10MB.',
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
        logger()->info('🔄 CSV file updated in Livewire', [
            'user_id' => auth()->id(),
            'file_present' => !is_null($this->csvFile),
            'file_name' => $this->csvFile?->getClientOriginalName(),
            'file_size' => $this->csvFile?->getSize(),
            'file_mime' => $this->csvFile?->getMimeType(),
        ]);

        $this->validateOnly('csvFile');

        logger()->info('✅ CSV file validation completed', [
            'user_id' => auth()->id(),
            'errors' => $this->getErrorBag()->toArray(),
        ]);
    }

    public function processUpload(): void
    {
        // Force emergency logs to ensure we see this
        \Log::emergency('🚨 UPLOAD METHOD CALLED - THIS IS CRITICAL!');
        error_log('UPLOAD METHOD CALLED - ERROR LOG');

        logger()->emergency('🚀 Upload method called - EMERGENCY LOG');
        logger()->info('🚀 Upload method called', [
            'user_id' => auth()->id(),
            'file_present' => !is_null($this->csvFile),
            'uploading_state' => $this->uploading,
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

        logger()->info('🔍 Starting validation');
        $this->validate();
        logger()->info('✅ Validation completed', [
            'errors' => $this->getErrorBag()->toArray(),
        ]);

        if (! $this->csvFile) {
            logger()->warning('❌ No CSV file present after validation');
            $errorMessage = 'No se ha seleccionado ningún archivo.';
            $this->dispatch('upload-error', [
                'message' => $errorMessage
            ]);
            $this->addError('csvFile', $errorMessage);
            return;
        }

        logger()->info('📋 File details before processing', [
            'original_name' => $this->csvFile->getClientOriginalName(),
            'size' => $this->csvFile->getSize(),
            'mime_type' => $this->csvFile->getMimeType(),
            'real_path' => $this->csvFile->getRealPath(),
        ]);

        // Check if user has enough credits
        logger()->info('💰 Checking user credits');
        $creditService = app(CreditService::class);
        $user = auth()->user();

        logger()->info('👤 User details', [
            'user_id' => $user->id,
            'current_credits' => $user->credits,
        ]);

        if (!$creditService->hasEnoughCredits($user, 1)) {
            logger()->warning('❌ Insufficient credits', [
                'user_id' => $user->id,
                'current_credits' => $user->credits,
                'required_credits' => 1,
            ]);

            $errorMessage = 'No tienes suficientes créditos para procesar este archivo. Necesitas al menos 1 crédito.';
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

                // Validate upload limits
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

            // Storage path pattern: uploads/{user_id}/{uuid}.csv
            $storagePath = 'uploads/' . auth()->id() . '/' . $filename;

            // Store file
            $path = $this->csvFile->storeAs(
                dirname($storagePath),
                basename($storagePath),
                'local'
            );

            $this->uploadProgress = 'Registrando en base de datos...';

            // Create upload record
            $upload = Upload::create([
                'user_id' => auth()->id(),
                'original_name' => $this->csvFile->getClientOriginalName(),
                'disk' => 'local',
                'path' => $path,
                'size_bytes' => $this->csvFile->getSize(),
                'csv_line_count' => $csvLineCount,
                'rows_count' => $rowsCount,
                'status' => Upload::STATUS_RECEIVED,
            ]);

            $this->uploadProgress = 'Completado!';

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
            $this->dispatch('upload-success', [
                'uploadId' => $upload->id,
                'fileName' => $upload->original_name,
                'message' => 'Archivo subido exitosamente. El procesamiento ha comenzado.'
            ]);

            // Update user credits display
            logger()->info('💰 Updating user credits display');
            $this->userCredits = auth()->user()->fresh()->credits;

            logger()->info('📊 Updated credits', [
                'new_credits' => $this->userCredits,
            ]);

            // Reset form
            logger()->info('🔄 Resetting form state');
            $this->reset(['csvFile', 'uploading', 'uploadProgress']);

            logger()->info('✅ Upload process completed successfully', [
                'upload_id' => $upload->id,
                'file_name' => $upload->original_name,
                'line_count' => $csvLineCount,
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

        $this->reset(['csvFile', 'uploading', 'uploadProgress']);
        $this->resetErrorBag();

        logger()->info('✅ Upload state reset after cancellation');
    }

    public function debugTest(): void
    {
        logger()->emergency('🔥 DEBUG METHOD CALLED - LIVEWIRE IS WORKING!');
        $this->dispatch('flash-message', [
            'type' => 'success',
            'message' => 'DEBUG: Livewire connection working!'
        ]);
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
