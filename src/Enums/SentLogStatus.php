<?php

declare(strict_types=1);

namespace Sujip\SentDm\Enums;

enum SentLogStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Read = 'read';
}
