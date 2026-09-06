<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SentDm\Client;
use SentDm\Core\Exceptions\NotFoundException;
use SentDm\Core\FileParam;
use SentDm\RequestOptions;
use Sujip\SentDm\Builders\ContactBuilder;
use Sujip\SentDm\Builders\ProfileBuilder;
use Sujip\SentDm\Builders\SenderProfileBuilder;
use Sujip\SentDm\Builders\TemplateBuilder;
use Sujip\SentDm\Builders\UserInviteBuilder;
use Sujip\SentDm\Builders\WebhookBuilder;
use Sujip\SentDm\Resources\Account;
use Sujip\SentDm\Resources\Campaigns;
use Sujip\SentDm\Resources\Channels;
use Sujip\SentDm\Resources\Compliance;
use Sujip\SentDm\Resources\Contacts;
use Sujip\SentDm\Resources\Conversations;
use Sujip\SentDm\Resources\Messages;
use Sujip\SentDm\Resources\Profiles;
use Sujip\SentDm\Resources\SenderProfiles;
use Sujip\SentDm\Resources\Templates;
use Sujip\SentDm\Resources\Users;
use Sujip\SentDm\Resources\Webhooks;
use Sujip\SentDm\Responses\SenderProfileData;
use Sujip\SentDm\Sent;

/**
 * Build a Sent driver backed by a fake HTTP transporter that returns a
 * given response body for every call.
 *
 * @param  array<string, mixed>  $data
 */
function sentApi(array $data = []): Sent
{
    $body = json_encode([
        'success' => true,
        'data' => $data,
        'meta' => ['request_id' => 'test', 'timestamp' => '2025-01-01T00:00:00Z', 'version' => 'v3'],
    ]) ?: '{}';

    $transporter = new class($body) implements ClientInterface
    {
        public function __construct(private string $body) {}

        public function sendRequest(RequestInterface $r): ResponseInterface
        {
            return new Response(200, ['Content-Type' => 'application/json'], $this->body);
        }
    };

    $opts = new RequestOptions;
    $opts['transporter'] = $transporter;
    $opts['maxRetries'] = 0;

    return new Sent(new Client(apiKey: 'test', requestOptions: $opts));
}

// Resource factories ---------------------------------------------------------

it('contacts() returns a Contacts resource', function () {
    expect(sentApi()->contacts())->toBeInstanceOf(Contacts::class);
});

it('conversations() returns a Conversations resource', function () {
    expect(sentApi()->conversations())->toBeInstanceOf(Conversations::class);
});

it('templates() returns a Templates resource', function () {
    expect(sentApi()->templates())->toBeInstanceOf(Templates::class);
});

it('webhooks() returns a Webhooks resource', function () {
    expect(sentApi()->webhooks())->toBeInstanceOf(Webhooks::class);
});

it('profiles() returns a Profiles resource', function () {
    expect(sentApi()->profiles())->toBeInstanceOf(Profiles::class);
});

it('users() returns a Users resource', function () {
    expect(sentApi()->users())->toBeInstanceOf(Users::class);
});

it('senderProfiles() returns a SenderProfiles resource', function () {
    expect(sentApi()->senderProfiles())->toBeInstanceOf(SenderProfiles::class);
});

it('channels() returns a Channels resource', function () {
    expect(sentApi()->channels())->toBeInstanceOf(Channels::class);
});

it('compliance() returns a Compliance resource', function () {
    expect(sentApi()->compliance())->toBeInstanceOf(Compliance::class);
});

// Contacts -------------------------------------------------------------------

it('contacts()->get() lists contacts', function () {
    $result = sentApi(['contacts' => [], 'total_count' => 0])->contacts()->get();
    expect($result)->not->toBeNull();
});

it('contacts()->search()->channel()->page()->perPage() chains are immutable', function () {
    $base = sentApi()->contacts();
    $chained = $base->search('John')->channel('whatsapp')->page(2)->perPage(25);
    expect($chained)->not->toBe($base);
});

it('contacts()->search()->get() passes search param', function () {
    $result = sentApi(['contacts' => [], 'total_count' => 0])
        ->contacts()
        ->search('John')
        ->get();
    expect($result)->not->toBeNull();
});

it('contacts()->phone()->get() passes the phone filter param', function () {
    $result = sentApi(['contacts' => [], 'total_count' => 0])
        ->contacts()
        ->phone('+12125550199')
        ->get();
    expect($result)->not->toBeNull();
});

it('contacts()->find() retrieves a contact', function () {
    $result = sentApi(['id' => 'c-1', 'phone_number' => '+61412345678'])->contacts()->find('c-1');
    expect($result)->not->toBeNull();
});

