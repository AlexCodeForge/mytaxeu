<?php

declare(strict_types=1);

namespace App\Services\SpanishTaxForms\Config;

use App\Exceptions\SpanishTaxForms\InvalidConfigurationException;

class Form369Config extends SpanishTaxConfig
{
    public function __construct(
        SpanishTaxConfig $baseConfig,
        public readonly string $period,
        public readonly string $regime, // MOSS, VOES, IMPO
        public readonly bool $isQuarterly,
        public readonly string $paymentType = 'I', // I=A ingresar, N=Negativa
        public readonly bool $isComplementary = false
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
        
        $this->validateForm369();
    }
    
    private function validateForm369(): void
    {
        if (!in_array($this->regime, ['MOSS', 'VOES', 'IMPO'])) {
            throw new InvalidConfigurationException('Invalid regime for Form 369: ' . $this->regime);
        }
        
        if ($this->regime === 'IMPO' && empty($this->iossNumber)) {
            throw new InvalidConfigurationException('IOSS number required for IMPO regime');
        }
        
        if (!in_array($this->paymentType, ['O', 'S', 'I', 'N', 'T'])) {
            throw new InvalidConfigurationException('Invalid payment type: ' . $this->paymentType);
        }
    }
}





