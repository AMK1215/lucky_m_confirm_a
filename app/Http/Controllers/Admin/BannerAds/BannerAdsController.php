<?php

namespace App\Http\Controllers\Admin\BannerAds;

use App\Http\Controllers\Controller;
use App\Models\Admin\BannerAds;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class BannerAdsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $banners = BannerAds::latest()->get();

        return view('admin.banner_ads.index', compact('banners'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.banner_ads.create');
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

        BannerAds::create([
            'image' => Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(10)),
        ]);

        return redirect(route('admin.adsbanners.index'))->with('success', 'New Ads Banner Image Added.');
    }

    /**
     * Display the specified resource.
     */
    public function show(BannerAds $adsbanner)
    {
        if (! $adsbanner->exists) {
            return redirect()->route('admin.adsbanners.index')->with('error', 'Banner not found');
        }

        return view('admin.banner_ads.show', compact('adsbanner'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(BannerAds $adsbanner)
    {
        return view('admin.banner_ads.edit', compact('adsbanner'));
    }

    public function update(Request $request, BannerAds $adsbanner)
    {
        if (! $adsbanner) {
            return redirect()->back()->with('error', 'Ads Banner Not Found');
        }
        $request->validate([
            'image' => 'required',
        ]);

        $path = $request->file('image')->store('images', 's3');

        $adsbanner->update([
            'image' => Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(10)),
        ]);

        return redirect(route('admin.adsbanners.index'))->with('success', 'Ads Banner Image Updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BannerAds $banner)
    {
        if (! $banner) {
            return redirect()->back()->with('error', 'Banner Not Found');
        }
        //remove banner from localstorage
        $banner->delete();

        return redirect()->back()->with('success', 'Ads Banner Deleted.');
    }
}
