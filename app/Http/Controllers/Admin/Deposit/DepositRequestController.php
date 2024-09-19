<?php

namespace App\Http\Controllers\Admin\Deposit;

use App\Enums\TransactionName;
use App\Http\Controllers\Controller;
use App\Models\DepositRequest;
use App\Models\User;
use App\Models\WithDrawRequest;
use App\Services\WalletService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DepositRequestController extends Controller
{
    public function index(Request $request)
    {
        $deposits = DepositRequest::with(['user', 'bank', 'agent'])
            ->where('agent_id', Auth::id())
            ->when($request->filled('status') && $request->input('status') !== 'all', function ($query) use ($request) {
                $query->where('status', $request->input('status'));
            })
            ->orderBy('id', 'desc')
            ->get();

        return view('admin.deposit_request.index', compact('deposits'));
    }

    public function statusChangeIndex(Request $request, DepositRequest $deposit)
    {
        try {
            $agent = Auth::user();
            $player = User::find($request->player);

            if ($request->status == 1 && $agent->balanceFloat < $request->amount) {
                return redirect()->back()->with('error', 'You do not have enough balance to transfer!');
            }

            $deposit->update([
                'status' => $request->status,
            ]);

            if ($request->status == 1) {
                app(WalletService::class)->transfer($agent, $player, $request->amount, TransactionName::DebitTransfer);
            }

            return redirect()->route('admin.agent.deposit')->with('success', 'Deposit status updated successfully!');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function statusChangeReject(Request $request, DepositRequest $deposit)
    {
        $request->validate([
            'status' => 'required|in:0,1,2',
        ]);

        try {
            // Update the deposit status
            $deposit->update([
                'status' => $request->status,
            ]);

            return redirect()->route('admin.agent.deposit')->with('success', 'Deposit status updated successfully!');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public  function view(DepositRequest $deposit)
    {
        return view('admin.deposit_request.view', compact('deposit'));
    }
}