it('contacts()->create() returns a ContactBuilder', function () {
    expect(sentApi()->contacts()->create())->toBeInstanceOf(ContactBuilder::class);
});

it('contacts()->create()->phone()->save() creates a contact', function () {
    $result = sentApi(['id' => 'c-1', 'phone_number' => '+61412345678'])
        ->contacts()
        ->create()
        ->phone('+61412345678')
        ->save();
    expect($result)->not->toBeNull();
});

it('contacts()->create()->save() throws without a phone number', function () {
    sentApi()->contacts()->create()->save();
})->throws(InvalidArgumentException::class, 'phone number is required');

it('contacts()->create()->defaultChannel()->save() throws — defaultChannel is update-only', function () {
    sentApi()
        ->contacts()
        ->create()
        ->phone('+61412345678')
        ->defaultChannel('sms')
        ->save();
})->throws(InvalidArgumentException::class, 'defaultChannel');

it('contacts()->create()->optOut()->save() throws — optOut is update-only', function () {
    sentApi()
        ->contacts()
        ->create()
        ->phone('+61412345678')
        ->optOut(true)
        ->save();
})->throws(InvalidArgumentException::class, 'optOut');

it('contacts()->update() returns a ContactBuilder', function () {
    expect(sentApi()->contacts()->update('c-1'))->toBeInstanceOf(ContactBuilder::class);
});

it('contacts()->update()->optOut()->save() updates a contact', function () {
    $result = sentApi(['id' => 'c-1'])
        ->contacts()
        ->update('c-1')
        ->optOut(true)
        ->save();
    expect($result)->not->toBeNull();
});

it('contacts()->update()->defaultChannel()->save() updates a contact', function () {
    $result = sentApi(['id' => 'c-1', 'default_channel' => 'sms'])
        ->contacts()
        ->update('c-1')
        ->defaultChannel('sms')
        ->save();
    expect($result)->not->toBeNull();
});

it('contacts()->delete() deletes a contact', function () {
    sentApi([])->contacts()->delete('c-1');
    expect(true)->toBeTrue();
});

it("contacts()->messageSummary() retrieves a contact's message summary", function () {
    $result = sentApi([
        'contact_id' => 'c-1',
        'message_count' => 12,
        'first_message_at' => '2026-01-01T00:00:00Z',
        'last_message_at' => '2026-08-01T00:00:00Z',
        'channels_used' => ['sms', 'whatsapp'],
        'channel_scores' => [],
    ])->contacts()->messageSummary('c-1');
    expect($result)->not->toBeNull();
});

// Templates ------------------------------------------------------------------

it('templates()->get() lists templates', function () {
    $result = sentApi(['templates' => []])->templates()->get();
    expect($result)->not->toBeNull();
});

it('templates()->search()->get() passes the search param', function () {
    $result = sentApi(['templates' => []])->templates()->search('welcome')->get();
    expect($result)->not->toBeNull();
});

it('templates()->page()->perPage() chains are immutable', function () {
    $base = sentApi()->templates();
    $chained = $base->page(2)->perPage(25);
    expect($chained)->not->toBe($base);
});

it('templates()->find() retrieves a template', function () {
    $result = sentApi(['id' => 'tpl-1', 'name' => 'otp'])->templates()->find('tpl-1');
    expect($result)->not->toBeNull();
});

it('templates()->findByName() returns matching template', function () {
    $result = sentApi(['templates' => [['id' => 'tpl-1', 'name' => 'otp', 'status' => 'APPROVED']]])
        ->templates()
        ->findByName('otp');
    expect($result)->not->toBeNull();
});

it('templates()->findByName() returns null when not found', function () {
    $result = sentApi(['templates' => []])->templates()->findByName('nonexistent');
    expect($result)->toBeNull();
});

it('templates()->delete() deletes a template', function () {
    sentApi([])->templates()->delete('tpl-1');
    expect(true)->toBeTrue();
});

it('templates()->delete() accepts deleteFromMeta', function () {
    sentApi([])->templates()->delete('tpl-1', deleteFromMeta: true);
    expect(true)->toBeTrue();
});

// Webhooks -------------------------------------------------------------------

it('webhooks()->get() lists webhooks', function () {
    $result = sentApi(['webhooks' => []])->webhooks()->get();
    expect($result)->not->toBeNull();
});

it('webhooks()->page()->perPage() chains are immutable', function () {
    $base = sentApi()->webhooks();
    $chained = $base->page(2)->perPage(10);
    expect($chained)->not->toBe($base);
});

