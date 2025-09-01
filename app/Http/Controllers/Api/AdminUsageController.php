<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UploadMetric;
use App\Models\User;
use App\Services\UsageMeteringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class AdminUsageController extends Controller
{
    public function __construct(
        private UsageMeteringService $usageMeteringService
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware(function ($request, $next) {
            Gate::authorize('manage-users');
            return $next($request);
        });
    }

    /**
     * Get system-wide usage overview.
     */
    public function overview(Request $request): JsonResponse
    {
        $systemStats = $this->usageMeteringService->getSystemUsageStatistics();

        return response()->json([
            'data' => [
                'total_users' => $systemStats['total_users'],
                'active_users' => $systemStats['active_users_count'],
                'total_uploads' => $systemStats['total_uploads'],
                'total_lines_processed' => $systemStats['total_lines_processed'],
                'total_processing_time' => $systemStats['total_processing_time_seconds'],
                'total_credits_consumed' => $systemStats['total_credits_consumed'],
                'average_file_size' => $systemStats['average_file_size_mb'] * 1024 * 1024,
                'success_rate' => $this->calculateSystemSuccessRate(),
            ]
        ]);
    }

    /**
     * Export system usage data.
     */
    public function export(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'date',
            'end_date' => 'date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid parameters',
                'errors' => $validator->errors(),
            ], 422);
        }

        $exportData = $this->getExportData($request);
        $csvData = $this->convertToCsv($exportData);

        return response()->json([
            'data' => [
                'csv_content' => $csvData,
                'filename' => 'admin_usage_export_' . now()->format('Y-m-d_H-i-s') . '.csv',
                'total_records' => count($exportData),
            ]
        ]);
    }

    private function calculateSystemSuccessRate(): float
    {
        $total = UploadMetric::count();
        if ($total === 0) {
            return 0.0;
        }

        $successful = UploadMetric::where('status', UploadMetric::STATUS_COMPLETED)->count();
        return round(($successful / $total) * 100, 2);
    }

    private function getExportData(Request $request): array
    {
        $query = UploadMetric::with('user:id,name,email');

        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->get('start_date'));
        }

        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->get('end_date'));
        }

        return $query->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($metric) {
                return [
                    'usuario' => $metric->user->name,
                    'email' => $metric->user->email,
                    'archivo' => $metric->file_name,
                    'lineas_procesadas' => $metric->line_count,
                    'tamaño_archivo_bytes' => $metric->file_size_bytes,
                    'tiempo_procesamiento_segundos' => $metric->processing_duration_seconds,
                    'creditos_consumidos' => $metric->credits_consumed,
                    'estado' => $metric->status,
                    'fecha_creacion' => $metric->created_at->format('Y-m-d H:i:s'),
                ];
            })
            ->toArray();
    }

    private function convertToCsv(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');

        $headers = array_keys($data[0]);
        fputcsv($output, $headers);

        foreach ($data as $row) {
            fputcsv($output, array_values($row));
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent;
    }
}
