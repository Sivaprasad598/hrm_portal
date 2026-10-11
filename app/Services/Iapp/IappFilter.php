<?php

namespace App\Services\Iapp;

use Carbon\Carbon;

/**
 * Builds iApp's OData-style filter expressions.
 *
 * Two things this exists to get right, both established against the live
 * server rather than the published docs:
 *
 *  - Operators. deviceTrans/get accepts gt / gteq / lt / lteq and rejects
 *    ge / le with "Invalid Parameter Operator!". The vendor's own examples
 *    use ge/le, so the operator names are configurable per endpoint and
 *    default to the ones that actually work.
 *  - Dates. The server reads "01-09-2026" as 9 January, not 1 September, so
 *    a DD-MM-YYYY value is silently wrong for any day of the month below 13.
 *    ISO (YYYY-MM-DD) is unambiguous and accepted, and is what this emits.
 *
 * Values are wrapped in single quotes, so an embedded quote would terminate
 * the literal early and produce error 03; escape() removes them.
 */
class IappFilter
{

    /** Inclusive comparison operator names, per endpoint dialect. */
    public const GTE_STRICT = 'gteq';
    public const LTE_STRICT = 'lteq';
    public const GTE_DOCS = 'ge';
    public const LTE_DOCS = 'le';

    protected array $clauses = [];

    public function __construct(
        protected string $gte = self::GTE_STRICT,
        protected string $lte = self::LTE_STRICT
    ) {
    }

    /**
     * Dialect used by the published documentation's examples (ge / le).
     * attd/getDetail is documented with these.
     */
    public static function docsDialect(): self
    {
        return new self(self::GTE_DOCS, self::LTE_DOCS);
    }

    /**
     * Dialect the live deviceTrans endpoint accepts (gteq / lteq).
     */
    public static function strictDialect(): self
    {
        return new self(self::GTE_STRICT, self::LTE_STRICT);
    }

    public function where(string $field, string $operator, string $value): self
    {
        $this->clauses[] = sprintf("%s %s '%s'", $field, $operator, self::escape($value));

        return $this;
    }

    public function equals(string $field, string $value): self
    {
        return $this->where($field, 'eq', $value);
    }

    public function notEquals(string $field, string $value): self
    {
        return $this->where($field, 'ne', $value);
    }

    public function greaterThan(string $field, string $value): self
    {
        return $this->where($field, 'gt', $value);
    }

    public function lessThan(string $field, string $value): self
    {
        return $this->where($field, 'lt', $value);
    }

    public function atLeast(string $field, string $value): self
    {
        return $this->where($field, $this->gte, $value);
    }

    public function atMost(string $field, string $value): self
    {
        return $this->where($field, $this->lte, $value);
    }

    /**
     * Inclusive date range on a date-typed field.
     */
    public function dateBetween(string $field, Carbon $from, Carbon $to): self
    {
        return $this->atLeast($field, $from->format(IappHelper::FILTER_DATE))
            ->atMost($field, $to->format(IappHelper::FILTER_DATE));
    }

    /**
     * Punches strictly after an instant — the cursor used to page the punch
     * feed, since pageno is inert on this server.
     */
    public function after(string $field, Carbon $moment): self
    {
        return $this->greaterThan($field, $moment->format(IappHelper::FILTER_DATETIME));
    }

    /**
     * Only AND is supported; the docs describe no OR operator, and an
     * AND-joined pair of "not empty" tests returns rows matching both rather
     * than either.
     */
    public function build(): string
    {
        return implode(' and ', $this->clauses);
    }

    public function __toString(): string
    {
        return $this->build();
    }

    public function isEmpty(): bool
    {
        return $this->clauses === [];
    }

    public static function escape(string $value): string
    {
        return IappHelper::escapeFilterValue($value);
    }

}