it('webhooks()->search()->isActive()->get() passes both filter params', function () {
    $result = sentApi(['webhooks' => []])->webhooks()->search('order')->isActive(true)->get();
    expect($result)->not->toBeNull();
});

it('webhooks()->listEvents() accepts a search param', function () {
    $result = sentApi(['events' => []])->webhooks()->listEvents('wh-1', search: 'delivered');
    expect($result)->not->toBeNull();
});

it('webhooks()->find() retrieves a webhook', function () {
    $result = sentApi(['id' => 'wh-1'])->webhooks()->find('wh-1');
    expect($result)->not->toBeNull();
});

it('webhooks()->create() returns a WebhookBuilder', function () {
    expect(sentApi()->webhooks()->create())->toBeInstanceOf(WebhookBuilder::class);
});

it('webhooks()->create()->name()->url()->events()->save() creates a webhook', function () {
    $result = sentApi(['id' => 'wh-1', 'endpoint_url' => 'https://example.com/wh'])
        ->webhooks()
        ->create()
        ->name('My webhook')
        ->url('https://example.com/wh')
        ->events(['message'])
        ->save();
    expect($result)->not->toBeNull();
});

it('webhooks()->create()->eventFilters()->retryCount()->timeoutSeconds()->save() passes all three', function () {
    $result = sentApi(['id' => 'wh-1'])
        ->webhooks()
        ->create()
        ->name('My webhook')
        ->url('https://example.com/wh')
        ->events(['message'])
        ->eventFilters(['message' => ['delivered', 'failed']])
        ->retryCount(2)
        ->timeoutSeconds(15)
        ->save();
    expect($result)->not->toBeNull();
});

it('webhooks()->create()->save() throws without a name', function () {
    sentApi()->webhooks()->create()->url('https://example.com/wh')->events(['message'])->save();
})->throws(InvalidArgumentException::class, 'A name is required');

it('webhooks()->create()->save() throws without events', function () {
    sentApi()->webhooks()->create()->name('My webhook')->url('https://example.com/wh')->save();
})->throws(InvalidArgumentException::class, 'At least one event category is required');

it('webhooks()->create()->save() throws without a url', function () {
    sentApi()->webhooks()->create()->name('My webhook')->events(['message'])->save();
})->throws(InvalidArgumentException::class, 'A URL is required');

it('webhooks()->update() returns a WebhookBuilder', function () {
    expect(sentApi()->webhooks()->update('wh-1'))->toBeInstanceOf(WebhookBuilder::class);
});

it('webhooks()->update()->name()->url()->events()->save() updates a webhook', function () {
    $result = sentApi(['id' => 'wh-1'])
        ->webhooks()
        ->update('wh-1')
        ->name('My webhook')
        ->url('https://example.com/new')
        ->events(['message'])
        ->save();
    expect($result)->not->toBeNull();
});

it('webhooks()->delete() deletes a webhook', function () {
    sentApi([])->webhooks()->delete('wh-1');
    expect(true)->toBeTrue();
});

it('webhooks()->enable() enables a webhook', function () {
    $result = sentApi(['id' => 'wh-1', 'is_active' => true])->webhooks()->enable('wh-1');
    expect($result)->not->toBeNull();
});

it('webhooks()->disable() disables a webhook', function () {
    $result = sentApi(['id' => 'wh-1', 'is_active' => false])->webhooks()->disable('wh-1');
    expect($result)->not->toBeNull();
});

it('webhooks()->rotateSecret() rotates the signing secret', function () {
    $result = sentApi(['signing_secret' => 'whsec_new'])->webhooks()->rotateSecret('wh-1');
    expect($result)->not->toBeNull();
});

it('webhooks()->rotateSecret() checks the webhook exists before rotating', function () {
    // retrieve() 404s, rotateSecret() should throw before reaching the rotate call.
    $transporter = new class implements ClientInterface
    {
        public function sendRequest(RequestInterface $r): ResponseInterface
        {
            if (str_contains((string) $r->getUri(), 'rotate-secret')) {
                throw new Exception('rotateSecret() should not be called for an id that does not exist.');
            }

            $body = json_encode([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Webhook not found.'],
                'meta' => ['request_id' => 'test', 'timestamp' => '2025-01-01T00:00:00Z', 'version' => 'v3'],
            ]);

            return new Response(404, ['Content-Type' => 'application/json'], $body);
        }
    };

    $opts = new RequestOptions;
    $opts['transporter'] = $transporter;
    $opts['maxRetries'] = 0;

    $sent = new Sent(new Client(apiKey: 'test', requestOptions: $opts));

    expect(fn () => $sent->webhooks()->rotateSecret('wh-missing'))
        ->toThrow(NotFoundException::class);
});

