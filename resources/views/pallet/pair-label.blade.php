<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Label Pasangan {{ $pallet->code }} / {{ $address->code }}</title>
    <style>
        @page { size: A4; margin: 12mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "DejaVu Sans", Arial, Helvetica, sans-serif; color: #222; background: #f1f3f5; }

        .toolbar {
            position: sticky; top: 0; z-index: 10; display: flex; align-items: center;
            justify-content: space-between; padding: 10px 16px; background: #fff;
            border-bottom: 1px solid #dee2e6; font-size: 13px;
        }
        .toolbar .btn {
            padding: 6px 14px; border-radius: 4px; border: 1px solid #0d6efd;
            background: #0d6efd; color: #fff; font-weight: 600; cursor: pointer; text-decoration: none;
        }
        .toolbar .btn.secondary { background: #fff; color: #0d6efd; }

        .card {
            width: 180mm; margin: 10mm auto; padding: 8mm; background: #fff;
            border: 0.5mm solid #4a4a4a; display: flex; gap: 8mm; align-items: center;
        }
        .cell { text-align: center; flex: 1; }
        .cell img { width: 62mm; height: 62mm; }
        .cell .code { font-size: 9mm; font-weight: 700; letter-spacing: 0.2mm; margin-top: 2mm; }
        .cell .cap { font-size: 3.6mm; text-transform: uppercase; letter-spacing: 0.6mm; color: #555; }
        .divider { width: 0.4mm; align-self: stretch; background: #ccc; }
        .foot { text-align: center; font-size: 3.8mm; color: #444; margin-top: 4mm; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .card { margin: 0 auto; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>
            <strong>Label Pasangan</strong> — {{ $address->code }} &amp; {{ $pallet->code }}
            (tempel di pallet fisik yang berdiri di lokasi ini)
        </div>
        <div>
            <a href="{{ route('pallet.index') }}" class="btn secondary">Kembali</a>
            <button type="button" class="btn" onclick="window.print()">Cetak</button>
        </div>
    </div>

    <div class="card">
        <div class="cell">
            <img src="{{ $qrLocation }}" alt="QR {{ $address->code }}">
            <div class="code">{{ $address->code }}</div>
            <div class="cap">Lokasi</div>
        </div>

        <div class="divider"></div>

        <div class="cell">
            <img src="{{ $qrPallet }}" alt="QR {{ $pallet->code }}">
            <div class="code">{{ $pallet->code }}</div>
            <div class="cap">Pallet</div>
        </div>
    </div>

    <div class="foot">
        Row {{ $address->row_label }} · Line {{ $address->col_label }} · Posisi {{ $address->position_no }}
    </div>
</body>
</html>