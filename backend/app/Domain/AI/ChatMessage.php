<?php

namespace App\Domain\AI;

final readonly class ChatMessage
{
    public function __construct(
        public string $role,
        public string $content,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            role: $data['role'],
            content: $data['content'],
        );
    }

    /** @return array{role: string, content: string} */
    public function toArray(): array
    {
        return [
            'role' => $this->role,
            'content' => $this->content,
        ];
    }
}
