@php
    $workflowSteps = [
        [
            'key'   => 'session',
            'label' => 'Sesi Import',
            'desc'  => 'Mulai / pilih session induk',
            'icon'  => 'fa-play-circle',
            'route' => 'session.index',
        ],
        [
            'key'   => 'import',
            'label' => 'Import Packing List',
            'desc'  => 'Upload file Excel PROFORMA / ACTUAL',
            'icon'  => 'fa-file-import',
            'route' => 'jancode.index',
        ],
        [
            'key'   => 'manage',
            'label' => 'Manage Packing List',
            'desc'  => 'Periksa revisi aktif & baris broken',
            'icon'  => 'fa-clipboard-list',
            'route' => 'jancode.packingLists',
        ],
        [
            'key'   => 'scan',
            'label' => 'Scan Jancode',
            'desc'  => 'Scan 1 karton per barcode',
            'icon'  => 'fa-barcode',
            'route' => 'scanner.index',
        ],
        [
            'key'   => 'pallet',
            'label' => 'Manage Pallet',
            'desc'  => 'Atur lokasi pallet karton',
            'icon'  => 'fa-pallet',
            'route' => 'pallet.index',
        ],
    ];

    $workflowCurrent = $current ?? null;
    $workflowCurrentIndex = false;
    foreach ($workflowSteps as $i => $s) {
        if ($s['key'] === $workflowCurrent) {
            $workflowCurrentIndex = $i;
            break;
        }
    }
@endphp

<div class="card shadow-sm border-0 mb-4 workflow-steps-card">
    <div class="card-body py-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="text-xs font-weight-bold text-uppercase text-muted">
                <i class="fas fa-route mr-1"></i> Alur Kerja
            </div>
            <small class="text-muted d-none d-md-block">
                Ikuti urutan langkah 1 &rarr; 5
            </small>
        </div>
        <div class="d-flex flex-wrap align-items-center">
            @foreach ($workflowSteps as $i => $step)
                @php
                    $isActive = $workflowCurrent === $step['key'];
                    $isDone   = ($workflowCurrentIndex !== false) && ($i < $workflowCurrentIndex);
                    $numClass = $isActive ? 'bg-primary text-white' : ($isDone ? 'bg-success text-white' : 'bg-light text-muted border');
                @endphp
                <a href="{{ route($step['route']) }}"
                   class="workflow-step d-flex align-items-center mr-2 mb-2 pr-2 rounded {{ $isActive ? 'active' : '' }}">
                    <span class="workflow-num {{ $numClass }} d-flex align-items-center justify-content-center rounded-circle font-weight-bold">
                        @if ($isDone)
                            <i class="fas fa-check"></i>
                        @else
                            {{ $i + 1 }}
                        @endif
                    </span>
                    <span class="ml-2">
                        <span class="d-block font-weight-bold small {{ $isActive ? 'text-primary' : 'text-gray-800' }}">
                            <i class="fas {{ $step['icon'] }} mr-1"></i>{{ $step['label'] }}
                        </span>
                        <span class="d-block text-muted" style="font-size: .72rem; line-height: 1.1;">
                            {{ $step['desc'] }}
                        </span>
                    </span>
                </a>
                @if (!$loop->last)
                    <i class="fas fa-chevron-right text-muted mx-1 mb-2 d-none d-lg-inline"></i>
                @endif
            @endforeach
        </div>
    </div>
</div>

<style>
    .workflow-step { text-decoration: none !important; transition: background-color .15s ease-in-out; }
    .workflow-step:hover { background-color: #f1f3f9; }
    .workflow-step.active { background-color: #eef2ff; }
    .workflow-num { width: 30px; height: 30px; flex: 0 0 30px; font-size: .85rem; }
</style>
