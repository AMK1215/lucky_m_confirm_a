<?php

namespace App\Services\Slot;

use App\Enums\SlotWebhookResponseCode;
use App\Http\Requests\Slot\SlotWebhookRequest;
use App\Models\SeamlessTransaction;
use App\Models\Wager;
use App\Services\Slot\Dto\RequestTransaction;
use App\Models\Admin\GameType;
use App\Models\Admin\Product;
use Exception;
use App\Models\Admin\GameTypeProduct;
use Illuminate\Support\Facades\Log; // Ensure this is included to use the Log facade

class SlotWebhookValidator
{
    protected ?SeamlessTransaction $existingTransaction;

    // TODO: imp: chang with actual wager
    protected ?Wager $existingWager;

    protected float $totalTransactionAmount = 0;

    protected float $before_balance;

    protected float $after_balance;

    protected array $response;

    /**
     * @var RequestTransaction[]
     */
    protected $requestTransactions;

    protected function __construct(protected SlotWebhookRequest $request) {}

    public function validate()
    {
        if (! $this->isValidSignature()) {
            return $this->response(SlotWebhookResponseCode::InvalidSign);
        }

        if (! $this->request->getMember()) {
            return $this->response(SlotWebhookResponseCode::MemberNotExists);
        }

        $this->getFullTransactions();

        if (! $this->hasEnoughBalance()) {
            return $this->response(SlotWebhookResponseCode::MemberInsufficientBalance);
        }

        return $this;
    }

    protected function isValidSignature()
    {
        $method = $this->request->getMethodName();
        $operatorCode = $this->request->getOperatorCode();
        $requestTime = $this->request->getRequestTime();

        $secretKey = $this->getSecretKey();

        $signature = md5($operatorCode.$requestTime.$method.$secretKey);

        return $this->request->getSign() == $signature;
    }

    protected function isNewWager(RequestTransaction $transaction)
    {
        return ! $this->getExistingWager($transaction);
    }

    public function getExistingWager(RequestTransaction $transaction)
    {
        if (! isset($this->existingWager)) {
            $this->existingWager = Wager::where('seamless_wager_id', $transaction->WagerID)->first();
        }

        return $this->existingWager;
    }

    protected function isNewTransaction(RequestTransaction $transaction)
    {
        return ! $this->getExistingTransaction($transaction);
    }

    public function getExistingTransaction(RequestTransaction $transaction)
    {
        if (! isset($this->existingTransaction)) {
            $this->existingTransaction = SeamlessTransaction::where('seamless_transaction_id', $transaction->TransactionID)->first();
        }

        return $this->existingTransaction;
    }

    public function getAfterBalance()
    {
        if (! isset($this->after_balance)) {
            $this->after_balance = $this->getBeforeBalance() + $this->totalTransactionAmount;
        }

        return $this->after_balance;
    }

    public function getBeforeBalance()
    {
        if (! isset($this->before_balance)) {
            $this->before_balance = $this->request->getMember()->balanceFloat;
        }

        return $this->before_balance;
    }

    protected function hasEnoughBalance()
    {
        return $this->getAfterBalance() >= 0;
    }

    public function getRequestTransactions()
    {
        return $this->requestTransactions;
    }

    protected function getSecretKey()
    {
        return config('game.api.secret_key');
    }

