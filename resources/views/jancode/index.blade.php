<!DOCTYPE html>
<html lang="en">
@include('layout.header')
<body id="page-top">
<!-- Page Wrapper -->
@include('sweetalert::alert')
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
                    <h1 class="h3 mb-0 text-gray-800">Jancode List</h1>
                    <div>
                        <a href="javascript:void(0)" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm" id="createNewJancode">
                            <i class="fas fa-plus fa-sm text-white-50"></i> Create Jancode
                        </a>
                        <button class="d-none d-sm-inline-block btn btn-sm btn-success shadow-sm" data-toggle="modal" data-target="#importModal">
                            <i class="fas fa-file-excel fa-sm text-white-50"></i> Import Excel
                        </button>
                    </div>
                </div>
                
                <!-- DataTales Example -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Data Jancode</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm data-table" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Jancode</th>
                                        <th>Size</th>
                                        <th>Qty</th>
                                        <th>Country</th>
                                        <th>Color</th>
                                        <th>Desc</th>
                                        <th>Void</th>
                                        <th width="100px">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
            <!-- /.container-fluid -->

        </div>
        <!-- End of Main Content -->

        @include('layout.footer')

        <!-- Jancode Modal -->

        <div class="modal fade" id="ajaxModel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title" id="modelHeading"></h4>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="jancodeForm" name="jancodeForm" class="form-horizontal">
                            <input type="hidden" name="id" id="jancode_id">
                            <div class="form-group">
                                <label for="jancode" class="col-sm-12 control-label">Jancode</label>
                                <div class="col-sm-12">
                                    <input type="text" class="form-control" id="jancode" name="jancode" placeholder="Enter Jancode" value="" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="size" class="col-sm-12 control-label">Size</label>
                                <div class="col-sm-12">
                                    <input type="text" class="form-control" id="size" name="size" placeholder="Enter Size" value="" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-12 control-label">Qty</label>
                                <div class="col-sm-12">
                                    <input type="number" id="qty" name="qty" required placeholder="Enter Qty" class="form-control">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-12 control-label">Country</label>
                                <div class="col-sm-12">
                                    <input type="text" id="country" name="country" required placeholder="Enter Country" class="form-control">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-12 control-label">Color</label>
                                <div class="col-sm-12">
                                    <input type="text" id="color" name="color" required placeholder="Enter Color" class="form-control">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-12 control-label">Description</label>
                                <div class="col-sm-12">
                                    <input type="text" id="description" name="description" required placeholder="Enter Description" class="form-control">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-12 control-label">Void</label>
                                <div class="col-sm-12">
                                    <select name="void" id="void" class="form-control">
                                        <option value="0">False</option>
                                        <option value="1">True</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-offset-2 col-sm-10 mt-3">
                                <button type="submit" class="btn btn-primary" id="saveBtn" value="create">Save changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Import Modal -->
        <div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form id="importForm" action="{{ route('jancode.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Import Excel</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label>Upload Excel File</label>
                                <input type="file" name="file" class="form-control" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Import</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>
<!-- End of Page Wrapper -->

