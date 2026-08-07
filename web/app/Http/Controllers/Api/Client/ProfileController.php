<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->profil($request->user())]);
    }

    /**
     * Ime i prekidaci obavjestenja. Mejl je samo za citanje, adresa se mijenja
     * kroz zahtjev dispeceru.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        /** @var array<string, mixed> $notifications */
        $notifications = $request->input('notifications', []);

        $user->fill(['name' => (string) $request->input('name')]);

        foreach (['push' => 'notif_push', 'email' => 'notif_email', 'marketing' => 'notif_marketing'] as $kljuc => $kolona) {
            if (array_key_exists($kljuc, $notifications) && $notifications[$kljuc] !== null) {
                $user->{$kolona} = (bool) $notifications[$kljuc];
            }
        }

        $user->save();

        return response()->json([
            'data' => $this->profil($user),
            'message' => 'Podaci su sačuvani.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function profil(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'notifications' => [
                'push' => (bool) $user->notif_push,
                'email' => (bool) $user->notif_email,
                'marketing' => (bool) $user->notif_marketing,
            ],
        ];
    }
}