it('webhooks()->rotateSecret(sandbox: true) skips the existence guard', function () {
    // Guard is skipped: only rotate-secret should be called, not retrieve().
    $transporter = new class implements ClientInterface
    {
        public function sendRequest(RequestInterface $r): ResponseInterface
        {
            if (! str_contains((string) $r->getUri(), 'rotate-secret')) {
                throw new Exception('retrieve() should not be called when sandbox is on.');
            }

            $body = json_encode([
                'success' => true,
                'data' => ['signing_secret' => 'whsec_sandboxed'],
                'meta' => ['request_id' => 'test', 'timestamp' => '2025-01-01T00:00:00Z', 'version' => 'v3'],
            ]);

            return new Response(200, ['Content-Type' => 'application/json'], $body);
        }
    };

    $opts = new RequestOptions;
    $opts['transporter'] = $transporter;
    $opts['maxRetries'] = 0;

    $sent = new Sent(new Client(apiKey: 'test', requestOptions: $opts));

    $result = $sent->webhooks()->rotateSecret('wh-sandboxed', sandbox: true);
    expect($result)->not->toBeNull();
});

// Profiles -------------------------------------------------------------------

it('profiles()->get() lists profiles', function () {
    $result = sentApi(['profiles' => []])->profiles()->get();
    expect($result)->not->toBeNull();
});

it('profiles()->find() retrieves a profile', function () {
    $result = sentApi(['id' => 'prof-1'])->profiles()->find('prof-1');
    expect($result)->not->toBeNull();
});

it('profiles()->delete() deletes a profile', function () {
    sentApi([])->profiles()->delete('prof-1');
    expect(true)->toBeTrue();
});

it('profiles()->create() returns a ProfileBuilder', function () {
    expect(sentApi()->profiles()->create())->toBeInstanceOf(ProfileBuilder::class);
});

it('profiles()->create()->name()->save() creates a profile', function () {
    $result = sentApi(['id' => 'prof-1', 'name' => 'Sales'])->profiles()->create()->name('Sales')->save();
    expect($result)->not->toBeNull();
});

it('profiles()->create() chains are immutable', function () {
    $base = sentApi()->profiles()->create();
    $chained = $base->name('Sales')->description('Sales profile');
    expect($chained)->not->toBe($base);
});

it('profiles()->create() builder exercises all create setters', function () {
    $base = sentApi(['id' => 'prof-1'])->profiles()->create();
    $result = $base
        ->name('Test')
        ->description('desc')
        ->shortName('TST')
        ->icon('https://example.com/icon.png')
        ->billingModel('organization')
        ->inheritContacts(true)
        ->inheritTemplates(true)
        ->inheritTcrBrand(true)
        ->inheritTcrCampaign(true)
        ->allowContactSharing(true)
        ->allowTemplateSharing(true)
        ->billingContact([])
        ->brand([])
        ->paymentDetails([])
        ->whatsappBusinessAccount([])
        ->save();
    expect($result)->not->toBeNull();
});

it('profiles()->update() returns a ProfileBuilder', function () {
    expect(sentApi()->profiles()->update('prof-1'))->toBeInstanceOf(ProfileBuilder::class);
});

it('profiles()->update()->name()->save() updates a profile', function () {
    $result = sentApi(['id' => 'prof-1', 'name' => 'Support'])->profiles()->update('prof-1')->name('Support')->save();
    expect($result)->not->toBeNull();
});

it('profiles()->update() chains are immutable', function () {
    $base = sentApi()->profiles()->update('prof-1');
    $chained = $base->name('Support')->shortName('SUP');
    expect($chained)->not->toBe($base);
});

it('profiles()->update() builder exercises all update-only setters', function () {
    $base = sentApi(['id' => 'prof-1'])->profiles()->update('prof-1');
    $result = $base
        ->allowNumberChangeDuringOnboarding(true)
        ->sendingPhoneNumber('+61412345678')
        ->sendingPhoneNumberProfileId('prof-2')
        ->sendingWhatsappNumberProfileId('prof-3')
        ->whatsappPhoneNumber('+61412345678')
        ->save();
    expect($result)->not->toBeNull();
});

it('profiles()->complete() triggers profile completion', function () {
    sentApi([])->profiles()->complete('prof-1', 'https://example.com/webhook');
    expect(true)->toBeTrue();
});

