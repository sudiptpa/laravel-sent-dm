<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use SentDm\Compliance\APIResponseOfComplianceRequirements;

/**
 * `/v3/compliance/requirements`.
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
    public function requirements(string $country, string $type, string $channel = 'sms'): APIResponseOfComplianceRequirements
    {
        return $this->client->compliance->retrieveRequirements(
            channel: $channel,
            country: $country,
            type: $type,
            xProfileID: $this->orgProfileId,
        );
    }
}
