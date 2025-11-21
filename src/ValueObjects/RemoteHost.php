<?php

// ABOUTME: Represents a validated Docker daemon remote host connection string.
// ABOUTME: Supports tcp://, ssh://, and unix:// protocols.

declare(strict_types=1);

namespace Ninja\Docker\ValueObjects;

final readonly class RemoteHost
{
    /** @var non-empty-string */
    public private(set) string $value;

    public function __construct(string $value)
    {
        if ($value === '') {
            throw new \InvalidArgumentException('Remote host cannot be empty');
        }

        // Special handling for unix:// URLs (parse_url fails on unix:///)
        if (str_starts_with($value, 'unix://')) {
            if (!preg_match('#^unix:///.+#', $value)) {
                throw new \InvalidArgumentException(
                    "Unix socket must be an absolute path starting with unix:///, got '{$value}'"
                );
            }
            $this->value = $value;
            return;
        }

        $parsed = parse_url($value);

        if ($parsed === false || !isset($parsed['scheme'])) {
            throw new \InvalidArgumentException(
                "Invalid remote host format. Expected tcp://host:port, ssh://user@host, or unix:///socket, got '{$value}'"
            );
        }

        if (!in_array($parsed['scheme'], ['tcp', 'ssh'], true)) {
            throw new \InvalidArgumentException(
                "Remote host protocol must be tcp, ssh, or unix, got '{$parsed['scheme']}'"
            );
        }

        // For tcp and ssh URLs, parse_url() guarantees a host key exists (or returns false)
        // Using assertion for PHPStan rather than runtime check since this is structurally unreachable
        assert(isset($parsed['host']), 'parse_url() must provide host for valid tcp/ssh URLs');

        // Validate host doesn't contain shell metacharacters
        if (preg_match('/[;\|\&\$\`<>]/', $parsed['host'])) {
            throw new \InvalidArgumentException(
                "Host contains invalid characters, got '{$parsed['host']}'"
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
