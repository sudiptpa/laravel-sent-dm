<?php

declare(strict_types=1);

use Sujip\SentDm\Builders\TenDlcCampaignBuilder;

$market = [
    'country' => 'US', 'number_type' => 'TEN_DLC', 'status' => 'ACTIVE',
    'numbers' => [['sender_value' => '+14155550123', 'area_code' => '415', 'campaign_id' => 'camp-1', 'status' => 'ACTIVE']],
];

it('channels()->tenDlcCompliance() reads the market compliance', function () {
    [$captured, $sent] = capturedSentHeaders(['campaigns' => [['id' => 'camp-1', 'inherit' => false]]]);

    $result = $sent->channels()->tenDlcCompliance();

    expect($captured->method)->toBe('GET')
        ->and($captured->uri)->toEndWith('/v3/channels/sms/compliances/US/TEN_DLC')
        ->and($result->data->campaigns)->toBe([['id' => 'camp-1', 'inherit' => false]]);
});

it('channels()->addTenDlcCampaign() posts the campaign fields with a key', function () use ($market) {
    [$captured, $sent] = capturedSentHeaders($market);

    $result = $sent->channels()->addTenDlcCampaign([
        'description' => 'Order updates',
        'help_keywords' => 'HELP',
        'use_cases' => [['useCase' => 'ACCOUNT_NOTIFICATION', 'sampleMessages' => ['Your order shipped']]],
        'volume' => '1000',
        'sandbox' => true,
    ]);

    $body = json_decode((string) $captured->body, true);

    expect($captured->method)->toBe('POST')
        ->and($captured->uri)->toEndWith('/v3/channels/sms/compliances/US/TEN_DLC/campaigns')
        ->and($captured->headers['Idempotency-Key'][0] ?? '')->not->toBe('')
        ->and($body['description'])->toBe('Order updates')
        ->and($body['help_keywords'])->toBe('HELP')
        ->and($body['use_cases'][0]['use_case'])->toBe('ACCOUNT_NOTIFICATION')
        ->and($body['use_cases'][0]['sample_messages'])->toBe(['Your order shipped'])
        ->and($body['sandbox'])->toBeTrue()
        ->and($result->data->numbers[0]->campaignID)->toBe('camp-1');
});

it('channels()->addTenDlcCampaign() uses the key it is given', function () use ($market) {
    [$captured, $sent] = capturedSentHeaders($market);

    $sent->channels()->addTenDlcCampaign(['description' => 'Order updates'], 'key-1');

    expect($captured->headers['Idempotency-Key'][0] ?? null)->toBe('key-1');
});

it('channels()->addTenDlcCampaign() refuses an unknown key', function () {
    sentApi()->channels()->addTenDlcCampaign(['name' => 'x']);
})->throws(InvalidArgumentException::class, 'Unknown key(s) for addTenDlcCampaign(): name');

it('channels()->updateTenDlcCampaign() patches one campaign', function () use ($market) {
    [$captured, $sent] = capturedSentHeaders($market);

    $sent->channels()->updateTenDlcCampaign('camp-1', ['message_flow' => 'Checkbox at checkout, unchecked by default']);

    $body = json_decode((string) $captured->body, true);

    expect($captured->method)->toBe('PATCH')
        ->and($captured->uri)->toEndWith('/v3/channels/sms/compliances/US/TEN_DLC/campaigns/camp-1')
        ->and($body)->toBe(['message_flow' => 'Checkbox at checkout, unchecked by default']);
});

it('channels()->updateTenDlcCampaign() refuses an empty campaign id', function () {
    sentApi()->channels()->updateTenDlcCampaign(' ', ['volume' => '10']);
})->throws(InvalidArgumentException::class, 'The campaign id cannot be empty.');

it('channels()->addTenDlcNumbers() adds numbers to a campaign', function () use ($market) {
    [$captured, $sent] = capturedSentHeaders($market);

    $sent->channels()->addTenDlcNumbers('camp-1', [['areaCode' => '415', 'quantity' => 2]], sandbox: true);

    $body = json_decode((string) $captured->body, true);

    expect($captured->method)->toBe('POST')
        ->and($captured->uri)->toEndWith('/v3/channels/sms/numbers/US/TEN_DLC')
        ->and($captured->headers['Idempotency-Key'][0] ?? '')->not->toBe('')
        ->and($body['campaign_id'])->toBe('camp-1')
        ->and($body['numbers'])->toBe([['area_code' => '415', 'quantity' => 2]])
        ->and($body['sandbox'])->toBeTrue();
});

