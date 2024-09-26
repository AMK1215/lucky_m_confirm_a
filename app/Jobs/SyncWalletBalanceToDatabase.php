<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;

class SyncWalletBalanceToDatabase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        // Get all user IDs
        $users = DB::table('users')->pluck('id');

        foreach ($users as $userId) {
            $walletKey = "wallet_balance_user_{$userId}";

            // Fetch balance from Redis
            $balance = Redis::get($walletKey);

            // Update balance in the database
            DB::table('wallets')->where('holder_id', $userId)->update(['balance' => $balance]);
        }
    }
}