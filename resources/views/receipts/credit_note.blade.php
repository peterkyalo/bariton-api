<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Credit Note - {{ $creditNote->credit_note_number }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1a202c;
            margin: 0;
            padding: 30px;
            font-size: 13px;
            line-height: 1.5;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #ef4444;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        .brand-title {
            font-size: 24px;
            font-weight: bold;
            color: #0a0e1a;
            margin: 0;
            letter-spacing: -0.5px;
        }
        .brand-subtitle {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #ef4444;
            margin-top: 4px;
        }
        .credit-badge {
            background-color: #0a0e1a;
            color: #ef4444;
            padding: 8px 16px;
            border-radius: 6px;
            text-align: right;
            font-weight: bold;
            font-size: 16px;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 25px;
        }
        .meta-col {
            width: 50%;
            vertical-align: top;
        }
        .meta-title {
            font-size: 11px;
            text-transform: uppercase;
            color: #718096;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        .items-table th {
            background-color: #f7fafc;
            color: #4a5568;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 12px;
            border-bottom: 2px solid #e2e8f0;
            text-align: left;
        }
        .items-table td {
            padding: 12px;
            border-bottom: 1px solid #edf2f7;
        }
        .totals-table {
            width: 45%;
            margin-left: auto;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .totals-table td {
            padding: 6px 12px;
        }
        .total-amount {
            font-size: 16px;
            font-weight: bold;
            color: #ef4444;
            border-top: 2px solid #e2e8f0;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            font-size: 11px;
            color: #a0aec0;
            text-align: center;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td>
                <div class="brand-title">BARITON MUSIC ACADEMY</div>
                <div class="brand-subtitle">Official Credit Note & Refund Adjustment</div>
            </td>
            <td style="text-align: right;">
                <div class="credit-badge">{{ $creditNote->credit_note_number }}</div>
                <div style="font-size: 11px; color: #718096; margin-top: 5px;">
                    Date: {{ $creditNote->issued_at->format('d M Y, H:i') }}
                </div>
            </td>
        </tr>
    </table>

    <table class="meta-table">
        <tr>
            <td class="meta-col">
                <div class="meta-title">Refunded To:</div>
                <div style="font-weight: bold; font-size: 14px;">{{ $creditNote->user->name ?? 'Student' }}</div>
                <div>{{ $creditNote->user->email ?? '' }}</div>
                <div>{{ $creditNote->user->phone ?? '' }}</div>
            </td>
            <td class="meta-col" style="text-align: right;">
                <div class="meta-title">Original Order Information:</div>
                <div><strong>Order Number:</strong> {{ $creditNote->order->order_number ?? '' }}</div>
                <div><strong>Refund Reason:</strong> {{ $creditNote->refundRequest->reason ?? 'Student refund request' }}</div>
                <div><strong>Refund Reference:</strong> {{ $creditNote->refundRequest->refund_reference ?? '-' }}</div>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th>Description</th>
                <th style="text-align: right;">Amount ({{ $creditNote->currency }})</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <div style="font-weight: bold;">
                        {{ $creditNote->refundRequest->orderItem->title ?? 'Full Course Order Refund' }}
                    </div>
                    <div style="font-size: 11px; color: #718096;">
                        Approved by Finance Management. Access to refunded item revoked.
                    </div>
                </td>
                <td style="text-align: right; font-weight: bold;">
                    - {{ number_format($creditNote->amount_kes / 100, 2) }}
                </td>
            </tr>
        </tbody>
    </table>

    <table class="totals-table">
        @if($creditNote->vat_kes > 0)
        <tr>
            <td style="color: #718096;">VAT Credit Adjustment:</td>
            <td style="text-align: right;">- KES {{ number_format($creditNote->vat_kes / 100, 2) }}</td>
        </tr>
        @endif
        <tr>
            <td class="total-amount">Total Refunded:</td>
            <td class="total-amount" style="text-align: right;">
                - {{ $creditNote->currency }} {{ number_format($creditNote->amount_kes / 100, 2) }}
            </td>
        </tr>
    </table>

    <div class="footer">
        Bariton Music Academy Ltd • Karen Campus & Westlands Center, Nairobi, Kenya<br/>
        Registered under Kenya Data Protection Act 2019 • Questions? finance@bmas.com
    </div>
</body>
</html>
