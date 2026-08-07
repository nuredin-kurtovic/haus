<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeviceRequest;
use App\Models\Device;
use Illuminate\Http\JsonResponse;

class DeviceController extends Controller
{
    /**
     * Upis uredjaja za push. Isti token istog korisnika se samo osvjezi.
     */
    public function store(DeviceRequest $request): JsonResponse
    {
        $device = Device::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'fcm_token' => (string) $request->input('fcm_token'),
            ],
            [
                'platform' => (string) $request->input('platform'),
            ]
        );

        return response()->json([
            'data' => [
                'id' => $device->id,
                'platform' => $device->platform->value,
            ],
        ]);
    }
}
