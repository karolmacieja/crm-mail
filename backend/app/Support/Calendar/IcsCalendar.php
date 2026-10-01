<?php

namespace App\Support\Calendar;

use Illuminate\Support\Carbon;

/**
 * Minimal RFC 5545 (iCalendar) writer for the private calendar feed.
 * Times are written in UTC ("Z"), so no VTIMEZONE block is needed.
 */
class IcsCalendar
{
    /** @var list<string> */
    private array $lines = [];

    public function __construct(string $name, string $description = '')
    {
        $this->lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//GastroFlowx//CRM//PL',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.self::escape($name),
            'X-WR-CALDESC:'.self::escape($description),
            // Ask clients that honour it to refresh every 15 minutes.
            'REFRESH-INTERVAL;VALUE=DURATION:PT15M',
            'X-PUBLISHED-TTL:PT15M',
        ];
    }

    /**
     * @param  array{uid: string, start: Carbon, end: Carbon, summary: string, description?: ?string, url?: ?string, categories?: ?string, alarm_minutes?: ?int, updated?: ?Carbon}  $event
     */
    public function addEvent(array $event): static
    {
        $lines = [
            'BEGIN:VEVENT',
            'UID:'.$event['uid'],
            'DTSTAMP:'.self::time($event['updated'] ?? now()),
            'DTSTART:'.self::time($event['start']),
            'DTEND:'.self::time($event['end']),
            'SUMMARY:'.self::escape($event['summary']),
        ];
        if (! empty($event['description'])) {
            $lines[] = 'DESCRIPTION:'.self::escape($event['description']);
        }
        if (! empty($event['categories'])) {
            $lines[] = 'CATEGORIES:'.self::escape($event['categories']);
        }
        if (! empty($event['url'])) {
            $lines[] = 'URL:'.$event['url'];
        }
        if (! empty($event['alarm_minutes'])) {
            array_push($lines,
                'BEGIN:VALARM',
                'ACTION:DISPLAY',
                'DESCRIPTION:'.self::escape($event['summary']),
                'TRIGGER:-PT'.(int) $event['alarm_minutes'].'M',
                'END:VALARM',
            );
        }
        $lines[] = 'END:VEVENT';

        array_push($this->lines, ...$lines);

        return $this;
    }

    public function render(): string
    {
        $lines = [...$this->lines, 'END:VCALENDAR'];

        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }

    public static function time(Carbon $time): string
    {
        return $time->copy()->utc()->format('Ymd\THis\Z');
    }

    /** RFC 5545 §3.3.11 text escaping. */
    public static function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\;', '\,', '\n', '\n', '\n'], $text);
    }

    /** RFC 5545 §3.1: lines longer than 75 octets are folded (UTF-8 safe). */
    public static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $out = '';
        $current = '';
        foreach (mb_str_split($line) as $char) {
            if (strlen($current) + strlen($char) > ($out === '' ? 75 : 74)) {
                $out .= ($out === '' ? '' : "\r\n ").$current;
                $current = '';
            }
            $current .= $char;
        }

        return $out.($current !== '' ? "\r\n ".$current : '');
    }
}
