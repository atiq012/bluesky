<?php

namespace App\Http\Controllers;

use App\Traits\HasControllerHashids;
use Illuminate\Http\Request;

class BaseController extends Controller
{
    use HasControllerHashids;

    public function SuccessResponse($result, $message)
    {
        $res = [
            'status' => true,
            'message' => $message,
            'data' => $result,
        ];

        return response()->json($res, 200);
    }

    public function ErrorResponse($errorMessage, $data = [], $code = 404)
    {
        $res = [
            'status' => false,
            'message' => !empty($errorMessage) ?  $errorMessage : 'No Error Message',
            'data' => $data
        ];

        return response()->json($res, $code);
    }
}
