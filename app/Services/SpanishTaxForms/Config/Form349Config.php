<?php

declare(strict_types=1);

namespace App\Services\SpanishTaxForms\Config;

class Form349Config extends SpanishTaxConfig
{
    public function __construct(
        SpanishTaxConfig $baseConfig,
        public readonly string $exerciseYear,
        public readonly string $period,
        public readonly bool $includeCorrections = false,
        public readonly int $maxRecordsPerPage = 28
    ) {
        parent::__construct(
            $baseConfig->declarantNif,
            $baseConfig->companyName,
            $baseConfig->addressLine1,
            $baseConfig->addressLine2,
            $baseConfig->postalCode,
            $baseConfig->city,
            $baseConfig->province,
            $baseConfig->iossNumber,
            $baseConfig->defaultPeriodType,
            $baseConfig->validateAgainstOfficialSamples
        );
    }
}





