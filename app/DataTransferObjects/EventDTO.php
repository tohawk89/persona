<?php

namespace App\DataTransferObjects;

class EventDTO
{
    public function __construct(
        public readonly string $type,
        public readonly string $content,
        public readonly string $scheduledAt,
    ) {
    }

    /**
     * Create from array (typically from JSON).
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'] ?? 'text',
            content: $data['content'] ?? '',
            scheduledAt: $data['scheduled_at'] ?? now()->toDateTimeString(),
        );
    }

    /**
     * Create multiple DTOs from array of arrays.
     *
     * @return array<EventDTO>
     */
    public static function collection(array $data): array
    {
        return array_map(fn($item) => self::fromArray($item), $data);
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'content' => $this->content,
            'scheduled_at' => $this->scheduledAt,
        ];
    }

    /**
     * Validate if the event type is supported.
     */
    public function isValid(): bool
    {
        return in_array($this->type, ['text', 'image']) && !empty($this->content);
    }
}
