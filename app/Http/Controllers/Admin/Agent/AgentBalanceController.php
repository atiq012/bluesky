<?php

namespace App\Http\Controllers\Admin\Agent;

use App\Http\Controllers\BaseController;
use App\Models\Agent\Agent;
use App\Services\AgentBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AgentBalanceController extends BaseController
{
    public function __construct(
        private readonly AgentBalanceService $balanceService
    ) {}

    public function balance()
    {
        $agent = $this->resolveAgent();
        if (!$agent) {
            return $this->ErrorResponse('Agent account not found.', [], 404);
        }

        return $this->SuccessResponse($this->balanceService->getBalances($agent->id), 'Balance loaded.');
    }

    public function statement(Request $request)
    {
        $agent = $this->resolveAgent();
        if (!$agent) {
            return $this->ErrorResponse('Agent account not found.', [], 404);
        }

        $rows = $this->balanceService->getStatement(
            $agent->id,
            $request->query('from'),
            $request->query('to'),
            (int) $request->query('per_page', 20)
        );

        return $this->SuccessResponse($rows, 'Statement loaded.');
    }

    private function resolveAgent(): ?Agent
    {
        return Agent::where('user_id', Auth::id())->first();
    }
}
