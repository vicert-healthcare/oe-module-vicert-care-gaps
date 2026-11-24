<?php

/**
 * Care Gaps Worklist for OpenEMR.
 *
 * @package   Vicert\CareGaps
 * @link      https://vicert.ai
 * @copyright Copyright (c) 2026 Vicert
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Vicert\CareGaps\Service;

use Vicert\CareGaps\Domain\MeasureKey;

/** The filters a user picked on the page. Built only from validated input. */
final readonly class WorklistFilter
{
    public const MAX_SEARCH_LENGTH = 64;

    public function __construct(
        public ?MeasureKey $measure,
        public ?int $providerId,
        public string $search,
    ) {
    }

    /** Builds a filter from the request query; anything invalid is dropped, never passed on. */
    public static function fromQuery(array $query): self
    {
        $measure = is_string($query['measure'] ?? null) ? MeasureKey::tryFrom($query['measure']) : null;

        $provider = is_string($query['provider'] ?? null)
            ? filter_var($query['provider'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;

        $search = is_string($query['q'] ?? null) ? trim($query['q']) : '';
        $search = mb_substr($search, 0, self::MAX_SEARCH_LENGTH);

        return new self($measure, $provider === false ? null : $provider, $search);
    }

    /** @return array<string, string> the filter as query parameters, for links and the CSV export */
    public function toQuery(): array
    {
        return array_filter([
            'measure' => $this->measure?->value ?? '',
            'provider' => $this->providerId === null ? '' : (string) $this->providerId,
            'q' => $this->search,
        ], static fn (string $value): bool => $value !== '');
    }
}
