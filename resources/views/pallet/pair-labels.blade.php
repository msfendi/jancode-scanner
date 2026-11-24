<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Stiker Pallet &amp; Lokasi — {{ count($pairs) }} stiker</title>
    <style>
        @page { size: A4; margin: 4mm; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: "DejaVu Sans", Arial, Helvetica, sans-serif;
            color: #222;
            background: #f1f3f5;
        }

        .toolbar {
            position: sticky; top: 0; z-index: 10;
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; padding: 10px 16px; background: #fff;
            border-bottom: 1px solid #dee2e6; font-size: 13px;
        }
        .toolbar .btn {
            padding: 6px 14px; border-radius: 4px; border: 1px solid #0d6efd;
            background: #0d6efd; color: #fff; font-weight: 600;
            cursor: pointer; text-decoration: none;
        }
        .toolbar .btn.secondary { background: #fff; color: #0d6efd; }

        /* Stiker 150 x 50 mm. Lembar A4: 1 kolom x 5 baris = 5 stiker. */
        .sheet {
            width: 150mm;
            margin: 8mm auto;
            padding-bottom: 0.6mm;
            background: #fff;
            display: grid;
            grid-template-columns: 150mm;
            grid-template-rows: repeat(5, 50mm);
            page-break-after: always;
            break-after: page;
        }
        .sheet:last-of-type { page-break-after: auto; break-after: auto; }

        .sticker {
            border: 0.4mm solid #4a4a4a;
            border-radius: 2mm;
            display: flex;
            align-items: center;
            justify-content: space-between;
            /* Padding 6mm: QR 34mm menyisakan quiet zone >=4 modul ke samping. */
            padding: 3mm 6mm;
            overflow: hidden;
        }

        .sticker .qr { width: 34mm; height: 34mm; flex: 0 0 34mm; }

        .sticker .tengah {
            flex: 1 1 auto;
            text-align: center;
            padding: 0 2mm;
        }

        .sticker .baris {
            display: flex;
            align-items: baseline;
            justify-content: center;
            gap: 2mm;
            white-space: nowrap;
        }

        .sticker .baris + .baris { margin-top: 1.5mm; }

        .sticker .label {
            font-size: 3mm;
            letter-spacing: .4mm;
            text-transform: uppercase;
            color: #666;
        }

        .sticker .kode {
            font-size: 7.5mm;
            font-weight: 700;
            letter-spacing: .2mm;
        }

        .sticker .kode.pallet { color: #1a4d8f; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0 auto; }
        }
    </style>
</head>
<body>

    <div class="toolbar">
        <div>
            <strong>Stiker Pallet &amp; Lokasi</strong> — {{ count($pairs) }} stiker,
            {{ count($sheets) }} lembar A4 (5 stiker/lembar, 150 × 50 mm)
        </div>
        <div>
            <a href="{{ route('pallet.index') }}" class="btn secondary">Kembali</a>
            <button type="button" class="btn" onclick="window.print()">Cetak</button>
        </div>
    </div>

    @foreach ($sheets as $sheet)
        <div class="sheet">
            @foreach ($sheet as $pair)
                <div class="sticker">
                    <img class="qr" src="{{ $pair['qr_location'] }}" alt="QR {{ $pair['location_code'] }}">

                    <div class="tengah">
                        <div class="baris">
                            <span class="label">Lokasi</span>
                            <span class="kode">{{ $pair['location_code'] }}</span>
                        </div>
                        <div class="baris">
                            <span class="label">Pallet</span>
                            <span class="kode pallet">{{ $pair['pallet_code'] }}</span>
                        </div>
                    </div>

                    <img class="qr" src="{{ $pair['qr_pallet'] }}" alt="QR {{ $pair['pallet_code'] }}">
                </div>
            @endforeach
        </div>
    @endforeach

</body>
</html>
