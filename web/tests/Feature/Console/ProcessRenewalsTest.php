<?php

namespace Tests\Feature\Console;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Mail\RacunMail;
use App\Mail\UplatnicaMail;
use App\Models\Invoice;
use App\Models\NotificationLog;
use App\Models\Payment;
use App\Models\PaymentToken;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payments\PaymentProcessor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * haus:process-renewals. Cetiri puta: MIT prolazi, MIT pada, nema tokena,
 * automatska obnova ugasena. Peti put: sve mora biti idempotentno.
 */
class ProcessRenewalsTest extends TestCase
{
    use RefreshDatabase;

    private User $klijent;

    private Subscription $pretplata;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        Mail::fake();

        Carbon::setTestNow(Carbon::create(2026, 8, 10, 6, 0, 0));

        $this->klijent = User::where('email', 'klijent@haus.ba')->firstOrFail();
        $this->pretplata = $this->klijent->activeSubscription;

        // Ostale pretplate ne smiju upasti u prolaz.
        Subscription::where('id', '!=', $this->pretplata->id)
            ->update(['ends_at' => Carbon::now()->addYear()]);

        // Pretplata je istekla jucer, izlasci su potroseni.
        $this->pretplata->update(['ends_at' => Carbon::now()->subDay()]);
        $this->pretplata->properties()->update(['remaining_visits' => 0, 'remaining_inspections' => 0]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_mit_naplata_produzava_pretplatu_i_resetuje_brojace(): void
    {
        $stariKraj = $this->pretplata->ends_at->copy();
        $this->token();

        $this->artisan('haus:process-renewals')->assertSuccessful();

        $this->pretplata->refresh();

        $this->assertSame(SubscriptionStatus::Aktivna, $this->pretplata->status);
        // Novi period nastavlja na stari kraj, klijent ne gubi dane.
        $this->assertSame($stariKraj->toIso8601String(), $this->pretplata->starts_at->toIso8601String());
        $this->assertSame($stariKraj->copy()->addYear()->toIso8601String(), $this->pretplata->ends_at->toIso8601String());
        $this->assertEqualsWithDelta(169, (float) $this->pretplata->price_paid, 0.001);
        $this->assertNull($this->pretplata->renewal_reminder_sent_at, 'Novi period trazi novi podsjetnik.');

        $stan = $this->pretplata->properties()->firstOrFail();
        $this->assertSame(3, (int) $stan->remaining_visits);
        $this->assertSame(1, (int) $stan->remaining_inspections);

        $invoice = $this->fakturaObnove();
        $this->assertSame(InvoiceStatus::Placeno, $invoice->status);
        $this->assertEqualsWithDelta(169, (float) $invoice->total, 0.001);
        $this->assertNotNull($invoice->paid_at);

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame(PaymentStatus::Uspjesan, $payment->status);

        Mail::assertQueued(RacunMail::class, fn (RacunMail $mail): bool => $mail->invoice->is($invoice));
        Mail::assertNotQueued(UplatnicaMail::class);

        $this->assertSame(2, NotificationLog::where('template_key', 'pretplata_aktivna')->count());
    }

    public function test_krediti_prezivljavaju_obnovu(): void
    {
        $this->pretplata->update(['free_interventions' => 2]);
        $this->token();

        $this->artisan('haus:process-renewals')->assertSuccessful();

        $this->assertSame(2, (int) $this->pretplata->refresh()->free_interventions);
    }

    public function test_odbijena_naplata_gasi_pretplatu_i_salje_uplatnicu(): void
    {
        config()->set('services.haus.fake_charge_outcome', 'declined');
        $this->token();

        $this->artisan('haus:process-renewals')->assertSuccessful();

        $this->pretplata->refresh();

        $this->assertSame(SubscriptionStatus::Istekla, $this->pretplata->status);

        $invoice = $this->fakturaObnove();
        $this->assertSame(InvoiceStatus::Nenaplaceno, $invoice->status);
        $this->assertNotNull($invoice->sent_at);

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame(PaymentStatus::Neuspjesan, $payment->status);

        Mail::assertQueued(UplatnicaMail::class, fn (UplatnicaMail $mail): bool => $mail->invoice->is($invoice));
        Mail::assertNotQueued(RacunMail::class);
    }

    public function test_bez_tokena_pretplata_istice_uz_fakturu_i_uplatnicu(): void
    {
        $this->artisan('haus:process-renewals')->assertSuccessful();

        $this->assertSame(SubscriptionStatus::Istekla, $this->pretplata->refresh()->status);

        $invoice = $this->fakturaObnove();
        $this->assertSame(InvoiceStatus::Nenaplaceno, $invoice->status);
        $this->assertEqualsWithDelta(169, (float) $invoice->total, 0.001);

        $this->assertSame(0, Payment::where('invoice_id', $invoice->id)->count());

        Mail::assertQueued(UplatnicaMail::class);
    }

    public function test_neaktivan_token_se_ne_koristi(): void
    {
        $this->token(['active' => false]);

        $this->artisan('haus:process-renewals')->assertSuccessful();

        $this->assertSame(SubscriptionStatus::Istekla, $this->pretplata->refresh()->status);
        Mail::assertQueued(UplatnicaMail::class);
    }

    public function test_bez_automatske_obnove_pretplata_samo_istice(): void
    {
        $this->pretplata->update(['auto_renew' => false]);
        $this->token();

        $this->artisan('haus:process-renewals')->assertSuccessful();

        $this->assertSame(SubscriptionStatus::Istekla, $this->pretplata->refresh()->status);
        $this->assertSame(
            0,
            Invoice::where('subscription_id', $this->pretplata->id)->where('type', InvoiceType::Pretplata)->count(),
            'Bez automatske obnove nema fakture.'
        );

        Mail::assertNothingQueued();
    }

    public function test_pretplata_sa_rokom_u_buducnosti_se_ne_dira(): void
    {
        $this->pretplata->update(['ends_at' => Carbon::now()->addMonth()]);
        $this->token();

        $this->artisan('haus:process-renewals')->assertSuccessful();

        $this->assertSame(SubscriptionStatus::Aktivna, $this->pretplata->refresh()->status);
        $this->assertSame(0, Invoice::where('subscription_id', $this->pretplata->id)->count());
    }

    public function test_drugi_prolaz_ne_duplira_fakturu(): void
    {
        $this->token();

        $this->artisan('haus:process-renewals')->assertSuccessful();
        $this->artisan('haus:process-renewals')->assertSuccessful();

        $this->assertSame(1, Invoice::where('subscription_id', $this->pretplata->id)->count());
        $this->assertSame(1, Payment::count());
        $this->assertSame(2, NotificationLog::where('template_key', 'pretplata_aktivna')->count());
    }

    public function test_drugi_prolaz_poslije_odbijenice_ne_duplira_fakturu(): void
    {
        config()->set('services.haus.fake_charge_outcome', 'declined');
        $this->token();

        $this->artisan('haus:process-renewals')->assertSuccessful();

        // Dispecer je pretplatu vratio u aktivnu, a rok je i dalje prosao.
        $this->pretplata->update(['status' => SubscriptionStatus::Aktivna]);

        $this->artisan('haus:process-renewals')->assertSuccessful();

        $this->assertSame(1, Invoice::where('subscription_id', $this->pretplata->id)->count());
        Mail::assertQueuedCount(1);
    }

    public function test_uplata_uplatnice_poslije_isteka_produzava_pretplatu(): void
    {
        $stariKraj = $this->pretplata->ends_at->copy();

        $this->artisan('haus:process-renewals')->assertSuccessful();

        $invoice = $this->fakturaObnove();

        // Klijent plati uplatnicom dva dana kasnije, webhook knjizi uplatu.
        Carbon::setTestNow(Carbon::now()->addDays(2));

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'method' => 'uplatnica',
            'amount' => $invoice->total,
            'status' => PaymentStatus::Iniciran,
            'gateway_reference' => 'UPLATNICA-'.$invoice->number,
        ]);

        app(PaymentProcessor::class)->approve($payment, []);

        $this->pretplata->refresh();

        $this->assertSame(SubscriptionStatus::Aktivna, $this->pretplata->status);
        $this->assertSame($stariKraj->toIso8601String(), $this->pretplata->starts_at->toIso8601String());
        $this->assertSame($stariKraj->copy()->addYear()->toIso8601String(), $this->pretplata->ends_at->toIso8601String());
        $this->assertSame(3, (int) $this->pretplata->properties()->firstOrFail()->remaining_visits);
    }

    private function fakturaObnove(): Invoice
    {
        return Invoice::where('subscription_id', $this->pretplata->id)
            ->where('type', InvoiceType::Pretplata)
            ->latest('id')
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function token(array $overrides = []): PaymentToken
    {
        return PaymentToken::create(array_merge([
            'user_id' => $this->klijent->id,
            'token' => 'mit-token-obnova',
            'masked_pan' => '403940xxxxxx1881',
            'active' => true,
        ], $overrides));
    }
}
