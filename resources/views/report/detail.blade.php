<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="apple-touch-icon" sizes="76x76" href="../../assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="../../assets/img/favicon.png">
    <title>
        Luckym Slot
    </title>
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700,900|Roboto+Slab:400,700" />
    <!-- Nucleo Icons -->
    <link href="{{ asset('admin_app/assets/css/nucleo-icons.css')}}" rel="stylesheet" />
    <link href="{{ asset('admin_app/assets/css/nucleo-svg.css')}}" rel="stylesheet" />
    <!-- Font Awesome Icons -->
    <script src="https://kit.fontawesome.com/b829c5162c.js" crossorigin="anonymous"></script>
    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <!-- CSS Files -->
    <link id="pagestyle" href="{{ asset('admin_app/assets/css/material-dashboard.css?v=3.0.6')}}" rel="stylesheet" />

 <style>

 </style>
</head>

<body class="g-sidenav-show  bg-gray-200">


    <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg ">

        <div class="container-fluid py-4">
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">

                        <div class="card-header">
                            <h5 class="mb-0">Win/Lose Detail Report</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered data-table" id="datatable-basic" >
                                <thead>
                                    <tr>
                                        <th>Id</th>
                                        <th>Date</th>
                                        <th>PlayerId</th>
                                        <th>Product</th>
                                        <th>WagerID</th>
                                        <th>Bet Amount</th>
                                        <th>Valid Amount</th>
                                        <th>Payout Amount</th>
                                        <th>Win/Lose</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($reports as $report)
                                    <tr>
                                        <td>{{$loop->iteration}}</td>
                                        <td>{{$report->settlement_date}}</td>
                                        <td>{{$report->name}}</td>
                                        <td>{{$report->product_name}}</td>
                                        <td><a href="https://prodmd.9977997.com/Report/BetDetail?agentCode=E829&WagerID={{$report->wager_id}}"
                                                target="_blank" style="color: blueviolet; text-decoration: underline;">{{$report->wager_id}}</a></td>
                                        <td>{{$report->bet_amount}}</td>
                                        <td>{{$report->valid_bet_amount}}</td>
                                        <td>{{$report->payout_amount}}</td>
                                        <td>{{$report->win_or_lose}}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script src="https://kit.fontawesome.com/b829c5162c.js" crossorigin="anonymous"></script>
    <script src="{{ asset('admin_app/assets/js/core/popper.min.js')}}"></script>
    <script src="{{ asset('admin_app/assets/js/core/bootstrap.min.js')}}"></script>
    <script src="{{ asset('admin_app/assets/js/plugins/perfect-scrollbar.min.js')}}"></script>
    <script src="{{ asset('admin_app/assets/js/plugins/smooth-scrollbar.min.js')}}"></script>

    <script>
        var win = navigator.platform.indexOf('Win') > -1;
        if (win && document.querySelector('#sidenav-scrollbar')) {
            var options = {
                damping: '0.5'
            }
            Scrollbar.init(document.querySelector('#sidenav-scrollbar'), options);
        }
    </script>
    <!-- Github buttons -->
    <script async defer src="https://buttons.github.io/buttons.js"></script>
    <script src="{{ asset('admin_app/assets/js/plugins/datatables.js') }}"></script>

    <script>
        const dataTableBasic = new simpleDatatables.DataTable("#datatable-basic", {
            searchable: true,
            fixedHeight: true,
            perPage: 15
        });

        const dataTableSearch = new simpleDatatables.DataTable("#datatable-search", {
            searchable: true,
            fixedHeight: true
        });
    </script>
    <script>
        var win = navigator.platform.indexOf('Win') > -1;
        if (win && document.querySelector('#sidenav-scrollbar')) {
            var options = {
                damping: '0.5'
            }
            Scrollbar.init(document.querySelector('#sidenav-scrollbar'), options);
        }
    </script>


</body>

</html>