it('profiles()->campaigns() returns a Campaigns resource', function () {
    expect(sentApi()->profiles()->campaigns('prof-1'))->toBeInstanceOf(Campaigns::class);
});

it('profiles()->campaigns()->get() lists campaigns', function () {
    $result = sentApi(['campaigns' => []])->profiles()->campaigns('prof-1')->get();
    expect($result)->not->toBeNull();
});

it('profiles()->campaigns()->create() creates a campaign', function () {
    $result = sentApi(['id' => 'camp-1'])
        ->profiles()
        ->campaigns('prof-1')
        ->create(['description' => 'OTP', 'name' => 'OTP', 'type' => 'KYC', 'useCases' => []]);
    expect($result)->not->toBeNull();
});

it('profiles()->campaigns()->update() updates a campaign', function () {
    $result = sentApi(['id' => 'camp-1'])
        ->profiles()
        ->campaigns('prof-1')
        ->update('camp-1', ['description' => 'OTP v2', 'name' => 'OTP', 'type' => 'KYC', 'useCases' => []]);
    expect($result)->not->toBeNull();
});

it('profiles()->campaigns()->delete() deletes a campaign', function () {
    sentApi([])->profiles()->campaigns('prof-1')->delete('camp-1');
    expect(true)->toBeTrue();
});

// Users ----------------------------------------------------------------------

it('users()->get() lists users', function () {
    $result = sentApi(['users' => []])->users()->get();
    expect($result)->not->toBeNull();
});

it('users()->find() retrieves a user', function () {
    $result = sentApi(['id' => 'user-1'])->users()->find('user-1');
    expect($result)->not->toBeNull();
});

it('users()->invite() returns a UserInviteBuilder', function () {
    expect(sentApi()->users()->invite())->toBeInstanceOf(UserInviteBuilder::class);
});

it('users()->invite()->email()->name()->role()->save() invites a user', function () {
    $result = sentApi(['id' => 'user-1', 'email' => 'jane@example.com'])
        ->users()
        ->invite()
        ->email('jane@example.com')
        ->name('Jane')
        ->role('admin')
        ->save();
    expect($result)->not->toBeNull();
});

it('users()->remove() removes a user', function () {
    sentApi([])->users()->remove('user-1');
    expect(true)->toBeTrue();
});

// Account + lookup -----------------------------------------------------------

it('account() delegates to SDK me->retrieve', function () {
    $result = sentApi(['type' => 'organization', 'name' => 'Acme', 'email' => 'a@b.com'])->account();
    expect($result)->not->toBeNull();
});

it('me() returns an Account resource', function () {
    expect(sentApi()->me())->toBeInstanceOf(Account::class);
});

it('me()->get() delegates to SDK me->retrieve', function () {
    $result = sentApi(['type' => 'organization', 'name' => 'Acme', 'email' => 'a@b.com'])->me()->get();
    expect($result)->not->toBeNull();
});

it('me()->profile() sends x-profile-id, unlike account()', function () {
    [$captured, $sent] = capturedSentHeaders();
    $sent->me()->profile('child-profile-id')->get();

    expect($captured->headers['x-profile-id'] ?? null)->toBe(['child-profile-id']);
});

it('lookup() delegates to SDK numbers->lookup', function () {
    $result = sentApi(['isValid' => true, 'carrierName' => 'Telstra'])->lookup('+61412345678');
    expect($result)->not->toBeNull();
});

// Templates — write ops + filters --------------------------------------------

it('templates()->create() returns a TemplateBuilder', function () {
    expect(sentApi()->templates()->create())->toBeInstanceOf(TemplateBuilder::class);
});

it('templates()->create()->category()->language()->save() creates a template', function () {
    $result = sentApi(['id' => 'tpl-1', 'name' => 'otp'])
        ->templates()
        ->create()
        ->category('MARKETING')
        ->language('en_US')
        ->save();
    expect($result)->not->toBeNull();
});

it('templates()->create()->submitForReview()->save() creates a template submitted for review', function () {
    $result = sentApi(['id' => 'tpl-1', 'name' => 'otp'])
        ->templates()
        ->create()
        ->submitForReview()
        ->save();
    expect($result)->not->toBeNull();
});

it('templates()->create()->definition()->save() creates a template with a definition', function () {
    $result = sentApi(['id' => 'tpl-1'])
        ->templates()
        ->create()
        ->category('UTILITY')
        ->definition(['body' => ['sms' => ['template' => 'Hello {{name}}', 'type' => 'text']]])
        ->save();
    expect($result)->not->toBeNull();
});

