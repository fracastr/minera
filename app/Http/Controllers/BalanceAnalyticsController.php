<?php

namespace App\Http\Controllers;

use App\Services\BalanceAnalyticsService;
use Illuminate\Http\Request;

class BalanceAnalyticsController extends Controller
{
    private BalanceAnalyticsService $analytics;

    public function __construct(BalanceAnalyticsService $analytics)
    {
        $this->analytics = $analytics;
    }

    public function summary(Request $request)
    {
        $filters = $this->analytics->parseFilters($request->all());

        return response()->json([
            'summary' => $this->analytics->summary($filters),
            'filters' => $filters,
        ]);
    }

    public function byUser(Request $request)
    {
        $filters = $this->analytics->parseFilters($request->all());

        return response()->json([
            'data' => $this->analytics->byUser($filters),
            'filters' => $filters,
        ]);
    }

    public function byValle(Request $request)
    {
        $filters = $this->analytics->parseFilters($request->all());

        return response()->json([
            'data' => $this->analytics->byValle($filters),
            'filters' => $filters,
        ]);
    }

    public function byProceso(Request $request)
    {
        $filters = $this->analytics->parseFilters($request->all());

        return response()->json([
            'data' => $this->analytics->byProceso($filters),
            'filters' => $filters,
        ]);
    }

    public function timeseries(Request $request)
    {
        $filters = $this->analytics->parseFilters($request->all());

        return response()->json([
            'data' => $this->analytics->timeseries($filters),
            'filters' => $filters,
        ]);
    }

    public function filterOptions(Request $request)
    {
        return response()->json($this->analytics->filterOptions($request->boolean('refresh')));
    }

    public function dashboard(Request $request)
    {
        $filters = $this->analytics->parseFilters($request->all());
        $fresh = $request->boolean('refresh');

        return response()->json($this->analytics->dashboard($filters, $fresh));
    }
}
