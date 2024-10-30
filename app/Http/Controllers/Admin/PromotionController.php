<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Promotion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class PromotionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $promotions = Promotion::latest()->get();

        return view('admin.promotions.index', compact('promotions'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.promotions.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'image' => 'required',
        ]);
        $path = $request->file('image')->store('images', 's3');

        Promotion::create([
            'image' => Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(10))
        ]);

        return redirect()->route('admin.promotions.index')->with('success', 'New Promotion Created Successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Promotion $promotion)
    {
        return view('admin.promotions.show', compact('promotion'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Promotion $promotion)
    {
        return view('admin.promotions.edit', compact('promotion'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Promotion $promotion)
    {
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('images', 's3');
            $promotion->update([
                'image' => Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(10))
            ]);

            return redirect()->route('admin.promotions.index')->with('success', 'Promotion Updated');
        }

        return redirect()->route('admin.promotions.index')->with('success', 'Promotion Updated');

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Promotion $promotion)
    {
        $promotion->delete();

        return redirect()->route('admin.promotions.index')->with('success', 'Promotion Deleted.');
    }
}
