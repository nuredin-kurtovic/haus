<?php

namespace Tests\Feature\Services;

use App\Enums\CityStatus;
use App\Models\City;
use App\Models\Package;
use App\Services\SupportChatService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportChatServiceTest extends TestCase
{
    use RefreshDatabase;

    private SupportChatService $support;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->support = app(SupportChatService::class);
        $this->support->forgetSystemPrompt();
    }

    public function test_prompt_sadrzi_nazive_paketa_iz_baze(): void
    {
        $prompt = $this->support->buildSystemPrompt();

        foreach (Package::query()->active()->get() as $package) {
            $this->assertStringContainsString($package->name, $prompt);
        }
    }

    public function test_prompt_sadrzi_parametre_paketa(): void
    {
        $prompt = $this->support->buildSystemPrompt();

        $plus = Package::query()->where('slug', Package::PLUS)->firstOrFail();

        $this->assertStringContainsString('169 KM godišnje', $prompt);
        $this->assertStringContainsString('Rok redovnog izlaska: '.$plus->deadline_hours.' h', $prompt);
        $this->assertStringContainsString('Popust na rad: '.$plus->labor_discount_pct.' posto', $prompt);
        $this->assertStringContainsString('Garancija na rad: '.$plus->warranty_months.' mjeseci', $prompt);
        $this->assertStringContainsString('Preventivnih pregleda godišnje: '.$plus->inspections_per_year, $prompt);
        $this->assertStringContainsString('hitna intervencija je uključena u cijenu', $prompt);
    }

    public function test_prompt_sadrzi_aktivne_gradove_i_gradove_u_pripremi(): void
    {
        $prompt = $this->support->buildSystemPrompt();

        $aktivni = City::query()->where('status', CityStatus::Aktivan)->pluck('name');
        $upripremi = City::query()->where('status', CityStatus::UPripremi)->pluck('name');

        $this->assertGreaterThan(0, $aktivni->count());
        $this->assertGreaterThan(0, $upripremi->count());

        foreach ($aktivni as $name) {
            $this->assertStringContainsString($name, $prompt);
        }

        foreach ($upripremi as $name) {
            $this->assertStringContainsString($name, $prompt);
        }

        $this->assertStringContainsString('U pripremi:', $prompt);
    }

    public function test_prompt_sadrzi_radno_vrijeme_satnice_i_doplate(): void
    {
        $prompt = $this->support->buildSystemPrompt();

        $this->assertStringContainsString('08:00', $prompt);
        $this->assertStringContainsString('Satnica redovna: 40 KM', $prompt);
        $this->assertStringContainsString('Satnica hitna: 70 KM', $prompt);
        $this->assertStringContainsString('uključeno 45 minuta rada', $prompt);
        $this->assertStringContainsString('# Doplate', $prompt);
        $this->assertStringContainsString('posto', $prompt);
        $this->assertStringContainsString('KM po kilometru', $prompt);
    }

    public function test_prompt_sadrzi_kategorije_cjenovnika_i_napomenu_o_popustu(): void
    {
        $prompt = $this->support->buildSystemPrompt();

        $this->assertStringContainsString('# Cjenovnik', $prompt);
        $this->assertStringContainsString('Grijanje', $prompt);
        $this->assertStringContainsString('Zamjena sifona 40 KM', $prompt);
        $this->assertStringContainsString('popusta na rad iz paketa', $prompt);
    }

    public function test_prompt_sadrzi_pravila_ponasanja(): void
    {
        $prompt = $this->support->buildSystemPrompt();

        $this->assertStringContainsString('bosanskom jeziku', $prompt);
        $this->assertStringContainsString('/registracija', $prompt);
        $this->assertStringContainsString('/klijent', $prompt);
        $this->assertStringContainsString('/kontakt', $prompt);
    }

    public function test_prompt_nema_em_dasha(): void
    {
        // Znak se gradi iz escape sekvence, da sam test ne padne na guardu iz SeedDataTest.
        $this->assertStringNotContainsString("\u{2014}", $this->support->buildSystemPrompt());
    }

    public function test_prompt_ne_prikazuje_neaktivan_paket(): void
    {
        Package::query()->where('slug', Package::MINI)->update(['active' => false]);

        $this->support->forgetSystemPrompt();

        $mini = Package::query()->where('slug', Package::MINI)->firstOrFail();

        $this->assertStringNotContainsString($mini->name, $this->support->buildSystemPrompt());
    }

    public function test_prompt_se_kesira(): void
    {
        $prvi = $this->support->buildSystemPrompt();

        Package::query()->where('slug', Package::MINI)->update(['name' => 'HAUS Nano']);

        $this->assertSame($prvi, $this->support->buildSystemPrompt());

        $this->support->forgetSystemPrompt();

        $this->assertStringContainsString('HAUS Nano', $this->support->buildSystemPrompt());
    }
}
