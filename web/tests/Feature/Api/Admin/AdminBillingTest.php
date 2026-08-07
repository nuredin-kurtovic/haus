<?php

namespace Tests\Feature\Api\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\City;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminBillingTest extends TestCase
{
    use RefreshDatabase;

    private User $dispecer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        Mail::fake();

        config()->set('services.haus.payment_gateway', 'fake');
        config()->set('app.debug', true);

        $this->dispecer = User::where('email', 'dispecer@haus.ba')->firstOrFail();
    }

    /**
     * Placena kartična faktura, kroz isti put kao pravi gateway.
     */
    private function placenaFaktura(): Invoice
    {
        $this->postJson('/api/v1/auth/register', [
            'package_id' => Package::where('slug', 'haus-plus')->value('id'),
            'name' => 'Selma Begić',
            'email' => 'selma@haus.ba',
            'password' => 'haus12345',
            'payment_method' => 'kartica',
            'properties' => [
                ['city_id' => City::where('slug', 'sarajevo')->value('id'), 'street' => 'Zmaja od Bosne 4'],
            ],
        ])->assertCreated();

        $payment = Payment::query()->latest('id')->firstOrFail();

        $this->postJson('/api/v1/dev/fake-payment', [
            'reference' => $payment->gateway_reference,
            'outcome' => 'approved',
        ])->assertOk();

        $this->app['auth']->forgetGuards();

        return $payment->refresh()->invoice;
    }

    public function test_lista_faktura_razdvaja_rad_i_materijal(): void
    {
        $invoice = $this->placenaFaktura();

        $this->actingAs($this->dispecer, 'sanctum')
            ->getJson('/api/v1/admin/billing')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.number', $invoice->number)
            ->assertJsonPath('data.0.type', 'pretplata')
            ->assertJsonPath('data.0.status', 'placeno')
            ->assertJsonPath('data.0.total', 169)
            ->assertJsonPath('data.0.material_total', 0)
            ->assertJsonPath('data.0.client.email', 'selma@haus.ba')
            ->assertJsonPath('data.0.subscription.package', 'HAUS Plus')
            ->assertJsonPath('meta.counts.placeno', 1);
    }

    public function test_filter_po_stanju_fakture(): void
    {
        $this->placenaFaktura();

        $this->actingAs($this->dispecer, 'sanctum')
            ->getJson('/api/v1/admin/billing?status=nenaplaceno')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_pun_povrat(): void
    {
        $invoice = $this->placenaFaktura();

        $this->actingAs($this->dispecer, 'sanctum')
            ->postJson('/api/v1/admin/invoices/'.$invoice->id.'/refund')
            ->assertOk()
            ->assertJsonPath('data.status', 'refundirano')
            ->assertJsonPath('data.refunded_amount', 169)
            ->assertJsonPath('message', 'Povrat je proveden u cijelosti.');

        $invoice->refresh();

        $this->assertSame(InvoiceStatus::Refundirano, $invoice->status);
        $this->assertSame(169.0, (float) $invoice->refunded_amount);
        $this->assertSame(PaymentStatus::Refundiran, $invoice->payments()->latest('id')->first()->status);
    }

    public function test_djelimican_povrat(): void
    {
        $invoice = $this->placenaFaktura();

        $this->actingAs($this->dispecer, 'sanctum')
            ->postJson('/api/v1/admin/invoices/'.$invoice->id.'/refund', ['amount' => 69])
            ->assertOk()
            ->assertJsonPath('data.status', 'djelimicno_refundirano')
            ->assertJsonPath('data.refunded_amount', 69);

        $invoice->refresh();

        $this->assertSame(InvoiceStatus::DjelimicnoRefundirano, $invoice->status);
        // Djelimican povrat ne gasi uplatu, ostatak je i dalje naplacen.
        $this->assertSame(PaymentStatus::Uspjesan, $invoice->payments()->latest('id')->first()->status);

        // Ostatak se moze vratiti naknadno.
        $this->actingAs($this->dispecer, 'sanctum')
            ->postJson('/api/v1/admin/invoices/'.$invoice->id.'/refund', ['amount' => 100])
            ->assertOk()
            ->assertJsonPath('data.status', 'refundirano')
            ->assertJsonPath('data.refunded_amount', 169);
    }

    public function test_povrat_veci_od_uplacenog_se_odbija(): void
    {
        $invoice = $this->placenaFaktura();

        $this->actingAs($this->dispecer, 'sanctum')
            ->postJson('/api/v1/admin/invoices/'.$invoice->id.'/refund', ['amount' => 200])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');

        $this->assertSame(InvoiceStatus::Placeno, $invoice->refresh()->status);
    }

    public function test_povrat_bez_uplate_se_odbija(): void
    {
        $klijent = User::where('email', 'klijent@haus.ba')->firstOrFail();

        $invoice = Invoice::create([
            'number' => Invoice::nextNumber(),
            'user_id' => $klijent->id,
            'type' => 'rad',
            'labor_total' => 41,
            'material_total' => 0,
            'total' => 41,
            'status' => InvoiceStatus::Nenaplaceno,
        ]);

        $this->actingAs($this->dispecer, 'sanctum')
            ->postJson('/api/v1/admin/invoices/'.$invoice->id.'/refund')
            ->assertStatus(422)
            ->assertJsonValidationErrors('invoice');
    }

    public function test_nepoznata_faktura_vraca_404(): void
    {
        $this->actingAs($this->dispecer, 'sanctum')
            ->postJson('/api/v1/admin/invoices/999999/refund')
            ->assertStatus(404);
    }
}
