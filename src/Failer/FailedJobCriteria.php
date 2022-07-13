<?php

namespace Bdf\Queue\Failer;

use DateTime;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Store FailedJob search filters
 * The criteria supports only on filter per field
 *
 * @see FailedJobRepositoryInterface::search()
 */
final class FailedJobCriteria
{
    public const OPERATORS = ['>=', '<=', '>', '=', '<'];
    public const WILDCARD = 'wildcard';

    /**
     * @var array<string, array{string, mixed}>
     */
    private $criteria = [];

    /**
     * Search for message name
     * You can use a wildcard "*" for perform partial search
     *
     * @param string $name The requested name
     * @param value-of<FailedJobCriteria::OPERATORS>|null $operator
     *
     * @return $this
     */
    public function name(string $name, ?string $operator = null): self
    {
        [$name, $operator] = self::parseOperatorAndValue($name, $operator);

        return $this->add('name', $operator, $name);
    }

    /**
     * Search for connection name
     * This search is strict, operators nor wildcard are supported
     *
     * @param string $connection
     *
     * @return $this
     */
    public function connection(string $connection): self
    {
        return $this->add('connection', '=', $connection);
    }

    /**
     * Search for queue name
     * This search is strict, operators nor wildcard are supported
     *
     * @param string $queue
     *
     * @return $this
     */
    public function queue(string $queue): self
    {
        return $this->add('queue', '=', $queue);
    }

    /**
     * Search for error message
     * You can use a wildcard "*" for perform partial search
     *
     * @param DateTimeInterface|string $error
     * @param value-of<FailedJobCriteria::OPERATORS>|null $operator
     *
     * @return $this
     */
    public function error(string $error, ?string $operator = null): self
    {
        [$error, $operator] = self::parseOperatorAndValue($error, $operator);

        return $this->add('error', $operator, $error);
    }

    /**
     * Search for failing date
     * Wildcard search can be used, if supported by the implementation
     *
     * @param DateTimeInterface|string $failedAt The failed date. Can be a string in format supported by PHP
     * @param value-of<FailedJobCriteria::OPERATORS>|null $operator
     *
     * @return $this
     */
    public function failedAt($failedAt, ?string $operator = null): self
    {
        return $this->addDateTime('failedAt', $operator, $failedAt);
    }

    /**
     * Search for initial failing date
     * Wildcard search can be used, if supported by the implementation
     *
     * @param DateTimeInterface|string $failedAt The failed date. Can be a string in format supported by PHP
     * @param value-of<FailedJobCriteria::OPERATORS>|null $operator
     *
     * @return $this
     */
    public function firstFailedAt($failedAt, ?string $operator = null): self
    {
        return $this->addDateTime('firstFailedAt', $operator, $failedAt);
    }

    /**
     * Search for message name
     *
     * @param string|int $attempts The number of retry attempts. Can be prefixed by the operator.
     * @param value-of<FailedJobCriteria::OPERATORS>|null $operator
     *
     * @return $this
     */
    public function attempts($attempts, ?string $operator = null): self
    {
        [$attempts, $operator] = self::parseOperatorAndValue($attempts, $operator);

        return $this->add('attempts', $operator, (int) $attempts);
    }

    /**
     * Add a new filter
     *
     * @param string $field The search field name
     * @param string $operator The comparison operator
     * @param mixed $value The compared value
     *
     * @return $this
     */
    public function add(string $field, string $operator, $value): self
    {
        $this->criteria[$field] = [$operator, $value];

        return $this;
    }

    /**
     * Export criteria to array format
     *
     * - The key is the field name
     * - The first value is the operator
     * - The second value is the compared value
     *
     * Format:
     * array [
     *     'field' => ['operator', value],
     * ];
     *
     * @return array<string, array{string, mixed}>
     */
    public function toArray(): array
    {
        return $this->criteria;
    }

    /**
     * Apply criteria to the query configurator
     *
     * The first argument is the field name
     * The second is the operator
     * The third is the compared value
     *
     * @param callable(string, string, mixed):void $queryConfigurator
     *
     * @return void
     */
    public function apply(callable $queryConfigurator): void
    {
        foreach ($this->criteria as $field => [$operator, $value]) {
            $queryConfigurator($field, $operator, $value);
        }
    }

    /**
     * Check if a FailedJob entity match with the given criteria
     *
     * @param FailedJob $job
     *
     * @return bool
     */
    public function match(FailedJob $job): bool
    {
        foreach ($this->criteria as $field => [$operator, $value]) {
            if (!self::matchSingleProperty($job->$field, $operator, $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Search on a date field
     * Wildcard search can be used, if supported by the implementation
     *
     * @param string $field The field name
     * @param value-of<FailedJobCriteria::OPERATORS>|null $operator
     * @param DateTimeInterface|string $date The date filter. Can be a string in format supported by PHP
     *
     * @return $this
     */
    private function addDateTime(string $field, ?string $operator, $date): self
    {
        if (is_string($date)) {
            [$date, $operator] = self::parseOperatorAndValue($date, $operator);

            // Allow search for date string using wildcard, like "2022-01-15*"
            if ($operator !== self::WILDCARD) {
                $date = new DateTime($date);
            }
        } elseif ($operator === null) {
            $operator = '=';
        }

        return $this->add($field, $operator, $date);
    }

    /**
     * Check for a single property value
     *
     * @param mixed $entityValue
     * @param string $operator
     * @param mixed $comparedValue
     * @return bool
     */
    private static function matchSingleProperty($entityValue, string $operator, $comparedValue): bool
    {
        switch ($operator) {
            case '=':
                if (is_string($comparedValue)) {
                    $entityValue = strtolower($entityValue);
                    $comparedValue = strtolower($comparedValue);
                }

                return $entityValue == $comparedValue;

            case '>':
                return $entityValue > $comparedValue;

            case '<':
                return $entityValue < $comparedValue;

            case '>=':
                return $entityValue >= $comparedValue;

            case '<=':
                return $entityValue <= $comparedValue;

            case FailedJobCriteria::WILDCARD:
                if ($entityValue instanceof \DateTimeInterface) {
                    $entityValue = $entityValue->format(DateTime::ATOM);
                }

                return (bool)preg_match('#^' . str_replace('\*', '.*', preg_quote($comparedValue)) . '$#i', $entityValue);

            default:
                throw new InvalidArgumentException('Unsupported operator ' . $operator);
        }
    }

    /**
     * Parse a filter value for extract the operator
     *
     * If the operator is not provided, it will be resolved from value :
     * - If the value contains a wildcard "*", use WILDCARD operator
     * - If the value starts with one of the known operator, it will be used, and the operator will be removed from value
     * - Otherwise, "=" operator will be used
     *
     * @param mixed $value The compared value
     * @param string|null $operator The requested operator
     *
     * @return array{mixed, string}
     */
    private static function parseOperatorAndValue(string $value, ?string $operator): array
    {
        if ($operator !== null) {
            return [$value, $operator];
        }

        if (strpos($value, '*') !== false) {
            return [$value, self::WILDCARD];
        }

        foreach (self::OPERATORS as $operator) {
            if (strpos($value, $operator) === 0) {
                return [ltrim(substr($value, strlen($operator))), $operator];
            }
        }

        return [$value, '='];
    }
}
