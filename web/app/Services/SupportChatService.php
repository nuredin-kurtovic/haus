<?php

namespace App\Services;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Enums\CityStatus;
use App\Enums\SurchargeType;
use App\Exceptions\SupportChatException;
use App\Models\City;
use App\Models\Package;
use App\Models\PriceCategory;
use App\Models\Surcharge;
use Illuminate\Support\Facades\Cache;

/**
 * AI podrska na sajtu.
 *
 * Znanje modela se gradi iz baze, nikad hardkodirano. Cijene, paketi, gradovi,
 * doplate i radno vrijeme se mijenjaju iz admina, pa se prompt kesira kratko
 * (10 minuta) da izmjena stigne u razgovor bez restarta.
 */
class SupportChatService
{
    private const CACHE_KEY = 'haus.support_chat.system_prompt';

    private const CACHE_TTL = 600;

    /** Koliko primjera pozicija po kategoriji ide u prompt. */
    private const PRIMJERA_PO_KATEGORIJI = 3;

    /** @var array<string, int> */
    private array $zadnjaPotrosnja = [];

    public function __construct(private readonly SettingsService $settings) {}

    /**
     * Odgovor modela na razgovor.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function chat(array $messages): string
    {
        $apiKey = (string) config('services.anthropic.api_key');

        if ($apiKey === '') {
            throw SupportChatException::nijePodesen();
        }

        $client = new Client(apiKey: $apiKey);

        try {
            $message = $client->messages->create(
                maxTokens: (int) config('services.anthropic.max_tokens', 1024),
                messages: $messages,
                model: (string) config('services.anthropic.model'),
                system: $this->buildSystemPrompt(),
            );
        } catch (RateLimitException $e) {
            throw SupportChatException::preopterecen($e);
        } catch (APIStatusException $e) {
            throw SupportChatException::apiGreska($e);
        }

        // Content je niz polimorfnih blokova. Citamo samo tekst, ostalo
        // (thinking, tool_use) nas ovdje ne zanima i ne smije puci.
        $tekst = '';

        foreach ($message->content as $block) {
            if (($block->type ?? null) === 'text') {
                $tekst .= $block->text;
            }
        }

        $tekst = trim($tekst);

        if ($tekst === '') {
            throw SupportChatException::prazanOdgovor();
        }

        $this->zadnjaPotrosnja = [
            'input_tokens' => $message->usage->inputTokens,
            'output_tokens' => $message->usage->outputTokens,
        ];

        return $tekst;
    }

    /**
     * Tokeni zadnjeg poziva, za praćenje troška u logu.
     *
     * @return array<string, int>
     */
    public function zadnjaPotrosnja(): array
    {
        return $this->zadnjaPotrosnja;
    }

