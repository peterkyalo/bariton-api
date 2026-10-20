<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CreditNoteResource;
use App\Http\Resources\RefundRequestResource;
use App\Models\CreditNote;
use App\Models\Order;
use App\Models\RefundRequest;
use App\Services\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class RefundController extends Controller
{
    public function __construct(
        protected RefundService $refundService
    ) {}

    /**
     * Student submits a refund request for an item or whole order.
     */
    public function store(Request $request, string $orderUuid): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'order_item_id' => ['nullable', 'integer'],
        ]);

        $order = Order::where('uuid', $orderUuid)->firstOrFail();

        if ($order->user_id !== $request->user()->id) {
            abort(403, 'You can only request refunds on your own orders.');
        }

        try {
            $refund = $this->refundService->requestRefund(
                $order,
                $validated['order_item_id'] ?? null,
                $validated['reason'],
                $request->user()
            );

            $refund->load(['order.items', 'orderItem', 'user']);

            return response()->json([
                'message' => 'Your refund request has been submitted for finance review.',
                'refund_request' => new RefundRequestResource($refund),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Student lists their refund requests.
     */
    public function studentIndex(Request $request): AnonymousResourceCollection
    {
        $refunds = $request->user()
            ->refundRequests()
            ->with(['order.items', 'orderItem', 'creditNote', 'reviewer'])
            ->latest()
            ->paginate($request->input('per_page', 10));

        return RefundRequestResource::collection($refunds);
    }

    /**
     * Download official Credit Note PDF.
     */
    public function downloadCreditNote(Request $request, string $uuid): Response
    {
        $creditNote = CreditNote::where('uuid', $uuid)->with('order')->firstOrFail();

        if ($creditNote->user_id !== $request->user()->id && ! $request->user()->hasAnyRole(['admin', 'finance_manager', 'finance_staff'])) {
            abort(403);
        }

        $disk = $creditNote->pdf_disk ?: config('filesystems.private_disk', 'local');

        if (! $creditNote->pdf_path || ! Storage::disk($disk)->exists($creditNote->pdf_path)) {
            abort(404, 'Credit Note PDF is not available.');
        }

        return Storage::disk($disk)->download(
            $creditNote->pdf_path,
            "{$creditNote->credit_note_number}.pdf",
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * Finance Manager lists pending and processed refund requests.
     */
    public function financeIndex(Request $request): AnonymousResourceCollection
    {
        $this->authorizeFinanceManager();

        $query = RefundRequest::query()
            ->with(['order.items', 'orderItem', 'user', 'creditNote', 'reviewer'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $refunds = $query->paginate($request->input('per_page', 15));

        return RefundRequestResource::collection($refunds);
    }

    /**
     * Finance Manager reviews (approves or rejects) refund request.
     */
    public function financeReview(Request $request, string $uuid): JsonResponse
    {
        $this->authorizeFinanceManager();

        $validated = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'manual_reference' => ['nullable', 'string', 'max:255'],
        ]);

        $refund = RefundRequest::where('uuid', $uuid)
            ->with(['order.items', 'orderItem', 'user'])
            ->firstOrFail();

        if (! $refund->isPending()) {
            return response()->json(['message' => 'This refund request has already been reviewed.'], 400);
        }

        if ($validated['action'] === 'approve') {
            try {
                $creditNote = $this->refundService->approve(
                    $refund,
                    $request->user(),
                    $validated['manual_reference'] ?? null
                );

                $refund->refresh();
                $refund->load(['order', 'orderItem', 'creditNote', 'reviewer']);

                return response()->json([
                    'message' => 'Refund approved, access revoked, and Credit Note issued.',
                    'refund_request' => new RefundRequestResource($refund),
                    'credit_note' => new CreditNoteResource($creditNote),
                ]);
            } catch (\Exception $e) {
                return response()->json(['message' => 'Failed to process refund: '.$e->getMessage()], 422);
            }
        }

        // Action is reject
        $this->refundService->reject($refund, $request->user(), $validated['notes'] ?? 'Rejected by finance management.');

        $refund->refresh();
        $refund->load(['order', 'orderItem', 'reviewer']);

        return response()->json([
            'message' => 'Refund request rejected.',
            'refund_request' => new RefundRequestResource($refund),
        ]);
    }

    /**
     * Finance Credit Notes ledger.
     */
    public function financeCreditNotes(Request $request): AnonymousResourceCollection
    {
        $this->authorizeFinanceManager();

        $creditNotes = CreditNote::with(['order', 'user', 'refundRequest.orderItem'])
            ->latest()
            ->paginate($request->input('per_page', 15));

        return CreditNoteResource::collection($creditNotes);
    }

    protected function authorizeFinanceManager(): void
    {
        $user = request()->user();
        if (! $user || ! $user->hasAnyRole(['admin', 'finance_manager'])) {
            abort(403, 'Only finance managers or administrators can manage refunds.');
        }
    }
}
