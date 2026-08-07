<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\JobStatus;
use App\Http\Controllers\Controller;
use App\Models\Technician;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Majstori. Sa mejlom i lozinkom dobijaju i korisnicki nalog sa ulogom majstor,
 * pa mogu u mobilnu aplikaciju.
 */
class TechnicianController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $technicians = Technician::query()
            ->with('user')
            ->withCount([
                'jobs',
                'jobs as open_jobs_count' => fn ($query) => $query->where('status', '!=', JobStatus::Zavrseno->value),
            ])
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $technicians->map(fn (Technician $technician) => $this->red($technician))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'trade' => ['required', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['nullable', 'string', 'min:8', 'max:255', 'required_with:email'],
        ], $this->poruke());

        $technician = DB::transaction(function () use ($validated): Technician {
            $user = $this->korisnik($validated);

            return Technician::create([
                'user_id' => $user?->id,
                'name' => (string) $validated['name'],
                'trade' => (string) $validated['trade'],
                'active' => (bool) ($validated['active'] ?? true),
            ]);
        });

        return response()->json([
            'data' => $this->red($technician->load('user')),
            'message' => 'Majstor je upisan.',
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $technician = Technician::query()->with('user')->find($id);

        if (! $technician) {
            return response()->json(['message' => 'Majstor nije pronađen.'], 404);
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'trade' => ['nullable', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($technician->user_id)],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
        ], $this->poruke());

        DB::transaction(function () use ($technician, $validated, $request): void {
            $izmjene = [];

            foreach (['name', 'trade'] as $kljuc) {
                if (isset($validated[$kljuc])) {
                    $izmjene[$kljuc] = (string) $validated[$kljuc];
                }
            }

            if ($request->has('active') && ($validated['active'] ?? null) !== null) {
                $izmjene['active'] = (bool) $validated['active'];
            }

            if ($izmjene !== []) {
                $technician->update($izmjene);
            }

            $this->osvjeziKorisnika($technician, $validated);
        });

        return response()->json([
            'data' => $this->red($technician->refresh()->load('user')),
            'message' => 'Majstor je ažuriran.',
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $technician = Technician::query()->withCount('jobs')->find($id);

        if (! $technician) {
            return response()->json(['message' => 'Majstor nije pronađen.'], 404);
        }

        if ($technician->jobs_count > 0) {
            return response()->json([
                'message' => 'Majstor ima naloge u historiji i ne može se obrisati. Isključite ga umjesto brisanja.',
            ], 422);
        }

        DB::transaction(function () use ($technician): void {
            $user = $technician->user;

            $technician->delete();

            // Serviserski nalog bez majstora nema sta raditi u aplikaciji.
            $user?->delete();
        });

        return response()->json(['message' => 'Majstor je obrisan.']);
    }

    /**
     * Novi korisnicki nalog za majstora, kad su stigli mejl i lozinka.
     *
     * @param  array<string, mixed>  $validated
     */
    private function korisnik(array $validated): ?User
    {
        if (empty($validated['email']) || empty($validated['password'])) {
            return null;
        }

        $user = User::create([
            'name' => (string) $validated['name'],
            'email' => (string) $validated['email'],
            'password' => (string) $validated['password'],
            'notif_push' => true,
            'notif_email' => true,
            'notif_marketing' => false,
        ]);

        $user->forceFill(['email_verified_at' => Carbon::now()])->save();
        $user->syncRoles(['majstor']);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function osvjeziKorisnika(Technician $technician, array $validated): void
    {
        $user = $technician->user;

        if (! $user) {
            $novi = $this->korisnik(array_merge($validated, ['name' => $technician->name]));

            if ($novi) {
                $technician->update(['user_id' => $novi->id]);
            }

            return;
        }

        $izmjene = [];

        if (isset($validated['name'])) {
            $izmjene['name'] = (string) $validated['name'];
        }

        if (! empty($validated['email'])) {
            $izmjene['email'] = (string) $validated['email'];
        }

        if (! empty($validated['password'])) {
            $izmjene['password'] = (string) $validated['password'];
        }

        if ($izmjene !== []) {
            $user->update($izmjene);
        }
    }

    /**
     * @return array<string, string>
     */
    private function poruke(): array
    {
        return [
            'name.required' => 'Upišite ime majstora.',
            'trade.required' => 'Upišite zanat.',
            'email.email' => 'Mejl adresa nije ispravna.',
            'email.unique' => 'Ta mejl adresa je već zauzeta.',
            'password.min' => 'Lozinka mora imati najmanje 8 znakova.',
            'password.required_with' => 'Uz mejl adresu upišite i lozinku.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function red(Technician $technician): array
    {
        if ($technician->jobs_count === null) {
            $technician->loadCount([
                'jobs',
                'jobs as open_jobs_count' => fn ($query) => $query->where('status', '!=', JobStatus::Zavrseno->value),
            ]);
        }

        return [
            'id' => $technician->id,
            'name' => $technician->name,
            'trade' => $technician->trade,
            'active' => (bool) $technician->active,
            'email' => $technician->user?->email,
            'has_account' => $technician->user_id !== null,
            'jobs_count' => (int) ($technician->jobs_count ?? 0),
            'open_jobs_count' => (int) ($technician->open_jobs_count ?? 0),
        ];
    }
}