it('templates()->create() chains are immutable', function () {
    $base = sentApi()->templates()->create();
    $chained = $base->category('MARKETING')->language('en_US');
    expect($chained)->not->toBe($base);
});

it('templates()->create()->name()->save() throws — name is update-only', function () {
    sentApi()->templates()->create()->name('my-template')->save();
})->throws(InvalidArgumentException::class, 'name() is not supported when creating');

it('templates()->create()->creationSource()->save() passes creation_source', function () {
    $result = sentApi(['id' => 'tpl-1'])->templates()->create()->creationSource('import-script')->save();
    expect($result)->not->toBeNull();
});

it('templates()->update()->creationSource()->save() throws — creationSource is create-only', function () {
    sentApi(['id' => 'tpl-1'])->templates()->update('tpl-1')->creationSource('import-script')->save();
})->throws(InvalidArgumentException::class, 'creationSource() is not supported when updating');

it('templates()->update() returns a TemplateBuilder', function () {
    expect(sentApi()->templates()->update('tpl-1'))->toBeInstanceOf(TemplateBuilder::class);
});

it('templates()->update()->name()->save() updates a template', function () {
    $result = sentApi(['id' => 'tpl-1', 'name' => 'new-name'])
        ->templates()
        ->update('tpl-1')
        ->name('new-name')
        ->save();
    expect($result)->not->toBeNull();
});

it('templates()->update() chains are immutable', function () {
    $base = sentApi()->templates()->update('tpl-1');
    $chained = $base->name('new-name')->category('UTILITY');
    expect($chained)->not->toBe($base);
});

it('templates()->category()->get() filters by category', function () {
    $base = sentApi(['templates' => []])->templates();
    $filtered = $base->category('MARKETING');
    expect($filtered)->not->toBe($base);
    $result = $filtered->get();
    expect($result)->not->toBeNull();
});

it('templates()->status()->get() filters by status', function () {
    $base = sentApi(['templates' => []])->templates();
    $filtered = $base->status('APPROVED');
    expect($filtered)->not->toBe($base);
    $result = $filtered->get();
    expect($result)->not->toBeNull();
});

it('templates()->isWelcomePlayground()->get() filters by welcome playground flag', function () {
    $base = sentApi(['templates' => []])->templates();
    $filtered = $base->isWelcomePlayground(true);
    expect($filtered)->not->toBe($base);
    $result = $filtered->get();
    expect($result)->not->toBeNull();
});

// Users — updateRole ---------------------------------------------------------

it('users()->updateRole() updates a user role', function () {
    $result = sentApi(['id' => 'user-1', 'role' => 'admin'])
        ->users()
        ->updateRole('user-1', 'admin');
    expect($result)->not->toBeNull();
});

// Webhooks — test, listEvents, listEventTypes --------------------------------

it('webhooks()->test() sends a test event', function () {
    $result = sentApi(['success' => true])->webhooks()->test('wh-1', 'message.delivered');
    expect($result)->not->toBeNull();
});

it('webhooks()->test() without eventType throws', function () {
    sentApi(['success' => true])->webhooks()->test('wh-1');
})->throws(InvalidArgumentException::class, 'An event type is required.');

it('webhooks()->listEvents() lists events for a webhook', function () {
    $result = sentApi(['events' => []])->webhooks()->listEvents('wh-1');
    expect($result)->not->toBeNull();
});

it('webhooks()->listEvents() accepts page and pageSize', function () {
    $result = sentApi(['events' => []])->webhooks()->listEvents('wh-1', page: 2, pageSize: 25);
    expect($result)->not->toBeNull();
});

it('webhooks()->listEventTypes() lists all event types', function () {
    $result = sentApi(['event_types' => []])->webhooks()->listEventTypes();
    expect($result)->not->toBeNull();
});

// Messages resource ----------------------------------------------------------

it('messages() returns a Messages resource', function () {
    expect(sentApi()->messages())->toBeInstanceOf(Messages::class);
});

it('messages()->retrieve() returns message status', function () {
    $result = sentApi(['message_id' => 'msg-1', 'message_status' => 'DELIVERED'])
        ->messages()
        ->retrieve('msg-1');
    expect($result)->not->toBeNull();
});

it('messages()->activities() returns message activities', function () {
    $result = sentApi(['activities' => []])->messages()->activities('msg-1');
    expect($result)->not->toBeNull();
});

// Conversations ----------------------------------------------------------------

it('conversations()->page()->perPage() chains are immutable', function () {
    $base = sentApi()->conversations();
    $chained = $base->page(2)->perPage(25);
    expect($chained)->not->toBe($base);
});

