<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UploadMetric;
use App\Services\UsageMeteringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UsageController extends Controller
{
    public function __construct(
        private UsageMeteringService $usageMeteringService
    ) {
        $this->middleware('auth:sanctum');
    }

    /**
     * Get current usage statistics for the authenticated user.
     */
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();
        $statistics = $this->usageMeteringService->getUserUsageStatistics($user);

        return response()->json([
            'data' => [
                'current_month_usage' => $statistics['current_month_usage'],
                'total_lines_processed' => $statistics['total_lines_processed'],
                'monthly_limit' => $this->usageMeteringService->getMonthlyLimit($user),
                'remaining_limit' => $statistics['remaining_monthly_limit'],
                'usage_percentage' => $this->calculateUsagePercentage($statistics),
                'total_uploads' => $statistics['total_uploads'],
                'successful_uploads' => $statistics['successful_uploads'],
                'failed_uploads' => $statistics['failed_uploads'],
                'average_processing_time' => $statistics['average_processing_time'],
                'total_credits_consumed' => $statistics['total_credits_consumed'],
                'average_file_size' => $statistics['average_file_size'],
            ]
        ]);
    }

    /**
     * Get paginated usage history for the authenticated user.
     */
    public function history(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'start_date' => 'date',
            'end_date' => 'date|after_or_equal:start_date',
            'status' => 'string|in:processing,completed,failed',
            'sort_by' => 'string|in:created_at,line_count,processing_duration_seconds,file_size_bytes',
            'sort_direction' => 'string|in:asc,desc',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid parameters',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $perPage = min((int) $request->get('per_page', 15), 100);

        $query = UploadMetric::where('user_id', $user->id)
            ->with('upload:id,original_name,transformed_path');

        // Apply date filtering
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->get('start_date'));
        }

        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->get('end_date'));
        }

        // Apply status filtering
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);

        $metrics = $query->paginate($perPage);

        // Transform the data
        $transformedData = $metrics->getCollection()->map(function ($metric) {
            return [
                'id' => $metric->id,
                'file_name' => $metric->file_name,
                'line_count' => $metric->line_count,
                'file_size_bytes' => $metric->file_size_bytes,
                'processing_duration_seconds' => $metric->processing_duration_seconds,
                'credits_consumed' => $metric->credits_consumed,
                'status' => $metric->status,
                'created_at' => $metric->created_at,
                'processing_started_at' => $metric->processing_started_at,
                'processing_completed_at' => $metric->processing_completed_at,
                'error_message' => $metric->error_message,
                'formatted_file_size' => $metric->formatted_size,
                'has_download' => $metric->upload && !empty($metric->upload->transformed_path),
            ];
        });

        return response()->json([
            'data' => $transformedData,
            'meta' => [
                'current_page' => $metrics->currentPage(),
                'last_page' => $metrics->lastPage(),
                'per_page' => $metrics->perPage(),
                'total' => $metrics->total(),
                'from' => $metrics->firstItem(),
                'to' => $metrics->lastItem(),
            ]
        ]);
    }

    /**
     * Get usage trends for the authenticated user.
     */
    public function trends(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'days' => 'integer|min:1|max:365',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid parameters',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $days = (int) $request->get('days', 30);

        $trends = $this->usageMeteringService->getUsageTrends($user, $days);

        return response()->json([
            'data' => $trends
        ]);
    }

    /**
     * Export user usage data as CSV.
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

        $user = $request->user();
        $startDate = $request->has('start_date') ? new \DateTime($request->get('start_date')) : null;
        $endDate = $request->has('end_date') ? new \DateTime($request->get('end_date')) : null;

        $exportData = $this->usageMeteringService->exportUserUsageData($user, $startDate, $endDate);

        // Convert to CSV format
        $csvData = $this->convertToCsv($exportData);

        return response()->json([
            'data' => [
                'csv_content' => $csvData,
                'filename' => 'usage_export_' . now()->format('Y-m-d_H-i-s') . '.csv',
                'total_records' => count($exportData),
            ]
        ]);
    }

    /**
     * Calculate usage percentage.
     */
    private function calculateUsagePercentage(array $statistics): float
    {
        $monthlyLimit = $this->usageMeteringService->getMonthlyLimit(auth()->user());

        if ($monthlyLimit <= 0) {
            return 0.0;
        }

        return round(($statistics['current_month_usage'] / $monthlyLimit) * 100, 1);
    }

    /**
     * Convert array data to CSV format.
     */
    private function convertToCsv(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');

        // Add CSV headers
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
                $row['status'],
                $row['created_at'],
            ];
            fputcsv($output, $csvRow);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent;
    }
}
