<?php

namespace App\Http\Controllers\Admin\Company;

use App\Http\Controllers\BaseController;
use App\Services\SearchV2\CompanyTravelAgencyResolver;
use Exception;

// b2b never authors companies — only reads the active one for the receipt letterhead
class CompanyController extends BaseController
{
    public function active(CompanyTravelAgencyResolver $resolver)
    {
        try {
            return $this->SuccessResponse($resolver->resolveReceiptAgencyPayload(), 'Active company loaded.');
        } catch (Exception $e) {
            return $this->ErrorResponse($e->getMessage());
        }
    }
}
