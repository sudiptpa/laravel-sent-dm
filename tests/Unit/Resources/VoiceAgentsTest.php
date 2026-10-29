<?php

declare(strict_types=1);

use SentDm\Voice\Agents\APIResponseOfVoiceAgent;
use SentDm\Voice\Agents\APIResponseOfVoiceAgentSecret;
use SentDm\Voice\Catalogue\APIResponseOfVoiceCatalogue;
use SentDm\VoiceAgentsPage;
use Sujip\SentDm\Builders\VoiceAgentBuilder;
use Sujip\SentDm\Resources\VoiceAgents;
use Sujip\SentDm\Resources\VoiceCatalogue;

it('voiceAgents() lists and retrieves voice agents', function () {
    $agents = sentApi(['agents' => [['id' => 'agent_1', 'name' => 'Reception', 'session_type' => 'text']], 'pagination' => ['has_more' => false]])->voiceAgents()->get();
    $agent = sentApi(['id' => 'agent_1', 'name' => 'Reception'])->voiceAgents()->find('agent_1');

    expect($agents)->toBeInstanceOf(VoiceAgentsPage::class)
        ->and($agents->getItems()[0]->id)->toBe('agent_1')
        ->and($agent)->toBeInstanceOf(APIResponseOfVoiceAgent::class)
        ->and($agent->data->name)->toBe('Reception');
});

it('voiceAgents() returns immutable builders for create and update', function () {
    $base = sentApi()->voiceAgents()->create();
    $configured = $base->name('Reception')->sessionType('text')->speech(['voice' => 'alloy', 'language' => 'en-US']);

    expect($base)->toBeInstanceOf(VoiceAgentBuilder::class)
        ->and($configured)->not->toBe($base)
        ->and(sentApi()->voiceAgents()->update('agent_1'))->toBeInstanceOf(VoiceAgentBuilder::class);
});

it('voice agent builders pass the complete agent configuration', function () {
    [$captured, $sent] = capturedSentHeaders(['id' => 'agent_1', 'name' => 'Reception']);

    $sent->voiceAgents()->create()
        ->name('Reception')
        ->sessionType('text')
        ->websocketUrl('wss://example.com/agent')
        ->greeting('Hello, how can I help?')
        ->speech(['voice' => 'alloy', 'language' => 'en-US'])
        ->audio(['sampleRate' => 8000])
        ->tools(['transfer'])
        ->transferTargets([['kind' => 'number', 'value' => '+12125550100']])
        ->fallback(['kind' => 'number', 'value' => '+12125550199'])
        ->limits(['max_call_seconds' => 300])
        ->transcripts(['store' => true, 'retentionDays' => 30])
        ->save();

    expect($captured->uri)->toContain('/v3/voice/agents')
        ->and($captured->body)->toContain('"session_type":"text"')
        ->and($captured->body)->toContain('"websocket_url":"wss://example.com/agent"')
        ->and($captured->body)->toContain('"greeting":"Hello, how can I help?"')
        ->and($captured->body)->toContain('"audio"')
        ->and($captured->body)->toContain('"fallback"')
        ->and($captured->body)->toContain('"transcripts"')
        ->and($captured->body)->toContain('"transfer_targets"');
});

it('voiceAgents()->update()->clear() sends an explicit null for the cleared field', function () {
    [$captured, $sent] = capturedSentHeaders(['id' => 'agent_1', 'greeting' => null]);

    $result = $sent->voiceAgents()->update('agent_1')
        ->name('Renamed agent')
        ->greeting('Updated greeting')
        ->sessionType('text')
        ->transferTargets([['kind' => 'number', 'value' => '+12125550100']])
        ->sandbox(true)
        ->clear('fallback')
        ->idempotencyKey('verify-clear')
        ->save();

    $body = json_decode((string) $captured->body, true);

    expect($body)->toHaveKey('fallback')
        ->and($body['fallback'])->toBeNull()
        ->and($body['name'])->toBe('Renamed agent')
        ->and($body['greeting'])->toBe('Updated greeting')
        ->and($body['session_type'])->toBe('text')
        ->and($body['transfer_targets'])->toBe([['kind' => 'number', 'value' => '+12125550100']])
        ->and($body['sandbox'])->toBeTrue()
        ->and($captured->uri)->toContain('/v3/voice/agents/agent_1')
        ->and($captured->headers['Idempotency-Key'] ?? null)->toBe(['verify-clear'])
        ->and($result)->toBeInstanceOf(APIResponseOfVoiceAgent::class);
});

it('voiceAgents()->update()->clear() converts nested limits/audio/transcripts keys to their wire names', function () {
    [$captured, $sent] = capturedSentHeaders(['id' => 'agent_1']);

    $sent->voiceAgents()->update('agent_1')
        ->limits(['maxCallSeconds' => 600])
        ->audio(['sampleRate' => 16000])
        ->transcripts(['retentionDays' => 14, 'store' => true])
        ->clear('greeting')
        ->save();

    $body = json_decode((string) $captured->body, true);

    expect($body['limits'])->toBe(['max_call_seconds' => 600])
        ->and($body['audio'])->toBe(['sample_rate' => 16000])
        ->and($body['transcripts'])->toBe(['retention_days' => 14, 'store' => true])
        ->and($body['greeting'])->toBeNull();
});

