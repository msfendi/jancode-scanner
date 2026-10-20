<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Report Packing List Finished</title>
    <style>
        @page {
            margin: 18mm 10mm 16mm 10mm;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8.5pt;
            color: #2f3542;
            margin: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-title {
            font-size: 15pt;
            font-weight: bold;
            color: #1f3864;
            letter-spacing: .3px;
        }

        .header-sub {
            font-size: 8.5pt;
            color: #5a6478;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 8pt;
            font-weight: bold;
            color: #ffffff;
            background: #1cc88a;
        }

        .meta {
            margin-top: 8px;
        }

        .meta td {
            border: 1px solid #d8dde8;
            padding: 4px 6px;
            font-size: 8pt;
        }

        .meta .label {
            background: #f2f5fa;
            color: #5a6478;
            font-weight: bold;
            width: 10%;
            white-space: nowrap;
        }

        .meta .value {
            width: 23.3%;
        }

        .summary {
            margin-top: 10px;
        }

        .summary td {
            border: 1px solid #d8dde8;
            padding: 6px 8px;
            text-align: center;
        }

        .summary .box-title {
            font-size: 7.5pt;
            color: #5a6478;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .summary .box-value {
            font-size: 13pt;
            font-weight: bold;
            color: #1f3864;
        }

        .summary .box-value.ok {
            color: #1cc88a;
        }

        h3.section {
            font-size: 9.5pt;
            margin: 12px 0 4px 0;
            color: #1f3864;
            border-bottom: 1px solid #d8dde8;
            padding-bottom: 3px;
        }

        .detail {
            margin-top: 4px;
        }

        .detail thead th {
            background: #4e73df;
            color: #ffffff;
            font-size: 7.6pt;
            text-transform: uppercase;
            letter-spacing: .3px;
            border: 1px solid #3f5fb3;
            padding: 5px 4px;
            text-align: left;
        }

        .detail thead {
            display: table-header-group;
        }

        .detail tbody td {
            border: 1px solid #e1e6ef;
            padding: 4px 4px;
            font-size: 8pt;
        }

        .detail tbody tr:nth-child(even) td {
            background: #f7f9fc;
        }

        .detail .num {
            text-align: right;
        }

        .detail .ctr {
            text-align: center;
        }

        .detail .total td {
            background: #e8f1fb !important;
            font-weight: bold;
            border-top: 2px solid #4e73df;
        }

        .ok {
            color: #12805c;
            font-weight: bold;
        }

        .signatures {
            margin-top: 18px;
            width: 100%;
        }

        .signatures td {
            width: 33%;
            text-align: center;
            font-size: 8pt;
            color: #5a6478;
            padding-top: 26px;
        }

        .signatures .line {
            border-bottom: 1px solid #333333;
            margin-bottom: 3px;
            height: 20px;
        }

        .note {
            margin-top: 8px;
            font-size: 7.8pt;
            color: #5a6478;
            font-style: italic;
        }
    </style>
</head>
<body>

@php
    $packingList = $snapshot['packing_list'] ?? [];
    $revision    = $snapshot['revision'] ?? [];
    $summary     = $snapshot['summary'] ?? [];
    $rows        = $snapshot['rows'] ?? [];
    $totalCarton = 0;
    $totalReceive = 0;
    $totalBalance = 0;
    $totalPcs     = 0;
@endphp

<table>
    <tr>
        <td style="width: 62%; border: none;">
            <div class="header-title">REPORT PACKING LIST FINISHED</div>
            <div class="header-sub">{{ $packingList['label'] ?? '-' }}</div>
            <div class="header-sub">
                Style: {{ $packingList['style'] ?: '-' }} &nbsp;|&nbsp;
                OCF/CPO: {{ $packingList['ocf'] ?: '-' }} &nbsp;|&nbsp;
                Destination: {{ $packingList['destination'] ?: '-' }} &nbsp;|&nbsp;
                Warehouse: {{ $packingList['warehouse'] ?: '-' }}
            </div>
        </td>
        <td style="border: none; text-align: right;">
            <span class="badge">FINISHED - LENGKAP</span>
            <div class="header-sub" style="margin-top: 4px;">
                Dicetak oleh: {{ $snapshot['finished_by'] ?? '-' }}<br>
                {{ \Illuminate\Support\Carbon::parse($snapshot['finished_at'] ?? now())->format('d/m/Y H:i') }}
            </div>
        </td>
    </tr>
</table>

<table class="meta">
    <tr>
        <td class="label">Session</td>
        <td class="value">{{ $snapshot['session']['code'] ?? '-' }}</td>
        <td class="label">Revisi</td>
        <td class="value">{{ $revision['label'] ?? '-' }} ({{ $revision['doc_type'] ?? '-' }})</td>
        <td class="label">Batch No</td>
        <td class="value">{{ $revision['batch_number'] ?? '-' }}</td>
    </tr>
    <tr>
        <td class="label">Supplier</td>
        <td class="value">{{ $packingList['supplier'] ?: '-' }}</td>
        <td class="label">Tanggal Import</td>
        <td class="value">{{ $revision['import_date'] ?? '-' }}</td>
        <td class="label">Finished</td>
        <td class="value">{{ $snapshot['finished_at'] ?? '-' }}</td>
    </tr>
</table>

<table class="summary">
    <tr>
        <td>
            <div class="box-title">Total JAN Code</div>
            <div class="box-value">{{ number_format((int) ($summary['row_count'] ?? 0)) }}</div>
        </td>
        <td>
            <div class="box-title">Total Carton (PL)</div>
            <div class="box-value">{{ number_format((int) ($summary['total_carton'] ?? 0)) }}</div>
        </td>
        <td>
            <div class="box-title">Sudah Receive</div>
            <div class="box-value">{{ number_format((int) ($summary['received_carton'] ?? 0)) }}</div>
        </td>
        <td>
            <div class="box-title">Sisa Balance</div>
            <div class="box-value ok">{{ number_format((int) ($summary['balance_carton'] ?? 0)) }}</div>
        </td>
        <td>
            <div class="box-title">Total Pcs</div>
            <div class="box-value">{{ number_format((int) ($summary['total_pcs'] ?? 0)) }}</div>
        </td>
    </tr>
</table>

<h3 class="section">Detail Jancode &amp; Balance</h3>

<table class="detail">
    <thead>
        <tr>
            <th style="width: 3%; text-align: center;">No</th>
            <th style="width: 13%;">JAN Code</th>
            <th style="width: 9%;">Color</th>
            <th style="width: 5%; text-align: center;">Size</th>
            <th style="width: 10%;">Carton No</th>
            <th style="width: 7%; text-align: right;">Qty/Ctn</th>
            <th style="width: 8%; text-align: right;">PL Carton</th>
            <th style="width: 8%; text-align: right;">Receive</th>
            <th style="width: 8%; text-align: right;">Balance</th>
            <th style="width: 10%; text-align: right;">Total Pcs</th>
            <th style="width: 9%; text-align: center;">Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            @php
                $totalCarton  += (int) ($row['packing_list_qty_carton'] ?? 0);
                $totalReceive += (int) ($row['receive_qty_carton'] ?? 0);
                $totalBalance += (int) ($row['balance'] ?? 0);
                $totalPcs     += (int) ($row['total_qty_pcs'] ?? 0);
            @endphp
            <tr>
                <td class="ctr">{{ $row['no'] ?? '' }}</td>
                <td>{{ $row['jancode'] ?? '-' }}</td>
                <td>{{ $row['color'] ?? '-' }}</td>
                <td class="ctr">{{ $row['size'] ?? '-' }}</td>
                <td>{{ $row['carton_no'] ?? '-' }}</td>
                <td class="num">{{ number_format((int) ($row['qty_per_carton'] ?? 0)) }}</td>
                <td class="num">{{ number_format((int) ($row['packing_list_qty_carton'] ?? 0)) }}</td>
                <td class="num">{{ number_format((int) ($row['receive_qty_carton'] ?? 0)) }}</td>
                <td class="num ok">{{ number_format((int) ($row['balance'] ?? 0)) }}</td>
                <td class="num">{{ number_format((int) ($row['total_qty_pcs'] ?? 0)) }}</td>
                <td class="ctr ok">{{ $row['status'] ?? 'Lengkap' }}</td>
            </tr>
        @endforeach
        <tr class="total">
            <td colspan="6" style="text-align: right;">TOTAL</td>
            <td class="num">{{ number_format($totalCarton) }}</td>
            <td class="num">{{ number_format($totalReceive) }}</td>
            <td class="num ok">{{ number_format($totalBalance) }}</td>
            <td class="num">{{ number_format($totalPcs) }}</td>
            <td class="ctr ok">Lengkap</td>
        </tr>
    </tbody>
</table>

<div class="note">
    Catatan: report ini dibuat dari snapshot saat packing list di-Finish.
    Seluruh JAN code pada revisi {{ $revision['label'] ?? '-' }} tercatat lengkap dengan sisa balance 0 karton.
</div>

<table class="signatures">
    <tr>
        <td>
            <div class="line"></div>
            Dibuat oleh (Admin)
        </td>
        <td>
            <div class="line"></div>
            Diperiksa oleh
        </td>
        <td>
            <div class="line"></div>
            Disetujui oleh
        </td>
    </tr>
</table>

</body>
</html>
