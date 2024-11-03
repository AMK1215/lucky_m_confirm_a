<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Lucky M</title>
    <script src="{{asset('admin_app/assets/js/jquery.min.js')}}"></script>
    <script src="{{asset('admin_app/assets/js/jquery.datatable.min.js')}}"></script>
    <script
        src="{{asset('admin_app/assets/js/dataTables.bootstrap.min.js')}}"></script>
    <link rel="stylesheet"
        href="{{asset('admin_app/assets/css/bootstrap.min.css')}}">
    <link rel="stylesheet"
        href="{{asset('admin_app/assets/css/dataTables.bootstrap.min.css')}}">
</head>
<style>
    .pagination {
        float: inline-end;
    }
</style>

<body>
    <div>
        <section>
            <h1>Player Detail Report</h1>
            <br>
            <table class="table table-bordered data-table">
                <thead>
                    <tr>
                        <th>Id</th>
                        <th>Date</th>
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
                        <td>{{$report->created_at}}</td>
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
            {{$reports->links()}}
    </div>
    </section>
    </div>
</body>

</html>