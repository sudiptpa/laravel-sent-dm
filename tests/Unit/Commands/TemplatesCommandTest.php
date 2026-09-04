<?php

declare(strict_types=1);

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use SentDm\Core\Exceptions\AuthenticationException;
use SentDm\Templates\TemplateListResponse;
use SentDm\Templates\TemplateListResponse\Data;
use SentDm\Templates\TemplateListResponse\Data\Template;
use Sujip\SentDm\Resources\Templates;
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

/** @return array{Sent, Templates} */
function mockTemplatesChain(): array
{
    $driver = Mockery::mock(Sent::class);
    $resource = Mockery::mock(Templates::class);

    $driver->shouldReceive('templates')->once()->andReturn($resource);
    $resource->shouldReceive('page')->once()->andReturn($resource);
    $resource->shouldReceive('perPage')->once()->andReturn($resource);

    return [$driver, $resource];
}

it('lists templates in a table', function () {
    [$driver, $resource] = mockTemplatesChain();
    $resource->shouldReceive('get')->once()->andReturn(fakeTemplateListResponse(['otp_verify', 'welcome']));

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:templates')
        ->expectsOutputToContain('otp_verify')
        ->expectsOutputToContain('welcome')
        ->assertExitCode(0);
});

it('shows info when no templates exist', function () {
    [$driver, $resource] = mockTemplatesChain();

    $data = new Data;
    $data['templates'] = [];
    $listResponse = new TemplateListResponse;
    $listResponse['data'] = $data;

    $resource->shouldReceive('get')->once()->andReturn($listResponse);

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:templates')
        ->expectsOutputToContain('No templates')
        ->assertExitCode(0);
});

it('shows failure on API exception', function () {
    [$driver, $resource] = mockTemplatesChain();
    $request = Mockery::mock(RequestInterface::class);
    $response = Mockery::mock(ResponseInterface::class);
    $response->shouldReceive('getStatusCode')->andReturn(401);
    $stream = Mockery::mock(StreamInterface::class);
    $stream->shouldReceive('__toString')->andReturn('{}');
    $stream->shouldReceive('getContents')->andReturn('{}');
    $response->shouldReceive('getBody')->andReturn($stream);
    $resource->shouldReceive('get')->andThrow(new AuthenticationException($request, $response));

    app()->instance(SentManager::class, mockSentManager($driver));

    $this->artisan('sent:templates')->assertExitCode(1);
});
