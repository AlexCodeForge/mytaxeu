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
        $this->userCredits = auth()->user()?->credits ?? 0;
    }

    protected array $rules = [
        'csvFile' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
    ];

    protected array $messages = [
        'csvFile.required' => 'Por favor seleccione un archivo CSV.',
        'csvFile.file' => 'El archivo debe ser un archivo válido.',
        'csvFile.mimes' => 'El archivo debe ser un CSV (.csv o .txt).',
        'csvFile.max' => 'El archivo no puede ser mayor a 10MB.',
    ];

    public function updatedCsvFile(): void
    {
        $this->validateOnly('csvFile');
    }

    public function upload(): void
    {
        $this->authorize('create', Upload::class);

        $this->validate();

        if (! $this->csvFile) {
            $this->addError('csvFile', 'No se ha seleccionado ningún archivo.');
            return;
        }

        // Check if user has enough credits
        $creditService = app(CreditService::class);
        $user = auth()->user();

        if (!$creditService->hasEnoughCredits($user, 1)) {
            $this->addError('csvFile', 'No tienes suficientes créditos para procesar este archivo. Necesitas al menos 1 crédito.');
            return;
        }

        try {
            $this->uploading = true;
            $this->uploadProgress = 'Validando archivo...';

            // Initialize services
            $csvService = app(CsvLineCountService::class);
            $limitValidator = app(UploadLimitValidator::class);

            // Validate file content and count lines accurately
            try {
                $analysisResult = $csvService->analyzeFile($this->csvFile);
                $csvLineCount = $analysisResult['line_count'];
                
                // Check if file is empty
                if ($csvLineCount === 0) {
                    $this->addError('csvFile', __('csv_upload.file_invalid_csv', ['error' => 'archivo vacío']));
                    return;
                }

                // Validate upload limits
                $ipAddress = request()->ip();
                $validationResult = $limitValidator->validateUpload(auth()->user(), $csvLineCount, $ipAddress);
                
                if (!$validationResult['allowed']) {
                    $errorMessage = $this->getLocalizedLimitError($validationResult, $csvLineCount);
                    $this->addError('csvFile', $errorMessage);
                    return;
                }
                
            } catch (\InvalidArgumentException $e) {
                $this->addError('csvFile', __('csv_upload.file_invalid_csv', ['error' => $e->getMessage()]));
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
            \App\Jobs\ProcessUploadJob::dispatch($upload->id);

            // Update status to queued
            $upload->update(['status' => Upload::STATUS_QUEUED]);

            // Dispatch events
            $this->dispatch('upload-created', uploadId: $upload->id);
            $this->dispatch('flash-message', [
                'type' => 'success',
                'message' => __('csv_upload.upload_success')
            ]);

            // Update user credits display
            $this->userCredits = auth()->user()->fresh()->credits;

            // Reset form
            $this->reset(['csvFile', 'uploading', 'uploadProgress']);

        } catch (\Exception $e) {
            $this->uploading = false;
            $this->uploadProgress = '';

            logger()->error('Upload failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addError('csvFile', 'Error al subir el archivo: ' . $e->getMessage());
        }
    }

    public function cancelUpload(): void
    {
        $this->reset(['csvFile', 'uploading', 'uploadProgress']);
        $this->resetErrorBag();
    }

    public function mount(): void
    {
        $this->userCredits = auth()->user()?->credits ?? 0;
    }

    public function render()
    {
        return view('livewire.uploads.upload-csv')->layout('layouts.panel');
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
                ? __('csv_upload.custom_limit_exceeded', [
                    'lines' => $lineCount,
                    'limit' => $limit
                ])
                : __('csv_upload.free_tier_limit_exceeded', [
                    'lines' => $lineCount,
                    'limit' => $limit
                ]);
        } else {
            // Anonymous user message
            return __('csv_upload.anonymous_limit_exceeded', [
                'lines' => $lineCount,
                'limit' => $limit
            ]);
        }
    }
}
