<?php

declare(strict_types=1);

namespace Sujip\SentDm\Facades;

use Illuminate\Support\Facades\Facade;
use SentDm\Me\MeGetResponse;
use SentDm\Numbers\NumberLookupResponse;
use Sujip\SentDm\Messages\SentMessage;
use Sujip\SentDm\Resources\Contacts;
use Sujip\SentDm\Resources\Profiles;
use Sujip\SentDm\Resources\Templates;
use Sujip\SentDm\Resources\Users;
use Sujip\SentDm\Resources\Webhooks;
use Sujip\SentDm\Sent as SentDriver;
use Sujip\SentDm\SentBulkDispatcher;
use Sujip\SentDm\SentFake;
use Sujip\SentDm\SentManager;

/**
 * @method static SentDriver connection(?string $name = null)
 *
 * // Messaging
 * @method static SentMessage to(string $recipient)
 * @method static SentBulkDispatcher bulk(array<int, string> $recipients)
 * @method static mixed send(SentMessage $message)
 * @method static void dispatch(SentMessage $message)
 *
 * // Account
 * @method static MeGetResponse account()
 *
 * // Number lookup
 * @method static NumberLookupResponse lookup(string $phoneNumber)
 *
 * // Resources
 * @method static Contacts contacts()
 * @method static Templates templates()
 * @method static Webhooks webhooks()
 * @method static Profiles profiles()
 * @method static Users users()
 *
 * // Testing
 * @method static SentFake fake()
 *
 * @see SentManager
 */
class Sent extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SentManager::class;
    }

    public static function fake(): SentFake
    {
        $fake = new SentFake;

        static::swap($fake);

        return $fake;
    }
}
