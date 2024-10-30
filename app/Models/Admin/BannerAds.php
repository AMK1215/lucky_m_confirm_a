<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BannerAds extends Model
{
    use HasFactory;

    protected $fillable = [
        'image',
    ];

    protected $table = 'banner_ads';

}
