<!DOCTYPE html>
<html lang="en">
@include('layout.header')

<body id="page-top">
    <!-- Page Wrapper -->
    <div id="wrapper">
        @include('layout.sidebar')
        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">

            <!-- Main Content -->
            <div id="content">
                @include('layout.navbar')

                <!-- Begin Page Content -->
                <div class="container-fluid">

                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Select Buyer for Hangtag</h1>
                    </div>

                    <div class="row text-center mt-5">
                        @foreach($buyers as $buyer)
                            <div class="col-lg-4 col-md-6 mb-4">
                                <a href="{{ route('hangtag.index', $buyer) }}" class="text-decoration-none">
                                    <div class="card shadow h-100 py-4 border-left-primary btn-buyer">
                                        <div class="card-body">
                                            <div class="row no-gutters align-items-center">
                                                <div class="col mr-2">
                                                    <div class="text-lg font-weight-bold text-primary text-uppercase mb-1">
                                                        {{ $buyer }}
                                                    </div>
                                                </div>
                                                <div class="col-auto">
                                                    <i class="fas fa-tags fa-2x text-gray-300"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>

                </div>
                <!-- /.container-fluid -->

            </div>
            <!-- End of Main Content -->

            @include('layout.footer')

        </div>
        <!-- End of Content Wrapper -->

    </div>
    <!-- End of Page Wrapper -->

    <style>
        .btn-buyer {
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn-buyer:hover {
            transform: scale(1.05);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
            cursor: pointer;
        }
    </style>
</body>
</html>