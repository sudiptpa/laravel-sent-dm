<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use DateTimeInterface;
use InvalidArgumentException;
use SentDm\Calls\APIResponseOfCall;
use SentDm\Calls\APIResponseOfCallRecordings;
use SentDm\Calls\Call;
use SentDm\CallsPage;
use Sujip\SentDm\Concerns\Paginatable;
use Sujip\SentDm\Support\Sandbox;

class Calls extends Resource
{
    use Paginatable;

    private ?string $direction = null;

    private ?DateTimeInterface $from = null;

    private ?DateTimeInterface $to = null;

    private ?string $number = null;

    private ?string $status = null;

    public function direction(string $direction): static
    {
        $clone = clone $this;
        $clone->direction = $direction;

        return $clone;
    }

    public function from(DateTimeInterface $from): static
    {
        $clone = clone $this;
        $clone->from = $from;

        return $clone;
    }

    public function to(DateTimeInterface $to): static
    {
        $clone = clone $this;
        $clone->to = $to;

        return $clone;
    }

    public function number(string $number): static
    {
        $clone = clone $this;
        $clone->number = $number;

        return $clone;
    }

    public function status(string $status): static
    {
        $clone = clone $this;
        $clone->status = $status;

        return $clone;
    }

    /** @return CallsPage<Call> */
    public function get(): CallsPage
    {
        return $this->client->calls->list(
            direction: $this->direction,
            from: $this->from,
            number: $this->number,
            page: $this->page,
            pageSize: $this->pageSize,
            status: $this->status,
            to: $this->to,
            xProfileID: $this->orgProfileId,
        );
    }

    public function retrieve(string $id): APIResponseOfCall
    {
        return $this->client->calls->retrieve(id: $id, xProfileID: $this->orgProfileId);
    }

    public function hangup(string $id, ?bool $sandbox = null, ?string $idempotencyKey = null): mixed
    {
        return $this->client->calls->hangup(
            id: $id,
            sandbox: Sandbox::resolve($sandbox, $this->sandbox),
            idempotencyKey: $idempotencyKey,
            xProfileID: $this->orgProfileId,
        );
    }

    public function listRecordings(string $id): APIResponseOfCallRecordings
    {
        return $this->client->calls->listRecordings(id: $id, xProfileID: $this->orgProfileId);
    }

    public function record(string $id, string $action, ?bool $sandbox = null, ?string $idempotencyKey = null): mixed
    {
        if (! in_array($action, ['start', 'stop'], true)) {
            throw new InvalidArgumentException('record() action must be start or stop.');
        }

        return $this->client->calls->record(
            id: $id,
            action: $action,
            sandbox: Sandbox::resolve($sandbox, $this->sandbox),
            idempotencyKey: $idempotencyKey,
            xProfileID: $this->orgProfileId,
        );
    }

    public function participants(string $callId): CallParticipants
    {
        $participants = new CallParticipants($this->client, $callId, $this->cache, $this->cacheEnabled, $this->cacheTtl, $this->sandbox, $this->connectionName);

        return $this->orgProfileId !== null ? $participants->profile($this->orgProfileId) : $participants;
    }
}
