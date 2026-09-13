# API reference

- [Contacts](#contacts)
- [Templates](#templates)
- [Webhooks](#webhooks)
- [Profiles](#profiles)
- [Users](#users)
- [Messages](#messages)
- [Conversations](#conversations)
- [Account](#account)
- [Artisan commands](#artisan-commands)

## Contacts

```php
// list: chainable query builder
Sent::contacts()->get();
// search matches the contact's national-format phone number exactly, including
// punctuation (e.g. "555-0142", not "5550142"). Contacts have no name field.
Sent::contacts()->search('555-0142')->channel('whatsapp')->page(2)->perPage(25)->get();

// read (cached)
Sent::contacts()->find('contact_id');

// create
Sent::contacts()->create()->phone('+61412345678')->save();
Sent::contacts()->create()->phone('+61412345678')->defaultChannel('sms')->save();

// update (invalidates cache)
Sent::contacts()->update('contact_id')->defaultChannel('whatsapp')->save();
Sent::contacts()->update('contact_id')->optOut(true)->save();

// delete (invalidates cache)
Sent::contacts()->delete('contact_id');

// message summary (cached): count, first/last message timestamps, channels used
$summary = Sent::contacts()->messageSummary('contact_id');
$summary->data->messageCount;
$summary->data->channelsUsed;
```

## Templates

```php
// list (cached per page)
Sent::templates()->get();
Sent::templates()->page(2)->perPage(25)->get();

// filter by category (MARKETING, UTILITY, AUTHENTICATION)
Sent::templates()->category('MARKETING')->get();

// filter by status (APPROVED, PENDING, REJECTED)
Sent::templates()->status('APPROVED')->get();

// filter by welcome playground flag
Sent::templates()->isWelcomePlayground()->get();

// read (cached)
Sent::templates()->find('template_id');
Sent::templates()->findByName('otp-verification');

// create
Sent::templates()->create()
    ->category('UTILITY')
    ->language('en_US')
    ->definition(['body' => [...]])
    ->save();

// create and submit for review immediately
Sent::templates()->create()
    ->category('MARKETING')
    ->definition(['body' => [...]])
    ->submitForReview()
    ->save();

// update (invalidates cache)
Sent::templates()->update('template_id')
    ->name('new-name')
    ->category('UTILITY')
    ->save();

// delete
Sent::templates()->delete('template_id');
```

From the command line:

```bash
php artisan sent:templates
php artisan sent:templates --page=2 --per-page=25
```

## Webhooks

Managing webhooks endpoints from code, beyond receiving events, is covered in
[webhooks.md](webhooks.md#managing-webhooks-from-code).

## Profiles

```php
// list (cached)
Sent::profiles()->get();

// read
Sent::profiles()->find('profile_id');

// create
Sent::profiles()->create()
    ->name('Sales Team')                   // required
    ->shortName('SALES')                   // 3-11 chars
    ->description('Outbound sales')
    ->billingModel('organization')         // 'organization' | 'profile' | 'profile_and_organization'
    ->inheritContacts(true)
    ->inheritTemplates(true)
    ->inheritTcrBrand(true)
    ->inheritTcrCampaign(true)
    ->allowContactSharing(false)
    ->allowTemplateSharing(false)
    ->icon('https://example.com/logo.png')
    ->billingContact([...])                // required when billingModel is 'profile'
    ->brand([...])                         // brand + KYC data
    ->paymentDetails([...])                // card details forwarded to payment processor
    ->whatsappBusinessAccount([...])       // direct WABA credentials from Meta
    ->save();

// update: all fields optional; also exposes sending number overrides
Sent::profiles()->update('profile_id')
    ->name('Support Team')
    ->inheritTemplates(true)
    ->allowNumberChangeDuringOnboarding(true)
    ->sendingPhoneNumber('+61412345678')
    ->sendingPhoneNumberProfileId('other_profile_id')
    ->sendingWhatsappNumberProfileId('other_profile_id')
    ->whatsappPhoneNumber('+61412345678')
    ->save();

// complete profile onboarding (runs in background, calls your webhook when done)
Sent::profiles()->complete('profile_id', 'https://yourapp.com/hooks/profile-complete');

// delete
Sent::profiles()->delete('profile_id');
```

### Campaigns sub-resource

Manage TCR campaigns scoped to a profile:

```php
$campaigns = Sent::profiles()->campaigns('profile_id');

// list
$campaigns->get();

// create
$campaigns->create([
    'name'        => 'OTP Verification',
    'description' => 'One-time passcode delivery',
    'type'        => 'STANDARD',
    'useCases'    => [
        ['messagingUseCaseUs' => 'TWO_FA', 'sampleMessages' => ['Your code is 123456.']],
    ],
]);

// update
$campaigns->update('campaign_id', [
    'name'        => 'OTP v2',
    'description' => 'Updated OTP campaign',
    'type'        => 'STANDARD',
    'useCases'    => [
        ['messagingUseCaseUs' => 'TWO_FA', 'sampleMessages' => ['Your verification code is 123456.']],
    ],
]);

// delete
$campaigns->delete('campaign_id');
```

## Users

```php
// list
Sent::users()->get();

// read
Sent::users()->find('user_id');

// invite (role: admin, billing, or developer)
Sent::users()->invite()
    ->email('alice@example.com')
    ->name('Alice')
    ->role('developer')
    ->save();

// update role (admin, billing, developer)
Sent::users()->updateRole('user_id', 'admin');

// remove
Sent::users()->remove('user_id');
```

## Messages

Check the status of a sent message or retrieve its activity log by message ID:

```php
// get current delivery status
$status = Sent::messages()->retrieve('msg_abc123');
$status->data->messageStatus; // 'QUEUED', 'SENT', 'DELIVERED', 'FAILED', etc.

// get activity log (all events for the message)
$activities = Sent::messages()->activities('msg_abc123');
```

Message IDs are returned in the `MessageSent` event and stored in `sent_logs.message_id` when logging is enabled.

## Conversations

Browse conversation threads (grouped by contact/channel) and the messages within one. Read-only, not cached, same as any other paginated list resource:

```php
// list conversations
Sent::conversations()->get();
Sent::conversations()->page(2)->perPage(25)->get();

// list messages within a conversation
Sent::conversations()->messages('conversation_id');
Sent::conversations()->page(2)->perPage(25)->messages('conversation_id');
```

## Account

```php
$account = Sent::account();

$account->data->type;    // 'organization', 'user', or 'profile'
$account->data->name;
$account->data->email;
$account->data->channels->sms->configured;       // bool
$account->data->channels->whatsapp->configured;  // bool
```

Check account health from the command line:

```bash
php artisan sent:health
php artisan sent:health --connection=acme
```

## Artisan commands

| Command | Description |
|---|---|
| `sent:install` | Publish `config/sent.php` |
| `sent:health` | Check API connectivity and account status |
| `sent:test-send {number} --template=` | Send a test message |
| `sent:templates` | List templates in a table |
| `sent:lookup {number}` | Carrier lookup for a phone number |
| `sent:setup-webhook {url}` | Create a webhook endpoint on Sent.dm |
| `sent:stats` | Show aggregate message counts from the local `sent_logs` table (not from the Sent.dm API; requires logging migration) |

All commands accept `--connection=` to target a named connection.

```bash
# test a send in sandbox mode
php artisan sent:test-send +61412345678 --template=otp --sandbox

# check a named tenant connection
php artisan sent:health --connection=acme

# create a webhook for specific events
php artisan sent:setup-webhook https://yourapp.com/sent/webhook \
    --events=message.delivered \
    --events=message.failed

# show local message stats (requires logging migration)
php artisan sent:stats
php artisan sent:stats --table=custom_logs_table
```
