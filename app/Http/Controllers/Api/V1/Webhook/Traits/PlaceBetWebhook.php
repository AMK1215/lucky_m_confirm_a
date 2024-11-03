<?php

namespace App\Http\Controllers\Api\V1\Webhook\Traits;

use App\Enums\TransactionName;
use App\Enums\WagerStatus;
use App\Http\Requests\Slot\SlotWebhookRequest;
use App\Models\SeamlessEvent;
use App\Models\User;
use App\Models\Wager;
use App\Services\WalletService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

trait PlaceBetWebhook
{
    public function placeBet(SlotWebhookRequest $request)
    {
        $userId = $request->getMember()->id;
        $lock = Redis::set("wallet:lock:$userId", true, 'EX', 10, 'NX');  // Redis lock

        if (!$lock) {
            return response()->json(['message' => 'The wallet is currently being updated. Please try again later.'], 409);
        }

        DB::beginTransaction();
        try {
            $validator = $request->check();
            if ($validator->fails()) {
                Redis::del("wallet:lock:$userId");
                return $validator->getResponse();
            }

            $before_balance = $request->getMember()->balanceFloat;
            $event = $this->createEvent($request);

            $seamless_transactions = $this->retryOnDeadlock(function () use ($validator, $event) {
                return $this->createWagerTransactions($validator->getRequestTransactions(), $event);
            });

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

    public function processTransfer(User $from, User $to, TransactionName $transactionName, float $amount, int $rate, array $meta)
    {
        $retryCount = 0;
        $maxRetries = 5;

        do {
            try {
                DB::transaction(function () use ($from, $to, $amount, $transactionName, $meta) {
                    $walletFrom = $from->wallet()->lockForUpdate()->firstOrFail();
                    $walletTo = $to->wallet()->lockForUpdate()->firstOrFail();

                    $walletFrom->balance -= $amount;
                    $walletTo->balance += $amount;

                    $walletFrom->save();
                    $walletTo->save();

                    app(WalletService::class)->transfer($from, $to, abs($amount), $transactionName, $meta);
                });
                break;
            } catch (\Illuminate\Database\QueryException $e) {
                if ($e->getCode() === '40001') {
                    $retryCount++;
                    if ($retryCount >= $maxRetries) throw $e;
                    sleep(pow(2, $retryCount));
                } else {
                    throw $e;
                }
            }
        } while ($retryCount < $maxRetries);
    }

    public function insertBets(array $bets, SeamlessEvent $event)
    {
        $chunkSize = 50; // Define the chunk size
        $batches = array_chunk($bets, $chunkSize);

        $userId = $event->user_id; // Get user_id from SeamlessEvent

        // Process chunks in a transaction to ensure data integrity
        DB::transaction(function () use ($batches, $event) {
            foreach ($batches as $batch) {
                // Call createWagerTransactions for each batch
                $this->createWagerTransactions($batch, $event);
            }
        });

        return count($bets).' bets inserted successfully.';
    }


    public function createWagerTransactions(array $betBatch, SeamlessEvent $event)
    {
        $retryCount = 0;
        $maxRetries = 5;
        $userId = $event->user_id;
        $seamlessEventId = $event->id;

        do {
            try {
                DB::transaction(function () use ($betBatch, $userId, $seamlessEventId) {
                    $wagerData = [];
                    $seamlessTransactionsData = [];

                    foreach ($betBatch as $transaction) {
                        if ($transaction instanceof \App\Services\Slot\Dto\RequestTransaction) {
                            $transactionData = [
                                'Status' => $transaction->Status,
                                'ProductID' => $transaction->ProductID,
                                'GameType' => $transaction->GameType,
                                'TransactionID' => $transaction->TransactionID,
                                'WagerID' => $transaction->WagerID,
                                'BetAmount' => $transaction->BetAmount,
                                'TransactionAmount' => $transaction->TransactionAmount,
                                'PayoutAmount' => $transaction->PayoutAmount,
                                'ValidBetAmount' => $transaction->ValidBetAmount,
                                'Rate' => $transaction->Rate,
                                'ActualGameTypeID' => $transaction->ActualGameTypeID,
                                'ActualProductID' => $transaction->ActualProductID,
                            ];
                        } else {
                            throw new \Exception('Invalid transaction data format.');
                        }

                        $existingWager = Wager::where('seamless_wager_id', $transactionData['WagerID'])->lockForUpdate()->first();

                        if (!$existingWager) {
                            $wagerData[] = [
                                'user_id' => $userId,
                                'seamless_wager_id' => $transactionData['WagerID'],
                                'status' => $transactionData['TransactionAmount'] > 0 ? WagerStatus::Win : WagerStatus::Lose,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }

                        $seamlessTransactionsData[] = [
                            'user_id' => $userId,  // Use user_id from the SeamlessEvent
                            'wager_id' => $existingWager ? $existingWager->id : null,
                            'game_type_id' => $transactionData['ActualGameTypeID'],
                            'product_id' => $transactionData['ActualProductID'],
                            'seamless_transaction_id' => $transactionData['TransactionID'],
                            'rate' => $transactionData['Rate'],
                            'transaction_amount' => $transactionData['TransactionAmount'],
                            'bet_amount' => $transactionData['BetAmount'],
                            'valid_amount' => $transactionData['ValidBetAmount'],
                            'status' => $transactionData['Status'],
                            'seamless_event_id' => $seamlessEventId,  // Include seamless_event_id
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }

                    if (!empty($wagerData)) {
                        DB::table('wagers')->insert($wagerData);
                    }
                    if (!empty($seamlessTransactionsData)) {
                        DB::table('seamless_transactions')->insert($seamlessTransactionsData);
                    }
                });
                break;
            } catch (\Illuminate\Database\QueryException $e) {
                if ($e->getCode() === '40001') {
                    $retryCount++;
                    if ($retryCount >= $maxRetries) throw $e;
                    sleep(pow(2, $retryCount));
                } else {
                    throw $e;
                }
            }
        } while ($retryCount < $maxRetries);
    }

    private function retryOnDeadlock(callable $callback, $maxRetries = 5)
    {
        $retryCount = 0;
        do {
            try {
                return $callback();
            } catch (\Illuminate\Database\QueryException $e) {
                if ($e->getCode() === '40001') {
                    $retryCount++;
                    if ($retryCount >= $maxRetries) throw $e;
                    sleep(pow(2, $retryCount));
                } else {
                    throw $e;
                }
            }
        } while ($retryCount < $maxRetries);
    }

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