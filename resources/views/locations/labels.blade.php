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

        /* Lembar A4: 6 baris x 2 kolom = 12 label (100 x 48 mm)
           Isi ke BAWAH dulu (6 baris), lalu pindah ke kolom kanan. */
        .sheet {
            width: 200mm;
            margin: 8mm auto;
            padding-bottom: 0.6mm;
            background: #fff;
            display: grid;
            grid-template-columns: repeat(2, 100mm);
            grid-template-rows: repeat(6, 48mm);
            grid-auto-flow: column;
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
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
            padding: 2.5mm;
            overflow: hidden;
        }

        .label .qr {
            width: 20mm;
            height: 20mm;
            flex: 0 0 20mm;
        }

        .label .code {
            flex: 1 1 auto;
            text-align: center;
            font-size: 16mm;
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
            <strong>Label Lokasi</strong> — {{ $total }} label,
            {{ count($sheets) }} lembar A4 (12 label/lembar, 100 × 48 mm, isi ke bawah dulu)
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
                    <img class="qr" src="{{ $item->qr_data_uri }}" alt="QR {{ $item->code }}">
                </div>
            @endforeach
        </div>
    @endforeach

</body>
</html>