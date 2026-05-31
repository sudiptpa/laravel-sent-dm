<?php

declare(strict_types=1);

namespace Sujip\SentDm\Builders;

use Closure;
use InvalidArgumentException;
use SentDm\Client;
use SentDm\Contacts\APIResponseOfContact;

class ContactBuilder
{
    private ?string $phone = null;

    private ?string $defaultChannel = null;

    private ?bool $optOut = null;

    public function __construct(
        private readonly Client $client,
        private readonly ?string $id = null,
        private readonly ?Closure $onSaved = null,
    ) {}

    public function phone(string $phone): static
    {
        $clone = clone $this;
        $clone->phone = $phone;

        return $clone;
    }

    public function defaultChannel(string $channel): static
    {
        $clone = clone $this;
        $clone->defaultChannel = $channel;

        return $clone;
    }

    public function optOut(bool $optOut = true): static
    {
        $clone = clone $this;
        $clone->optOut = $optOut;

        return $clone;
    }

    public function save(): APIResponseOfContact
    {
        if ($this->id !== null) {
            $result = $this->client->contacts->update(
                id: $this->id,
                defaultChannel: $this->defaultChannel,
                optOut: $this->optOut,
            );

            if ($this->onSaved !== null) {
                ($this->onSaved)();
            }

            return $result;
        }

        if ($this->defaultChannel !== null || $this->optOut !== null) {
            throw new InvalidArgumentException(
                'defaultChannel and optOut are not supported when creating a contact — the Sent.dm API only accepts these on update. Create the contact first, then use contacts()->update() to set these fields.'
            );
        }

        if ($this->phone === null) {
            throw new InvalidArgumentException('A phone number is required to create a contact. Call phone() before save().');
        }

        return $this->client->contacts->create(phoneNumber: $this->phone);
    }
}
