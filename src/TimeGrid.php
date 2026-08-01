<?php

declare(strict_types=1);

namespace App;

/**
 * Builds the time-column model for the grid (open..close in fixed slots) and
 * maps a reservation time to its slot index.
 */
final class TimeGrid
{
    private int $openMin;
    private int $closeMin;

    public function __construct(
        string $openTime,
        string $closeTime,
        private int $slotMinutes
    ) {
        $this->openMin  = self::toMinutes($openTime);
        $this->closeMin = self::toMinutes($closeTime);
    }

    public function slotCount(): int
    {
        return (int) max(0, ($this->closeMin - $this->openMin) / $this->slotMinutes);
    }

    /**
     * @return array<int,array{index:int,time:string,minutes:int,isHour:bool,label:string}>
     */
    public function slots(): array
    {
        $slots = [];
        $count = $this->slotCount();
        for ($i = 0; $i < $count; $i++) {
            $m = $this->openMin + $i * $this->slotMinutes;
            $isHour = ($m % 60) === 0;
            $slots[] = [
                'index'   => $i,
                'time'    => sprintf('%02d:%02d', intdiv($m, 60), $m % 60),
                'minutes' => $m,
                'isHour'  => $isHour,
                'label'   => self::hourLabel($m),
            ];
        }

        return $slots;
    }

    /**
     * Slot index for a HH:MM(:SS) time, or null if outside business hours.
     */
    public function slotIndexFor(string $time): ?int
    {
        $m = self::toMinutes($time);
        if ($m < $this->openMin || $m >= $this->closeMin) {
            return null;
        }

        return intdiv($m - $this->openMin, $this->slotMinutes);
    }

    private static function hourLabel(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $suffix = $h < 12 ? 'am' : 'pm';
        $h12 = $h % 12;
        if ($h12 === 0) {
            $h12 = 12;
        }

        return $h12 . $suffix;
    }

    private static function toMinutes(string $time): int
    {
        $parts = explode(':', $time);
        $h = (int) ($parts[0] ?? 0);
        $m = (int) ($parts[1] ?? 0);

        return $h * 60 + $m;
    }
}
