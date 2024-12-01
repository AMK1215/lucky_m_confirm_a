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

    <script defer data-site="https://delightmyanmar.online" src="https://api.nepcha.com/js/nepcha-analytics.js"></script>
    <style>
        .dataTable-wrapper .dataTable-container .table thead tr th{
            color: black;
        }
        .dataTable-table>thead>tr>th {
            border-bottom: 1px solid black;
        }
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
                            <form action="{{route('admin.report.index')}}" method="GET">
                                <div class="row">

                                    <div class="col-md-3">
                                        <div class="input-group input-group-static my-3">
                                            <label>Player</label>
                                            <input type="text" class="form-control" id="" value="{{request()->get('member_name')}}" name="member_name" >
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="input-group input-group-static my-3">
                                            <label>From</label>
                                            <input type="date" class="form-control" id="start_date" name="start_date" >
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="input-group input-group-static my-3">
                                            <label>To</label>
                                            <input type="date" class="form-control" id="to" name="end_date" >
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <button type="submit" class="btn btn-sm btn-primary mt-5">Search</button>
                                        <a href="{{route('admin.report.index')}}" class="btn btn-link text-primary ms-auto border-0" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Refresh" style="margin-top: 50px;">
                          <i class="material-icons text-lg">refresh</i>
                      </a>                                    </div>
                            </form>
                        </div>
                        <div class="table-responsive">
                        <table class="table table-flush" id="datatable-basic">
                <thead>
                    <tr>
                        <th rowspan="2">Account</th>
                        <th rowspan="2">Name</th>
                        <th rowspan="2">Bet Amount</th>
                        <th rowspan="2">Stake Count</th>
                        <th colspan="3">Member</th>
                        <th colspan="3">Downline</th>
                        <th colspan="3">Myself</th>
                        <th colspan="3">Upline</th>
                        <th colspan="3">Detail</th>
                    </tr>
                    <tr>
                        <th>W/L</th>
                        <th>Comm</th>
                        <th>Total</th>
                        <th>W/L</th>
                        <th>Comm</th>
                        <th>Total</th>
                        <th>W/L</th>
                        <th>Comm</th>
                        <th>Total</th>
                        <th>W/L</th>
                        <th>Comm</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($agentReports as $report)
                    <tr>
                        <td>{{ $report->user_name }}</td>
                        <td>{{ $report->member_name }}</td>
                        <td>{{ number_format($report->total_bet_amount, 2) }}</td>
                        <td>{{ $report->stake_count }}</td> <!-- Placeholder for stake count -->

                        <!-- Win/Loss for Member -->
                        <td class="{{ $report->win_or_lose < 0 ? 'lose' : 'win' }}">
                            {{ number_format($report->win_or_lose, 2) }}
                        </td>
                        <td>0 </td>
                        <td>{{ number_format($report->win_or_lose + $report->total_commission_amount, 2) }}</td> <!-- Member Total -->

                        <td>--</td> <!-- Downline W/L Placeholder -->
                        <td>0</td> <!-- Downline Comm Placeholder -->
                        <td>--</td> <!-- Downline Total Placeholder -->

                        <!-- Win/Loss for Myself -->
                        <td class="{{ $report->win_or_lose < 0 ? 'lose' : 'win' }}">
                            {{ number_format($report->win_or_lose, 2) }}
                        </td>
                        <td>0</td> <!-- Myself Comm -->
                        <td>{{ number_format($report->win_or_lose + $report->total_commission_amount, 2) }}</td> <!-- Myself Total -->

                        <!-- Win/Loss for Upline -->
                        <td class="{{ $report->win_or_lose < 0 ? 'lose' : 'win' }}">
                            {{ number_format($report->win_or_lose, 2) }}
                        </td>
                        <td>0</td> <!-- Upline Comm -->
                        <td>{{ number_format($report->win_or_lose + $report->total_commission_amount, 2) }}</td> <!-- Upline Total -->
                        <td>
                            <a href="{{ route('admin.report.detail', $report->user_name) }}" class="btn btn-info btn-sm">
                             Detail
                            </a>
                        </td>
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
            fixedHeight: true
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