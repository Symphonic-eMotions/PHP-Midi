<?php

declare(strict_types=1);

class MidiVolume extends Midi
{
    /**
     * Set volume for all channels at time=0
     */
    public function setGlobalVolume(int $vol, bool $removeAll = true): void
    {
        $vol = max(0, min(127, $vol));

        if ($removeAll) {
            foreach ($this->tracks as $ti => $track) {
                foreach (array_reverse($track, true) as $mi => $msgStr) {
                    $msg = explode(' ', $msgStr);
                    if ($msg[1] === 'Par' && $msg[3] === 'c=7') {
                        array_splice($this->tracks[$ti], $mi, 1);
                    }
                }
            }
        }

        // Insert into track 0
        $insertIndex = 0;
        $track0 = $this->tracks[0];
        $count = count($track0);

        while ($insertIndex < $count) {
            $msg = explode(' ', $track0[$insertIndex]);
            if ((int)$msg[0] !== 0 || $msg[1] === 'On' || $msg[1] === 'Meta') {
                break;
            }
            $insertIndex++;
        }

        // Add controllers for ch 1..16
        $newMessages = [];
        for ($ch = 1; $ch <= 16; $ch++) {
            $newMessages[] = "0 Par ch=$ch c=7 v=$vol";
        }

        array_splice($this->tracks[0], $insertIndex, 0, $newMessages);
    }

    /**
     * Set volume for one MIDI channel
     */
    public function setChannelVolume(int $chan, int $vol): void
    {
        $chan = max(1, min(16, $chan));
        $vol  = max(0, min(127, $vol));

        $i = 0;
        $track0 = &$this->tracks[0];
        $cnt = count($track0);

        while ($i < $cnt) {
            $msg = explode(' ', $track0[$i]);
            if ((int)$msg[0] !== 0 || $msg[1] === 'On') {
                break;
            }
            if ($msg[1] === 'Par' && $msg[2] === "ch=$chan" && $msg[3] === 'c=7') {
                array_splice($track0, $i, 1);
                $cnt--;
            } else {
                $i++;
            }
        }

        $msg = "0 Par ch=$chan c=7 v=$vol";
        array_splice($track0, $i, 0, $msg);
    }

    /**
     * Read all volume controllers at time=0 in track 0
     */
    public function getVolumes(): array
    {
        $volumes = [];
        $i = 0;

        foreach ($this->tracks[0] as $msgStr) {
            $msg = explode(' ', $msgStr);
            if ((int)$msg[0] !== 0) {
                break;
            }

            if ($msg[1] === 'Par' && $msg[3] === 'c=7') {
                // msg[2] = 'ch=5'
                $chan = (int)substr($msg[2], 3);
                // msg[4] = 'v=96'
                $v = (int)substr($msg[4], 2);
                $volumes[$chan] = $v;
            }
            $i++;
        }

        return $volumes;
    }
}
