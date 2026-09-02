<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recibo de recepción de productos</title>
    <style>
        @page { margin: 28px 34px 48px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #27304a; font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        .header { border-bottom: 3px solid #343a56; padding-bottom: 12px; margin-bottom: 16px; }
        .header-table, .meta-table, .products { width: 100%; border-collapse: collapse; }
        .logo-cell { width: 92px; vertical-align: middle; }
        .logo { width: 72px; height: 72px; object-fit: contain; border-radius: 8px; }
        .company { vertical-align: middle; }
        .company h1 { margin: 0 0 3px; font-size: 23px; color: #262d49; }
        .company p { margin: 2px 0; color: #616980; font-size: 10px; }
        .document-cell { width: 210px; text-align: right; vertical-align: middle; }
        .document-title { display: inline-block; padding: 8px 12px; color: white; background: #343a56; font-size: 13px; font-weight: bold; }
        .folio { margin-top: 7px; color: #6b7280; font-size: 9px; }
        .summary { margin-bottom: 15px; padding: 10px 12px; background: #f3f5f9; border-left: 4px solid #82bf3f; }
        .meta-table td { width: 50%; padding: 3px 8px 3px 0; vertical-align: top; }
        .label { color: #697086; font-size: 8px; font-weight: bold; text-transform: uppercase; }
        .value { margin-top: 2px; color: #252c47; font-size: 10px; font-weight: bold; }
        .reception { margin-top: 13px; page-break-inside: auto; }
        .reception-title { padding: 7px 9px; color: white; background: #4a506d; font-size: 10px; font-weight: bold; }
        .reception-info { padding: 7px 9px; background: #eef1f6; border: 1px solid #d9deea; border-top: 0; }
        .products { margin-top: 8px; }
        .products thead { display: table-header-group; }
        .products tr { page-break-inside: avoid; page-break-after: auto; }
        .products th { padding: 7px 5px; color: white; background: #343a56; border: 1px solid #343a56; font-size: 8px; text-align: left; }
        .products td { padding: 7px 5px; border: 1px solid #dce0e8; vertical-align: middle; }
        .products tbody tr:nth-child(even) { background: #f6f7f9; }
        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }
        .product-name { font-weight: bold; }
        .product-code { color: #7a8194; font-size: 8px; }
        .reception-total { padding: 8px; text-align: right; background: #edf4e7; border: 1px solid #d5e4c8; font-weight: bold; }
        .grand-total { margin-top: 14px; padding: 10px 12px; color: white; background: #343a56; text-align: right; font-size: 13px; font-weight: bold; page-break-inside: avoid; }
        .notes { margin-top: 14px; padding: 10px 12px; border: 1px solid #d9deea; background: #fafbfc; page-break-inside: avoid; }
        .notes-title { margin-bottom: 5px; font-weight: bold; color: #343a56; }
        .notes p { margin: 3px 0; }
        .signature { width: 300px; margin: 58px auto 5px; text-align: center; page-break-inside: avoid; }
        .signature-line { border-top: 1px solid #343a56; padding-top: 6px; font-weight: bold; }
        .signature-help { margin-top: 2px; color: #737b90; font-size: 8px; }
        .footer { position: fixed; right: 0; bottom: -30px; left: 0; color: #858b9b; font-size: 8px; text-align: center; }
        .cancelled-watermark { position: fixed; top: 330px; left: 55px; width: 500px; color: rgba(190, 35, 45, .17); font-size: 48px; font-weight: bold; text-align: center; transform: rotate(-32deg); z-index: -1; }
        .cancelled-label { float: right; color: #fff; background: #c0392b; padding: 2px 7px; font-size: 8px; }
        .cancelled-row { color: #8b3a3a; background: #fbe9e9 !important; text-decoration: line-through; }
        .audit-info { margin-top: 4px; color: #697086; font-size: 8px; }
    </style>
</head>
<body>
    @if($cancelledWatermark)
        <div class="cancelled-watermark">{{ $cancelledWatermark }}</div>
    @endif
    <div class="footer">Carnicería Franco · Recibo de recepción de productos</div>

    <div class="header">
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    @if($logoData)<img src="{{ $logoData }}" class="logo" alt="Logo">@endif
                </td>
                <td class="company">
                    <h1>Carnicería Franco</h1>
                    <p>Av. Narciso Bassols 10, 3 de Mayo</p>
                    <p>60990 La Orilla, Mich.</p>
                </td>
                <td class="document-cell">
                    <div class="document-title">RECIBO DE RECEPCIÓN</div>
                    <div class="folio">Folio: REC-{{ $receiptDate->format('Ymd') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="summary">
        <table class="meta-table">
            <tr>
                <td>
                    <div class="label">Fecha de recepción</div>
                    <div class="value">{{ $receiptDate->locale('es')->translatedFormat('d \d\e F \d\e Y') }}</div>
                </td>
                <td>
                    <div class="label">Registrado por</div>
                    <div class="value">{{ $users->isEmpty() ? 'Usuario no disponible' : $users->implode(', ') }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="label">Recepciones incluidas</div>
                    <div class="value">{{ $receptions->count() }}</div>
                </td>
                <td>
                    <div class="label">Fecha de generación</div>
                    <div class="value">{{ now()->format('d/m/Y H:i') }}</div>
                </td>
            </tr>
        </table>
    </div>

    @foreach($receptions as $receptionId => $receptionMovements)
        @php
            $first = $receptionMovements->first();
            $receptionTotal = $receptionMovements->sum(function ($movement) {
                return $movement->total_cost !== null
                    ? (float) $movement->total_cost
                    : (float) ($movement->product->precio ?? 0) * (float) $movement->quantity;
            });
            $receptionLabel = $receptionId === 'historica'
                ? 'Recepción histórica'
                : 'Recepción ' . strtoupper(substr($receptionId, 0, 8));
            $receptionCancelled = $receptionMovements->every(function ($movement) {
                return $movement->status === \App\Models\InventoryMovement::STATUS_CANCELLED;
            });
        @endphp
        <div class="reception">
            <div class="reception-title">
                {{ $receptionLabel }}
                @if($receptionCancelled)<span class="cancelled-label">RECEPCIÓN CANCELADA</span>@endif
            </div>
            <div class="reception-info">
                <strong>Creada:</strong> {{ $first->moved_at->format('d/m/Y H:i:s') }}
                &nbsp;&nbsp;|&nbsp;&nbsp;
                <strong>Usuario:</strong> {{ optional($first->user)->name ?: 'No disponible' }}
                @if($first->notes)
                    <br><strong>Notas:</strong> {{ $first->notes }}
                @endif
                @if($receptionCancelled)
                    <br><strong>Cancelada:</strong> {{ optional($first->cancelled_at)->format('d/m/Y H:i') }}
                    por {{ optional($first->cancelledBy)->name ?: 'Usuario no disponible' }}
                    @if($first->cancellation_reason) · {{ $first->cancellation_reason }} @endif
                @endif
                @if($receptionMovements->pluck('audits')->flatten()->isNotEmpty())
                    <div class="audit-info">Este recibo contiene cambios registrados en el historial de auditoría.</div>
                @endif
            </div>

            <table class="products">
                <thead>
                    <tr>
                        <th style="width: 25px;" class="text-center">#</th>
                        <th style="width: 70px;">CÓDIGO</th>
                        <th>PRODUCTO</th>
                        <th style="width: 95px;" class="text-right">CANTIDAD</th>
                        <th style="width: 78px;" class="text-right">PRECIO</th>
                        <th style="width: 85px;" class="text-right">COSTO</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($receptionMovements as $movement)
                        @php
                            $unitCost = $movement->unit_cost !== null
                                ? (float) $movement->unit_cost
                                : (float) ($movement->product->precio ?? 0);
                            $lineCost = $movement->total_cost !== null
                                ? (float) $movement->total_cost
                                : $unitCost * (float) $movement->quantity;
                        @endphp
                        <tr class="{{ $movement->status === \App\Models\InventoryMovement::STATUS_CANCELLED ? 'cancelled-row' : '' }}">
                            <td class="text-center">{{ $loop->iteration }}</td>
                            <td>{{ $movement->product->codigo ?? 'S/C' }}</td>
                            <td>
                                <div class="product-name">{{ $movement->product->nombre ?? 'Producto no disponible' }}</div>
                                <div class="product-code">Unidad: {{ ucfirst($movement->unit) }}</div>
                                @if($movement->status === \App\Models\InventoryMovement::STATUS_CANCELLED)
                                    <div class="product-code">
                                        CANCELADA {{ optional($movement->cancelled_at)->format('d/m/Y H:i') }}
                                        @if($movement->cancellation_reason) · {{ $movement->cancellation_reason }} @endif
                                    </div>
                                @endif
                            </td>
                            <td class="text-right">{{ number_format($movement->quantity, 3) }} {{ ucfirst($movement->unit) }}</td>
                            <td class="text-right">${{ number_format($unitCost, 2) }}</td>
                            <td class="text-right"><strong>${{ number_format($lineCost, 2) }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="reception-total">Subtotal de recepción: ${{ number_format($receptionTotal, 2) }}</div>
        </div>
    @endforeach

    <div class="grand-total">COSTO TOTAL RECIBIDO: ${{ number_format($totalCost, 2) }}</div>

    @if($notes->isNotEmpty())
        <div class="notes">
            <div class="notes-title">Notas de recepción</div>
            @foreach($notes as $note)<p>• {{ $note }}</p>@endforeach
        </div>
    @endif

    <div class="signature">
        <div class="signature-line">Firma de recibido</div>
        <div class="signature-help">Nombre y firma de la persona que valida la recepción</div>
    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->getFont('DejaVu Sans', 'normal');
            $pdf->page_text(500, 756, 'Página {PAGE_NUM} de {PAGE_COUNT}', $font, 8, array(0.45, 0.48, 0.56));
        }
    </script>
</body>
</html>
