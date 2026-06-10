<?php

declare(strict_types=1);

namespace SebCode\Provider\Application\Dto\Input;

final readonly class GenerateModelReplyInput
{
    /**
     * @param list<ModelMessageInput> $messages
     */
    public function __construct(
        public string $providerName,
        public string $model,
        public array $messages,
    ) {
    }
}
