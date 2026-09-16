<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('indexes the columns SentLog\'s own query scopes filter on', function () {
    $indexedColumns = collect(Schema::getIndexes('sent_logs'))
        ->pluck('columns')
        ->flatten()
        ->unique();

    expect($indexedColumns)->toContain('status')
        ->toContain('recipient')
        ->toContain('created_at');
});
