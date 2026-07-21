<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $document->getDocumentNumber() }} - Official Print</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 14px; color: #333; }
        .container { width: 100%; max-width: 800px; margin: 0 auto; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .logo { max-width: 80px; margin-bottom: 10px; }
        .doc-title { font-size: 24px; font-weight: bold; text-transform: uppercase; margin: 0; }
        .doc-meta { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .meta-box { width: 48%; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .signatures { display: flex; justify-content: space-between; margin-top: 80px; }
        .sig-line { width: 200px; border-top: 1px solid #000; text-align: center; padding-top: 5px; }
        .audit-trail { font-size: 10px; color: #666; margin-top: 50px; border-top: 1px dotted #ccc; padding-top: 10px; }
        
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print();">
    <div class="container">
        
        <div class="no-print" style="margin-bottom: 20px; text-align: right;">
            <button onclick="window.print();" style="padding: 10px 20px;">Print Document</button>
            <button onclick="window.close();" style="padding: 10px 20px;">Close</button>
        </div>

        <div class="header">
            <!-- Government/Entity Logo can go here -->
            <h1 class="doc-title">
                {{ $type === 'receipt' ? 'Goods Receipt Note (GRN)' : 'Goods Issue Note' }}
            </h1>
            <p style="margin: 5px 0;">Government of the People's Republic of Bangladesh</p>
        </div>

        <div class="doc-meta">
            <div class="meta-box">
                <strong>Document No:</strong> {{ $document->getDocumentNumber() }}<br>
                <strong>Date Posted:</strong> {{ $document->timelines()->where('state', 'POSTED')->first()->created_at->format('d F Y, h:i A') }}<br>
                <strong>Operator:</strong> {{ $document->creator->present()->fullName ?? 'System' }}
            </div>
            <div class="meta-box" style="text-align: right;">
                @if($type === 'receipt')
                    <strong>Source:</strong> {{ $document->purchase_type }}<br>
                    <strong>Reference:</strong> {{ $document->reference_no ?? 'N/A' }}<br>
                @elseif($type === 'issue')
                    <strong>Issue Type:</strong> {{ $document->issue_type }}<br>
                @endif
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 5%;">SL</th>
                    <th style="width: 55%;">Item Description</th>
                    <th style="width: 20%; text-align: center;">Quantity</th>
                    <th style="width: 20%; text-align: right;">Unit Cost (৳)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($document->getLineItems() as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item->stockable->name }} <br><small class="text-muted">{{ class_basename($item->stockable_type) }}</small></td>
                    <td style="text-align: center;">{{ $item->quantity }}</td>
                    <td style="text-align: right;">{{ number_format($item->unit_cost ?? 0, 2) }}</td>
                </tr>
                @endforeach
                <tr>
                    <td colspan="2" style="text-align: right;"><strong>Total:</strong></td>
                    <td style="text-align: center;"><strong>{{ $document->getLineItems()->sum('quantity') }}</strong></td>
                    <td style="text-align: right;">
                        <strong>{{ number_format($document->getLineItems()->sum(fn($i) => $i->quantity * ($i->unit_cost ?? 0)), 2) }}</strong>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="signatures">
            <div class="sig-line">Prepared By (Storekeeper)</div>
            <div class="sig-line">Verified By</div>
            <div class="sig-line">{{ $type === 'receipt' ? 'Approved By' : 'Received By' }}</div>
        </div>

        <div class="audit-trail">
            <strong>System Audit Trail:</strong> Document generated securely from Gov-Store Ledger. 
            UUID: {{ $document->id }}
        </div>
    </div>
</body>
</html>