    /**
     * Sistemski prompt: sve sto model smije znati o HAUS-u, iz baze.
     */
    public function buildSystemPrompt(): string
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): string {
            $dijelovi = [
                $this->oHausu(),
                $this->paketi(),
                $this->gradovi(),
                $this->radnoVrijeme(),
                $this->doplate(),
                $this->cjenovnik(),
                $this->pravila(),
            ];

            return implode("\n\n", array_filter($dijelovi));
        });
    }

    /** Kes prompta se brise kad se podaci promijene. */
    public function forgetSystemPrompt(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function oHausu(): string
    {
        return <<<'TXT'
        # HAUS

        HAUS je godišnja pretplata na održavanje doma u Bosni i Hercegovini.
        Klijent plati paket jednom godišnje i prijavljuje kvarove kroz aplikaciju.

        Tri obećanja koja sistem garantuje:
        1. Poznata cijena. Cjenovnik je javan, cijena se zna prije izlaska.
        2. Dogovoren rok. Svaki paket ima rok izlaska koji se upisuje na nalog.
        3. Pisana garancija. Na svaki rad ide garancija, nalaz i fotografije.

        Nema telefonskih poziva i nema gotovine. Sve ide kroz aplikaciju.
        TXT;
    }

    private function paketi(): string
    {
        $packages = Package::query()->active()->orderBy('sort')->get();

        if ($packages->isEmpty()) {
            return '';
        }

        $redovi = ['# Paketi'];

        foreach ($packages as $package) {
            $hitno = $package->emergency_included
                ? 'hitna intervencija je uključena u cijenu'
                : 'hitna intervencija se doplaćuje';

            $obracun = $package->is_per_apartment
                ? 'cijena je po stanu'
                : 'cijena je za jednu adresu';

            $redovi[] = implode("\n", [
                '',
                '## '.$package->name,
                '- Cijena: '.$this->broj($package->price_year).' KM godišnje ('.$obracun.')',
                '- Izlazaka godišnje: '.$package->visits_per_year,
                '- Rok redovnog izlaska: '.$package->deadline_hours.' h',
                '- Rok hitnog izlaska: '.$package->emergency_deadline_hours.' h, '.$hitno,
                '- Popust na rad: '.$package->labor_discount_pct.' posto',
                '- Popust na materijal: '.$package->material_discount_pct.' posto',
                '- Preventivnih pregleda godišnje: '.$package->inspections_per_year,
                '- Garancija na rad: '.$package->warranty_months.' mjeseci',
            ]);
        }

        $tiers = $this->settings->get('pro_volume_tiers', []);

        if (is_array($tiers) && $tiers !== []) {
            $opisi = [];

            foreach ($tiers as $tier) {
                if (! is_array($tier) || ! isset($tier['min'], $tier['max'], $tier['pct'])) {
                    continue;
                }

                $opisi[] = $tier['min'].' do '.$tier['max'].' stanova: '.$tier['pct'].' posto popusta';
            }

            if ($opisi !== []) {
                $redovi[] = "\nPopust na količinu stanova: ".implode('; ', $opisi).'.';
                $redovi[] = 'Za deset i više stanova nema automatske naplate. Registracija postaje zahtjev za ponudu.';
            }
        }

        return implode("\n", $redovi);
    }

    private function gradovi(): string
    {
        $cities = City::query()->orderBy('name')->get();

        if ($cities->isEmpty()) {
            return '';
        }

        $aktivni = $cities->where('status', CityStatus::Aktivan)->pluck('name')->all();
        $upripremi = $cities->where('status', CityStatus::UPripremi)->pluck('name')->all();

        $redovi = ['# Gradovi'];

        $redovi[] = $aktivni !== []
            ? 'Radimo u: '.implode(', ', $aktivni).'.'
            : 'Trenutno nema aktivnih gradova.';

        if ($upripremi !== []) {
            $redovi[] = 'U pripremi: '.implode(', ', $upripremi).'. Tamo još ne primamo prijave.';
        }

        $redovi[] = 'Grad koji nije na spisku ne pokrivamo. Nikad ne obećavajte izlazak van aktivnih gradova.';

        return implode("\n", $redovi);
    }

    private function radnoVrijeme(): string
    {
        $rv = $this->settings->get('radno_vrijeme', []);
        $rv = is_array($rv) ? $rv : [];

        $redovi = ['# Radno vrijeme i satnice'];

        if (isset($rv['pon_pet']['od'], $rv['pon_pet']['do'])) {
            $redovi[] = '- Ponedjeljak do petak: '.$rv['pon_pet']['od'].'–'.$rv['pon_pet']['do'];
        }

        if (isset($rv['subota']['od'], $rv['subota']['do'])) {
            $redovi[] = '- Subota: '.$rv['subota']['od'].'–'.$rv['subota']['do'];
        }

        if (! empty($rv['nedjelja']['samo_hitno'])) {
            $redovi[] = '- Nedjelja: samo hitne intervencije';
        }

        if (! empty($rv['napomena'])) {
            $redovi[] = '- Napomena: '.$rv['napomena'];
        }

        $ukljuceno = (int) $this->settings->get('ukljuceno_minuta', 0);

        $redovi[] = '- Satnica redovna: '.$this->broj($this->settings->get('satnica_redovna', 0)).' KM';
        $redovi[] = '- Satnica hitna: '.$this->broj($this->settings->get('satnica_hitna', 0)).' KM';
        $redovi[] = '- Izlazak bez pretplate: '.$this->broj($this->settings->get('izlazak_bez_pretplate', 0)).' KM';
        $redovi[] = '- U izlazak je uključeno '.$ukljuceno.' minuta rada. Preko toga se računa po satnici.';
        $redovi[] = '- Marža na materijal: '.(int) $this->settings->get('materijal_marza_pct', 0).' posto na nabavnu cijenu.';

        return implode("\n", $redovi);
    }

    private function doplate(): string
    {
        $surcharges = Surcharge::query()->active()->orderBy('sort')->get();

        if ($surcharges->isEmpty()) {
            return '';
        }

        $redovi = ['# Doplate'];

        foreach ($surcharges as $surcharge) {
            $redovi[] = '- '.$surcharge->label.': '.match ($surcharge->type) {
                SurchargeType::Percent => $this->broj($surcharge->value).' posto',
                SurchargeType::PerKm => $this->broj($surcharge->value).' KM po kilometru',
                SurchargeType::Flat => $this->broj($surcharge->value).' KM',
            };
        }

        return implode("\n", $redovi);
    }

    private function cjenovnik(): string
    {
        $categories = PriceCategory::query()
            ->with(['items' => fn ($items) => $items->where('active', true)->orderBy('sort')])
            ->orderBy('sort')
            ->get()
            ->filter(fn (PriceCategory $category) => $category->items->isNotEmpty());

        if ($categories->isEmpty()) {
            return '';
        }

        $redovi = ['# Cjenovnik'];
        $redovi[] = 'Ovo su OSNOVNE cijene, bez pretplate. Ispod je nekoliko primjera po kategoriji.';
        $redovi[] = 'Cijena za pretplatnika se računa iz osnovne cijene i popusta na rad iz paketa, zaokruženo na cijeli KM.';
        $redovi[] = 'Pun cjenovnik je na stranici /cjenovnik.';

        foreach ($categories as $category) {
            $primjeri = $category->items->take(self::PRIMJERA_PO_KATEGORIJI)
                ->map(fn ($item) => $item->name.' '.$this->broj($item->base_price).' KM'.($item->unit ? ' / '.$item->unit : ''))
                ->implode('; ');

            $redovi[] = '- '.$category->name.' ('.$category->items->count().' pozicija): '.$primjeri.'.';
        }

        return implode("\n", $redovi);
    }

    private function pravila(): string
    {
        return <<<'TXT'
        # Kako odgovarate

        Vi ste HAUS podrška na sajtu. Odgovarate na bosanskom jeziku.
        Korisniku se uvijek obraćate sa "Vi". Rečenice su kratke i konkretne.
        Brojevi umjesto prideva: "72 sata", ne "brzo".

        Nikad ne koristite znak duge crte (em dash). Koristite tačku ili zarez.

        Nikad ne izmišljate cijene, rokove, gradove ni uslove kojih nema u
        podacima iznad. Ako podatka nema, kažete da ne znate i uputite na
        kontakt formu na /kontakt.

        Ne obećavate termine. Termin potvrđuje dispečer nakon prijave.

        Za prijavu kvara upućujete na HAUS aplikaciju ili na /klijent.
        Za registraciju upućujete na /registracija.

        Ne raspravljate o temama van HAUS-a. Ako pitanje nije o HAUS-u,
        ljubazno kažete da odgovarate samo na pitanja o HAUS usluzi i
        ponudite da pomognete oko paketa, cijena ili prijave kvara.

        Odgovor držite ispod 120 riječi osim ako korisnik traži detalje.
        TXT;
    }

    /** Cifra bez suvisnih nula: 59.00 postaje 59, 1.20 postaje 1.2. */
    private function broj(mixed $value): string
    {
        $float = (float) $value;

        return rtrim(rtrim(number_format($float, 2, '.', ''), '0'), '.');
    }
}
