<?php

namespace Elegant\Support\Facades;

use Elegant\Support\DateFactory;

/**
 * @see https://carbon.nesbot.com/docs/
 * @see https://github.com/briannesbitt/Carbon/blob/master/src/Carbon/Factory.php
 *
 * @method static \Elegant\Support\Carbon create($year = 0, $month = 1, $day = 1, $hour = 0, $minute = 0, $second = 0, $tz = null)
 * @method static \Elegant\Support\Carbon createFromDate($year = null, $month = null, $day = null, $tz = null)
 * @method static \Elegant\Support\Carbon createFromTime($hour = 0, $minute = 0, $second = 0, $tz = null)
 * @method static \Elegant\Support\Carbon createFromTimeString($time, $tz = null)
 * @method static \Elegant\Support\Carbon createFromTimestamp($timestamp, $tz = null)
 * @method static \Elegant\Support\Carbon createFromTimestampMs($timestamp, $tz = null)
 * @method static \Elegant\Support\Carbon createFromTimestampUTC($timestamp)
 * @method static \Elegant\Support\Carbon createMidnightDate($year = null, $month = null, $day = null, $tz = null)
 * @method static \Elegant\Support\Carbon disableHumanDiffOption($humanDiffOption)
 * @method static \Elegant\Support\Carbon enableHumanDiffOption($humanDiffOption)
 * @method static \Elegant\Support\Carbon fromSerialized($value)
 * @method static \Elegant\Support\Carbon getLastErrors()
 * @method static \Elegant\Support\Carbon getTestNow()
 * @method static \Elegant\Support\Carbon instance($date)
 * @method static \Elegant\Support\Carbon isMutable()
 * @method static \Elegant\Support\Carbon maxValue()
 * @method static \Elegant\Support\Carbon minValue()
 * @method static \Elegant\Support\Carbon now($tz = null)
 * @method static \Elegant\Support\Carbon parse($time = null, $tz = null)
 * @method static \Elegant\Support\Carbon setHumanDiffOptions($humanDiffOptions)
 * @method static \Elegant\Support\Carbon setTestNow($testNow = null)
 * @method static \Elegant\Support\Carbon setUtf8($utf8)
 * @method static \Elegant\Support\Carbon today($tz = null)
 * @method static \Elegant\Support\Carbon tomorrow($tz = null)
 * @method static \Elegant\Support\Carbon useStrictMode($strictModeEnabled = true)
 * @method static \Elegant\Support\Carbon yesterday($tz = null)
 * @method static \Elegant\Support\Carbon|false createFromFormat($format, $time, $tz = null)
 * @method static \Elegant\Support\Carbon|false createSafe($year = null, $month = null, $day = null, $hour = null, $minute = null, $second = null, $tz = null)
 * @method static \Elegant\Support\Carbon|null make($var)
 * @method static array getAvailableLocales()
 * @method static array getDays()
 * @method static array getIsoUnits()
 * @method static array getWeekendDays()
 * @method static bool hasFormat($date, $format)
 * @method static bool hasMacro($name)
 * @method static bool hasRelativeKeywords($time)
 * @method static bool hasTestNow()
 * @method static bool isImmutable()
 * @method static bool isModifiableUnit($unit)
 * @method static bool isStrictModeEnabled()
 * @method static bool localeHasDiffOneDayWords($locale)
 * @method static bool localeHasDiffSyntax($locale)
 * @method static bool localeHasDiffTwoDayWords($locale)
 * @method static bool localeHasPeriodSyntax($locale)
 * @method static bool localeHasShortUnits($locale)
 * @method static bool setLocale($locale)
 * @method static bool shouldOverflowMonths()
 * @method static bool shouldOverflowYears()
 * @method static int getHumanDiffOptions()
 * @method static int getMidDayAt()
 * @method static int getWeekEndsAt()
 * @method static int getWeekStartsAt()
 * @method static mixed executeWithLocale($locale, $func)
 * @method static mixed use(mixed $handler)
 * @method static string getLocale()
 * @method static string pluralUnit(string $unit)
 * @method static string singularUnit(string $unit)
 * @method static void macro($name, $macro)
 * @method static void mixin($mixin)
 * @method static void resetMonthsOverflow()
 * @method static void resetToStringFormat()
 * @method static void resetYearsOverflow()
 * @method static void serializeUsing($callback)
 * @method static void setMidDayAt($hour)
 * @method static void setToStringFormat($format)
 * @method static void setWeekEndsAt($day)
 * @method static void setWeekStartsAt($day)
 * @method static void setWeekendDays($days)
 * @method static void useCallable(callable $callable)
 * @method static void useClass(string $class)
 * @method static void useDefault()
 * @method static void useFactory(object $factory)
 * @method static void useMonthsOverflow($monthsOverflow = true)
 * @method static void useYearsOverflow($yearsOverflow = true)
 */
class Date extends Facade
{
    const DEFAULT_FACADE = DateFactory::class;

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'date';
    }

    protected static function resolveFacadeInstance($name)
    {
        if (! isset(static::$resolvedInstance[$name]) && ! isset(static::$app, static::$app->$name)) {
            $class = static::DEFAULT_FACADE;

            static::swap(new $class);
        }

        return parent::resolveFacadeInstance($name);
    }
}