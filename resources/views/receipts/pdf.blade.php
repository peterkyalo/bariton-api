<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Receipt - {{ $receipt->receipt_number }}</title>
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
            border-bottom: 2px solid #d4af37;
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
            color: #d4af37;
            margin-top: 4px;
        }
        .receipt-badge {
            background-color: #0a0e1a;
            color: #d4af37;
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
            border-bottom: 2px solid #e2e8f0;
            color: #4a5568;
            font-size: 11px;
            text-transform: uppercase;
            padding: 10px 12px;
            text-align: left;
        }
        .items-table td {
            padding: 12px;
            border-bottom: 1px solid #edf2f7;
        }
        .totals-table {
            width: 100%;
            margin-top: 10px;
        }
        .totals-table td {
            padding: 6px 12px;
        }
        .total-row {
            font-size: 15px;
            font-weight: bold;
            color: #0a0e1a;
            border-top: 2px solid #e2e8f0;
            border-bottom: 2px solid #d4af37;
        }
        .footer {
            margin-top: 40px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            font-size: 11px;
            color: #a0aec0;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td>
                <div class="brand-title">BARITON MUSIC ACADEMY</div>
                <div class="brand-subtitle">Conservatory of Performing Arts</div>
                <div style="font-size: 11px; color: #718096; margin-top: 5px;">
                    Nairobi, Kenya • info@bmas.com • https://app.bmas.com
                </div>
            </td>
            <td align="right">
                <div class="receipt-badge">OFFICIAL RECEIPT</div>
                <div style="font-size: 12px; font-weight: bold; margin-top: 6px; color: #2d3748;">
                    {{ $receipt->receipt_number }}
                </div>
            </td>
        </tr>
    </table>

    <table class="meta-table">
        <tr>
            <td class="meta-col">
                <div class="meta-title">Received From:</div>
                <div style="font-weight: bold; font-size: 14px;">{{ $receipt->user->name }}</div>
                <div>{{ $receipt->user->email }}</div>
                @if($receipt->user->phone)
                    <div>{{ $receipt->user->phone }}</div>
                @endif
            </td>
            <td class="meta-col" align="right">
                <div class="meta-title">Payment Details:</div>
                <div><strong>Date Issued:</strong> {{ $receipt->issued_at->format('d M Y, H:i') }} EAT</div>
                <div><strong>Order Number:</strong> {{ $receipt->order->order_number }}</div>
                <div><strong>Payment Method:</strong> {{ strtoupper($receipt->payment->gateway) }}</div>
                <div><strong>Gateway Reference:</strong> {{ $receipt->payment->reference }}</div>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th>Description</th>
                <th width="15%" align="center">Type</th>
                <th width="20%" align="right">Amount (KES)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($receipt->order->items as $item)
                <tr>
                    <td>
                        <strong style="color: #2d3748;">{{ $item->title }}</strong>
                    </td>
                    <td align="center" style="font-size: 11px; text-transform: uppercase; color: #718096;">
                        {{ str_replace('_', ' ', $item->item_type) }}
                    </td>
                    <td align="right" style="font-weight: 500;">
                        {{ number_format($item->price_kes / 100, 2) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td width="70%" align="right"><strong>Subtotal:</strong></td>
            <td width="30%" align="right">KES {{ number_format(($receipt->amount_kes - $receipt->vat_kes) / 100, 2) }}</td>
        </tr>
        <tr>
            <td align="right"><strong>VAT ({{ number_format($receipt->order->vat_rate_percent, 1) }}% Included):</strong></td>
            <td align="right">KES {{ number_format($receipt->vat_kes / 100, 2) }}</td>
        </tr>
        <tr class="total-row">
            <td align="right"><strong>Total Paid in Full:</strong></td>
            <td align="right" style="color: #0a0e1a;"><strong>KES {{ number_format($receipt->amount_kes / 100, 2) }}</strong></td>
        </tr>
    </table>

    <div class="footer">
        Thank you for studying with Bariton Music Academy. All fees include applicable taxes.<br>
        This is a computer-generated receipt issued under sequential counter registration.
    </div>
</body>
</html>
