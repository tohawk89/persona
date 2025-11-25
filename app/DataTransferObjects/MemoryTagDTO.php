<?php

namespace App\DataTransferObjects;

class MemoryTagDTO
{
    public function __construct(
        public readonly string $target,
        public readonly string $key,
        public readonly string $value,
    ) {
    }

    /**
     * Create from array (typically from JSON).
     */
    public static function fromArray(array $data): self
    {
        return new self(
            target: $data['target'] ?? 'user',
            key: $data['key'] ?? '',
            value: $data['value'] ?? '',
        );
    }

    /**
     * Create multiple DTOs from array of arrays.
     *
     * @return array<MemoryTagDTO>
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
            'target' => $this->target,
            'key' => $this->key,
            'value' => $this->value,
        ];
    }

    /**
     * Validate if the memory tag is valid.
     */
    public function isValid(): bool
    {
        return in_array($this->target, ['user', 'self'])
            && !empty($this->key)
            && !empty($this->value);
    }
}
