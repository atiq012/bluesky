<?php

namespace App\Http\Controllers\Admin\Policy;

use App\Models\Policy\Policy;
use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;

// b2b never authors policy points — only reads them (e.g. ticket terms shown on the receipt)
class PolicyController extends BaseController
{
    public function edit(Request $request)
    {
        $rows = Policy::where('type', $request->type)
            ->orderBy('sort_order')
            ->get(['id', 'point', 'status']);

        $result = $rows->map(fn ($row) => [
            'id'     => $this->encodeHashid((int) $row->id),
            'point'  => $row->point,
            'status' => (int) $row->status,
        ]);

        return response()->json($result);
    }
}