it('channels()->addTenDlcNumbers() needs a campaign id and at least one number', function () {
    expect(fn () => sentApi()->channels()->addTenDlcNumbers('', [['quantity' => 1]]))
        ->toThrow(InvalidArgumentException::class, 'The campaign id cannot be empty.')
        ->and(fn () => sentApi()->channels()->addTenDlcNumbers('camp-1', []))
        ->toThrow(InvalidArgumentException::class, 'Name at least one number to add.');
});

it('channels()->tenDlcCampaign() builder sends the fields it was given', function () use ($market) {
    [$captured, $sent] = capturedSentHeaders($market);

    $builder = $sent->channels()->tenDlcCampaign();
    $withDescription = $builder->description('Order updates');

    expect($builder)->toBeInstanceOf(TenDlcCampaignBuilder::class)
        ->and($withDescription)->not->toBe($builder);

    $withDescription->volume('500')->idempotencyKey('key-2')->sandbox(false)->save();

    $body = json_decode((string) $captured->body, true);

    expect($captured->method)->toBe('POST')
        ->and($captured->headers['Idempotency-Key'][0] ?? null)->toBe('key-2')
        ->and($body['description'])->toBe('Order updates')
        ->and($body['volume'])->toBe('500')
        ->and($body)->not->toHaveKey('sandbox');
});

it('channels()->updateTenDlcCampaignBuilder() updates the named campaign', function () use ($market) {
    [$captured, $sent] = capturedSentHeaders($market);

    $sent->channels()->updateTenDlcCampaignBuilder('camp-1')->optOutMessage('Reply STOP to end')->save();

    $body = json_decode((string) $captured->body, true);

    expect($captured->method)->toBe('PATCH')
        ->and($captured->uri)->toEndWith('/campaigns/camp-1')
        ->and($body)->toBe(['opt_out_message' => 'Reply STOP to end']);
});

it('the campaign builder sets every text field and the use cases', function () use ($market) {
    [$captured, $sent] = capturedSentHeaders($market);

    $sent->channels()->tenDlcCampaign()
        ->description('d')->helpKeywords('HELP')->helpMessage('hm')->messageFlow('mf')
        ->optInKeywords('START')->optInMessage('om')->optOutKeywords('STOP')->optOutMessage('xm')
        ->privacyPolicyLink('https://example.com/p')->termsAndConditionsLink('https://example.com/t')
        ->volume('10')->useCases([['useCase' => 'MARKETING', 'sampleMessages' => ['Sale']]])
        ->save();

    $body = json_decode((string) $captured->body, true);

    expect($body)->toHaveKeys([
        'description', 'help_keywords', 'help_message', 'message_flow', 'opt_in_keywords', 'opt_in_message',
        'opt_out_keywords', 'opt_out_message', 'privacy_policy_link', 'terms_and_conditions_link', 'volume', 'use_cases',
    ]);
});

it('the campaign calls refuse an empty body', function () {
    expect(fn () => sentApi()->channels()->addTenDlcCampaign(['sandbox' => true]))
        ->toThrow(InvalidArgumentException::class, 'Set at least one campaign field for addTenDlcCampaign().')
        ->and(fn () => sentApi()->channels()->updateTenDlcCampaignBuilder('camp-1')->save())
        ->toThrow(InvalidArgumentException::class, 'Set at least one campaign field for updateTenDlcCampaign().')
        ->and(fn () => sentApi()->channels()->tenDlcCampaign()->save())
        ->toThrow(InvalidArgumentException::class, 'Set at least one campaign field for addTenDlcCampaign().');
});

it('the update builder refuses an empty campaign id', function () {
    sentApi()->channels()->updateTenDlcCampaignBuilder(' ');
})->throws(InvalidArgumentException::class, 'The campaign id cannot be empty.');

it('channels()->addTenDlcNumbers() refuses a quantity below 1', function () {
    sentApi()->channels()->addTenDlcNumbers('camp-1', [['areaCode' => '415', 'quantity' => 0]]);
})->throws(InvalidArgumentException::class, 'quantity must be at least 1.');
