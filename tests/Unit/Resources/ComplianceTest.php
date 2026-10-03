<?php

declare(strict_types=1);

it('compliance()->requirements() defaults to the sms channel', function () {
    $result = sentApi([
        'channel' => 'sms',
        'country' => 'US',
        'type' => 'TEN_DLC',
        'required' => true,
        'sender_id_pattern' => null,
        'requirements' => [['field' => 'brand.legal_name', 'required' => true]],
        'setup' => [['step' => 'register_brand']],
    ])->compliance()->requirements('US', 'TEN_DLC');

    expect($result->channel)->toBe('sms')
        ->and($result->country)->toBe('US')
        ->and($result->type)->toBe('TEN_DLC')
        ->and($result->required)->toBeTrue()
        ->and($result->senderIdPattern)->toBeNull()
        ->and($result->requirements)->toBe([['field' => 'brand.legal_name', 'required' => true]])
        ->and($result->setup)->toBe([['step' => 'register_brand']]);
});

it('compliance()->requirements() accepts an explicit channel override', function () {
    $result = sentApi([
        'channel' => 'sms', 'country' => 'US', 'type' => 'TEN_DLC', 'required' => true,
        'sender_id_pattern' => null, 'requirements' => [], 'setup' => [],
    ])->compliance()->requirements('US', 'TEN_DLC', 'sms');

    expect($result->channel)->toBe('sms')
        ->and($result->country)->toBe('US')
        ->and($result->type)->toBe('TEN_DLC');
});
