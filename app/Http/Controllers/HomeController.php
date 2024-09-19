<?php

namespace App\Http\Controllers;

use App\Enums\TransactionName;
use App\Models\Admin\UserLog;
use App\Models\SeamlessTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Services\WalletService;
use App\Settings\AppSetting;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('checkBanned');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $user = Auth::user();
        $isAdmin = $user->hasRole('Admin');
        $getUserCounts = $this->getUserCounts($isAdmin, $user);
        $agent_count = $getUserCounts('Agent');
        $player_count = $getUserCounts('Player');
        $totalDeposit = $this->getTotalDeposit();
        $totalWithdraw = $this->getTotalWithdraw();
        $todayDeposit = $this->getTodayDeposit();
        $todayWithdraw = $this->getTodayWithdraw();
        $provider_balance = (new AppSetting)->provider_initial_balance + SeamlessTransaction::sum('transaction_amount');

        return view('admin.dashboard', compact(
            'provider_balance',
            'agent_count',
            'player_count',
            'user',
            'totalDeposit',
            'totalWithdraw',
            'todayDeposit',
            'todayWithdraw'
        ));
    }

    public function balanceUp(Request $request)
    {
        abort_if(
            Gate::denies('admin_access'),
            Response::HTTP_FORBIDDEN,
            '403 Forbidden |You cannot Access this page because you do not have permission'
        );

        $request->validate([
            'balance' => 'required|numeric',
        ]);

        // Get the current user (admin)
        $admin = Auth::user();

        // Get the current balance before the update
        $openingBalance = $admin->wallet->balance;

        // Update the balance using the WalletService
        app(WalletService::class)->deposit($admin, $request->balance, TransactionName::CapitalDeposit);

        // Record the transaction in the transactions table
        Transaction::create([
            'payable_type' => get_class($admin),
            'payable_id' => $admin->id,
            'wallet_id' => $admin->wallet->id,
            'type' => 'deposit',
            'amount' => $request->balance,
            'confirmed' => true,
            'meta' => json_encode([
                'name' => TransactionName::CapitalDeposit,
                'opening_balance' => $openingBalance,
                'new_balance' => $admin->wallet->balance,
                'target_user_id' => $admin->id,
            ]),
            'uuid' => Str::uuid()->toString(),
        ]);

        return back()->with('success', 'Add New Balance Successfully.');
    }

    // public function balanceUp(Request $request)
    // {
    //     abort_if(
    //         Gate::denies('admin_access'),
    //         Response::HTTP_FORBIDDEN,
    //         '403 Forbidden |You cannot  Access this page because you do not have permission'
    //     );
    //     $request->validate([
    //         'balance' => 'required|numeric',
    //     ]);

    //     app(WalletService::class)->deposit($request->user(), $request->balance, TransactionName::CapitalDeposit);

    //     return back()->with('success', 'Add New Balance Successfully.');
    // }

    public function logs($id)
    {
        $logs = UserLog::with('user')->where('user_id', $id)->get();

        return view('admin.logs', compact('logs'));
    }

    private function getTodayWithdraw()
    {
        // Fetch today's deposits with the 'credit_transfer' name and 'deposit' type
        $withdraws = DB::table('transactions')
            ->where('name', 'debit_transfer')
            ->where('type', 'withdraw')
            ->whereDate('created_at', now()->toDateString())
            ->get();

        Log::info('getTodayWithdraw:', ['withdraws' => $withdraws]);

        // Summing up the 'amount' field
        $sum = $withdraws->sum('amount');
        Log::info('Today Withdraw Sum:', ['amount' => $sum]);

        return $sum;
    }

    private function getTodayDeposit()
    {
        // Fetch today's deposits with the 'credit_transfer' name and 'deposit' type
        $deposits = DB::table('transactions')
            ->where('name', 'credit_transfer')
            ->where('type', 'deposit')
            ->whereDate('created_at', now()->toDateString())
            ->get();

        Log::info('Today Deposits:', ['deposits' => $deposits]);

        // Summing up the 'amount' field
        $sum = $deposits->sum('amount');
        Log::info('Today Deposit Sum:', ['amount' => $sum]);

        return $sum;
    }

    private function getTotalWithdraw()
    {
        // Fetch all withdraw transactions with the 'debit_transfer' name and 'withdraw' type
        $withdrawals = DB::table('transactions')
            ->where('name', 'debit_transfer')
            ->where('type', 'withdraw')
            ->get();

        Log::info('Total Withdrawals:', ['withdrawals' => $withdrawals]);

        // Summing up the 'amount' field
        $sum = $withdrawals->sum('amount');
        Log::info('Total Withdrawal Sum:', ['amount' => $sum]);

        return $sum;
    }

    private function getTotalDeposit()
    {
        // Fetch all deposit transactions with the 'credit_transfer' name and 'deposit' type
        $deposits = DB::table('transactions')
            ->where('name', 'credit_transfer')
            ->where('type', 'deposit')
            ->get();

        Log::info('Total Deposits:', ['deposits' => $deposits]);

        // Summing up the 'amount' field
        $sum = $deposits->sum('amount');
        Log::info('Total Deposit Sum:', ['amount' => $sum]);

        return $sum;
    }

    // private function  getTodayWithdraw()
    // {
    //     return Auth::user()->transactions()->with('targetUser')->select(
    //             DB::raw('SUM(transactions.amount) as amount'))
    //             ->where('transactions.name', 'debit_transfer')
    //             ->where('transactions.type', 'withdraw')
    //             ->whereDate('created_at', date('Y-m-d'))
    //             ->first();
    // }

    // private  function getTodayDeposit()
    // {
    //     return Auth::user()->transactions()->with('targetUser')
    //             ->select(DB::raw('SUM(transactions.amount) as amount'))
    //             ->where('transactions.name', 'credit_transfer')
    //             ->where('transactions.type', 'deposit')
    //             ->whereDate('created_at', date('Y-m-d'))
    //             ->first();
    // }

    // private  function getTotalWithdraw()
    // {
    //     return Auth::user()->transactions()->with('targetUser')->select(
    //             DB::raw('SUM(transactions.amount) as amount'))
    //             ->where('transactions.name', 'debit_transfer')
    //             ->where('transactions.type', 'withdraw')
    //             ->first();
    // }

    // private  function getTotalDeposit()
    // {
    //     return Auth::user()->transactions()->with('targetUser')
    //             ->select(DB::raw('SUM(transactions.amount) as amount'))
    //             ->where('transactions.name', 'credit_transfer')
    //             ->where('transactions.type', 'deposit')
    //             ->first();
    // }

    private function getUserCounts($isAdmin, $user)
    {
        return function ($roleTitle) use ($isAdmin, $user) {
            return User::whereHas('roles', function ($query) use ($roleTitle) {
                $query->where('title', '=', $roleTitle);
            })->when(! $isAdmin, function ($query) use ($user) {
                $query->where('agent_id', $user->id);
            })->count();
        };
    }
}
