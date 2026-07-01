<!DOCTYPE html>
<html lang="en">

@include('layout.header')

<body id="page-top">

    @include('sweetalert::alert')

    <div id="wrapper">

        @include('layout.sidebar')

        <div id="content-wrapper" class="d-flex flex-column">

            <div id="content">

                @include('layout.navbar')

                <div class="container-fluid">

                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">

                        <h1 class="h3 mb-0 text-gray-800">
                            RFID LIVE BATCH READER
                        </h1>

                        <button id="clearBtn"
                            class="d-none d-sm-inline-block btn btn-sm btn-danger shadow-sm">

                            <i class="fas fa-trash fa-sm text-white-50"></i>
                            CLEAR

                        </button>

                    </div>

                    <!-- Total Card -->
                    <div class="row mb-4">

                        <div class="col-xl-3 col-md-6 mb-4">

                            <div class="card border-left-success shadow h-100 py-2">

                                <div class="card-body">

                                    <div class="row no-gutters align-items-center">

                                        <div class="col mr-2">

                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                Total Tag
                                            </div>

                                            <div class="h3 mb-0 font-weight-bold text-gray-800">
                                                <span id="total">0</span>
                                            </div>

                                        </div>

                                        <div class="col-auto">
                                            <i class="fas fa-tags fa-2x text-gray-300"></i>
                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                    <!-- RFID Table -->
                    <div class="card shadow mb-4">

                        <div class="card-header py-3">

                            <h6 class="m-0 font-weight-bold text-primary">
                                RFID TAG DATA
                            </h6>

                        </div>

                        <div class="card-body">

                            <div class="table-responsive">

                                <table id="rfidTable"
                                    class="table table-bordered table-striped table-sm"
                                    width="100%"
                                    cellspacing="0">

                                    <thead class="thead-dark">

                                        <tr>
                                            <th width="60">No</th>
                                            <th>EPC TAG</th>
                                            <th width="120">ANTENNA</th>
                                            <th width="120">RSSI</th>
                                            <th width="220">READ AT</th>
                                        </tr>

                                    </thead>

                                    <tbody>

                                    </tbody>

                                </table>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

            @include('layout.footer')

        </div>

    </div>

    <!-- Datatables -->
    <script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>

    <!-- SweetAlert -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script type="text/javascript">

        $(document).ready(function () {

            // ============================================
            // AJAX SETUP
            // ============================================

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            // ============================================
            // DATATABLE INIT
            // ============================================

            const table = $('#rfidTable').DataTable({

                processing: true,
                searching: true,
                paging: true,
                ordering: true,
                info: true,
                autoWidth: false,
                pageLength: 10,

                columns: [
                    { data: 'no' },
                    { data: 'epc' },
                    { data: 'antenna' },
                    { data: 'rssi' },
                    { data: 'read_at' }
                ]

            });

            // ============================================
            // LOAD RFID DATA
            // ============================================

            function loadRFID()
            {

                $.ajax({

                    url: '/rfid/data',
                    type: 'GET',

                    success: function (res)
                    {

                        $('#total').html(res.length);

                        let rows = [];

                        res.forEach(function (item, index) {

                            rows.push({

                                no: index + 1,
                                epc: item.epc ?? '',
                                antenna: item.antenna ?? '',
                                rssi: item.rssi ?? '',
                                read_at: item.read_at ?? ''

                            });

                        });

                        table.clear().rows.add(rows).draw(false);

                    },

                    error: function ()
                    {

                        console.log('Failed load RFID data');

                    }

                });

            }

            // ============================================
            // FIRST LOAD
            // ============================================

            loadRFID();

            // ============================================
            // AUTO REFRESH EVERY 1 SECOND
            // ============================================

            setInterval(function () {

                loadRFID();

            }, 1000);

            // ============================================
            // CLEAR RFID
            // ============================================

            $('#clearBtn').click(function () {

                Swal.fire({

                    title: 'Are you sure?',
                    text: "All RFID data will be cleared.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, clear it!'

                }).then((result) => {

                    if (result.isConfirmed) {

                        Swal.fire({
                            title: 'Clearing Data...',
                            allowOutsideClick: false,
                            didOpen: () => Swal.showLoading()
                        });

                        $.ajax({

                            url: '/rfid/clear',
                            type: 'POST',

                            data: {
                                _token: $('meta[name="csrf-token"]').attr('content')
                            },

                            success: function () {

                                loadRFID();

                                Swal.fire({

                                    icon: 'success',
                                    title: 'Success!',
                                    text: 'RFID data cleared successfully.',
                                    confirmButtonText: 'OK'

                                });

                            },

                            error: function () {

                                Swal.fire({

                                    icon: 'error',
                                    title: 'Error!',
                                    text: 'Failed to clear RFID data.',
                                    confirmButtonText: 'OK'

                                });

                            }

                        });

                    }

                });

            });

        });

    </script>

</body>

</html>