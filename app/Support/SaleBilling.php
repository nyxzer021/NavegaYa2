<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class SaleBilling
{
    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'needs_invoice' => ['sometimes', 'boolean'],
            'buyer_ruc' => ['exclude_unless:needs_invoice,1,true', 'required', 'digits:11'],
            'buyer_legal_name' => ['exclude_unless:needs_invoice,1,true', 'required', 'string', 'max:160'],
            'buyer_address' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'buyer_ruc.required' => __('receipts.ruc_invalid'),
            'buyer_ruc.digits' => __('receipts.ruc_invalid'),
            'buyer_legal_name.required' => __('receipts.legal_name_required'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{needs_invoice: bool, buyer_document_type: ?string, buyer_document_number: ?string, buyer_legal_name: ?string, buyer_address: ?string}
     */
    public static function attributes(array $data): array
    {
        $invoice = filter_var($data['needs_invoice'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $address = trim((string) ($data['buyer_address'] ?? ''));
        if ($invoice) {
            $ruc = preg_replace('/\s+/', '', (string) ($data['buyer_ruc'] ?? '')) ?? '';
            $name = trim((string) ($data['buyer_legal_name'] ?? ''));
            if (preg_match('/^\d{11}$/', $ruc) !== 1) {
                throw ValidationException::withMessages(['buyer_ruc' => __('receipts.ruc_invalid')]);
            }
            if ($name === '') {
                throw ValidationException::withMessages(['buyer_legal_name' => __('receipts.legal_name_required')]);
            }

            return [
                'needs_invoice' => true,
                'buyer_document_type' => 'RUC',
                'buyer_document_number' => $ruc,
                'buyer_legal_name' => $name,
                'buyer_address' => $address !== '' ? $address : null,
            ];
        }

        $type = trim((string) ($data['document_type'] ?? ''));
        $number = trim((string) ($data['document_number'] ?? ''));

        return [
            'needs_invoice' => false,
            'buyer_document_type' => $type !== '' ? $type : null,
            'buyer_document_number' => $number !== '' ? $number : null,
            'buyer_legal_name' => null,
            'buyer_address' => $address !== '' ? $address : null,
        ];
    }
}
