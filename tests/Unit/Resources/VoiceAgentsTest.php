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
