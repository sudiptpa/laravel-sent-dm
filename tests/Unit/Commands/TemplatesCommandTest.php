<?php

declare(strict_types=1);

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use SentDm\Core\Exceptions\AuthenticationException;
use SentDm\Templates\Template;
use SentDm\Templates\TemplateListResponse;
use SentDm\Templates\TemplateListResponse\Data;
use Sujip\SentDm\Sent;
use Sujip\SentDm\SentManager;

function fakeTemplateListResponse(array $names = []): TemplateListResponse
{
    $templates = [];
    foreach ($names as $name) {
        $t = new Template;
        $t['id'] = 'tpl-'.substr(md5($name), 0, 8);
        $t['name'] = $name;
        $t['category'] = 'UTILITY';
        $t['status'] = 'APPROVED';
        $t['channels'] = ['sms'];
        $templates[] = $t;
    }

    $data = new Data;
    $data['templates'] = $templates;

    $response = new TemplateListResponse;
    $response['data'] = $data;

    return $response;
}

it('lists templates in a table', function () {
    $driver = Mockery::mock(Sent::class);
    $driver->shouldReceive('listTemplates')->once()->andReturn(fakeTemplateListResponse(['otp_verify', 'welcome']));

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:templates')
        ->expectsOutputToContain('otp_verify')
        ->expectsOutputToContain('welcome')
        ->assertExitCode(0);
});

it('shows info when no templates exist', function () {
    $response = new TemplateListResponse;
    $data = new Data;
    $data['templates'] = [];
    $response['data'] = $data;

    $driver = Mockery::mock(Sent::class);
    $driver->shouldReceive('listTemplates')->once()->andReturn($response);

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:templates')
        ->expectsOutputToContain('No templates')
        ->assertExitCode(0);
});

it('shows failure on API exception', function () {
    $driver = Mockery::mock(Sent::class);
    $request = Mockery::mock(RequestInterface::class);
    $response = Mockery::mock(ResponseInterface::class);
    $response->shouldReceive('getStatusCode')->andReturn(401);
    $stream = Mockery::mock(StreamInterface::class);
    $stream->shouldReceive('__toString')->andReturn('{}');
    $stream->shouldReceive('getContents')->andReturn('{}');
    $response->shouldReceive('getBody')->andReturn($stream);
    $driver->shouldReceive('listTemplates')
        ->andThrow(new AuthenticationException($request, $response));

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:templates')->assertExitCode(1);
});
