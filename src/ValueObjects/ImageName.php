<?php

// ABOUTME: Represents a validated Docker image name with optional registry, tag, and digest.
// ABOUTME: Enforces Docker image naming rules and format.

declare(strict_types=1);

namespace Ninja\Docker\ValueObjects;

final readonly class ImageName
{
    /** @var non-empty-string */
    public private(set) string $value;

    public function __construct(string $value)
    {
        if ($value === '') {
            throw new \InvalidArgumentException('Image name cannot be empty');
        }

        // Basic validation: must contain alphanumeric and allowed chars
        // Format: [registry/][namespace/]repository[:tag][@digest]
        // Note: Docker repository names must be lowercase
        $pattern = '/^(?:(?:[a-z0-9]+(?:[._-][a-z0-9]+)*(?:\:[0-9]+)?\/)?(?:[a-z0-9_-]+\/)?)?[a-z0-9_-]+(?::[a-z0-9._-]+)?(?:@[a-z0-9:]+)?$/';

        if (!preg_match($pattern, $value)) {
            throw new \InvalidArgumentException(
                "Invalid image name format. Expected [registry/][namespace/]repository[:tag][@digest], got '{$value}'"
            );
        }

        $this->value = $value;
    }

    public static function from(string $value): self
    {
        return new self($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
