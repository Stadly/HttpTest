<?php

declare(strict_types=1);

namespace Stadly\Http\Header\Value;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use Stadly\Http\Utilities\Rfc7231;

/**
 * Class for handling dates.
 *
 * Specification: https://tools.ietf.org/html/rfc7231#section-7.1.1.1
 */
final class Date
{
    /**
     * @var DateTimeImmutable Date.
     */
    private $date;

    /**
     * @var bool Whether the date is a weak validator.
     */
    private $isWeak;

    /**
     * Constructor.
     *
     * @param DateTimeInterface $date Date.
     * @param bool $isWeak Whether the date is a weak validator.
     */
    public function __construct(DateTimeInterface $date, bool $isWeak = true)
    {
        // Discard microseconds.
        $immutableDate = DateTimeImmutable::createFromFormat('U', $date->format('U'));
        assert(false !== $immutableDate);

        $this->date = $immutableDate->setTimezone(new DateTimeZone('GMT'));
        $this->isWeak = $isWeak;
    }

    /**
     * Construct date from a Unix time timestamp.
     *
     * @param float $timestamp Unix time timestamp.
     * @param bool $isWeak Whether the date is a weak validator.
     * @return self Date generated based on the Unix time timestamp.
     */
    public static function fromTimestamp(float $timestamp, bool $isWeak = true): self
    {
        $dateTime = DateTime::createFromFormat('U.u', sprintf('%.6f', $timestamp));
        assert(false !== $dateTime);

        return new self($dateTime, $isWeak);
    }

    /**
     * Construct date from string.
     *
     * @param string $date Date string.
     * @param bool $isWeak Whether the date is a weak validator.
     * @return self Date generated based on the string.
     */
    public static function fromString(string $date, bool $isWeak = true): self
    {
        if (utf8_decode($date) === $date) {
            if (1 === preg_match('{^'.Rfc7231::IMF_FIXDATE.'$}', $date)) {
                $dateTime = DateTime::createFromFormat('D, d M Y H:i:s T', $date);
                assert(false !== $dateTime);
                return new self($dateTime, $isWeak);
            }

            if (1 === preg_match('{^'.Rfc7231::RFC850_DATE.'$}', $date)) {
                return self::fromRfc850String($date, $isWeak);
            }

            if (1 === preg_match('{^'.Rfc7231::ASCTIME_DATE.'$}', $date)) {
                $dateTime = DateTime::createFromFormat('D M j H:i:s Y', $date, new DateTimeZone('GMT'));
                assert(false !== $dateTime);
                return new self($dateTime, $isWeak);
            }
        }

        throw new InvalidArgumentException("Invalid date: $date");
    }

    private static function fromRfc850String(string $date, bool $isWeak): self
    {
        preg_match('{^'.Rfc7231::RFC850_DATE_CAPTURE.'$}', $date, $matches);

        $year = substr(gmdate('Y'), 0, -2).$matches['YEAR'];

        $newDate = sprintf('%s-%s-%s %s', $matches['DAY'], $matches['MONTH'], $year, $matches['TIME_OF_DAY']);
        $dateTime = DateTime::createFromFormat('d-M-Y H:i:s', $newDate, new DateTimeZone('GMT'));
        assert(false !== $dateTime);

        // If more than 50 years in the future, interpret the date as past.
        $now = new DateTimeImmutable();
        if ($now->modify('+50 years') < $dateTime) {
            // Interpret 99 as year 1999 instead of 2099 in year 2000.
            // @codeCoverageIgnoreStart
            $dateTime->modify('-100 years');
            // @codeCoverageIgnoreEnd
        } elseif ($dateTime < $now->modify('-50 years')) {
            // Interpret 00 as year 2000 instead of 1900 in year 1999.
            // @codeCoverageIgnoreStart
            $dateTime->modify('+100 years');
            // @codeCoverageIgnoreEnd
        }

        return new self($dateTime, $isWeak);
    }

    /**
     * @return string String representation of the date.
     */
    public function __toString(): string
    {
        return $this->date->format('D, d M Y H:i:s T');
    }

    /**
     * Specification: https://tools.ietf.org/html/rfc7232#section-2.1
     *
     * @return bool Whether the date is a weak validator.
     */
    public function isWeak(): bool
    {
        return $this->isWeak;
    }

    /**
     * @param self $date Date to compare with.
     * @return bool Whether this date is earlier than the date it is compared to.
     */
    public function isLt(self $date): bool
    {
        return $this->date < $date->date;
    }

    /**
     * @param self $date Date to compare with.
     * @return bool Whether this date is earlier than or the same as the date it is compared to.
     */
    public function isLte(self $date): bool
    {
        return $this->date <= $date->date;
    }

    /**
     * @param self $date Date to compare with.
     * @return bool Whether this date is the same as the date it is compared to.
     */
    public function isEq(self $date): bool
    {
        return $this->date == $date->date;
    }

    /**
     * @param self $date Date to compare with.
     * @return bool Whether this date is later than or the same as the date it is compared to.
     */
    public function isGte(self $date): bool
    {
        return $this->date >= $date->date;
    }

    /**
     * @param self $date Date to compare with.
     * @return bool Whether this date is later than the date it is compared to.
     */
    public function isGt(self $date): bool
    {
        return $this->date > $date->date;
    }
}
