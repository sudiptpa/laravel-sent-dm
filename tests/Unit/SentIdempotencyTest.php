<?php

declare(strict_types=1);

/**
 * `Idempotency-Key` is a header on every POST/PUT/PATCH in Sent.dm's v3 spec (confirmed
 * against the live spec directly, not assumed). These tests capture the actual outgoing
 * header for every one of those 23 operations this package wraps, both through typed SDK
 * calls and through the raw() escape hatch (Channels, SenderProfiles). Confirmed live
 * separately (not in this suite): the same key sent twice returns the same record instead
 * of creating a duplicate, for both a typed call (Contacts) and a raw() call
 * (SenderProfiles).
 */
it('contacts()->create() sends Idempotency-Key', function () {
    [$captured, $sent] = capturedSentHeaders(['id' => 'c-1']);
    $sent->contacts()->create()->phone('+61412345678')->idempotencyKey('k-1')->save();

    expect($captured->headers['Idempotency-Key'] ?? null)->toBe(['k-1']);
});

it('contacts()->update() sends Idempotency-Key', function () {
    [$captured, $sent] = capturedSentHeaders(['id' => 'c-1']);
    $sent->contacts()->update('c-1')->optOut(true)->idempotencyKey('k-1')->save();

    expect($captured->headers['Idempotency-Key'] ?? null)->toBe(['k-1']);
});

it('profiles()->create() sends Idempotency-Key', function () {
    [$captured, $sent] = capturedSentHeaders(['id' => 'p-1']);
    $sent->profiles()->create()->name('Acme')->idempotencyKey('k-1')->save();

    expect($captured->headers['Idempotency-Key'] ?? null)->toBe(['k-1']);
});

it('profiles()->update() sends Idempotency-Key', function () {
    [$captured, $sent] = capturedSentHeaders(['id' => 'p-1']);
    $sent->profiles()->update('p-1')->name('Acme')->idempotencyKey('k-1')->save();

    expect($captured->headers['Idempotency-Key'] ?? null)->toBe(['k-1']);
});

it('profiles()->complete() sends Idempotency-Key', function () {
    [$captured, $sent] = capturedSentHeaders();
    $sent->profiles()->complete('p-1', 'https://example.com/hook', 'k-1');

    expect($captured->headers['Idempotency-Key'] ?? null)->toBe(['k-1']);
});

it('profiles()->campaigns()->create() sends Idempotency-Key', function () {
    [$captured, $sent] = capturedSentHeaders(['id' => 'camp-1']);
    $sent->profiles()->campaigns('p-1')->create([
        'description' => 'x', 'name' => 'x', 'type' => 'STANDARD',
        'useCases' => [['messagingUseCaseUs' => 'ACCOUNT_NOTIFICATION', 'sampleMessages' => ['hi']]],
    ], 'k-1');

    expect($captured->headers['Idempotency-Key'] ?? null)->toBe(['k-1']);
});

it('profiles()->campaigns()->update() sends Idempotency-Key', function () {
    [$captured, $sent] = capturedSentHeaders(['id' => 'camp-1']);
    $sent->profiles()->campaigns('p-1')->update('camp-1', [
        'description' => 'x', 'name' => 'x', 'type' => 'STANDARD',
        'useCases' => [['messagingUseCaseUs' => 'ACCOUNT_NOTIFICATION', 'sampleMessages' => ['hi']]],
    ], 'k-1');

    expect($captured->headers['Idempotency-Key'] ?? null)->toBe(['k-1']);
});

it('senderProfiles()->create() sends Idempotency-Key through raw()', function () {
    [$captured, $sent] = capturedSentHeaders(['id' => 'sp-1']);
    $sent->senderProfiles()->create()->name('Acme')->shortName('ACME')->idempotencyKey('k-1')->save();

    expect($captured->headers['Idempotency-Key'] ?? null)->toBe(['k-1']);
});

it('senderProfiles()->update() sends Idempotency-Key through raw()', function () {
    [$captured, $sent] = capturedSentHeaders(['id' => 'sp-1']);
    $sent->senderProfiles()->update('sp-1')->description('x')->idempotencyKey('k-1')->save();

    expect($captured->headers['Idempotency-Key'] ?? null)->toBe(['k-1']);
});

it('templates()->create() sends Idempotency-Key', function () {
    [$captured, $sent] = capturedSentHeaders(['id' => 'tpl-1']);
    $sent->templates()->create()->idempotencyKey('k-1')->save();

    expect($captured->headers['Idempotency-Key'] ?? null)->toBe(['k-1']);
});

it('templates()->update() sends Idempotency-Key', function () {
    [$captured, $sent] = capturedSentHeaders(['id' => 'tpl-1']);
    $sent->templates()->update('tpl-1')->idempotencyKey('k-1')->save();

    expect($captured->headers['Idempotency-Key'] ?? null)->toBe(['k-1']);
});

