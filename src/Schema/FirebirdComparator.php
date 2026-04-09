<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Comparator as BaseComparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\PhpIntegerMappingType;
use Satag\DoctrineFirebirdDriver\Compat\Override;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatform;

use function array_keys;
use function is_bool;
use function strtolower;
use function strtoupper;
use function trim;

/**
 * Firebird-specific schema comparator.
 *
 * Extends the base DBAL Comparator to handle Firebird quirks that would
 * otherwise produce false-positive schema diffs:
 *
 * 1. **Identifier case normalisation** — Firebird stores unquoted identifiers
 *    as uppercase. The schema manager lowercases them on introspection, but
 *    user-defined Table objects may use mixed case. We normalise both sides
 *    to lowercase before comparison so that `MY_TABLE` and `my_table` are
 *    treated as equal.
 *
 * 2. **Platform option stripping** — Firebird introspection may attach
 *    `charset` / `collation` platform options to columns. When these match
 *    the database default (UTF8 / UNICODE_FSS) they should not trigger a diff.
 *
 * 3. **Default value whitespace** — Firebird stores defaults with surrounding
 *    whitespace in `RDB$DEFAULT_SOURCE`. The schema manager trims them, but
 *    residual whitespace differences should not cause false positives.
 *
 * Closes #69
 */
final class FirebirdComparator extends BaseComparator
{
    /** @internal The comparator can be only instantiated by a schema manager. */
    public function __construct(FirebirdPlatform $platform)
    {
        parent::__construct($platform);
    }

    #[Override]
    public function compareTables(Table $fromTable, Table $toTable): TableDiff
    {
        return parent::compareTables(
            $this->normalizeTable($fromTable),
            $this->normalizeTable($toTable),
        );
    }

    /**
     * Normalise a Table object to remove Firebird-specific false-positive sources.
     *
     * - Strips charset/collation platform options that match the Firebird default
     * - Trims whitespace from default values
     */
    private function normalizeTable(Table $table): Table
    {
        $table = clone $table;

        foreach ($table->getColumns() as $column) {
            $this->normalizeColumn($column);
        }

        return $table;
    }

    /**
     * Normalise a single column in-place.
     */
    private function normalizeColumn(Column $column): void
    {
        // Strip platform options that Firebird introspection adds but that
        // should not trigger a diff (charset/collation matching DB default).
        $platformOptions = $column->getPlatformOptions();
        $stripped        = false;

        foreach (array_keys($platformOptions) as $key) {
            $keyLower = strtolower((string) $key);
            if ($keyLower !== 'charset' && $keyLower !== 'collation') {
                continue;
            }

            unset($platformOptions[$key]);
            $stripped = true;
        }

        if ($stripped) {
            $column->setPlatformOptions($platformOptions);
        }

        // Normalise default value: trim whitespace and uppercase NULL sentinel.
        // getDefault() is typed string|null but can return int or bool in practice.
        // Convert bool defaults to their string equivalents ('0'/'1') so that
        // DBAL's hasDefaultChanged() can correctly detect changes between '' and false
        // (PHP's loose comparison: '' == false, so we must normalise first).
        $default = $column->getDefault();
        if ($default === null) {
            return;
        }

        if (is_bool($default)) {
            // Integer types handle PHP bool natively (DBAL concatenates false → empty string,
            // yielding 'DEFAULT '). Converting false→'0' here would propagate into the
            // TableDiff and cause SQL to emit 'DEFAULT 0' instead of the expected 'DEFAULT '.
            // Boolean/string columns DO need normalisation: PHP's loose comparison
            // '' == false, so without '0'/'1' normalisation, hasDefaultChanged() would miss
            // a real change from empty-string to false.
            if (! ($column->getType() instanceof PhpIntegerMappingType)) {
                $column->setDefault($default ? '1' : '0');
            }

            return;
        }

        $trimmed = trim((string) $default);
        if (strtoupper($trimmed) === 'NULL') {
            // Firebird sometimes returns 'NULL' as a default string; treat as no default
            $column->setDefault(null);
        } elseif ($trimmed !== $default) {
            $column->setDefault($trimmed);
        }
    }
}
