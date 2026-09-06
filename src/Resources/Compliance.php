<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use Sujip\SentDm\Responses\ComplianceRequirementsData;

/**
 * `/v3/compliance/requirements`. Not in any published `sentdm/sent-dm-php` version yet, so
 * this calls the SDK's own generic `Client::request()` (via `Resource::raw()`) instead of
 * a typed convenience method (see `CONTRIBUTING.md`). Field shapes come from Sent.dm's
 * published OpenAPI spec (api.sent.dm/swagger/v3/swagger.json).
 *
 * Call this before building a `compliance` object anywhere else in this package
 * (`SenderProfileBuilder::compliance()`, `Channels::addSmsMarket()`). It's the same
 * declaration that validates those calls, so it can't drift from what the API accepts.
 * `whatsapp` and `rcs` return a 501 until their requirements are declared upstream,
 * because an empty requirement set would wrongly say those channels demand nothing.
 *
 * `country` and `type` are required, not optional, despite how the OpenAPI spec's
 * parameter list reads. Omitting either for the one currently-published channel (`sms`)
 * returns a 400 (`VALIDATION_004: country is required`, then `VALIDATION_001: type must
 * be one of: LOCAL, TOLL_FREE, SHORT_CODE, ALPHANUMERIC, TEN_DLC, MOBILE, 10DLC`).
 */
class Compliance extends Resource
{
    public function requirements(string $country, string $type, string $channel = 'sms'): ComplianceRequirementsData
    {
        return ComplianceRequirementsData::fromArray($this->raw('get', 'v3/compliance/requirements', query: [
            'channel' => $channel,
            'country' => $country,
            'type' => $type,
        ]));
    }
}
