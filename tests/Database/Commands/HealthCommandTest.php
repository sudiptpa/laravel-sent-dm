<?php

declare(strict_types=1);

use SentDm\Me\Account as Data;
use SentDm\Me\APIResponseOfAccount;
use Sujip\SentDm\Sent;
use Sujip\SentDm\SentManager;

function fakeDatabaseHealthMeResponse(): APIResponseOfAccount
{
    $data = new Data;
    $data['type'] = 'organization';
    $data['name'] = 'Acme';
    $data['email'] = 'admin@example.com';

    $response = new APIResponseOfAccount;
    $response['data'] = $data;

    return $response;
}

it('shows enabled database feature tables as ready', function () {
    config()->set('sent.logging.enabled', true);
    config()->set('sent.opt_out.enabled', true);

    $driver = Mockery::mock(Sent::class);
    $driver->shouldReceive('account')->once()->andReturn(fakeDatabaseHealthMeResponse());

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:health')
        ->expectsOutputToContain('table ready')
        ->assertExitCode(0);
});
