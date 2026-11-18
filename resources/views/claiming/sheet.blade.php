<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Acknowledgment Receipt</title>
    <style>
        @page { margin: 30px 34px 46px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #111; }
        .gov { text-align: center; line-height: 1.35; }
        .gov .small { font-size: 9px; }
        .gov .office { font-size: 11px; font-weight: bold; }
        h1 { text-align: center; font-size: 15px; letter-spacing: 1px; margin: 12px 0 2px; }
        .meta { width: 100%; margin-top: 8px; font-size: 10px; }
        .meta td { padding: 2px 0; }
        .note { margin-top: 6px; font-size: 9px; color: #333; }
        table.rows { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .rows th, .rows td { border: 0.8px solid #000; padding: 5px 4px; vertical-align: middle; }
        .rows th { background: #eee; font-size: 9px; text-align: left; }
        .rows td.sig { height: 26px; width: 150px; }
        .rows tr { page-break-inside: avoid; }
        .num { text-align: right; }
        .signs { width: 100%; margin-top: 26px; }
        .signs td { width: 33%; padding: 0 10px; vertical-align: bottom; text-align: center; font-size: 9.5px; }
        .line { border-top: 0.8px solid #000; margin-top: 30px; padding-top: 2px; font-weight: bold; }
        .footer { position: fixed; bottom: -30px; left: 0; right: 0; font-size: 8px; color: #666; }
    </style>
</head>
<body>
    <div class="footer">Printed from AGAPAY on {{ now()->format('M j, Y g:i A') }} · {{ $cycle->code }} · {{ strtoupper($intervention->source) }} - {{ $intervention->name }}</div>

    <div class="gov">
        <div class="small">Republic of the Philippines</div>
        <div class="small">Province of Mountain Province · Municipality of Bontoc</div>
        <div class="office">OFFICE OF THE MUNICIPAL AGRICULTURIST</div>
    </div>
    <h1>ACKNOWLEDGMENT RECEIPT</h1>

    <table class="meta">
        <tr>
            <td><b>Program:</b> {{ $intervention->source === 'da' ? 'DA' : 'LGU' }} - {{ $intervention->name }}</td>
            <td><b>Cycle:</b> {{ $cycle->label }} ({{ $cycle->code }})</td>
        </tr>
        <tr>
            <td><b>Claiming date:</b> {{ $cycle->claimingLabel() }}</td>
            <td><b>Venue:</b> {{ $cycle->venue ?: '________________________' }}</td>
        </tr>
    </table>
    <p class="note">I acknowledge receipt of the item / assistance stated below from the Office of the Municipal Agriculturist, Bontoc.
        Each farmer signs (or affixes a thumbmark) personally upon receiving; claims by another person are not accepted.</p>

    <table class="rows">
        <thead>
            <tr>
                <th style="width: 22px">No.</th>
                <th>Name of Farmer</th>
                <th style="width: 78px">RSBSA No.</th>
                <th style="width: 72px">Barangay</th>
                <th style="width: 60px" class="num">Quantity</th>
                <th style="width: 62px">Date Received</th>
                <th>Signature / Thumbmark</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($records as $record)
                <tr>
                    <td class="num">{{ $loop->iteration }}</td>
                    <td>{{ $record->beneficiary->fullName() }}</td>
                    <td>{{ $record->beneficiary->rsbsaDisplay() }}</td>
                    <td>{{ $record->beneficiary->barangay?->name }}</td>
                    <td class="num">{{ $record->quantityDisplay() }}</td>
                    <td>{{ $record->isClaimed() ? $record->date_distributed?->format('M j, Y') : '' }}</td>
                    <td class="sig"></td>
                </tr>
            @empty
                <tr><td colspan="7">No farmer is listed for this program in this cycle.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="signs">
        <tr>
            <td><div class="line">Prepared by</div>Data Encoder</td>
            <td><div class="line">Released by</div>Agricultural Technologist</td>
            <td><div class="line">Noted by</div>Municipal Agriculturist</td>
        </tr>
    </table>
</body>
</html>
