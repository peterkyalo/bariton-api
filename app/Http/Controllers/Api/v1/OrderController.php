<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Course;
use App\Models\Module;
use App\Models\Order;
use App\Models\Receipt;
use App\Services\PricingService;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function __construct(
        protected PricingService $pricingService,
        protected SettingsService $settingsService
    ) {}

    /**
     * Create an order snapshotting VAT rate, exchange rate, and setting expiry.
     */
    public function store(CreateOrderRequest $request): JsonResponse
    {
        $user = $request->user();
        $itemType = $request->input('item_type');
        $itemId = (int) $request->input('item_id');

        $priceKes = 0;
        $title = '';
        $meta = [];

        if ($itemType === 'course_bundle') {
            $course = Course::with('modules')->findOrFail($itemId);
            $priceKes = $course->bundle_price_kes;
            $title = "Complete Course Bundle: {$course->title}";
            $meta = ['course_id' => $course->id, 'module_count' => $course->modules->count()];
        } elseif ($itemType === 'module') {
            $module = Module::with('course')->findOrFail($itemId);
            $priceKes = $module->price_kes;
            $title = "Module: {$module->title} ({$module->course->title})";
            $meta = ['course_id' => $module->course_id, 'module_id' => $module->id, 'level' => $module->level];
        } elseif ($itemType === 'bundle_upgrade') {
            $course = Course::with('modules')->findOrFail($itemId);
            $upgradeData = $this->pricingService->calculateUpgradePrice($course, $user);
            $priceKes = $upgradeData['upgrade_price']['kes'];
            $title = "Course Bundle Upgrade: {$course->title}";
            $meta = [
                'course_id' => $course->id,
                'total_paid_kes' => $upgradeData['total_paid_kes'],
                'owned_module_ids' => $upgradeData['owned_module_ids'],
            ];
        } elseif ($itemType === 'exam_retake_package') {
            $course = Course::findOrFail($itemId);
            $priceKes = (int) \App\Models\Setting::getValue('exam_retake_package_price_kes', 500000);
            $title = "Exam Retake Package (3 Additional Attempts): {$course->title}";
            $meta = ['course_id' => $course->id];
        }

        // Snapshot settings
        $vatRate = (float) $this->settingsService->get('vat_rate_percent', 0.00);
        $exchangeRate = (float) $this->settingsService->get('usd_exchange_rate', 130.00);
        $orderExpiryMinutes = (int) $this->settingsService->get('order_expiry_minutes', 30);

        // Price includes VAT: VAT amount = price * (rate / (100 + rate))
        $vatKes = $vatRate > 0 ? (int) round($priceKes * ($vatRate / (100 + $vatRate))) : 0;
        $totalUsdCents = $this->pricingService->convertKesToUsdCents($priceKes);

        $order = DB::transaction(function () use (
            $user, $priceKes, $vatKes, $vatRate, $exchangeRate, $totalUsdCents, $orderExpiryMinutes, $itemType, $itemId, $title, $meta
        ) {
            $order = Order::create([
                'user_id' => $user->id,
                'status' => 'pending',
                'total_kes' => $priceKes,
                'vat_kes' => $vatKes,
                'vat_rate_percent' => $vatRate,
                'exchange_rate_used' => $exchangeRate,
                'total_usd_cents' => $totalUsdCents,
                'currency' => 'KES',
                'expires_at' => now()->addMinutes($orderExpiryMinutes),
            ]);

            $order->items()->create([
                'item_type' => $itemType,
                'item_id' => $itemId,
                'title' => $title,
                'price_kes' => $priceKes,
                'vat_kes' => $vatKes,
                'meta' => $meta,
            ]);

            return $order;
        });

        $order->load(['items', 'user']);

        return response()->json([
            'message' => 'Order created successfully.',
            'order' => new OrderResource($order),
        ], 201);
    }

    /**
     * Show order details and payment status.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $order = Order::where('uuid', $uuid)
            ->with(['items', 'payments', 'successfulPayment', 'receipt'])
            ->firstOrFail();

        // Ensure user owns order or is finance/admin
        if ($order->user_id !== $request->user()->id && ! $request->user()->hasAnyRole(['admin', 'finance_manager', 'finance_staff'])) {
            abort(403);
        }

        return response()->json([
            'order' => new OrderResource($order),
        ]);
    }

    /**
     * Student's past and pending orders.
     */
    public function studentOrders(Request $request): AnonymousResourceCollection
    {
        $orders = $request->user()->orders()
            ->with(['items', 'successfulPayment', 'receipt'])
            ->latest()
            ->paginate($request->input('per_page', 10));

        return OrderResource::collection($orders);
    }

    /**
     * Download generated PDF receipt.
     */
    public function downloadReceipt(Request $request, string $uuid): Response
    {
        $receipt = Receipt::where('uuid', $uuid)->with('order')->firstOrFail();

        if ($receipt->user_id !== $request->user()->id && ! $request->user()->hasAnyRole(['admin', 'finance_manager', 'finance_staff'])) {
            abort(403);
        }

        $disk = $receipt->pdf_disk ?: config('filesystems.private_disk', 'local');

        if (! $receipt->pdf_path || ! Storage::disk($disk)->exists($receipt->pdf_path)) {
            abort(404, 'Receipt PDF is not available.');
        }

        return Storage::disk($disk)->download(
            $receipt->pdf_path,
            "{$receipt->receipt_number}.pdf",
            ['Content-Type' => 'application/pdf']
        );
    }
}
