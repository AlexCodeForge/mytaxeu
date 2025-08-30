<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\CsvLineCountService;
use App\Services\UploadLimitValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CsvUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by policies/middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'csvFile' => [
                'required',
                'file',
                'mimes:csv,txt',
                'max:10240', // 10MB max
            ],
        ];
    }

    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return [
            'csvFile.required' => __('csv_upload.file_required'),
            'csvFile.file' => __('csv_upload.file_invalid'),
            'csvFile.mimes' => __('csv_upload.file_wrong_type'),
            'csvFile.max' => __('csv_upload.file_too_large'),
        ];
    }

    /**
     * Configure the validator instance
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            if ($this->hasFile('csvFile') && $this->file('csvFile')->isValid()) {
                $this->validateCsvLineLimit($validator);
            }
        });
    }

    /**
     * Validate CSV line count against user/IP limits
     */
    private function validateCsvLineLimit(Validator $validator): void
    {
        try {
            $csvService = app(CsvLineCountService::class);
            $limitValidator = app(UploadLimitValidator::class);

            // Analyze the CSV file
            $analysisResult = $csvService->analyzeFile($this->file('csvFile'));
            $lineCount = $analysisResult['line_count'];

            // Get IP address for validation
            $ipAddress = $limitValidator->getIpFromRequest($this);

            // Validate against limits
            $validationResult = $limitValidator->validateUpload(
                $this->user(),
                $lineCount,
                $ipAddress
            );

            if (!$validationResult['allowed']) {
                $validator->errors()->add(
                    'csvFile',
                    $this->getLocalizedLimitMessage($validationResult, $lineCount)
                );
            }

            // Store line count in request for later use
            $this->merge(['csv_line_count' => $lineCount]);

        } catch (\InvalidArgumentException $e) {
            $validator->errors()->add('csvFile', __('csv_upload.file_invalid_csv', ['error' => $e->getMessage()]));
        } catch (\Exception $e) {
            $validator->errors()->add('csvFile', __('csv_upload.validation_error'));
        }
    }

    /**
     * Get localized error message for limit violations
     */
    private function getLocalizedLimitMessage(array $validationResult, int $lineCount): string
    {
        $limit = $validationResult['limit'];
        $isCustomLimit = $validationResult['is_custom_limit'] ?? false;

        if ($this->user()) {
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

    /**
     * Get the CSV line count from the validated request
     */
    public function getCsvLineCount(): ?int
    {
        return $this->input('csv_line_count');
    }
}
