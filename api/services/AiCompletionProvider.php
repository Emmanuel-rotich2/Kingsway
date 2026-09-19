<?php

declare(strict_types=1);

namespace App\API\Services;

interface AiCompletionProvider
{
    public function complete(array $messages, array $options = []): array;
}
