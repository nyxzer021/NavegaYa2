<?php

namespace App\Contracts;

interface ElectronicInvoicing
{
    /**
     * La etapa siguiente puede emitir con un OSE o PSE. Hoy nadie emite.
     *
     * @param  array{number: string, amount: string, buyer_name: string, buyer_document: string}  $document
     * @return array{issued: bool, message: string}
     */
    public function issue(array $document): array;
}