    protected function response(SlotWebhookResponseCode $responseCode)
    {
        $this->response = SlotWebhookService::buildResponse(
            $responseCode,
            $this->request->getMember() ? $this->getAfterBalance() : 0,
            $this->request->getMember() ? $this->getBeforeBalance() : 0
        );

        return $this;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function fails()
    {
        return isset($this->response);
    }

    public static function make(SlotWebhookRequest $request)
    {
        return new self($request);
    }


protected function getFullTransactions()
{
    $transactions = $this->request->getTransactions();

    Log::debug("Retrieved transactions: ", ['Transactions' => $transactions]);

    // Validate that we're working with multiple transactions
    if (!is_array($transactions)) {
        Log::error("Transactions must be an array.");
        throw new Exception("Transactions must be an array.");
    }

    foreach ($transactions as $transaction) {
        // Log the transaction details before processing
        Log::debug("Processing transaction: ", ['Transaction' => $transaction]);

        // Find game type and product based on the transaction details
        $game_type = GameType::where('code', $transaction['GameType'])->first();
        $product = Product::where('code', $transaction['ProductID'])->first();

        // Log the fetched game type and product
        Log::debug("Fetched GameType: ", ['GameType' => $game_type]);
        Log::debug("Fetched Product: ", ['Product' => $product]);

        // Check if both the game type and product were found
        if (!$game_type || !$product) {
            Log::error("Product or GameType not found.", ['ProductID' => $transaction['ProductID'], 'GameType' => $transaction['GameType']]);
            throw new Exception("Product or GameType not found for ProductID: " . $transaction['ProductID'] . " and GameType: " . $transaction['GameType']);
        }

        // Check if the GameType-Product relationship exists
        $game_type_product = GameTypeProduct::where('game_type_id', $game_type->id)
            ->where('product_id', $product->id)
            ->first();

        // Log the fetched GameTypeProduct relationship
        Log::debug("Fetched GameTypeProduct: ", ['GameTypeProduct' => $game_type_product]);

        if (!$game_type_product) {
            Log::error("No matching GameTypeProduct found.", ['ProductID' => $transaction['ProductID'], 'GameType' => $transaction['GameType']]);
            throw new Exception("No matching GameTypeProduct found for ProductID: " . $transaction['ProductID'] . " and GameType: " . $transaction['GameType']);
        }

        // Assign values to the transaction
        $transaction['Rate'] = $game_type_product->rate; // or 1.0000 if you prefer a fixed rate
        $transaction['ActualGameTypeID'] = $game_type->id;
        $transaction['ActualProductID'] = $product->id;

        // Log the final transaction details before conversion
        Log::debug("Final Transaction Details: ", ['Transaction' => $transaction]);

        // Convert the transaction and add it to requestTransactions
        $requestTransaction = RequestTransaction::from($transaction);
        $this->requestTransactions[] = $requestTransaction;

        // Check for duplicate transactions
        if ($requestTransaction->TransactionID && !$this->isNewTransaction($requestTransaction)) {
            Log::info("Duplicate Transaction detected: ", ['TransactionID' => $requestTransaction->TransactionID]);
            return $this->response(SlotWebhookResponseCode::DuplicateTransaction);
        }

        // Check for wagers, if necessary
        if (!in_array($this->request->getMethodName(), ['placebet', 'bonus', 'jackpot', 'buyin', 'buyout', 'pushbet']) && $this->isNewWager($requestTransaction)) {
            Log::info("Bet not found for wager: ", ['TransactionID' => $requestTransaction->TransactionID]);
            return $this->response(SlotWebhookResponseCode::BetNotExist);
        }

        // Add to the total transaction amount
        $this->totalTransactionAmount += $requestTransaction->TransactionAmount;

        // Log the updated total transaction amount
        Log::debug("Updated totalTransactionAmount: ", ['TotalAmount' => $this->totalTransactionAmount]);
    }
}


//     protected function getFullTransactions()
// {
//     $transactions = $this->request->getTransactions();

//     // Validate that we're working with multiple transactions
//     if (!is_array($transactions)) {
//         throw new Exception("Transactions must be an array.");
//     }

//     foreach ($transactions as $transaction) {
//         // Find game type and product based on the transaction details
//         $game_type = GameType::where('code', $transaction['GameType'])->first();
//         $product = Product::where('code', $transaction['ProductID'])->first();

//         // Check if both the game type and product were found
//         if (!$game_type || !$product) {
//             throw new Exception("Product or GameType not found for ProductID: " . $transaction['ProductID'] . " and GameType: " . $transaction['GameType']);
//         }

//         // Check if the GameType-Product relationship exists
//         $game_type_product = GameTypeProduct::where('game_type_id', $game_type->id)
//             ->where('product_id', $product->id)
//             ->first();

//         if (!$game_type_product) {
//             throw new Exception("No matching GameTypeProduct found for ProductID: " . $transaction['ProductID'] . " and GameType: " . $transaction['GameType']);
//         }

//         // Assign values to the transaction
//         $transaction['Rate'] = $game_type_product->rate; // or 1.0000 if you prefer a fixed rate
//         $transaction['ActualGameTypeID'] = $game_type->id;
//         $transaction['ActualProductID'] = $product->id;

//         // Convert the transaction and add it to requestTransactions
//         $requestTransaction = RequestTransaction::from($transaction);
//         $this->requestTransactions[] = $requestTransaction;

//         // Check for duplicate transactions
//         if ($requestTransaction->TransactionID && !$this->isNewTransaction($requestTransaction)) {
//             return $this->response(SlotWebhookResponseCode::DuplicateTransaction);
//         }

//         // Check for wagers, if necessary
//         if (!in_array($this->request->getMethodName(), ['placebet', 'bonus', 'jackpot', 'buyin', 'buyout', 'pushbet']) && $this->isNewWager($requestTransaction)) {
//             return $this->response(SlotWebhookResponseCode::BetNotExist);
//         }

//         // Add to the total transaction amount
//         $this->totalTransactionAmount += $requestTransaction->TransactionAmount;
//     }
// }


    // protected function getFullTransactions()
    // {
    //     $transactions = $this->request->getTransactions();
    //     //$game_type_codes_array = array_column($transactions, 'GameType');
    //     $game_type_ids_array = GameType::where('code', $transactions->GameType)->first();

    //     //$game_type_ids_array = GameType::whereIn('code', $game_type_codes_array)->pluck('id')->toArray();

    //    // $product_codes_array = array_column($transactions, 'ProductID');
    //     $product_id_array = Product::where('code', $transactions->ProductID)->first();

    //     //$product_id_array = Product::whereIn('code', $product_codes_array)->pluck('id')->toArray();
    //     // if id arrays length are not equal to transactions length, then throw exception
    //     if (count($game_type_ids_array) != count($transactions) || count($product_id_array) != count($transactions)) {
    //         throw new Exception("Product or GameType not found.");
    //     }

    //     foreach ($transactions as $key => $transaction) {
    //         $game_type_product = GameTypeProduct::where('game_type_id', $game_type_ids_array[$key])
    //             ->where('product_id', $product_id_array[$key])
    //             ->first();
    //         if (!$game_type_product) {
    //             throw new Exception("Product or GameType not found for {" . $transaction['ProductID'] . " " . $transaction['GameType'] . "}");
    //         }
    //         //$transaction['Rate'] = $game_type_product->rate;
    //         $transaction['Rate'] = 1.0000;

    //         $transaction['ActualGameTypeID'] = $game_type_ids_array[$key];
    //         $transaction['ActualProductID'] = $product_id_array[$key];

    //         $requestTransaction = RequestTransaction::from($transaction);

    //         $this->requestTransactions[] = $requestTransaction;

    //         if ($requestTransaction->TransactionID && ! $this->isNewTransaction($requestTransaction)) {
    //             return $this->response(SlotWebhookResponseCode::DuplicateTransaction);
    //         }

    //         if (! in_array($this->request->getMethodName(), ['placebet', 'bonus', 'jackpot', 'buyin', 'buyout', 'pushbet']) && $this->isNewWager($requestTransaction)) {
    //             return $this->response(SlotWebhookResponseCode::BetNotExist);
    //         }

    //         $this->totalTransactionAmount += $requestTransaction->TransactionAmount;
    //     }
    // }
}
// class SlotWebhookValidator
// {
//     protected ?SeamlessTransaction $existingTransaction;

//     // TODO: imp: chang with actual wager
//     protected ?Wager $existingWager;

//     protected float $totalTransactionAmount = 0;

//     protected float $before_balance;

//     protected float $after_balance;

//     protected array $response;

//     /**
//      * @var RequestTransaction[]
//      */
//     protected $requestTransactions;

//     protected function __construct(protected SlotWebhookRequest $request) {}

//     public function validate()
//     {
//         if (! $this->isValidSignature()) {
//             return $this->response(SlotWebhookResponseCode::InvalidSign);
//         }

//         if (! $this->request->getMember()) {
//             return $this->response(SlotWebhookResponseCode::MemberNotExists);
//         }

//         foreach ($this->request->getTransactions() as $transaction) {
//             $requestTransaction = RequestTransaction::from($transaction);

//             $this->requestTransactions[] = $requestTransaction;

//             if ($requestTransaction->TransactionID && ! $this->isNewTransaction($requestTransaction)) {
//                 return $this->response(SlotWebhookResponseCode::DuplicateTransaction);
//             }

//             if (! in_array($this->request->getMethodName(), ['placebet', 'bonus', 'jackpot', 'buyin', 'buyout', 'pushbet']) && $this->isNewWager($requestTransaction)) {
//                 return $this->response(SlotWebhookResponseCode::BetNotExist);
//             }

//             $this->totalTransactionAmount += $requestTransaction->TransactionAmount;
//         }

//         if (! $this->hasEnoughBalance()) {
//             return $this->response(SlotWebhookResponseCode::MemberInsufficientBalance);
//         }

//         return $this;
//     }

//     protected function isValidSignature()
//     {
//         $method = $this->request->getMethodName();
//         $operatorCode = $this->request->getOperatorCode();
//         $requestTime = $this->request->getRequestTime();

//         $secretKey = $this->getSecretKey();

//         $signature = md5($operatorCode.$requestTime.$method.$secretKey);

//         return $this->request->getSign() == $signature;
//     }

//     protected function isNewWager(RequestTransaction $transaction)
//     {
//         return ! $this->getExistingWager($transaction);
//     }

//     public function getExistingWager(RequestTransaction $transaction)
//     {
//         if (! isset($this->existingWager)) {
//             $this->existingWager = Wager::where('seamless_wager_id', $transaction->WagerID)->first();
//         }

//         return $this->existingWager;
//     }

//     protected function isNewTransaction(RequestTransaction $transaction)
//     {
//         return ! $this->getExistingTransaction($transaction);
//     }

//     public function getExistingTransaction(RequestTransaction $transaction)
//     {
//         if (! isset($this->existingTransaction)) {
//             $this->existingTransaction = SeamlessTransaction::where('seamless_transaction_id', $transaction->TransactionID)->first();
//         }

//         return $this->existingTransaction;
//     }

//     public function getAfterBalance()
//     {
//         if (! isset($this->after_balance)) {
//             $this->after_balance = $this->getBeforeBalance() + $this->totalTransactionAmount;
//         }

//         return $this->after_balance;
//     }

//     public function getBeforeBalance()
//     {
//         if (! isset($this->before_balance)) {
//             $this->before_balance = $this->request->getMember()->balanceFloat;
//         }

//         return $this->before_balance;
//     }

//     protected function hasEnoughBalance()
//     {
//         return $this->getAfterBalance() >= 0;
//     }

//     public function getRequestTransactions()
//     {
//         return $this->requestTransactions;
//     }

//     protected function getSecretKey()
//     {
//         return config('game.api.secret_key');
//     }

//     protected function response(SlotWebhookResponseCode $responseCode)
//     {
//         $this->response = SlotWebhookService::buildResponse(
//             $responseCode,
//             $this->request->getMember() ? $this->getAfterBalance() : 0,
//             $this->request->getMember() ? $this->getBeforeBalance() : 0
//         );

//         return $this;
//     }

//     public function getResponse()
//     {
//         return $this->response;
//     }

//     public function fails()
//     {
//         return isset($this->response);
//     }

//     public static function make(SlotWebhookRequest $request)
//     {
//         return new self($request);
//     }
// }