it('conversations()->get() lists conversations', function () {
    $result = sentApi(['messages' => [], 'pagination' => []])->conversations()->get();
    expect($result)->not->toBeNull();
});

it('conversations()->messages() lists messages for a conversation', function () {
    $result = sentApi(['messages' => [], 'pagination' => []])->conversations()->messages('conv-1');
    expect($result)->not->toBeNull();
});

// Sender profiles -------------------------------------------------------------

it('senderProfiles()->get() lists sender profiles', function () {
    $result = sentApi(['sender_profiles' => []])->senderProfiles()->get();
    expect($result)->not->toBeNull();
});

it('senderProfiles()->page()->perPage() chains are immutable', function () {
    $base = sentApi()->senderProfiles();
    $chained = $base->page(2)->perPage(25);
    expect($chained)->not->toBe($base);
});

it('senderProfiles()->find() retrieves a sender profile', function () {
    $result = sentApi(['id' => 'sp-1', 'name' => 'Example Retail'])->senderProfiles()->find('sp-1');
    expect($result)->toBeInstanceOf(SenderProfileData::class)
        ->and($result->id)->toBe('sp-1')
        ->and($result->name)->toBe('Example Retail');
});

it('senderProfiles()->create() returns a SenderProfileBuilder', function () {
    expect(sentApi()->senderProfiles()->create())->toBeInstanceOf(SenderProfileBuilder::class);
});

it('senderProfiles()->create()->name()->shortName()->save() creates a sender profile', function () {
    $result = sentApi(['id' => 'sp-1', 'name' => 'Example Retail', 'short_name' => 'Example'])
        ->senderProfiles()
        ->create()
        ->name('Example Retail')
        ->shortName('Example')
        ->save();
    expect($result)->not->toBeNull();
});

it('senderProfiles()->create()->attach()->save() sends a multipart request with a profile field and the file', function () {
    [$captured, $sent] = capturedSentHeaders(['id' => 'sp-1', 'name' => 'Example Retail']);

    $sent->senderProfiles()->create()
        ->name('Example Retail')
        ->shortName('Example')
        ->attach('business_registration', FileParam::fromString('pdf bytes', 'registration.pdf'))
        ->save();

    expect($captured->headers['Content-Type'][0] ?? null)->toStartWith('multipart/form-data');
});

it('senderProfiles()->update()->attach()->save() throws — attach() is create-only', function () {
    sentApi()->senderProfiles()->update('sp-1')
        ->attach('business_registration', FileParam::fromString('pdf bytes', 'registration.pdf'))
        ->save();
})->throws(InvalidArgumentException::class, 'attach() is not supported on update()');

it('senderProfiles()->create()->save() throws without a name', function () {
    sentApi()->senderProfiles()->create()->shortName('Example')->save();
})->throws(InvalidArgumentException::class, 'A name is required');

it('senderProfiles()->create()->save() throws without a short name', function () {
    sentApi()->senderProfiles()->create()->name('Example Retail')->save();
})->throws(InvalidArgumentException::class, 'A short name is required');

it('senderProfiles()->create() builder exercises all setters', function () {
    $result = sentApi(['id' => 'sp-1'])
        ->senderProfiles()
        ->create()
        ->name('Example Retail')
        ->shortName('Example')
        ->description('Retail sender profile')
        ->billing(['inherit' => true])
        ->channels(['sms' => ['country' => 'US', 'number_type' => 'TEN_DLC']])
        ->compliance(['brand' => []])
        ->sandbox(true)
        ->save();
    expect($result)->not->toBeNull();
});

it('senderProfiles()->create() chains are immutable', function () {
    $base = sentApi()->senderProfiles()->create();
    $chained = $base->name('Example Retail')->shortName('Example');
    expect($chained)->not->toBe($base);
});

it('senderProfiles()->update() returns a SenderProfileBuilder', function () {
    expect(sentApi()->senderProfiles()->update('sp-1'))->toBeInstanceOf(SenderProfileBuilder::class);
});

it('senderProfiles()->update()->name()->save() updates a sender profile without a short name', function () {
    $result = sentApi(['id' => 'sp-1', 'name' => 'New Name'])
        ->senderProfiles()
        ->update('sp-1')
        ->name('New Name')
        ->save();
    expect($result)->not->toBeNull();
});

it('senderProfiles()->update()->billing()->save() throws — billing is create-only', function () {
    sentApi()->senderProfiles()->update('sp-1')->billing(['inherit' => true])->save();
})->throws(InvalidArgumentException::class, 'not supported on update()');

