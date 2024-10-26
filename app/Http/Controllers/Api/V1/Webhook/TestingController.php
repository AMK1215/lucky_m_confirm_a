<?php

namespace App\Http\Controllers\Api\V1\Webhook;

use App\Enums\TransactionName;
use App\Http\Controllers\Controller;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TestingController extends Controller
{
    public function AppGetGameList(Request $request)
    {
        try {
            // Validate the request input
            $request->validate([
                'balance' => 'required|numeric',
            ]);

            // Fetch the wallet with ID 174
            $wallet = DB::table('wallets')->where('id', 3)->first();

            if (! $wallet) {
                return response()->json(['error' => 'Wallet ID 53 not found.'], 404);
            }

            // Assuming that your wallets table has a holder_id column that links to the users table
            $user = \App\Models\User::find($wallet->holder_id);

            if (! $user) {
                return response()->json(['error' => 'User not found for wallet holder.'], 404);
            }

            // Call WalletService deposit method with the correct user object
            app(WalletService::class)->deposit($user, $request->balance, TransactionName::JackPot);

            return response()->json(['success' => 'Balance updated successfully for wallet ID 53.'], 200);

        } catch (\Exception $e) {
            // Catch any errors and return a server error response
            return response()->json(['error' => 'An error occurred: '.$e->getMessage()], 500);
        }
    }

    public function withdrawAmount(Request $request)
    {
        try {
            // Validate the request input
            $request->validate([
                'amount' => 'required|numeric|min:1',
            ]);

            // Fetch the user with ID 63
            $user = \App\Models\User::find(63);

            if (! $user) {
                return response()->json(['error' => 'User ID 63 not found.'], 404);
            }

            // Fetch the system/admin account (to transfer withdrawn funds to)
            $adminUser = \App\Models\User::find(1); // Assuming user ID 1 is the admin/system

            if (! $adminUser) {
                return response()->json(['error' => 'Admin account not found.'], 404);
            }

            // Call WalletService transfer method to withdraw the amount
            app(WalletService::class)->transfer($user, $adminUser, $request->amount, TransactionName::Cancel);

            return response()->json(['success' => 'Amount withdrawn successfully from user ID 63.'], 200);

        } catch (\Exception $e) {
            // Catch any errors and return a server error response
            return response()->json(['error' => 'An error occurred: '.$e->getMessage()], 500);
        }
    }
}