it('users()->invite() sends Idempotency-Key', function () {
    [$captured, $sent] = capturedSentHeaders(['id' => 'u-1']);
    $sent->users()->invite()->email('a@example.com')->name('A')->role('developer')->idempotencyKey('k-1')->save();

    expect($captured->headers['Idempotency-Key'] ?? null)->toBe(['k-1']);
});

it('users()->updateRole() sends Idempotency-Key', function () {
    [$captured, $sent] = capturedSentHeaders(['id' => 'u-1']);
    $sent->users()->updateRole('u-1', 'admin', 'k-1');

    expect($captured->headers['Idempotency-Key'] ?? null)->toBe(['k-1']);
});

it('webhooks()->create() sends Idempotency-Key', function () {
    [$captured, $sent] = capturedSentHeaders(['id' => 'wh-1']);
    $sent->webhooks()->create()->name('x')->url('https://example.com')->events(['message'])->idempotencyKey('k-1')->save();

    expect($captured->headers['Idempotency-Key'] ?? null)->toBe(['k-1']);
});

it('webhooks()->update() sends Idempotency-Key', function () {
    [$captured, $sent] = capturedSentHeaders(['id' => 'wh-1']);
    $sent->webhooks()->update('wh-1')->name('x')->url('https://example.com')->events(['message'])->idempotencyKey('k-1')->save();

    expect($captured->headers['Idempotency-Key'] ?? null)->toBe(['k-1']);
});

it('webhooks()->rotateSecret() sends Idempotency-Key', function () {
    [$captured, $sent] = capturedSentHeaders(['signing_secret' => 'whsec_x']);
    $sent->webhooks()->rotateSecret('wh-1', sandbox: true, idempotencyKey: 'k-1');

    expect($captured->headers['Idempotency-Key'] ?? null)->toBe(['k-1']);
});

it('webhooks()->test() sends Idempotency-Key', function () {
    [$captured, $sent] = capturedSentHeaders();
    $sent->webhooks()->test('wh-1', 'message.sent', 'k-1');

    expect($captured->headers['Idempotency-Key'] ?? null)->toBe(['k-1']);
});

it('webhooks()->enable() sends Idempotency-Key', function () {
    [$captured, $sent] = capturedSentHeaders(['id' => 'wh-1', 'is_active' => true]);
    $sent->webhooks()->enable('wh-1', 'k-1');

    expect($captured->headers['Idempotency-Key'] ?? null)->toBe(['k-1']);
});

it('webhooks()->disable() sends Idempotency-Key', function () {
    [$captured, $sent] = capturedSentHeaders(['id' => 'wh-1', 'is_active' => false]);
    $sent->webhooks()->disable('wh-1', 'k-1');

    expect($captured->headers['Idempotency-Key'] ?? null)->toBe(['k-1']);
});

it('channels()->addSmsMarket() sends Idempotency-Key through raw()', function () {
    [$captured, $sent] = capturedSentHeaders(['country' => 'US']);
    $sent->channels()->addSmsMarket(['country' => 'US', 'number_type' => 'TEN_DLC'], 'k-1');

    expect($captured->headers['Idempotency-Key'] ?? null)->toBe(['k-1']);
});

it('channels()->updateSmsMarket() sends Idempotency-Key through raw()', function () {
    [$captured, $sent] = capturedSentHeaders(['country' => 'US']);
    $sent->channels()->updateSmsMarket('US', 'TEN_DLC', ['compliance' => []], 'k-1');

    expect($captured->headers['Idempotency-Key'] ?? null)->toBe(['k-1']);
});

it('channels()->addWhatsapp() sends Idempotency-Key through raw()', function () {
    [$captured, $sent] = capturedSentHeaders(['waba_id' => 'x']);
    $sent->channels()->addWhatsapp(['waba_id' => 'x'], 'k-1');

    expect($captured->headers['Idempotency-Key'] ?? null)->toBe(['k-1']);
});

it('channels()->addRcs() sends Idempotency-Key through raw()', function () {
    [$captured, $sent] = capturedSentHeaders(['id' => 'rcs-1']);
    $sent->channels()->addRcs([
        'brand_name' => 'x', 'privacy_policy_url' => 'https://x', 'terms_and_conditions_url' => 'https://x',
    ], 'k-1');

    expect($captured->headers['Idempotency-Key'] ?? null)->toBe(['k-1']);
});

it('no Idempotency-Key header is sent when idempotencyKey() was never called', function () {
    [$captured, $sent] = capturedSentHeaders(['id' => 'c-1']);
    $sent->contacts()->create()->phone('+61412345678')->save();

    expect($captured->headers)->not->toHaveKey('Idempotency-Key');
});

it('channels()->addSmsMarket() sends no Idempotency-Key header without a key', function () {
    [$captured, $sent] = capturedSentHeaders(['country' => 'US']);
    $sent->channels()->addSmsMarket(['country' => 'US', 'number_type' => 'TEN_DLC']);

    expect($captured->headers)->not->toHaveKey('Idempotency-Key');
});
