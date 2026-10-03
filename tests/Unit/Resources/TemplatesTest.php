<?php

declare(strict_types=1);

use Sujip\SentDm\Builders\TemplateBuilder;

it('templates()->get() lists templates', function () {
    $result = sentApi([
        'templates' => [[
            'id' => 'tpl-1',
            'customer_id' => 'cust-1',
            'name' => 'Welcome Message',
            'category' => 'MARKETING',
            'language' => 'en_US',
            'status' => 'APPROVED',
            'auto_reply_action' => 'HELP',
            'channels' => ['sms', 'whatsapp', 'rcs'],
            'variables' => ['name', 'company'],
            'created_at' => '2026-08-12T14:57:36+00:00',
            'updated_at' => '2026-08-27T14:57:36+00:00',
            'is_published' => true,
        ]],
        'pagination' => [
            'page' => 1, 'page_size' => 20, 'total_count' => 1,
            'total_pages' => 1, 'has_more' => false, 'cursors' => null,
        ],
    ])->templates()->get();

    $template = $result->data->templates[0];
    expect($template->id)->toBe('tpl-1')
        ->and($template->customerID)->toBe('cust-1')
        ->and($template->name)->toBe('Welcome Message')
        ->and($template->category)->toBe('MARKETING')
        ->and($template->language)->toBe('en_US')
        ->and($template->status)->toBe('APPROVED')
        ->and($template->autoReplyAction)->toBe('HELP')
        ->and($template->channels)->toBe(['sms', 'whatsapp', 'rcs'])
        ->and($template->variables)->toBe(['name', 'company'])
        ->and($template->createdAt)->toBeInstanceOf(DateTimeInterface::class)
        ->and($template->updatedAt)->toBeInstanceOf(DateTimeInterface::class)
        ->and($template->isPublished)->toBeTrue();
});

it('templates()->search()->get() passes the search param', function () {
    $result = sentApi(['templates' => []])->templates()->search('welcome')->get();
    expect($result)->not->toBeNull();
});

it('templates()->page()->perPage() chains are immutable', function () {
    $base = sentApi()->templates();
    $chained = $base->page(2)->perPage(25);
    expect($chained)->not->toBe($base);
});

it('templates()->find() retrieves a template', function () {
    $result = sentApi([
        'id' => 'tpl-1',
        'customer_id' => 'cust-1',
        'name' => 'Welcome Message',
        'category' => 'MARKETING',
        'language' => 'en_US',
        'status' => 'APPROVED',
        'auto_reply_action' => null,
        'channels' => ['sms', 'whatsapp', 'rcs'],
        'variables' => ['name', 'company'],
        'created_at' => '2026-08-12T14:57:36+00:00',
        'updated_at' => '2026-08-27T14:57:36+00:00',
        'is_published' => true,
    ])->templates()->find('tpl-1');

    expect($result->data->id)->toBe('tpl-1')
        ->and($result->data->customerID)->toBe('cust-1')
        ->and($result->data->name)->toBe('Welcome Message')
        ->and($result->data->category)->toBe('MARKETING')
        ->and($result->data->language)->toBe('en_US')
        ->and($result->data->status)->toBe('APPROVED')
        ->and($result->data->autoReplyAction)->toBeNull()
        ->and($result->data->channels)->toBe(['sms', 'whatsapp', 'rcs'])
        ->and($result->data->variables)->toBe(['name', 'company'])
        ->and($result->data->createdAt)->toBeInstanceOf(DateTimeInterface::class)
        ->and($result->data->updatedAt)->toBeInstanceOf(DateTimeInterface::class)
        ->and($result->data->isPublished)->toBeTrue();
});

it('templates()->findByName() returns matching template', function () {
    $result = sentApi(['templates' => [[
        'id' => 'tpl-1',
        'customer_id' => 'cust-1',
        'name' => 'otp',
        'category' => 'UTILITY',
        'language' => 'en',
        'status' => 'APPROVED',
        'channels' => ['sms'],
        'variables' => [],
        'created_at' => '2026-08-12T14:57:36+00:00',
        'updated_at' => null,
        'is_published' => true,
    ]]])
        ->templates()
        ->findByName('otp');

    expect($result->id)->toBe('tpl-1')
        ->and($result->customerID)->toBe('cust-1')
        ->and($result->name)->toBe('otp')
        ->and($result->category)->toBe('UTILITY')
        ->and($result->language)->toBe('en')
        ->and($result->status)->toBe('APPROVED')
        ->and($result->channels)->toBe(['sms'])
        ->and($result->isPublished)->toBeTrue();
});

it('templates()->findByName() returns null when not found', function () {
    $result = sentApi(['templates' => []])->templates()->findByName('nonexistent');
    expect($result)->toBeNull();
});

it('templates()->delete() deletes a template', function () {
    sentApi([])->templates()->delete('tpl-1');
    expect(true)->toBeTrue();
});

it('templates()->delete() accepts deleteFromMeta', function () {
    sentApi([])->templates()->delete('tpl-1', deleteFromMeta: true);
    expect(true)->toBeTrue();
});

it('templates()->create() returns a TemplateBuilder', function () {
    expect(sentApi()->templates()->create())->toBeInstanceOf(TemplateBuilder::class);
});