<!-- Page level plugins -->
<script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script type="text/javascript">
  $(function () {
      // --- Ajax Setup ---
      $.ajaxSetup({
          headers: {
              'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
          }
      });
      
      // --- DataTables Initialization ---
      const table = $('.data-table').DataTable({
          processing: true,
          serverSide: true,
          ajax: "{{ route('jancode.index') }}",
          columns: [
              {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
              {data: 'jancode', name: 'jancode'},
              {data: 'size', name: 'size'},
              {data: 'qty', name: 'qty'},
              {data: 'country', name: 'country'},
              {data: 'color', name: 'color'},
              {data: 'description', name: 'description'},
              {data: 'void', name: 'void', render: function(data){ return data == 1 ? '<span class="badge badge-danger">True</span>' : '<span class="badge badge-success">False</span>'; }},
              {data: 'action', name: 'action', orderable: false, searchable: false},
          ]
      });
      
      // --- Show Create Modal ---
      $('#createNewJancode').click(function () {
          $('#saveBtn').val("create-jancode").html('Save changes');
          $('#jancode_id').val('');
          $('#jancodeForm').trigger("reset");
          $('#modelHeading').html("Create New Jancode");
          $('#ajaxModel').modal('show');
      });
      
      // --- Show Edit Modal ---
      $('body').on('click', '.editJancode', function () {
          const jancode_id = $(this).data('id');
          const editUrl = "{{ route('jancode.edit', ':id') }}".replace(':id', jancode_id);
          $.get(editUrl, function (data) {
              $('#modelHeading').html("Edit Jancode");
              $('#saveBtn').val("edit-jancode").html('Save changes');
              $('#ajaxModel').modal('show');
              $('#jancode_id').val(data.id);
              $('#jancode').val(data.jancode);
              $('#size').val(data.size);
              $('#qty').val(data.qty);
              $('#country').val(data.country);
              $('#color').val(data.color);
              $('#description').val(data.description);
              $('#void').val(data.void);
          });
      });
      
      // --- Create/Edit Form Submit ---
      $('#jancodeForm').submit(function (e) {
          e.preventDefault();
          const formData = new FormData(this);
          
          Swal.fire({
              title: 'Saving Data...',
              text: 'Please wait while we save your changes.',
              allowOutsideClick: false,
              didOpen: () => Swal.showLoading()
          });
      
          $.ajax({
              data: formData,
              url: "{{ route('jancode.store') }}",
              type: "POST",
              processData: false,
              contentType: false,
              success: function (response) {
                  $('#jancodeForm').trigger("reset");
                  $('#ajaxModel').modal('hide');
                  table.draw();
                  Swal.fire({
                      icon: 'success',
                      title: 'Success!',
                      text: response.success,
                      confirmButtonText: 'OK'
                  });
              },
              error: function (xhr) {
                  const errorMsg = xhr.responseJSON ? xhr.responseJSON.message : 'An error occurred while saving.';
                  Swal.fire({
                      icon: 'error',
                      title: 'Error!',
                      text: errorMsg,
                      confirmButtonText: 'Try Again'
                  });
              }
          });
      });
      
      // --- Delete Action ---
      $('body').on('click', '.deleteJancode', function () {
          const jancode_id = $(this).data("id");
          
          Swal.fire({
              title: 'Are you sure?',
              text: "You won't be able to revert this!",
              icon: 'warning',
              showCancelButton: true,
              confirmButtonColor: '#d33',
              cancelButtonColor: '#3085d6',
              confirmButtonText: 'Yes, delete it!'
          }).then((result) => {
              if (result.isConfirmed) {
                  Swal.fire({
                      title: 'Deleting Data...',
                      allowOutsideClick: false,
                      didOpen: () => Swal.showLoading()
                  });

                  const formData = new FormData();
                  formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
                  formData.append('_method', 'DELETE');

                  $.ajax({
                      type: "POST",
                      url: "{{ route('jancode.index') }}"+'/'+jancode_id,
                      data: formData,
                      processData: false,
                      contentType: false,
                      success: function (response) {
                          table.draw();
                          Swal.fire('Deleted!', response.success, 'success');
                      },
                      error: function (xhr) {
                          Swal.fire('Error!', 'Failed to delete data.', 'error');
                      }
                  });
              }
          });
      });

      // --- Import Form Submit ---
      $('#importForm').on('submit', function(e) {
          e.preventDefault();
          const formData = new FormData(this);

          Swal.fire({
              title: 'Importing Data...',
              text: 'Please wait while we process the Excel file.',
              allowOutsideClick: false,
              didOpen: () => Swal.showLoading()
          });

          $.ajax({
              url: $(this).attr('action'),
              type: 'POST',
              data: formData,
              processData: false,
              contentType: false,
              success: function(response) {
                  Swal.fire({
                      icon: 'success',
                      title: 'Success!',
                      text: response.message,
                      confirmButtonText: 'OK'
                  }).then(() => {
                      table.draw(); 
                      $('#importForm')[0].reset();
                      $('#importModal').modal('hide'); 
                  });
              },
              error: function(xhr) {
                  const errorMsg = xhr.responseJSON ? xhr.responseJSON.message : 'An error occurred during import.';
                  Swal.fire({
                      icon: 'error',
                      title: 'Import Failed',
                      text: errorMsg,
                      confirmButtonText: 'Try Again'
                  });
              }
          });
      });
  });
</script>
</html>
