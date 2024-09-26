<?php

namespace App\Http\Controllers\Api\V1\Webhood\V2;

use Illuminate\Support\Facades\Redis;
use App\Enums\SlotWebhookResponseCode;
use App\Enums\TransactionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Slot\SlotWebhookRequest;
use App\Services\Slot\SlotWebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Api\V1\Webhood\V2\Traits\UseWebhookRedis;
use App\Models\User;
use App\Jobs\UpdateWalletBalanceInDatabase;

class PlaceBetRedisController extends Controller
{
    use UseWebhookRedis;

    public function placeBet(SlotWebhookRequest $request)
    {
        DB::beginTransaction();
        try {
            // Validate the request
            $validator = $request->check();
            if ($validator->fails()) {
                return $validator->getResponse();
            }

            $before_balance = $request->getMember()->balanceFloat;

            // Cache event in Redis
            $ttl = 600; // Time-to-live in seconds
            Redis::setex('event:' . $request->getMessageID(), $ttl, json_encode($request->all()));

            // Retrieve cached data from Redis (optional)
            $cachedData = Redis::get('event:' . $request->getMessageID());
            $cachedDataArray = json_decode($cachedData, true);

            // Create and store the event in the database
            $event = $this->createEvent($request);

            // Create wager transactions related to the event
            $seamless_transactions = $this->createWagerTransactions($validator->getRequestTransactions(), $event);

            // Process each seamless transaction
            foreach ($seamless_transactions as $seamless_transaction) {
                $this->processTransfer(
                    $request->getMember(),
                    User::adminUser(),
                    TransactionName::Stake,
                    $seamless_transaction->transaction_amount,
                    $seamless_transaction->rate,
                    [
                        'wager_id' => $seamless_transaction->wager_id,
                        'event_id' => $request->getMessageID(),
                        'seamless_transaction_id' => $seamless_transaction->id,
                    ]
                );
            }

            // Refresh balance after transactions
            $request->getMember()->wallet->refreshBalance();
            $after_balance = $request->getMember()->balanceFloat;

            DB::commit();

            // Return success response
            return SlotWebhookService::buildResponse(
                SlotWebhookResponseCode::Success,
                $after_balance,
                $before_balance
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error during placeBet', ['error' => $e->getMessage()]);

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get wallet balance, either from Redis or database.
     */
    public function getWalletBalance($userId)
    {
        $walletKey = "wallet_balance_user_{$userId}";

        // Try to get the balance from Redis
        $balance = Redis::get($walletKey);

        if ($balance === null) {
            // Fallback to MySQL if Redis doesn't have the balance
            $wallet = DB::table('wallets')->where('user_id', $userId)->first();
            if ($wallet) {
                $balance = $wallet->balance;
                // Store balance in Redis with a TTL of 10 minutes
                Redis::setex($walletKey, 600, $balance);
            }
        }

        return $balance;
    }

    /**
     * Update wallet balance in Redis and queue a job to update in MySQL.
     */
    public function updateWalletBalance($userId, $amount)
    {
        $walletKey = "wallet_balance_user_{$userId}";

        // Update the balance in Redis
        Redis::incrbyfloat($walletKey, $amount);

        // Dispatch a job to update the balance in MySQL asynchronously
        dispatch(new UpdateWalletBalanceInDatabase($userId, $amount));
    }
}