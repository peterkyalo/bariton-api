<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecordCashPaymentRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\ReceiptResource;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderFulfillmentService;
use App\Services\Payments\CashPaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class FinancePaymentController extends Controller
{
    public function __construct(
        protected CashPaymentGateway $cashGateway,
        protected OrderFulfillmentService $fulfillmentService
    ) {}

    /**
     * Search orders pending cash payment.
     */
    public function orders(Request $request): AnonymousResourceCollection
    {
        $this->authorizeFinance();

        $query = Order::query()
            ->with(['user', 'items'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->input('search').'%';
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', $search)
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', $search)
                            ->orWhere('email', 'like', $search)
                            ->orWhere('phone', 'like', $search);
                    });
            });
        }

        $orders = $query->paginate($request->input('per_page', 15));

        return OrderResource::collection($orders);
    }

    /**
     * Record school office cash payment taken by finance staff.
     */
    public function recordCash(RecordCashPaymentRequest $request): JsonResponse
    {
        $this->authorizeFinance();

        $order = Order::with('items')->findOrFail($request->input('order_id'));

        if ($order->isPaid()) {
            return response()->json(['message' => 'This order has already been marked as paid.'], 400);
        }

        $amountKes = (int) $request->input('amount_kes');
        $payerName = $request->input('payer_name');
        $reference = $request->input('reference') ?: 'CSH-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'gateway' => 'cash',
            'amount_kes' => $amountKes,
            'status' => 'pending',
            'reference' => $reference,
            'payer_name' => $payerName,
            'recorded_by' => $request->user()->id,
            'payload' => [
                'recorded_by_name' => $request->user()->name,
                'notes' => $request->input('notes'),
                'payment_date' => $request->input('payment_date') ?: now()->toDateString(),
            ],
        ]);

        // Fulfill order, grant access, and generate sequential receipt
        $this->fulfillmentService->fulfill($order, $payment);

        $order->refresh();
        $order->load(['successfulPayment', 'receipt', 'items']);

        return response()->json([
            'message' => 'Cash payment recorded and official receipt issued.',
            'order' => new OrderResource($order),
            'receipt' => new ReceiptResource($order->receipt),
        ]);
    }

    /**
     * All payments transaction ledger.
     */
    public function payments(Request $request): AnonymousResourceCollection
    {
        $this->authorizeFinance();

        $query = Payment::query()
            ->with(['order.items', 'order.user', 'recorder', 'receipt'])
            ->latest();

        if ($request->filled('gateway')) {
            $query->where('gateway', $request->input('gateway'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $payments = $query->paginate($request->input('per_page', 20));

        return PaymentResource::collection($payments);
    }

    protected function authorizeFinance(): void
    {
        if (! request()->user()?->hasAnyRole(['admin', 'finance_manager', 'finance_staff'])) {
            abort(403, 'Unauthorized access to finance operations.');
        }
    }
}
