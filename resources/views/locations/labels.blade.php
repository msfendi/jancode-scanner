<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Label Lokasi — {{ $total }} label</title>
    <style>
        @page {
            size: A4;
            margin: 4mm;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: "DejaVu Sans", Arial, Helvetica, sans-serif;
            color: #222;
            background: #f1f3f5;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 16px;
            background: #fff;
            border-bottom: 1px solid #dee2e6;
            font-size: 13px;
        }

        .toolbar .btn {
            padding: 6px 14px;
            border-radius: 4px;
            border: 1px solid #0d6efd;
            background: #0d6efd;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }

        .toolbar .btn.secondary {
            background: #fff;
            color: #0d6efd;
        }

        /* Lembar A4: 2 kolom x 3 baris = 6 label (90 x 90 mm) */
        .sheet {
            width: 180mm;
            margin: 8mm auto;
            padding-bottom: 0.6mm;
            background: #fff;
            display: grid;
            grid-template-columns: repeat(2, 90mm);
            grid-auto-rows: 90mm;
            page-break-after: always;
            break-after: page;
        }

        .sheet:last-of-type {
            page-break-after: auto;
            break-after: auto;
        }

        .label {
            border: 0.4mm solid #4a4a4a;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 4mm;
            overflow: hidden;
        }

        .label .qr {
            width: 56mm;
            height: 56mm;
        }

        .label .code {
            margin-top: 3mm;
            font-size: 11mm;
            font-weight: 700;
            letter-spacing: 0.2mm;
            white-space: nowrap;
        }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0 auto; box-shadow: none; }
        }

        @media screen {
            .sheet { box-shadow: 0 2px 8px rgba(0, 0, 0, .12); }
        }
    </style>
</head>
<body>

    <div class="toolbar">
        <div>
            <strong>Label Lokasi</strong> — {{ $total }} label,
            {{ count($sheets) }} lembar A4 (6 label/lembar, 90 × 90 mm)
        </div>
        <div>
            <a href="{{ route('locations.index') }}" class="btn secondary">Kembali</a>
            <button type="button" class="btn" onclick="window.print()">Cetak</button>
        </div>
    </div>

    @foreach ($sheets as $sheet)
        <div class="sheet">
            @foreach ($sheet as $item)
                <div class="label">
                    <img class="qr" src="{{ $item->qr_data_uri }}" alt="QR {{ $item->code }}">
                    <span class="code">{{ $item->code }}</span>
                </div>
            @endforeach
        </div>
    @endforeach

</body>
</html>