it('voiceAgents()->update()->clear() strips a nested null in an untouched sibling field instead of sending it', function () {
    [$captured, $sent] = capturedSentHeaders(['id' => 'agent_1']);

    $sent->voiceAgents()->update('agent_1')
        ->limits(['maxCallSeconds' => 600, 'maxConcurrentCalls' => null])
        ->clear('greeting')
        ->save();

    $body = json_decode((string) $captured->body, true);

    expect($body['limits'])->toBe(['max_call_seconds' => 600])
        ->and($body['limits'])->not->toHaveKey('max_concurrent_calls')
        ->and($body['greeting'])->toBeNull();
});

it('voiceAgents()->update()->clear() can clear transferTargets', function () {
    [$captured, $sent] = capturedSentHeaders(['id' => 'agent_1']);

    $sent->voiceAgents()->update('agent_1')
        ->clear('transferTargets')
        ->save();

    $body = json_decode((string) $captured->body, true);

    expect($body)->toHaveKey('transfer_targets')
        ->and($body['transfer_targets'])->toBeNull();
});

it('voiceAgents()->update()->clear() respects call order against a later setter', function () {
    [$capturedClearThenSet, $sentClearThenSet] = capturedSentHeaders(['id' => 'agent_1']);
    $sentClearThenSet->voiceAgents()->update('agent_1')
        ->clear('greeting')
        ->greeting('Set after clear')
        ->save();
    $bodyClearThenSet = json_decode((string) $capturedClearThenSet->body, true);

    [$capturedSetThenClear, $sentSetThenClear] = capturedSentHeaders(['id' => 'agent_1']);
    $sentSetThenClear->voiceAgents()->update('agent_1')
        ->greeting('Set before clear')
        ->clear('greeting')
        ->save();
    $bodySetThenClear = json_decode((string) $capturedSetThenClear->body, true);

    expect($bodyClearThenSet['greeting'])->toBe('Set after clear')
        ->and($bodySetThenClear['greeting'])->toBeNull();
});

it('voiceAgents()->update()->clear() can be profile scoped', function () {
    [$captured, $sent] = capturedSentHeaders(['id' => 'agent_1']);

    $sent->voiceAgents()->profile('profile_1')->update('agent_1')
        ->clear('greeting')
        ->save();

    expect($captured->headers['x-profile-id'][0] ?? null)->toBe('profile_1');
});

it('VoiceAgentBuilder::clear() rejects a field the API does not support clearing', function () {
    $builder = sentApi()->voiceAgents()->update('agent_1');

    expect(fn () => $builder->clear('name'))->toThrow(InvalidArgumentException::class);
});

it('voiceAgents()->create()->save() ignores clear() since creation has no fields to reset', function () {
    [$captured, $sent] = capturedSentHeaders(['id' => 'agent_1']);

    $sent->voiceAgents()->create()
        ->name('Reception')
        ->sessionType('text')
        ->speech(['voice' => 'alloy', 'language' => 'en-US'])
        ->clear('greeting')
        ->save();

    $body = json_decode((string) $captured->body, true);

    expect($captured->uri)->toContain('/v3/voice/agents')
        ->and($captured->uri)->not->toContain('agent_1')
        ->and($body)->not->toHaveKey('greeting');
});

it('voiceAgents() deletes and rotates an agent secret', function () {
    expect(sentApi()->voiceAgents()->delete('agent_1'))->toBeNull()
        ->and(sentApi(['signing_secret' => 'whsec_new'])->voiceAgents()->rotateSecret('agent_1'))->toBeInstanceOf(APIResponseOfVoiceAgentSecret::class);
});

it('voiceCatalogue() returns the available voices', function () {
    $catalogue = sentApi(['voices' => [['name' => 'alloy', 'language' => 'en-US']], 'languages' => ['en-US']])->voiceCatalogue()->get();

    expect($catalogue)->toBeInstanceOf(APIResponseOfVoiceCatalogue::class)
        ->and($catalogue->data->voices[0]->name)->toBe('alloy')
        ->and($catalogue->data->languages)->toBe(['en-US']);
});

it('voice agent resources can be profile scoped', function () {
    [$captured, $sent] = capturedSentHeaders(['agents' => [], 'pagination' => ['has_more' => false]]);
    $sent->voiceAgents()->profile('profile_1')->get();

    expect($captured->headers['x-profile-id'][0] ?? null)->toBe('profile_1');
});

it('voice agent factories return resources', function () {
    expect(sentApi()->voiceAgents())->toBeInstanceOf(VoiceAgents::class)
        ->and(sentApi()->voiceCatalogue())->toBeInstanceOf(VoiceCatalogue::class);
});
