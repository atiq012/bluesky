<?php

namespace App\Services\SearchV2;

use Exception;
use App\Models\Company\Company;

class CompanyTravelAgencyResolver
{
    public function resolveActiveCompany(): Company
    {
        $company = Company::query()
            ->where('status', 1)
            ->orderBy('id')
            ->first();

        if (!$company) {
            throw new Exception('No active company configured. Add an active company in Settings.');
        }

        return $company;
    }

    public function resolveAgencyPayload(): array
    {
        $company = $this->resolveActiveCompany();

        return [
            'name'          => trim((string) ($company->display_name ?: $company->name)),
            'iata_number'   => trim((string) ($company->iata ?? '')),
            'phone'         => trim((string) ($company->phone ?? '')),
            'email'         => trim((string) ($company->email ?? '')),
            'address_line'  => trim((string) ($company->address ?? '')),
            'city'          => trim((string) ($company->city ?? '')),
            'country_code'  => strtoupper(trim((string) ($company->country_code ?: 'BD'))),
        ];
    }

    public function resolveReceiptAgencyPayload(): array
    {
        $company = $this->resolveActiveCompany();
        $addressParts = array_values(array_filter([
            trim((string) ($company->address ?? '')),
            trim((string) ($company->city ?? '')),
            trim((string) ($company->country_code ?? '')),
        ]));

        return [
            'name'    => trim((string) ($company->display_name ?: $company->name)),
            'address' => $addressParts ? implode(', ', $addressParts) : '—',
            'email'   => trim((string) ($company->email ?? '')) ?: '—',
            'phone'   => trim((string) ($company->phone ?? '')) ?: '—',
            'logo'    => $company->logo_path ? '/' . ltrim($company->logo_path, '/') : null,
        ];
    }
}
