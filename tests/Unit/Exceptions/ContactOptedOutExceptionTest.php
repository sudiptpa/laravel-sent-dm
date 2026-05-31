<?php

declare(strict_types=1);

use Sujip\SentDm\Exceptions\ContactOptedOutException;

it('stores the phone number and generates a message', function () {
    $e = new ContactOptedOutException('+61412345678');

    expect($e->phoneNumber)->toBe('+61412345678')
        ->and($e->getMessage())->toContain('+61412345678');
});
