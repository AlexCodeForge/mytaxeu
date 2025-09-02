<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Upload;
use App\Jobs\ProcessUploadJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class BulkOperationsService
{
    private const BATCH_SIZE = 50;
    private const MAX_BULK_SIZE = 500;

    /**
     * Retry multiple failed uploads
     */
    public function retryUploads(array $uploadIds, int $adminUserId): array
    {
        if (!$this->validateAdminPermissions($adminUserId)) {
            return $this->errorResponse('User has insufficient permissions for bulk operations');
        }

        if (count($uploadIds) > self::MAX_BULK_SIZE) {
            return $this->errorResponse('Bulk operation size exceeds maximum limit of ' . self::MAX_BULK_SIZE);
        }

        $processedCount = 0;
        $skippedCount = 0;
        $failedCount = 0;
        $details = [];

        try {
            DB::beginTransaction();

            $uploads = Upload::whereIn('id', $uploadIds)->get();

            foreach ($uploads as $upload) {
                try {
                    if ($upload->status === Upload::STATUS_COMPLETED) {
                        $skippedCount++;
                        $details[] = "Upload {$upload->id} already completed";
                        continue;
                    }

                    if ($upload->status === Upload::STATUS_PROCESSING) {
                        $skippedCount++;
                        $details[] = "Upload {$upload->id} currently processing";
                        continue;
                    }

                    $upload->update([
                        'status' => Upload::STATUS_QUEUED,
                        'failure_reason' => null,
                    ]);

                    ProcessUploadJob::dispatch($upload);
                    $processedCount++;
                    $details[] = "Upload {$upload->id} queued for retry";
                } catch (\Exception $e) {
                    $failedCount++;
                    $details[] = "Failed to retry upload {$upload->id}: " . $e->getMessage();
                    Log::error("Bulk retry failed for upload {$upload->id}", [
                        'error' => $e->getMessage(),
                        'admin_user' => $adminUserId,
                    ]);
                }
            }

            // Handle non-existent upload IDs
            $foundIds = $uploads->pluck('id')->toArray();
            $notFoundIds = array_diff($uploadIds, $foundIds);
            $failedCount += count($notFoundIds);

            foreach ($notFoundIds as $notFoundId) {
                $details[] = "Upload {$notFoundId} not found";
            }

            $this->logBulkOperation('bulk_retry_uploads', $adminUserId, [
                'upload_ids' => $uploadIds,
                'processed' => $processedCount,
                'skipped' => $skippedCount,
                'failed' => $failedCount,
            ]);

            DB::commit();

            return $this->successResponse('Bulk retry operation completed', [
                'processed_count' => $processedCount,
                'skipped_count' => $skippedCount,
                'failed_count' => $failedCount,
                'details' => $details,
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Bulk retry operation failed', [
                'error' => $e->getMessage(),
                'admin_user' => $adminUserId,
                'upload_ids' => $uploadIds,
            ]);

            return $this->errorResponse('Bulk retry operation failed: ' . $e->getMessage());
        }
    }

    /**
     * Update status for multiple uploads
     */
    public function updateUploadStatus(array $uploadIds, string $newStatus, int $adminUserId): array
    {
        if (!$this->validateAdminPermissions($adminUserId)) {
            return $this->errorResponse('User has insufficient permissions for bulk operations');
        }

        if (!$this->isValidUploadStatus($newStatus)) {
            return $this->errorResponse('Invalid upload status: ' . $newStatus);
        }

        $processedCount = 0;
        $skippedCount = 0;
        $failedCount = 0;
        $details = [];

        try {
            DB::beginTransaction();

            $uploads = Upload::whereIn('id', $uploadIds)->get();

            foreach ($uploads as $upload) {
                try {
                    if (!$this->isValidStatusTransition($upload->status, $newStatus)) {
                        $skippedCount++;
                        $details[] = "Invalid status transition for upload {$upload->id}: {$upload->status} -> {$newStatus}";
                        continue;
                    }

                    $upload->update(['status' => $newStatus]);
                    $processedCount++;
                    $details[] = "Upload {$upload->id} status updated to {$newStatus}";
                } catch (\Exception $e) {
                    $failedCount++;
                    $details[] = "Failed to update upload {$upload->id}: " . $e->getMessage();
                }
            }

            $this->logBulkOperation('bulk_update_upload_status', $adminUserId, [
                'upload_ids' => $uploadIds,
                'new_status' => $newStatus,
                'processed' => $processedCount,
                'skipped' => $skippedCount,
                'failed' => $failedCount,
            ]);

            DB::commit();

            if ($processedCount === 0) {
                return $this->errorResponse('Invalid status transition for selected uploads');
            }

            return $this->successResponse('Bulk status update completed', [
                'processed_count' => $processedCount,
                'skipped_count' => $skippedCount,
                'failed_count' => $failedCount,
                'details' => $details,
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Bulk status update failed: ' . $e->getMessage());
        }
    }

    /**
     * Delete multiple uploads
     */
    public function deleteUploads(array $uploadIds, int $adminUserId): array
    {
        if (!$this->validateAdminPermissions($adminUserId)) {
            return $this->errorResponse('User has insufficient permissions for bulk operations');
        }

        $processedCount = 0;
        $skippedCount = 0;
        $failedCount = 0;
        $details = [];

        try {
            DB::beginTransaction();

            $uploads = Upload::whereIn('id', $uploadIds)->get();

            foreach ($uploads as $upload) {
                try {
                    if ($upload->status === Upload::STATUS_PROCESSING) {
                        $skippedCount++;
                        $details[] = "Cannot delete upload {$upload->id} while processing";
                        continue;
                    }

                    $upload->delete();
                    $processedCount++;
                    $details[] = "Upload {$upload->id} deleted successfully";
                } catch (\Exception $e) {
                    $failedCount++;
                    $details[] = "Failed to delete upload {$upload->id}: " . $e->getMessage();
                }
            }

            $this->logBulkOperation('bulk_delete_uploads', $adminUserId, [
                'upload_ids' => $uploadIds,
                'processed' => $processedCount,
                'skipped' => $skippedCount,
                'failed' => $failedCount,
            ]);

            DB::commit();

            return $this->successResponse('Bulk delete operation completed', [
                'processed_count' => $processedCount,
                'skipped_count' => $skippedCount,
                'failed_count' => $failedCount,
                'details' => $details,
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Bulk delete operation failed: ' . $e->getMessage());
        }
    }

    /**
     * Suspend multiple users
     */
    public function suspendUsers(array $userIds, int $adminUserId): array
    {
        if (!$this->validateAdminPermissions($adminUserId)) {
            return $this->errorResponse('User has insufficient permissions for bulk operations');
        }

        $processedCount = 0;
        $skippedCount = 0;
        $failedCount = 0;
        $details = [];

        try {
            DB::beginTransaction();

            $users = User::whereIn('id', $userIds)->get();

            foreach ($users as $user) {
                try {
                    if ($user->is_admin) {
                        $skippedCount++;
                        $details[] = "Cannot suspend admin user {$user->email}";
                        continue;
                    }

                    if ($user->id === $adminUserId) {
                        $skippedCount++;
                        $details[] = "Cannot suspend yourself";
                        continue;
                    }

                    if ($user->is_suspended) {
                        $skippedCount++;
                        $details[] = "User {$user->email} already suspended";
                        continue;
                    }

                    $user->update([
                        'is_suspended' => true,
                        'suspended_at' => now(),
                        'suspended_by' => $adminUserId,
                    ]);

                    $processedCount++;
                    $details[] = "User {$user->email} suspended successfully";
                } catch (\Exception $e) {
                    $failedCount++;
                    $details[] = "Failed to suspend user {$user->email}: " . $e->getMessage();
                }
            }

            $this->logBulkOperation('bulk_suspend_users', $adminUserId, [
                'user_ids' => $userIds,
                'processed' => $processedCount,
                'skipped' => $skippedCount,
                'failed' => $failedCount,
            ]);

            DB::commit();

            return $this->successResponse('Bulk user suspension completed', [
                'processed_count' => $processedCount,
                'skipped_count' => $skippedCount,
                'failed_count' => $failedCount,
                'details' => $details,
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Bulk suspension failed: ' . $e->getMessage());
        }
    }

    /**
     * Activate multiple users
     */
    public function activateUsers(array $userIds, int $adminUserId): array
    {
        if (!$this->validateAdminPermissions($adminUserId)) {
            return $this->errorResponse('User has insufficient permissions for bulk operations');
        }

        $processedCount = 0;
        $skippedCount = 0;
        $failedCount = 0;
        $details = [];

        try {
            DB::beginTransaction();

            $users = User::whereIn('id', $userIds)->get();

            foreach ($users as $user) {
                try {
                    if (!$user->is_suspended) {
                        $skippedCount++;
                        $details[] = "User {$user->email} is not suspended";
                        continue;
                    }

                    $user->update([
                        'is_suspended' => false,
                        'suspended_at' => null,
                        'suspended_by' => null,
                        'suspension_reason' => null,
                    ]);

                    $processedCount++;
                    $details[] = "User {$user->email} activated successfully";
                } catch (\Exception $e) {
                    $failedCount++;
                    $details[] = "Failed to activate user {$user->email}: " . $e->getMessage();
                }
            }

            $this->logBulkOperation('bulk_activate_users', $adminUserId, [
                'user_ids' => $userIds,
                'processed' => $processedCount,
                'skipped' => $skippedCount,
                'failed' => $failedCount,
            ]);

            DB::commit();

            return $this->successResponse('Bulk user activation completed', [
                'processed_count' => $processedCount,
                'skipped_count' => $skippedCount,
                'failed_count' => $failedCount,
                'details' => $details,
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Bulk activation failed: ' . $e->getMessage());
        }
    }

    /**
     * Generate a detailed report of bulk operation results
     */
    public function generateOperationReport(array $operationResult): string
    {
        $adminUser = User::find($operationResult['admin_user_id'] ?? null);
        $timestamp = now()->format('Y-m-d H:i:s');

        $report = "=== Bulk Operation Report ===\n";
        $report .= "Timestamp: {$timestamp}\n";
        $report .= "Administrator: " . ($adminUser ? $adminUser->name : 'Unknown') . "\n";
        $report .= "Operation: " . ($operationResult['operation'] ?? 'Unknown') . "\n\n";

        $report .= "Results Summary:\n";
        $report .= "- Processed: " . ($operationResult['processed_count'] ?? 0) . "\n";
        $report .= "- Skipped: " . ($operationResult['skipped_count'] ?? 0) . "\n";
        $report .= "- Failed: " . ($operationResult['failed_count'] ?? 0) . "\n\n";

        if (!empty($operationResult['details'])) {
            $report .= "Detailed Results:\n";
            foreach ($operationResult['details'] as $detail) {
                $report .= "- {$detail}\n";
            }
        }

        return $report;
    }

    /**
     * Validate admin permissions
     */
    private function validateAdminPermissions(int $userId): bool
    {
        $user = User::find($userId);
        return $user && $user->isAdmin();
    }

    /**
     * Check if upload status is valid
     */
    private function isValidUploadStatus(string $status): bool
    {
        $validStatuses = [
            Upload::STATUS_QUEUED,
            Upload::STATUS_PROCESSING,
            Upload::STATUS_COMPLETED,
            Upload::STATUS_FAILED,
            Upload::STATUS_CANCELLED,
        ];

        return in_array($status, $validStatuses);
    }

    /**
     * Check if status transition is valid
     */
    private function isValidStatusTransition(string $currentStatus, string $newStatus): bool
    {
        // Prevent invalid transitions
        $invalidTransitions = [
            Upload::STATUS_COMPLETED => [Upload::STATUS_PROCESSING, Upload::STATUS_QUEUED],
            Upload::STATUS_PROCESSING => [Upload::STATUS_QUEUED],
        ];

        if (isset($invalidTransitions[$currentStatus])) {
            return !in_array($newStatus, $invalidTransitions[$currentStatus]);
        }

        return true;
    }

    /**
     * Log bulk operation for audit trail
     */
    private function logBulkOperation(string $action, int $adminUserId, array $metadata): void
    {
        DB::table('admin_action_logs')->insert([
            'admin_user_id' => $adminUserId,
            'action' => $action,
            'metadata' => json_encode($metadata),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Return success response
     */
    private function successResponse(string $message, array $data = []): array
    {
        return array_merge([
            'success' => true,
            'message' => $message,
        ], $data);
    }

    /**
     * Return error response
     */
    private function errorResponse(string $message): array
    {
        return [
            'success' => false,
            'message' => $message,
            'processed_count' => 0,
            'skipped_count' => 0,
            'failed_count' => 0,
            'details' => [],
        ];
    }
}

