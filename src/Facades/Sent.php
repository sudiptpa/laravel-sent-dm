<?php

declare(strict_types=1);

namespace Sujip\SentDm\Facades;

use Illuminate\Support\Facades\Facade;
use SentDm\Contacts\APIResponseOfContact;
use SentDm\Contacts\ContactListResponse;
use SentDm\Me\MeGetResponse;
use SentDm\Numbers\NumberLookupResponse;
use SentDm\Profiles\APIResponseOfProfileDetail;
use SentDm\Profiles\ProfileListResponse;
use SentDm\Templates\APIResponseTemplate;
use SentDm\Templates\Template;
use SentDm\Templates\TemplateListResponse;
use SentDm\Users\APIResponseOfUser;
use SentDm\Users\UserListResponse;
use SentDm\Webhooks\APIResponseWebhook;
use SentDm\Webhooks\WebhookListResponse;
use Sujip\SentDm\Messages\SentMessage;
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
 * // Contacts
 * @method static APIResponseOfContact createContact(string $phoneNumber)
 * @method static ContactListResponse listContacts(int $page = 1, int $pageSize = 50, ?string $search = null, ?string $channel = null)
 * @method static APIResponseOfContact getContact(string $id)
 * @method static APIResponseOfContact updateContact(string $id, ?string $defaultChannel = null, ?bool $optOut = null)
 * @method static void deleteContact(string $id)
 *
 * // Templates
 * @method static TemplateListResponse listTemplates(int $page = 1, int $pageSize = 50)
 * @method static APIResponseTemplate getTemplate(string $id)
 * @method static Template|null getTemplateByName(string $name)
 * @method static void deleteTemplate(string $id)
 *
 * // Profiles
 * @method static ProfileListResponse listProfiles()
 * @method static APIResponseOfProfileDetail getProfile(string $profileId)
 * @method static void deleteProfile(string $profileId)
 *
 * // Users
 * @method static UserListResponse listUsers()
 * @method static APIResponseOfUser getUser(string $userId)
 * @method static APIResponseOfUser inviteUser(string $email, string $name, string $role)
 * @method static void removeUser(string $userId)
 *
 * // Webhooks
 * @method static WebhookListResponse listWebhooks(int $page = 1, int $pageSize = 50)
 * @method static APIResponseWebhook getWebhook(string $id)
 * @method static APIResponseWebhook createWebhook(string $endpointUrl, array<int, string> $eventTypes)
 * @method static void deleteWebhook(string $id)
 * @method static APIResponseWebhook toggleWebhook(string $id, bool $active)
 * @method static mixed rotateWebhookSecret(string $id)
 *
 * // Number lookup + validation
 * @method static NumberLookupResponse lookup(string $phoneNumber)
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
