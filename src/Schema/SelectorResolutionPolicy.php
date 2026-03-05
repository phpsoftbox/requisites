<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Schema;

enum SelectorResolutionPolicy: string
{
    case PAYLOAD_FIRST = 'payload_first';
    case CONTEXT_FIRST = 'context_first';
    case CONTEXT_ONLY  = 'context_only';
    case PAYLOAD_ONLY  = 'payload_only';
}