it('templates()->create()->category()->language()->save() creates a template', function () {
    $result = sentApi([
        'id' => 'tpl-1',
        'customer_id' => 'cust-1',
        'name' => 'Welcome Message',
        'category' => 'MARKETING',
        'language' => 'en_US',
        'status' => 'DRAFT',
        'channels' => ['sms', 'whatsapp', 'rcs'],
        'variables' => ['name', 'company'],
        'created_at' => '2026-09-11T14:57:36+00:00',
        'updated_at' => '2026-09-11T14:57:36+00:00',
        'is_published' => false,
    ])
        ->templates()
        ->create()
        ->category('MARKETING')
        ->language('en_US')
        ->save();

    expect($result->data->id)->toBe('tpl-1')
        ->and($result->data->customerID)->toBe('cust-1')
        ->and($result->data->name)->toBe('Welcome Message')
        ->and($result->data->category)->toBe('MARKETING')
        ->and($result->data->language)->toBe('en_US')
        ->and($result->data->status)->toBe('DRAFT')
        ->and($result->data->channels)->toBe(['sms', 'whatsapp', 'rcs'])
        ->and($result->data->variables)->toBe(['name', 'company'])
        ->and($result->data->createdAt)->toBeInstanceOf(DateTimeInterface::class)
        ->and($result->data->updatedAt)->toBeInstanceOf(DateTimeInterface::class)
        ->and($result->data->isPublished)->toBeFalse();
});

it('templates()->create()->submitForReview()->save() creates a template submitted for review', function () {
    $result = sentApi(['id' => 'tpl-1', 'name' => 'otp'])
        ->templates()
        ->create()
        ->submitForReview()
        ->save();
    expect($result)->not->toBeNull();
});

it('templates()->create()->definition()->save() creates a template with a definition', function () {
    $result = sentApi(['id' => 'tpl-1'])
        ->templates()
        ->create()
        ->category('UTILITY')
        ->definition(['body' => ['sms' => ['template' => 'Hello {{name}}', 'type' => 'text']]])
        ->save();
    expect($result)->not->toBeNull();
});

it('templates()->create() chains are immutable', function () {
    $base = sentApi()->templates()->create();
    $chained = $base->category('MARKETING')->language('en_US');
    expect($chained)->not->toBe($base);
});

it('templates()->create()->name()->save() throws, name is update-only', function () {
    sentApi()->templates()->create()->name('my-template')->save();
})->throws(InvalidArgumentException::class, 'name() is not supported when creating');

it('templates()->create()->creationSource()->save() passes creation_source', function () {
    $result = sentApi(['id' => 'tpl-1'])->templates()->create()->creationSource('import-script')->save();
    expect($result)->not->toBeNull();
});

it('templates()->create()->autoCreateForSenderProfiles()->save() passes auto_create_for_sp', function () {
    [$captured, $sent] = capturedSentHeaders(['id' => 'tpl-1']);

    $sent->templates()->create()->autoCreateForSenderProfiles()->save();

    $body = json_decode((string) $captured->body, true);

    expect($body['auto_create_for_sp'])->toBeTrue();
});

it('templates()->update()->creationSource()->save() throws, creationSource is create-only', function () {
    sentApi(['id' => 'tpl-1'])->templates()->update('tpl-1')->creationSource('import-script')->save();
})->throws(InvalidArgumentException::class, 'creationSource() is not supported when updating');

it('templates()->update()->autoCreateForSenderProfiles()->save() throws, autoCreateForSenderProfiles is create-only', function () {
    sentApi(['id' => 'tpl-1'])->templates()->update('tpl-1')->autoCreateForSenderProfiles()->save();
})->throws(InvalidArgumentException::class, 'autoCreateForSenderProfiles() is not supported when updating');

it('templates()->update() returns a TemplateBuilder', function () {
    expect(sentApi()->templates()->update('tpl-1'))->toBeInstanceOf(TemplateBuilder::class);
});

it('templates()->update()->name()->save() updates a template', function () {
    $result = sentApi([
        'id' => 'tpl-1',
        'customer_id' => 'cust-1',
        'name' => 'Updated Welcome Message',
        'category' => 'MARKETING',
        'language' => 'en_US',
        'status' => 'DRAFT',
        'channels' => ['sms', 'whatsapp'],
        'variables' => ['name', 'company'],
        'created_at' => '2026-08-12T14:57:36+00:00',
        'updated_at' => '2026-09-11T14:57:36+00:00',
        'is_published' => false,
    ])
        ->templates()
        ->update('tpl-1')
        ->name('new-name')
        ->save();

    expect($result->data->id)->toBe('tpl-1')
        ->and($result->data->customerID)->toBe('cust-1')
        ->and($result->data->name)->toBe('Updated Welcome Message')
        ->and($result->data->category)->toBe('MARKETING')
        ->and($result->data->language)->toBe('en_US')
        ->and($result->data->status)->toBe('DRAFT')
        ->and($result->data->channels)->toBe(['sms', 'whatsapp'])
        ->and($result->data->variables)->toBe(['name', 'company'])
        ->and($result->data->isPublished)->toBeFalse();
});

it('templates()->update() chains are immutable', function () {
    $base = sentApi()->templates()->update('tpl-1');
    $chained = $base->name('new-name')->category('UTILITY');
    expect($chained)->not->toBe($base);
});

it('templates()->category()->get() filters by category', function () {
    $base = sentApi(['templates' => []])->templates();
    $filtered = $base->category('MARKETING');
    expect($filtered)->not->toBe($base);
    $result = $filtered->get();
    expect($result)->not->toBeNull();
});

it('templates()->status()->get() filters by status', function () {
    $base = sentApi(['templates' => []])->templates();
    $filtered = $base->status('APPROVED');
    expect($filtered)->not->toBe($base);
    $result = $filtered->get();
    expect($result)->not->toBeNull();
});

it('templates()->isWelcomePlayground()->get() filters by welcome playground flag', function () {
    $base = sentApi(['templates' => []])->templates();
    $filtered = $base->isWelcomePlayground(true);
    expect($filtered)->not->toBe($base);
    $result = $filtered->get();
    expect($result)->not->toBeNull();
});
