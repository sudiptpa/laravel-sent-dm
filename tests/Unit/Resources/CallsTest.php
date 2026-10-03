<?php

declare(strict_types=1);

use SentDm\Calls\Participants\CallParticipantTarget;
use SentDm\CallsPage;
use Sujip\SentDm\Resources\CallParticipants;

it('calls()->get() lists calls', function () {
    $result = sentApi([
        'calls' => [[
            'id' => 'call_1',
            'direction' => 'outbound',
            'number' => '+12125550100',
            'status' => 'completed',
            'duration_seconds' => 42,
            'recording_available' => true,
            'from' => ['kind' => 'number', 'value' => '+12125550100'],
            'to' => ['kind' => 'number', 'value' => '+12125550101'],
        ]],
        'pagination' => ['has_more' => false],
    ])->calls()->get();

    expect($result)->toBeInstanceOf(CallsPage::class)
        ->and($result->getItems()[0]->id)->toBe('call_1')
        ->and($result->getItems()[0]->durationSeconds)->toBe(42);
});

it('calls() query builder chains are immutable', function () {
    $base = sentApi()->calls();
    $from = new DateTimeImmutable('2026-10-01T00:00:00Z');
    $to = new DateTimeImmutable('2026-10-02T00:00:00Z');

    $chained = $base
        ->direction('outbound')
        ->from($from)
        ->to($to)
        ->number('+12125550100')
        ->status('completed')
        ->page(2)
        ->perPage(25);

    expect($chained)->not->toBe($base);
});

it('calls()->retrieve() retrieves a call', function () {
    $result = sentApi(['id' => 'call_1', 'status' => 'completed'])
        ->calls()
        ->retrieve('call_1');

    expect($result->data->id)->toBe('call_1')
        ->and($result->data->status)->toBe('completed');
});

it('calls()->hangup() ends a call', function () {
    $result = sentApi()->calls()->hangup('call_1');

    expect($result)->toBeNull();
});

it('calls()->listRecordings() lists call recordings', function () {
    $result = sentApi([
        'recordings' => [[
            'recording_id' => 'rec_1',
            'download_url' => 'https://example.com/rec.wav',
            'url_expires_at' => '2026-10-01T01:00:00Z',
        ]],
    ])->calls()->listRecordings('call_1');

    expect($result->data->recordings[0]->recordingID)->toBe('rec_1')
        ->and($result->data->recordings[0]->downloadURL)->toBe('https://example.com/rec.wav');
});

it('calls()->record() starts or stops recording', function () {
    $result = sentApi()->calls()->record('call_1', 'start');

    expect($result)->toBeNull();
});

it('calls()->record() rejects unknown actions', function () {
    sentApi()->calls()->record('call_1', 'pause');
})->throws(InvalidArgumentException::class, 'record() action must be start or stop.');

it('calls()->participants() returns a call-scoped participant resource', function () {
    expect(sentApi()->calls()->participants('call_1'))->toBeInstanceOf(CallParticipants::class);
});

it('calls()->participants()->list() lists call participants', function () {
    $result = sentApiList([[
        'id' => 'call_participant_1',
        'kind' => 'number',
        'value' => '+12125550101',
        'muted' => false,
        'duration_seconds' => 12,
    ]])->calls()->participants('call_1')->list();

    expect($result->data[0]->id)->toBe('call_participant_1')
        ->and($result->data[0]->muted)->toBeFalse();
});

it('calls()->participants()->add() adds a participant', function () {
    $target = CallParticipantTarget::with(kind: 'number', value: '+12125550101');
    $result = sentApi(['id' => 'call_participant_1'])
        ->calls()
        ->participants('call_1')
        ->add($target, callerId: '+12125550100');

    expect($result->data->id)->toBe('call_participant_1');
});

it('calls()->participants()->update() mutes a participant', function () {
    $result = sentApi()->calls()->participants('call_1')->update('call_participant_1', true);

    expect($result)->toBeNull();
});

it('calls()->participants()->remove() removes a participant', function () {
    $result = sentApi()->calls()->participants('call_1')->remove('call_participant_1');

    expect($result)->toBeNull();
});

it('calls()->participants()->removeAll() removes all participants', function () {
    $result = sentApi()->calls()->participants('call_1')->removeAll();

    expect($result)->toBeNull();
});
