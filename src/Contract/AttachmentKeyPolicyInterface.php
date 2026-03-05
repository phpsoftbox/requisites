<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Contract;

interface AttachmentKeyPolicyInterface
{
    public function isAllowed(string $profile, string $selector, string $key): bool;
}
