<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\GameType;
use App\Models\Admin\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GameTypeProductController extends Controller
{
    public function index()
    {
        $gameTypes = GameType::with('products')->get();

        return view('admin.game_type.index', compact('gameTypes'));
    }

    public function edit($gameTypeId, $productId)
    {
        $gameType = GameType::with([
            'products' => function ($query) use ($productId) {
                $query->where('products.id', $productId);
            },
        ])->where('id', $gameTypeId)->first();

        return view('admin.game_type.edit', compact('gameType', 'productId'));
    }

    public function update(Request $request, $gameTypeId, $productId)
    {
        $image = $request->file('image');
            
        $path = $request->file('image')->store('images', 's3');

        DB::table('game_type_product')->where('game_type_id', $gameTypeId)->where('product_id', $productId)
            ->update(['image' => Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(10))]);

        return redirect()->route('admin.gametypes.index');
    }
}
