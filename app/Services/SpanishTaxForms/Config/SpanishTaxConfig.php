<?php

declare(strict_types=1);

namespace App\Services\SpanishTaxForms\Config;

use App\Exceptions\SpanishTaxForms\InvalidConfigurationException;

class SpanishTaxConfig
{
    public function __construct(
        public readonly string $declarantNif,
        public readonly string $companyName,
        public readonly string $addressLine1,
        public readonly ?string $addressLine2,
        public readonly string $postalCode,
        public readonly string $city,
        public readonly string $province,
        public readonly ?string $iossNumber = null,
        public readonly string $defaultPeriodType = 'quarterly',
        public readonly bool $validateAgainstOfficialSamples = true
    ) {
        $this->validate();
    }
    
    private function validate(): void
    {
        if (!$this->isValidNif($this->declarantNif)) {
            throw new InvalidConfigurationException('Invalid Spanish NIF format: ' . $this->declarantNif);
        }
        
        if (empty($this->companyName)) {
            throw new InvalidConfigurationException('Company name is required');
        }
        
        if (empty($this->addressLine1)) {
            throw new InvalidConfigurationException('Address line 1 is required');
        }
        
        if (empty($this->postalCode)) {
            throw new InvalidConfigurationException('Postal code is required');
        }
        
        if (empty($this->city)) {
            throw new InvalidConfigurationException('City is required');
        }
        
        if (empty($this->province)) {
            throw new InvalidConfigurationException('Province is required');
        }
        
        if (!in_array($this->defaultPeriodType, ['quarterly', 'monthly'])) {
            throw new InvalidConfigurationException('Invalid period type: ' . $this->defaultPeriodType);
        }
        
        if ($this->iossNumber !== null && !$this->isValidIossNumber($this->iossNumber)) {
            throw new InvalidConfigurationException('Invalid IOSS number format: ' . $this->iossNumber);
        }
    }
    
    private function isValidNif(string $nif): bool
    {
        // Spanish NIF format validation (company or individual)
        return preg_match('/^[ABCDEFGHJNPQRSUVW]\d{8}$|^\d{8}[TRWAGMYFPDXBNJZSQVHLCKE]$/', $nif) === 1;
    }
    
    private function isValidIossNumber(string $iossNumber): bool
    {
        // IOSS number format: IM followed by 10 digits
        return preg_match('/^IM\d{10}$/', $iossNumber) === 1;
    }
}





