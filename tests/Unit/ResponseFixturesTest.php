<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SentDm\Client;
use SentDm\Core\Contracts\BaseModel;
use SentDm\RequestOptions;
use Sujip\SentDm\Sent;

/**
 * Responses recorded from the live API, with every value replaced, parse into the SDK models
 * the package returns, and the models declare every field the API sent.
 */
function sentReplaying(string $fixture): Sent
{
    $body = (string) file_get_contents(__DIR__.'/../Fixtures/responses/'.$fixture.'.json');

    $transporter = new class($body) implements ClientInterface
    {
        public function __construct(private string $body) {}

        public function sendRequest(RequestInterface $request): ResponseInterface
        {
            return new Response(200, ['Content-Type' => 'application/json'], $this->body);
        }
    };

    $options = new RequestOptions;
    $options['transporter'] = $transporter;
    $options['maxRetries'] = 0;

    return new Sent(new Client(apiKey: 'test', requestOptions: $options));
}

/** @return array<string, string> wire name => property name */
function modelWireNames(object $model): array
{
    $names = [];

    foreach ((new ReflectionClass($model))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
        $attributes = $property->getAttributes();
        $apiName = null;

        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            $apiName = $instance->apiName ?? null;
        }

        $names[$apiName ?? $property->getName()] = $property->getName();
    }

    return $names;
}

/**
 * Keys in the recorded response that the SDK model does not declare, at every depth.
 *
 * @return list<string>
 */
function unmodelledKeys(mixed $model, mixed $recorded, string $path = ''): array
{
    if (is_array($recorded) && array_is_list($recorded)) {
        $found = [];
        foreach ($recorded as $i => $item) {
            $child = is_array($model) ? ($model[$i] ?? null) : (is_iterable($model) ? iterator_to_array($model, false)[$i] ?? null : null);
            array_push($found, ...unmodelledKeys($child, $item, $path.'[]'));
        }

        return array_values(array_unique($found));
    }

    if (! is_array($recorded) || ! is_object($model) || ! method_exists($model, 'toArray') && ! $model instanceof BaseModel) {
        return [];
    }

    $names = modelWireNames($model);
    $found = [];

    foreach ($recorded as $key => $value) {
        $where = $path === '' ? (string) $key : $path.'.'.$key;

        if (! isset($names[$key])) {
            $found[] = $where;

            continue;
        }

        $property = new ReflectionProperty($model, $names[$key]);
        $child = $property->isInitialized($model) ? $property->getValue($model) : null;
        array_push($found, ...unmodelledKeys($child, $value, $where));
    }

    return $found;
}

// A paginated list returns the SDK's page model, which declares only `data`, and its pagination
// object does not declare `cursors`. Both are in the API's response and the spec. When the SDK adds
// them, these cases fail so the list can be emptied.
$listGaps = ['success', 'data.pagination.cursors', 'meta'];

dataset('recorded responses', [
    'account' => ['account', fn (Sent $s) => $s->account(), []],
    'contacts' => ['contacts', fn (Sent $s) => $s->contacts()->get(), $listGaps],
    'templates' => ['templates', fn (Sent $s) => $s->templates()->get(), $listGaps],
    'sender profiles' => ['sender_profiles', fn (Sent $s) => $s->senderProfiles()->get(), $listGaps],
    'profiles' => ['profiles', fn (Sent $s) => $s->profiles()->get(), []],
    'channels' => ['channels', fn (Sent $s) => $s->channels()->get(), []],
    'sms markets' => ['sms_markets', fn (Sent $s) => $s->channels()->smsMarkets(), []],
    'sms market' => ['sms_market', fn (Sent $s) => $s->channels()->findSmsMarket('US', 'TEN_DLC'), []],
    '10DLC compliance' => ['tendlc_compliance', fn (Sent $s) => $s->channels()->tenDlcCompliance(), []],
    'compliance requirements' => ['compliance_requirements', fn (Sent $s) => $s->compliance()->requirements('US', 'TEN_DLC'), []],
    'webhooks' => ['webhooks', fn (Sent $s) => $s->webhooks()->get(), $listGaps],
    'webhook event types' => ['webhook_event_types', fn (Sent $s) => $s->webhooks()->listEventTypes(), []],
    'users' => ['users', fn (Sent $s) => $s->users()->get(), []],
    'voice agents' => ['voice_agents', fn (Sent $s) => $s->voiceAgents()->get(), $listGaps],
    'voices' => ['voice_voices', fn (Sent $s) => $s->voiceCatalogue()->get(), []],
    'calls' => ['calls', fn (Sent $s) => $s->calls()->get(), $listGaps],
    'conversations' => ['conversations', fn (Sent $s) => $s->conversations()->get(), $listGaps],
    'voice numbers' => ['voice_numbers', fn (Sent $s) => $s->channels()->voice()->list(), []],
]);

it('parses a recorded response and the SDK model leaves out only the known fields', function (string $fixture, Closure $call, array $knownGaps) {
    $recorded = json_decode((string) file_get_contents(__DIR__.'/../Fixtures/responses/'.$fixture.'.json'), true);

    $result = $call(sentReplaying($fixture));

    expect($result)->toBeObject()
        ->and(unmodelledKeys($result, $recorded))->toBe($knownGaps);
})->with('recorded responses');
