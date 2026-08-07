<?php

namespace App\Http\Controllers\Api\Client;

use App\Enums\HomeRecordType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\AddressChangeRequest;
use App\Mail\PromjenaAdreseMail;
use App\Models\HomeRecord;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class AddressChangeController extends Controller
{
    /** Naslov reda u kartonu doma, isti za sve zahtjeve. */
    private const NASLOV = 'Zahtjev za promjenu adrese';

    public function __construct(private readonly SettingsService $settings) {}

    /**
     * Zahtjev ostaje trag u kartonu doma i ide dispeceru na mejl.
     */
    public function store(AddressChangeRequest $request): JsonResponse
    {
        $user = $request->user();
        $stan = $request->stan();

        if (! $stan) {
            return response()->json(['message' => 'Adresu nismo pronašli.'], 404);
        }

        $poruka = (string) $request->input('message');

        HomeRecord::create([
            'subscription_property_id' => $stan->id,
            'type' => HomeRecordType::Napomena,
            'title' => self::NASLOV,
            'body' => $poruka,
            'recorded_at' => Carbon::now(),
        ]);

        Mail::to($this->dispecerEmail())->queue(new PromjenaAdreseMail($user, $stan, $poruka));

        return response()->json([
            'message' => 'Zahtjev je poslan. Dispečer se javlja na vaš mejl.',
        ], 201);
    }

    private function dispecerEmail(): string
    {
        $izPostavki = $this->settings->get('dispecer_email');

        if (is_string($izPostavki) && $izPostavki !== '') {
            return $izPostavki;
        }

        return (string) config('services.haus.dispatcher_email');
    }
}
