<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Label Pallet — {{ $total }} label</title>
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

        /* Lembar A4: 3 kolom x 4 baris = 12 label (66 x 66 mm) */
        .sheet {
            width: 198mm;
            margin: 8mm auto;
            padding-bottom: 0.6mm;
            background: #fff;
            display: grid;
            grid-template-columns: repeat(3, 66mm);
            grid-auto-rows: 66mm;
            page-break-after: always;
            break-after: page;
        }

        .sheet:last-of-type {
            page-break-after: auto;
            break-after: auto;
        }

        .label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 0.4mm solid #4a4a4a;
            padding: 3mm;
            overflow: hidden;
        }

        .label .qr {
            width: 38mm;
            height: 38mm;
        }

        .label .code {
            margin-top: 2mm;
            font-size: 10mm;
            line-height: 1;
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
            <strong>Label Pallet</strong> — {{ $total }} label,
            {{ count($sheets) }} lembar A4 (12 label/lembar, 66 × 66 mm)
        </div>
        <div>
            <a href="{{ route('pallets.index') }}" class="btn secondary">Kembali</a>
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