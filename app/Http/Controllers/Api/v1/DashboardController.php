<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\DashboardAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardAnalyticsService $analyticsService
    ) {}

    /**
     * Admin Dashboard Metrics
     */
    public function admin(Request $request): JsonResponse
    {
        $from = $request->query('from');
        $to = $request->query('to');

        $metrics = $this->analyticsService->getAdminMetrics($from, $to);

        return response()->json($metrics);
    }

    /**
     * Export Admin Metrics CSV
     */
    public function adminCsv(Request $request): StreamedResponse
    {
        $from = $request->query('from');
        $to = $request->query('to');

        $csv = $this->analyticsService->exportAdminCsv($from, $to);
        $filename = 'bmas-admin-report-'.now()->format('Y-m-d').'.csv';

        return response()->stream(function () use ($csv) {
            echo $csv;
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Finance Dashboard Metrics
     */
    public function finance(Request $request): JsonResponse
    {
        $from = $request->query('from');
        $to = $request->query('to');

        $metrics = $this->analyticsService->getFinanceMetrics($from, $to);

        return response()->json($metrics);
    }

    /**
     * Export Finance Ledger CSV
     */
    public function financeCsv(Request $request): StreamedResponse
    {
        $from = $request->query('from');
        $to = $request->query('to');

        $csv = $this->analyticsService->exportFinanceCsv($from, $to);
        $filename = 'bmas-finance-ledger-'.now()->format('Y-m-d').'.csv';

        return response()->stream(function () use ($csv) {
            echo $csv;
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Instructor Dashboard Metrics
     */
    public function instructor(Request $request): JsonResponse
    {
        $metrics = $this->analyticsService->getInstructorMetrics($request->user());

        return response()->json($metrics);
    }

    /**
     * Coordinator Dashboard Metrics
     */
    public function coordinator(): JsonResponse
    {
        $metrics = $this->analyticsService->getCoordinatorMetrics();

        return response()->json($metrics);
    }
}