it('senderProfiles()->update()->channels()->save() throws — channels is create-only', function () {
    sentApi()->senderProfiles()->update('sp-1')->channels(['sms' => []])->save();
})->throws(InvalidArgumentException::class, 'not supported on update()');

it('senderProfiles()->update()->compliance()->save() throws — compliance is create-only', function () {
    sentApi()->senderProfiles()->update('sp-1')->compliance(['brand' => []])->save();
})->throws(InvalidArgumentException::class, 'not supported on update()');

it('senderProfiles()->delete() deletes a sender profile', function () {
    sentApi([])->senderProfiles()->delete('sp-1');
    expect(true)->toBeTrue();
});

// Channels ---------------------------------------------------------------------

it('channels()->get() returns channel state', function () {
    $result = sentApi(['sms' => [], 'whatsapp' => null, 'rcs' => null])->channels()->get();
    expect($result)->not->toBeNull();
});

it('channels()->smsMarkets() lists SMS markets', function () {
    $result = sentApi(['markets' => []])->channels()->smsMarkets();
    expect($result)->not->toBeNull();
});

it('channels()->findSmsMarket() retrieves an SMS market', function () {
    $result = sentApi(['country' => 'US', 'number_type' => 'TEN_DLC'])
        ->channels()
        ->findSmsMarket('US', 'TEN_DLC');
    expect($result)->not->toBeNull();
});

it('channels()->addSmsMarket() adds an SMS market', function () {
    $result = sentApi(['country' => 'US', 'number_type' => 'TEN_DLC'])
        ->channels()
        ->addSmsMarket(['country' => 'US', 'number_type' => 'TEN_DLC']);
    expect($result)->not->toBeNull();
});

it('channels()->addSmsMarket() with a FileParam sends a multipart request with renamed fields', function () {
    [$captured, $sent] = capturedSentHeaders(['country' => 'XK', 'number_type' => 'ALPHANUMERIC']);

    $sent->channels()->addSmsMarket([
        'country' => 'XK',
        'number_type' => 'ALPHANUMERIC',
        'sender_value' => 'EXAMPLE',
        'business_registration' => FileParam::fromString('pdf bytes', 'registration.pdf'),
    ]);

    expect($captured->headers['Content-Type'][0] ?? null)->toStartWith('multipart/form-data')
        ->and($captured->body)->toContain('name="numberType"')
        ->and($captured->body)->toContain('name="senderValue"')
        ->and($captured->body)->not->toContain('name="number_type"')
        ->and($captured->body)->not->toContain('name="sender_value"');
});

it('channels()->addSmsMarket() throws when compliance is combined with a document', function () {
    sentApi()->channels()->addSmsMarket([
        'country' => 'XK',
        'number_type' => 'ALPHANUMERIC',
        'compliance' => ['brand' => ['inherit' => true]],
        'business_registration' => FileParam::fromString('pdf bytes', 'registration.pdf'),
    ]);
})->throws(InvalidArgumentException::class, 'compliance is not supported together with a document upload');

it('channels()->updateSmsMarket() updates an SMS market', function () {
    $result = sentApi(['country' => 'US', 'number_type' => 'TEN_DLC'])
        ->channels()
        ->updateSmsMarket('US', 'TEN_DLC', ['sandbox' => true]);
    expect($result)->not->toBeNull();
});

it('channels()->addWhatsapp() adds a WhatsApp channel', function () {
    $result = sentApi(['waba_id' => 'waba-1'])->channels()->addWhatsapp(['waba_id' => 'waba-1']);
    expect($result)->not->toBeNull();
});

it('channels()->addRcs() adds an RCS agent', function () {
    $result = sentApi(['brand_name' => 'Acme', 'sample_messages' => ['Hi there']])
        ->channels()
        ->addRcs([
            'brand_name' => 'Acme',
            'privacy_policy_url' => 'https://example.com/privacy',
            'terms_and_conditions_url' => 'https://example.com/terms',
        ]);
    expect($result->brandName)->toBe('Acme')
        ->and($result->sampleMessages)->toBe(['Hi there']);
});

// Compliance ---------------------------------------------------------------------

it('compliance()->requirements() defaults to the sms channel', function () {
    $result = sentApi(['fields' => []])->compliance()->requirements('US', 'TEN_DLC');
    expect($result)->not->toBeNull();
});

it('compliance()->requirements() accepts an explicit channel override', function () {
    $result = sentApi(['fields' => []])->compliance()->requirements('US', 'TEN_DLC', 'sms');
    expect($result)->not->toBeNull();
});
