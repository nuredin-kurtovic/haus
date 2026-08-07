<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\SubscriptionResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    private const TOKEN_NAME = 'haus';

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email')->value())->first();

        if (! $user || ! Hash::check((string) $request->input('password'), (string) $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Pogrešna mejl adresa ili lozinka.',
            ]);
        }

        return response()->json([
            'user' => new UserResource($user),
            'token' => $user->createToken(self::TOKEN_NAME)->plainTextToken,
            'role' => $user->getRoleNames()->first(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['message' => 'Odjavljeni ste.']);
    }

    /**
     * Registracija je jedan poziv: korisnik, pretplata, adrese, faktura i naplata.
     *
     * Token se izdaje odmah, i dok pretplata ceka uplatu. Prava koja traze
     * aktivnu pretplatu cuva poseban guard, ne prijava.
     */
    public function register(RegisterRequest $request, RegistrationService $registration): JsonResponse
    {
        $package = $request->paket();

        $result = $registration->register(
            package: $package,
            name: (string) $request->input('name'),
            email: (string) $request->input('email'),
            password: (string) $request->input('password'),
            method: $request->paymentMethod(),
            properties: $request->properties(),
        );

        $payload = [
            'status' => $result->subscription->status->value,
            'user' => new UserResource($result->user),
            'subscription' => new SubscriptionResource($result->subscription, $result->total),
            'token' => $result->user->createToken(self::TOKEN_NAME)->plainTextToken,
        ];

        if ($result->invoice) {
            $payload['invoice'] = new InvoiceResource($result->invoice);
        }

        if ($result->initiation) {
            $payload['payment'] = ['redirect_url' => $result->initiation->redirectUrl];
        }

        return response()->json($payload, 201);
    }
}
