<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\Integrations;
use App\Models\Integration;
use App\Models\User;
use App\Services\PayMongo;
use App\Support\Credentials;
use App\Support\ReplyAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Credentials that can be changed from the screen that reports on them.
 *
 * The screen used to be read-only, and worse than read-only: it read
 * `services.paymongo.public_key` and `services.paymongo.secret_key` while the
 * config file defines `public` and `secret`, so a deployment that was taking
 * payments was told it had no keys at all. The first test here is that
 * regression.
 *
 * The rule the rest of these tests pin down is the fallback order — saved value
 * first, environment second, nothing third — because every failure mode of this
 * feature is a deployment where the two disagree.
 */
class IntegrationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): User
    {
        return User::where('email', 'sofia@tarpalink.ph')->firstOrFail();
    }

    // ------------------------------------------------------------- the card

    public function test_the_paymongo_card_reads_the_keys_that_actually_exist(): void
    {
        // The config keys are `public` and `secret`. The card used to ask for
        // `public_key` and `secret_key` and report "Not configured" forever.
        config([
            'services.paymongo.public' => 'pk_test_51H8xabcdefghijklmnop',
            'services.paymongo.secret' => 'sk_test_51H8xqrstuvwxyz',
            'services.paymongo.webhook_secret' => 'whsec_from_env',
        ]);

        Livewire::actingAs($this->admin())
            ->test(Integrations::class)
            ->assertOk()
            ->assertSee('Sandbox')
            ->assertSee('pk_test_')
            ->assertDontSee('Not configured');
    }

    public function test_the_card_masks_a_public_key_instead_of_printing_it(): void
    {
        config(['services.paymongo.public' => 'pk_test_51H8xabcdefghijklmnop']);

        Livewire::actingAs($this->admin())
            ->test(Integrations::class)
            ->assertDontSee('pk_test_51H8xabcdefghijklmnop')
            ->assertSee('pk_test_••••mnop');
    }

    public function test_the_card_says_where_a_value_came_from(): void
    {
        config(['services.paymongo.secret' => 'sk_test_env']);

        Livewire::actingAs($this->admin())
            ->test(Integrations::class)
            ->assertSee('from .env');

        Credentials::put('paymongo', ['secret' => 'sk_test_saved']);

        Livewire::actingAs($this->admin())
            ->test(Integrations::class)
            ->assertSee('saved here');
    }

    // -------------------------------------------------------- the fallback

    public function test_the_environment_is_the_fallback_when_nothing_is_saved(): void
    {
        config(['services.paymongo.secret' => 'sk_test_env']);

        $this->assertSame('sk_test_env', Credentials::get('paymongo', 'secret'));
        $this->assertSame(Credentials::FROM_ENVIRONMENT, Credentials::source('paymongo', 'secret'));
    }

    public function test_a_saved_value_overrides_the_environment(): void
    {
        config(['services.paymongo.secret' => 'sk_test_env']);

        Credentials::put('paymongo', ['secret' => 'sk_test_saved']);

        $this->assertSame('sk_test_saved', Credentials::get('paymongo', 'secret'));
        $this->assertSame(Credentials::FROM_DATABASE, Credentials::source('paymongo', 'secret'));
    }

    public function test_clearing_a_field_falls_back_to_the_environment_again(): void
    {
        config(['services.paymongo.secret' => 'sk_test_env']);

        Credentials::put('paymongo', ['secret' => 'sk_test_saved', 'public' => 'pk_test_saved']);
        Credentials::put('paymongo', ['secret' => '', 'public' => 'pk_test_saved']);

        // Blank means "revert", not "disable": the only reading of an empty
        // field that is not ambiguous.
        $this->assertSame('sk_test_env', Credentials::get('paymongo', 'secret'));
        $this->assertSame('pk_test_saved', Credentials::get('paymongo', 'public'));
    }

    public function test_clearing_every_field_removes_the_row(): void
    {
        Credentials::put('paymongo', ['secret' => 'sk_test_saved']);
        $this->assertSame(1, Integration::count());

        Credentials::put('paymongo', ['secret' => '', 'public' => null]);

        $this->assertSame(0, Integration::count());
        $this->assertNull(Credentials::get('paymongo', 'secret'));
    }

    public function test_an_unconfigured_key_is_null_not_an_empty_string(): void
    {
        // `blank()` and `=== null` are not the same question, and callers use
        // both. Returning '' here would make "not configured" read as "set to
        // nothing", which is how a missing webhook secret gets past a guard.
        $this->assertNull(Credentials::get('paymongo', 'secret'));
        $this->assertSame(Credentials::FROM_NOWHERE, Credentials::source('paymongo', 'secret'));
    }

    // ------------------------------------------------------------ at rest

    public function test_a_saved_key_is_not_readable_in_the_database(): void
    {
        Credentials::put('paymongo', ['secret' => 'sk_test_super_secret_value']);

        $raw = (string) DB::table('integrations')->where('provider', 'paymongo')->value('settings');

        $this->assertStringNotContainsString('sk_test_super_secret_value', $raw);

        // …and it round-trips, so the encryption is not just corruption.
        $this->assertSame('sk_test_super_secret_value', Credentials::get('paymongo', 'secret'));
    }

    public function test_the_row_records_who_changed_it(): void
    {
        Credentials::put('paymongo', ['secret' => 'sk_test_saved'], $this->admin());

        $this->assertSame(
            $this->admin()->id,
            Integration::where('provider', 'paymongo')->firstOrFail()->updated_by,
        );
    }

    // -------------------------------------------------------- the effects

    public function test_a_saved_secret_takes_the_payment_client_out_of_simulated_mode(): void
    {
        $this->assertTrue(app(PayMongo::class)->isSimulated());

        Credentials::put('paymongo', ['secret' => 'sk_test_saved']);

        // A fresh instance: the client must not be holding a cached answer.
        $this->assertFalse(app()->make(PayMongo::class)->isSimulated());
    }

    public function test_the_paymongo_webhook_accepts_the_saved_secret(): void
    {
        Queue::fake();

        Credentials::put('paymongo', ['webhook_secret' => 'whsec_saved_here']);

        $payload = json_encode(['data' => ['id' => 'evt_saved', 'attributes' => ['type' => 'payment.paid']]]);
        $timestamp = time();

        $this->call('POST', '/webhooks/paymongo', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => 't='.$timestamp.',te='
                .hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_saved_here'),
        ], $payload)->assertOk();

        Queue::assertPushed(\App\Jobs\ProcessPayMongoWebhook::class);
    }

    public function test_the_inbound_webhook_accepts_the_saved_secret(): void
    {
        Credentials::put('inbound', ['secret' => 'inbound_saved_here']);

        $payload = json_encode(['subject' => 'Nothing that matches a job.']);
        $signature = hash_hmac('sha256', $payload, 'inbound_saved_here');

        $this->call('POST', '/webhooks/inbound', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_INBOUND_SIGNATURE' => 'sha256='.$signature,
        ], $payload)->assertStatus(202);
    }

    public function test_the_reply_address_uses_the_saved_mailbox(): void
    {
        config(['services.inbound.address' => 'env@inbound.example']);

        $this->assertSame('env+TL-48213@inbound.example', ReplyAddress::for('TL-48213')?->address);

        Credentials::put('inbound', ['address' => 'saved@inbound.example']);

        $this->assertSame('saved+TL-48213@inbound.example', ReplyAddress::for('TL-48213')?->address);
    }

    // ------------------------------------------------------------- the form

    public function test_the_form_saves_the_paymongo_keys(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Integrations::class)
            ->mountAction('configurePayMongo')
            ->fillForm([
                'public' => 'pk_test_from_form',
                'secret' => 'sk_test_from_form',
                'webhook_secret' => 'whsec_from_form',
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame('sk_test_from_form', Credentials::get('paymongo', 'secret'));
        $this->assertSame('whsec_from_form', Credentials::get('paymongo', 'webhook_secret'));
        $this->assertSame($this->admin()->id, Integration::where('provider', 'paymongo')->value('updated_by'));
    }

    public function test_the_form_saves_the_inbound_mailbox(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Integrations::class)
            ->mountAction('configureInbound')
            ->fillForm([
                'address' => 'reply@inbound.example.com',
                'secret' => 'inbound_secret',
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame('reply@inbound.example.com', Credentials::get('inbound', 'address'));
    }

    public function test_the_form_does_not_leak_an_environment_secret_into_the_page(): void
    {
        config(['services.paymongo.secret' => 'sk_test_only_in_env']);

        // The card can say "set". The form must not copy the value into HTML.
        Livewire::actingAs($this->admin())
            ->test(Integrations::class)
            ->mountAction('configurePayMongo')
            ->assertSchemaStateSet(['secret' => null]);
    }

    public function test_the_form_is_filled_from_what_was_saved(): void
    {
        Credentials::put('paymongo', ['secret' => 'sk_test_saved']);

        Livewire::actingAs($this->admin())
            ->test(Integrations::class)
            ->mountAction('configurePayMongo')
            ->assertSchemaStateSet(['secret' => 'sk_test_saved', 'public' => null]);
    }

    public function test_the_mailbox_field_rejects_something_that_is_not_an_address(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Integrations::class)
            ->mountAction('configureInbound')
            ->fillForm(['address' => 'not an address', 'secret' => 'x'])
            ->callMountedAction()
            ->assertHasActionErrors(['address']);

        $this->assertNull(Credentials::get('inbound', 'address'));
    }

    // --------------------------------------------------------- the screen

    public function test_only_the_credential_cards_are_editable(): void
    {
        $cards = collect(
            Livewire::actingAs($this->admin())->test(Integrations::class)->instance()->cards()
        )->keyBy('key');

        // Mail, queue and storage are deployment shape. A form that can switch
        // the queue driver to `sync` from a browser is a form that can stop
        // payments settling without anyone noticing.
        $this->assertSame('configurePayMongo', $cards['paymongo']['editable']);
        $this->assertSame('configureInbound', $cards['inbound']['editable']);
        $this->assertNull($cards['mail']['editable']);
        $this->assertNull($cards['queue']['editable']);
        $this->assertNull($cards['storage']['editable']);
    }

    public function test_the_page_renders_an_edit_button_for_the_editable_cards(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Integrations::class)
            ->assertOk()
            ->assertSeeHtml("mountAction('configurePayMongo')")
            ->assertSeeHtml("mountAction('configureInbound')");
    }

    public function test_the_health_check_counts_a_saved_webhook_secret(): void
    {
        config(['services.paymongo.webhook_secret' => null]);

        Livewire::actingAs($this->admin())
            ->test(Integrations::class)
            ->assertSee('PayMongo webhook secret set');

        Credentials::put('paymongo', ['webhook_secret' => 'whsec_saved']);

        $checks = collect(
            Livewire::actingAs($this->admin())->test(Integrations::class)->instance()->health()
        )->keyBy('label');

        $this->assertTrue($checks['PayMongo webhook secret set']['ok']);
    }
}
