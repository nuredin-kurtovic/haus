<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serviserske rute traze red u technicians vezan na prijavljenog korisnika.
 * Bez te veze nema naloga koje bismo mogli prikazati.
 */
class EnsureTechnicianProfile
{
    public function handle(Request $request, Closure $next): Response
    {
        $technician = $request->user()?->technician;

        if (! $technician) {
            return response()->json([
                'message' => 'Vaš nalog nije povezan sa serviserom. Javite se dispečeru.',
            ], 403);
        }

        if (! $technician->active) {
            return response()->json([
                'message' => 'Vaš serviserski nalog je isključen. Javite se dispečeru.',
            ], 403);
        }

        return $next($request);
    }
}
