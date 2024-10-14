<?php
namespace App\Http\Controllers\Api\V1\Webhook\Traits;

use Exception;
use App\Models\User;
use App\Models\Wager;
use App\Enums\WagerStatus;
use App\Models\Admin\Product;
use App\Models\SeamlessEvent;
use App\Enums\TransactionName;
use App\Models\Admin\GameType;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\GameTypeProduct;
use Illuminate\Support\Facades\Redis;
use App\Http\Requests\Slot\SlotWebhookRequest;

trait OptimizedBettingProcess
{
    public function placeBet(SlotWebhookRequest $request)
    {
        $userId = $request->getMember()->id;

        // Try to acquire a Redis lock for the user's wallet
        $lock = Redis::set("wallet:lock:$userId", true, 'EX', 10, 'NX');  // 10-second lock

        if (!$lock) {
            return response()->json(['message' => 'The wallet is currently being updated. Please try again later.'], 409);
        }

        DB::beginTransaction();
        try {
            // Validate the request
            $validator = $request->check();
            if ($validator->fails()) {
                Redis::del("wallet:lock:$userId");
                return $validator->getResponse();
            }

            $before_balance = $request->getMember()->balanceFloat;

            // Create and store the event in the database
            $event = $this->createEvent($request);

            // Create wagers and seamless transactions with retry logic
            $seamless_transactions = $this->retryOnDeadlock(function () use ($validator, $event) {
                return $this->createWagerTransactions($validator->getRequestTransactions(), $event);
            });

            // Process each seamless transaction and ensure wager_id is present
            foreach ($seamless_transactions as $seamless_transaction) {
                if (isset($seamless_transaction->wager_id)) {
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
                } else {
                    throw new Exception("wager_id is missing for seamless transaction.");
                }
            }

            // Refresh balance after transactions
            $request->getMember()->wallet->refreshBalance();
            $after_balance = $request->getMember()->balanceFloat;

            DB::commit();
            Redis::del("wallet:lock::$userId");

            return response()->json([
                'balance_before' => $before_balance,
                'balance_after' => $after_balance,
                'message' => 'Bet placed successfully.',
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            Redis::del("wallet:lock::$userId");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Create seamless transactions with correct wager_id
     */
    public function createWagerTransactions($requestTransactions, SeamlessEvent $event, bool $refund = false)
    {
        $wagerData = [];
        $seamless_transactions = [];

        foreach ($requestTransactions as $requestTransaction) {
            // Collect data for batch insert
            $wagerData[] = [
                'user_id' => $event->user_id,
                'seamless_wager_id' => $requestTransaction->WagerID,
                'status' => $refund ? WagerStatus::Refund : ($requestTransaction->TransactionAmount > 0 ? WagerStatus::Win : WagerStatus::Lose),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Perform batch insert for wagers and get the inserted wagers with IDs
        $wagerIds = DB::table('wagers')->insertGetId($wagerData);

        // Now create seamless transactions
        foreach ($requestTransactions as $requestTransaction) {
            $game_type = GameType::where('code', $requestTransaction->GameType)->firstOrFail();
            $product = Product::where('code', $requestTransaction->ProductID)->firstOrFail();
            $rate = GameTypeProduct::where('game_type_id', $game_type->id)
                ->where('product_id', $product->id)
                ->firstOrFail()->rate;

            // Ensure wager_id is passed to seamless transactions
            $seamless_transactions[] = $event->transactions()->create([
                'user_id' => $event->user_id,
                'wager_id' => $wagerIds, // Make sure this is the correct wager_id
                'game_type_id' => $game_type->id,
                'product_id' => $product->id,
                'seamless_transaction_id' => $requestTransaction->TransactionID,
                'rate' => $rate,
                'transaction_amount' => $requestTransaction->TransactionAmount,
                'bet_amount' => $requestTransaction->BetAmount,
                'valid_amount' => $requestTransaction->ValidBetAmount,
                'status' => $requestTransaction->Status,
            ]);
        }

        return $seamless_transactions;
    }

    /**
     * Process the wallet transfer, handling deadlock retries.
     */
    public function processTransfer(User $from, User $to, TransactionName $transactionName, float $amount, int $rate, array $meta)
    {
        return $this->retryOnDeadlock(function () use ($from, $to, $transactionName, $amount, $rate, $meta) {
            DB::transaction(function () use ($from, $to, $transactionName, $amount, $rate, $meta) {
                app(WalletService::class)->transfer($from, $to, abs($amount), $transactionName, $meta);
            });
        });
    }


    /**
     * Retry logic for handling deadlocks with exponential backoff.
     */
    private function retryOnDeadlock(callable $callback, $maxRetries = 5)
    {
        $retryCount = 0;

        do {
            try {
                return $callback();
            } catch (\Illuminate\Database\QueryException $e) {
                if ($e->getCode() === '40001') {  // Deadlock error code
                    $retryCount++;
                    if ($retryCount >= $maxRetries) {
                        throw $e;  // Max retries reached, fail
                    }
                    sleep(pow(2, $retryCount));  // Exponential backoff
                } else {
                    throw $e;  // Rethrow non-deadlock exceptions
                }
            }
        } while ($retryCount < $maxRetries);
    }

    /**
     * Create the event in the system.
     */
    public function createEvent(SlotWebhookRequest $request): SeamlessEvent
    {
        return SeamlessEvent::create([
            'user_id' => $request->getMember()->id,
            'message_id' => $request->getMessageID(),
            'product_id' => $request->getProductID(),
            'request_time' => $request->getRequestTime(),
            'raw_data' => $request->all(),
        ]);
    }
}