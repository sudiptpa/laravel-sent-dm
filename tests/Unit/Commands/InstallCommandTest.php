<?php

declare(strict_types=1);

it('publishes config and shows next steps', function () {
    $this->artisan('sent:install')
        ->expectsOutputToContain('config/sent.php')
        ->expectsOutputToContain('SENT_API_KEY')
        ->expectsOutputToContain('sent:health')
        ->assertExitCode(0);
});
