<?php

declare(strict_types=1);

class MidiTrim extends Midi
{
    /**
     * Trim entire song between two timestamps
     */
    public function trimSong(int $from = 0, int|false $to = false): void
    {
        $trackCount = count($this->tracks);

        for ($i = 0; $i < $trackCount; $i++) {
            $this->trimTrack($i, $from, $to);
        }
    }

    /**
     * Trim a single track
     */
    public function trimTrack(int $tn, int $from = 0, int|false $to = false): void
    {
        $track = $this->tracks[$tn];
        $new = [];

        foreach ($track as $msgStr) {
            $msg = explode(' ', $msgStr);
            $time = (int) $msg[0];

            // Always preserve time=0 metadata
            if ($time === 0) {
                $new[] = $msgStr;
                continue;
            }

            // Keep messages inside window
            if ($time >= $from && ($to === false || $time <= $to)) {
                $msg[0] = $time - $from;
                $new[] = implode(' ', $msg);
            }
        }

        // Add TrkEnd at end if $to specified
        if ($to !== false) {
            $new[] = ($to - $from) . ' Meta TrkEnd';
        }

        $this->tracks[$tn] = $new;
    }

    /**
     * Convert timestamp (ticks) to seconds
     */
    public function timestamp2seconds(int $ts): float
    {
        return $ts * ($this->getTempo() / $this->getTimebase()) / 1_000_000;
    }

    /**
     * Convert seconds to timestamp (ticks)
     */
    public function seconds2timestamp(float $sec): int
    {
        return (int) round($sec * 1_000_000 * $this->getTimebase() / $this->getTempo());
    }
}
