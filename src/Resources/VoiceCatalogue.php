<?php

declare(strict_types=1);

namespace Sujip\SentDm\Resources;

use SentDm\Voice\Catalogue\APIResponseOfVoiceCatalogue;

class VoiceCatalogue extends Resource
{
    public function get(): APIResponseOfVoiceCatalogue
    {
        return $this->cached(
            'sent.voice.catalogue',
            fn (): APIResponseOfVoiceCatalogue => $this->client->voice->catalogue->list(xProfileID: $this->orgProfileId),
        );
    }
}
