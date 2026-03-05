<?php

declare(strict_types=1);

namespace PhpSoftBox\Requisites\Attachment;

use PhpSoftBox\Requisites\Contract\AttachmentKeyPolicyInterface;

final readonly class AllowAllAttachmentKeyPolicy implements AttachmentKeyPolicyInterface
{
    public function isAllowed(string $profile, string $selector, string $key): bool
    {
        return true;
    }
}
