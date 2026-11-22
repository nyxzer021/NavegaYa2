<?php

namespace App\Services;

use App\Contracts\ElectronicInvoicing;

class NullElectronicInvoicing implements ElectronicInvoicing
{
    public function issue(array $document): array
    {
        return [
            'issued' => false,
            'message' => __('receipts.no_provider'),
        ];
    }
}
