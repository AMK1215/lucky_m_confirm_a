<?php

use App\Http\Controllers\Api\V1\AgentLogoController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Bank\BankController;
use App\Http\Controllers\Api\V1\BannerController;
use App\Http\Controllers\Api\V1\Game\DirectLaunchGameController;
use App\Http\Controllers\Api\V1\Game\GameController;
use App\Http\Controllers\Api\V1\Game\LaunchGameController;
use App\Http\Controllers\Api\V1\NewVersion\PlaceBetNewVersionController;
use App\Http\Controllers\Api\V1\NewVersion\PlaceBetWebhookController;
use App\Http\Controllers\Api\V1\Player\DepositRequestController;
use App\Http\Controllers\Api\V1\Player\PaymentTypeController;
use App\Http\Controllers\Api\V1\Player\PlayerTransactionLogController;
use App\Http\Controllers\Api\V1\Player\TransactionController;
use App\Http\Controllers\Api\V1\Player\WagerController;
use App\Http\Controllers\Api\V1\Player\WithDrawRequestController;
use App\Http\Controllers\Api\V1\PromotionController;
use App\Http\Controllers\Api\V1\Webhook\BonusController;
use App\Http\Controllers\Api\V1\Webhook\BuyInController;
use App\Http\Controllers\Api\V1\Webhook\BuyOutController;
use App\Http\Controllers\Api\V1\Webhook\CancelBetController;
use App\Http\Controllers\Api\V1\Webhook\GameResultController;
use App\Http\Controllers\Api\V1\Webhook\GetBalanceController;
use App\Http\Controllers\Api\V1\Webhook\JackPotController;
use App\Http\Controllers\Api\V1\Webhook\MobileLoginController;
use App\Http\Controllers\Api\V1\Webhook\NewBonusController;
use App\Http\Controllers\Api\V1\Webhook\NewJackpotController;
// use App\Http\Controllers\Api\V1\Webhook\NewBonusController;
// use App\Http\Controllers\Api\V1\Webhook\NewJackpotController;
use App\Http\Controllers\Api\V1\Webhook\PushBetController;
use App\Http\Controllers\Api\V1\Webhook\RollbackController;
use App\Http\Controllers\Api\V1\Webhook\TestingController;
use Illuminate\Support\Facades\Route;
// use App\Http\Controllers\Api\V1\NewVersion\NewBonusController;
// use App\Http\Controllers\Api\V1\NewVersion\NewJackpotController;


//login route post
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/player-change-password', [AuthController::class, 'playerChangePassword']);
Route::post('Seamless/Test', [TestingController::class, 'AppGetGameList']);
Route::post('Seamless/withdraw', [TestingController::class, 'withdrawAmount']);

// logout

Route::post('/logout', [AuthController::class, 'logout']);
Route::get('promotion', [PromotionController::class, 'index']);
Route::get('banner', [BannerController::class, 'index']);
Route::get('bannerText', [BannerController::class, 'bannerText']);
Route::get('popup-ads-banner', [BannerController::class, 'AdsBannerIndex']);

Route::get('v1/validate', [AuthController::class, 'callback']);
Route::get('gameTypeProducts/{id}', [GameController::class, 'gameTypeProducts']);
Route::get('allGameProducts', [GameController::class, 'allGameProducts']);
Route::get('gameType', [GameController::class, 'gameType']);
Route::get('hotgamelist', [GameController::class, 'HotgameList']);
Route::get('payment-type', [PaymentTypeController::class, 'get']);
Route::post('Seamless/PullReport', [LaunchGameController::class, 'pullReport']);

Route::group(['prefix' => 'Seamless'], function () {
    Route::post('GetBalance', [GetBalanceController::class, 'getBalance']);

    // Route::group(["middleware" => ["webhook_log"]], function(){
    Route::post('GetGameList', [LaunchGameController::class, 'getGameList']);
    Route::post('GameResult', [GameResultController::class, 'gameResult']);
    Route::post('Rollback', [RollbackController::class, 'rollback']);
    Route::post('PlaceBet', [PlaceBetNewVersionController::class, 'placeBetNew']);
    //Route::post('PlaceBet', [PlaceBetWebhookController::class, 'placeBetNew']);

    Route::post('CancelBet', [CancelBetController::class, 'cancelBet']);
    Route::post('BuyIn', [BuyInController::class, 'buyIn']);
    Route::post('BuyOut', [BuyOutController::class, 'buyOut']);
    Route::post('PushBet', [PushBetController::class, 'pushBet']);
    //Route::post('Bonus', [BonusController::class, 'bonus']);
    Route::post('Bonus', [NewBonusController::class, 'bonus']);
    //Route::post('Jackpot', [JackPotController::class, 'jackPot']);
    Route::post('Jackpot', [NewJackpotController::class, 'jackPot']);

    Route::post('MobileLogin', [MobileLoginController::class, 'MobileLogin']);
    // });
});

Route::group(['middleware' => ['auth:sanctum', 'checkBanned']], function () {
    Route::get('wager-logs', [WagerController::class, 'index']);
    Route::get('transactions', [TransactionController::class, 'index']);

    //logout
    Route::get('user', [AuthController::class, 'getUser']);
    Route::get('agent', [AuthController::class, 'getAgent']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('changePassword', [AuthController::class, 'changePassword']);
    Route::post('profile', [AuthController::class, 'profile']);
    Route::get('logo', [AgentLogoController::class, 'index']);
    Route::group(['prefix' => 'transaction'], function () {
        Route::post('withdraw', [WithDrawRequestController::class, 'withdraw']);
        Route::get('withdraw-log', [WithDrawRequestController::class, 'log']);
        Route::post('deposit', [DepositRequestController::class, 'deposit']);
        Route::get('deposit-log', [DepositRequestController::class, 'log']);
        Route::get('player-transactionlog', [PlayerTransactionLogController::class, 'index']);
    });

    Route::group(['prefix' => 'bank'], function () {
        Route::get('all', [BankController::class, 'all']);
    });
    Route::group(['prefix' => 'game'], function () {
        Route::post('Seamless/LaunchGame', [LaunchGameController::class, 'launchGame']);
        Route::get('gamelist/{provider_id}/{game_type_id}', [GameController::class, 'gameList']);
    });

    Route::group(['prefix' => 'direct'], function () {
        Route::post('Seamless/LaunchGame', [DirectLaunchGameController::class, 'launchGame']);
    });
});