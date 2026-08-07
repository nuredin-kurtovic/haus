<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PackageResource;
use App\Models\Package;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;

class PackageController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function index(): JsonResponse
    {
        $tiers = $this->settings->get('pro_volume_tiers', []);
        $tiers = is_array($tiers) ? $tiers : [];

        $packages = Package::query()
            ->active()
            ->orderBy('sort')
            ->get()
            ->map(fn (Package $package) => new PackageResource($package, $tiers));

        return response()->json(['data' => $packages]);
    }
}
