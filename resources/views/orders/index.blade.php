@extends('layouts.app')
@section('css')
    <link href="https://cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.10.19/css/dataTables.bootstrap4.min.css" rel="stylesheet">
@endsection
@section('content')

    <div class="pagetitle">
        <div class="row">
            <div class="col-8">
                <h1>Orders</h1>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{route('home')}}">Home</a></li>
                        <li class="breadcrumb-item">Orders</li>
                    </ol>
                </nav>
            </div>
            <div class="col-4">
                @can('write-customers')
                    <a href="{{route('orders.sync')}}" style="float: right" class="btn btn-primary">Sync Orders</a>
                @endcan
            </div>
        </div>
    </div><!-- End Page Title -->
    <section class="section">
        <div class="row">
            <div class="col-lg-12">

                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Your Orders</h5>

                        <!-- Table with stripped rows -->
                        <table class="" id="dt-table">
                            <thead>
                            <tr>
                                <th scope="col">ORDER NAME</th>
                                <th scope="col">GATEWAY TRANSACTION ID</th>
                                <th scope="col">TRACKING NUMBER	</th>
                                <th scope="col">CARRIER</th>
                                <th scope="col">CREATED AT</th>
                                <th scope="col">SYNC STATE</th>
                            </tr>
                            </thead>
                            <tbody>

                            </tbody>
                        </table>
                        <!-- End Table with stripped rows -->
                    </div>
                </div>

            </div>
        </div>
    </section>
@endsection

@section('scripts')

    <script src="https://cdn.datatables.net/1.10.16/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap4.min.js"></script>

    <script>
        $('#dt-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{route('orders.list')}}',
            columns: [
                {data: 'name', name: 'name'},
                {data: 'payment_details',
                    name: 'payment_details',
                    render: function(data, type, full, meta) {
                        // Retrieve the value at index 0 of the payment_details array
                        var paymentDetails = JSON.parse(data);
                        var value = paymentDetails[0]['receipt']['payment_id'] ?? 'Not provided';

                        // Return the value as the rendered data
                        return value;
                    }},
                {
                    data: 'fulfillments',
                    name: 'fulfillments',
                    render: function(data, type, full, meta) {
                        var value = data[0];
                        if (value && value.tracking_number) {
                            var value2 = value['tracking_number'] ?? 'notworking';
                            // console.log(value2);
                            return value2;
                        }else {
                            var value2 = 'Not provided';
                            // console.log(value2);
                            return value2;
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('error:', error);
                    }


                },
                { data: 'fulfillments',
                    name: 'fulfillments',
                    render: function(data, type, full, meta) {
                        var value = data[0];
                        if (value && value.tracking_company) {
                            var value2 = value['tracking_company'] ?? 'notworking';
                            // console.log(value2);
                            return value2;
                        }else {
                            var value2 = 'Not provided';
                            // console.log(value2);
                            return value2;
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('error:', error);
                    }
                    },
                {data: 'created_at', name: 'created_at'},
                {data: '#', name: '#'}

            ]
        });
    </script>
@endsection