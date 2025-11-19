<?php

class MidiConversion extends Midi
{
    //---------------------------------------------------------------
    // converts midi file (typically type 1, meerdere tracks) naar type 0 (enkele track)
    //---------------------------------------------------------------
    public function convertToType0(): void
    {
        // Als er al maar één track is, alleen type forceren en klaar
        if (empty($this->tracks) || count($this->tracks) < 2) {
            $this->type = 0;
            return;
        }

        $singleTrack = array();

        foreach ($this->tracks as $track) {
            if (empty($track)) {
                continue;
            }

            // Laatste event bekijken – vaak "Meta TrkEnd"
            $lastIndex = count($track) - 1;
            $lastMsg   = explode(' ', $track[$lastIndex]);

            if (isset($lastMsg[1], $lastMsg[2]) &&
                $lastMsg[1] === 'Meta' &&
                $lastMsg[2] === 'TrkEnd'
            ) {
                // verwijder TrkEnd uit deze track
                array_pop($track);
            }

            // Bij elkaar voegen
            $singleTrack = array_merge($singleTrack, $track);
        }

        // Alles sorteren op tijd (eerste veld van de string)
        usort(
            $singleTrack,
            static function (string $a, string $b): int {
                $ta = (int) strtok($a, ' ');
                $tb = (int) strtok($b, ' ');
                return $ta <=> $tb;
            }
        );

        // Als er geen events over zijn, zet we alsnog een lege track
        if (empty($singleTrack)) {
            $this->tracks = array(array());
            $this->type   = 0;
            return;
        }

        // Eindtijd van laatste event
        $lastLine  = $singleTrack[count($singleTrack) - 1];
        $endTime   = (int) strtok($lastLine, ' ');
        $singleTrack[] = $endTime . ' Meta TrkEnd';

        // Vervang alle tracks door één gecombineerde track
        $this->tracks = array($singleTrack);
        $this->type   = 0;
    }
}


// TEST:
// $midi = new MidiConversion();
// $midi->importMid($file);
// $midi->convertToType0();
// $midi->downloadMidFile('converted.mid');
// exit();

?>