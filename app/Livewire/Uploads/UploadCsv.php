<?php

declare(strict_types=1);

namespace App\Livewire\Uploads;

use App\Models\Upload;
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

        try {
            $this->uploading = true;
            $this->uploadProgress = 'Validando archivo...';

            // Validate file content (basic CSV structure check)
            $content = file_get_contents($this->csvFile->getRealPath());
            if (empty($content)) {
                $this->addError('csvFile', 'El archivo está vacío.');
                return;
            }

            // Count rows for metadata
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
                'rows_count' => $rowsCount,
                'status' => Upload::STATUS_RECEIVED,
            ]);

            $this->uploadProgress = 'Completado!';

            // Dispatch the processing job
            \App\Jobs\ProcessUploadJob::dispatch($upload->id);

            // Update status to queued
            $upload->update(['status' => Upload::STATUS_QUEUED]);

            // Dispatch events
            $this->dispatch('upload-created', uploadId: $upload->id);
            $this->dispatch('flash-message', [
                'type' => 'success',
                'message' => "Archivo '{$upload->original_name}' subido correctamente. El procesamiento comenzará en breve."
            ]);

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

    public function render()
    {
        return view('livewire.uploads.upload-csv')->layout('layouts.panel');
    }
}
