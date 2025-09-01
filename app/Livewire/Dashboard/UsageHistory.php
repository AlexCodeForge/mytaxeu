<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\UploadMetric;
use App\Services\UsageMeteringService;
use Livewire\Component;
use Livewire\WithPagination;

class UsageHistory extends Component
{
    use WithPagination;

    public string $startDate = '';
    public string $endDate = '';
    public string $statusFilter = '';
    public string $sortBy = 'created_at';
    public string $sortDirection = 'desc';
    public int $perPage = 15;

    protected array $queryString = [
        'startDate' => ['except' => ''],
        'endDate' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'sortBy' => ['except' => 'created_at'],
        'sortDirection' => ['except' => 'desc'],
        'page' => ['except' => 1],
    ];

    public function mount(): void
    {
        // Set default date range to last 30 days if not specified
        if (empty($this->startDate)) {
            $this->startDate = now()->subDays(30)->format('Y-m-d');
        }
        if (empty($this->endDate)) {
            $this->endDate = now()->format('Y-m-d');
        }
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->startDate = now()->subDays(30)->format('Y-m-d');
        $this->endDate = now()->format('Y-m-d');
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function sortBy(string $field, string $direction = null): void
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = $direction ?? 'asc';
        }

        $this->resetPage();
    }

    public function exportToCsv(): void
    {
        $user = auth()->user();
        $usageMeteringService = app(UsageMeteringService::class);

        $startDate = $this->startDate ? new \DateTime($this->startDate) : null;
        $endDate = $this->endDate ? new \DateTime($this->endDate) : null;

        $exportData = $usageMeteringService->exportUserUsageData($user, $startDate, $endDate);

        // Convert to CSV and trigger download
        $csvContent = $this->convertToCsv($exportData);
        $filename = 'historial_uso_' . now()->format('Y-m-d_H-i-s') . '.csv';

        $this->dispatch('download-ready', [
            'content' => $csvContent,
            'filename' => $filename,
        ]);
    }

    public function getMetricsProperty()
    {
        $user = auth()->user();

        $query = UploadMetric::where('user_id', $user->id)
            ->with('upload:id,original_name,transformed_path');

        // Apply date filtering
        if ($this->startDate) {
            $query->whereDate('created_at', '>=', $this->startDate);
        }

        if ($this->endDate) {
            $query->whereDate('created_at', '<=', $this->endDate);
        }

        // Apply status filtering
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        // Apply sorting
        $query->orderBy($this->sortBy, $this->sortDirection);

        return $query->paginate($this->perPage);
    }

    public function getStatusBadgeClass(string $status): string
    {
        return match ($status) {
            'completed' => 'bg-green-100 text-green-800',
            'failed' => 'bg-red-100 text-red-800',
            'processing' => 'bg-yellow-100 text-yellow-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    public function getStatusText(string $status): string
    {
        return match ($status) {
            'completed' => 'Completado',
            'failed' => 'Fallido',
            'processing' => 'Procesando',
            default => 'Desconocido',
        };
    }

    public function getSortIcon(string $field): string
    {
        if ($this->sortBy !== $field) {
            return '↕'; // Both directions
        }

        return $this->sortDirection === 'asc' ? '↑' : '↓';
    }

    private function convertToCsv(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');

        // Add CSV headers in Spanish
        $headers = [
            'Nombre del Archivo',
            'Líneas Procesadas',
            'Tamaño del Archivo (bytes)',
            'Tiempo de Procesamiento (segundos)',
            'Créditos Consumidos',
            'Estado',
            'Fecha de Creación',
        ];
        fputcsv($output, $headers);

        // Add data rows
        foreach ($data as $row) {
            $csvRow = [
                $row['file_name'],
                $row['line_count'],
                $row['file_size_bytes'],
                $row['processing_duration_seconds'],
                $row['credits_consumed'],
                $this->getStatusText($row['status']),
                $row['created_at'],
            ];
            fputcsv($output, $csvRow);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent;
    }

    public function render()
    {
        return view('livewire.dashboard.usage-history', [
            'metrics' => $this->metrics,
        ]);
    }
}
