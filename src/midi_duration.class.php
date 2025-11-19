<?php
//require('midi.class.php');

/**
 * Class MidiDuration
 *
 * Last changes:
 * 2010-08-09 improved getDuration, now also handles tempo changes
 *
 * @author Valentin Schmidt
 * @version 0.2
 */

class MidiDuration extends Midi
{
    /**
     * Returns duration in seconds
     */
    public function getDuration(): float
    {
        // Geen tracks / geen track 0 → duur 0
        if (empty($this->tracks) || !isset($this->tracks[0])) {
            return 0.0;
        }

        $duration      = 0.0;
        $currentTempo  = 0;    // µs per quarter note
        $t             = 0;    // laatste tempo-tijd in ticks
        $track0        = $this->tracks[0];
        $messageCount0 = count($track0);

        // factor: ticks * tempo(µs/quarter) → seconden
        // f = 1 / timebase / 1_000_000
        $f = 1 / $this->getTimebase() / 1_000_000;

        // 1) Tempo-wissels in track 0 langs
        for ($i = 0; $i < $messageCount0; $i++) {
            $msg = explode(' ', $track0[$i]);

            if (!isset($msg[1]) || $msg[1] !== 'Tempo') {
                continue;
            }

            $msgTime = (int) $msg[0];   // tijd in ticks

            // tijd sinds vorige tempo-event
            $dt = $msgTime - $t;

            // Alleen optellen als we al een tempo kennen
            if ($currentTempo > 0 && $dt > 0) {
                $duration += $dt * $currentTempo * $f;
            }

            $t            = $msgTime;
            $currentTempo = (int) $msg[2]; // µs per quarter note
        }

        // 2) Vind de tijd van de laatste event in alle tracks
        $endTime = $t;
        foreach ($this->tracks as $track) {
            if (empty($track)) {
                continue;
            }
            $lastMsg  = explode(' ', $track[count($track) - 1]);
            $endTime  = max($endTime, (int) $lastMsg[0]);
        }

        // 3) Laatste stuk (na laatste tempo-event tot eind van song)
        if ($endTime > $t && $currentTempo > 0) {
            $dt        = $endTime - $t;
            $duration += $dt * $currentTempo * $f;
        }

        return $duration;
    }
}
