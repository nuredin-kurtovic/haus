<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\SupportChatException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SupportChatRequest;
use App\Services\SupportChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SupportChatController extends Controller
{
    /**
     * AI podrska na sajtu. Javna ruta, throttle po IP-u.
     *
     * Bez podesenog kljuca vraca 503, da widget moze reci istinu umjesto da
     * cuti. Sadrzaj poruka se NIKAD ne loguje, samo broj poruka i tokeni.
     */
    public function store(SupportChatRequest $request, SupportChatService $support): JsonResponse
    {
        if ((string) config('services.anthropic.api_key') === '') {
            return response()->json([
                'message' => 'Podrška uživo trenutno nije dostupna. Pišite nam na mejl.',
            ], 503);
        }

        $razgovor = $request->razgovor();

        try {
            $reply = $support->chat($razgovor);
        } catch (SupportChatException $e) {
            Log::warning('support_chat.greska', [
                'messages' => count($razgovor),
                'razlog' => $e->getMessage(),
            ]);

            return response()->json(['message' => $e->getMessage()], 503);
        }

        Log::info('support_chat.odgovor', [
            'messages' => count($razgovor),
            'model' => (string) config('services.anthropic.model'),
            ...$support->zadnjaPotrosnja(),
        ]);

        return response()->json(['reply' => $reply]);
    }
}
