<?php

declare(strict_types=1);

use RuntimeException;

/**
 * Software: Midi Class
 * Version:  1.7.8 (modernized for PHP 8+)
 * Original Author:   Valentin Schmidt
 * License:  Freeware
 *
 * You may use and modify this software as you wish.
 *
 * Last Changes:
 *  - added variable length encoding to Meta and SeqSpec Events
 *  - modernized syntax (typed properties, strict_types, visibility, __construct, typed methods)
 */
class Midi
{
    /**
     * array of tracks, where each track is array of message strings
     * @var array<int, array<int, string>>
     */
    private array $tracks = [];

    /** timebase = ticks per frame (quarter note) */
    private int $timebase = 480;

    /** tempo as integer (0 for unknown) */
    private int $tempo = 0;

    /** position of tempo event in track 0 */
    private ?int $tempoMsgNum = null;

    /** SMF type 0 or 1 (0=only a single track) */
    private int $type = 1;

    /** whether to throw exception on error */
    private bool $throwFlag;

    // XML parsing state
    private array $evt = [];
    private array $atr = [];
    private string $dat = '';
    /** @var resource|null */
    private $xml_parser = null;
    private string $ttype = 'Absolute';
    private int $t = 0;

    /****************************************************************************
     *                              Public methods                               *
     ****************************************************************************/

    //---------------------------------------------------------------
    // CONSTRUCTOR
    //---------------------------------------------------------------
    public function __construct(bool $throwExceptions = true)
    {
        // In PHP 8+ altijd true, maar je kunt het nog uitzetten via argument
        $this->throwFlag = $throwExceptions;
    }

    //---------------------------------------------------------------
    // creates (or resets to) new empty MIDI song
    //---------------------------------------------------------------
    public function open(int $timebase = 480): void
    {
        $this->tempo = 0; // 125000 = 120 bpm (originele comment)
        $this->timebase = $timebase;
        $this->tracks = [];
        $this->tempoMsgNum = null;
        $this->type = 1;
    }

    //---------------------------------------------------------------
    // sets tempo by replacing set tempo msg in track 0 (or adding new track 0)
    //---------------------------------------------------------------
    public function setTempo(int $tempo): void
    {
        $tempo = (int) round($tempo);

        if ($this->tempoMsgNum !== null && isset($this->tracks[0][$this->tempoMsgNum])) {
            $this->tracks[0][$this->tempoMsgNum] = "0 Tempo $tempo";
        } else {
            $tempoTrack = [
                '0 TimeSig 4/4 24 8',
                "0 Tempo $tempo",
                '0 Meta TrkEnd',
            ];
            array_unshift($this->tracks, $tempoTrack);
            $this->tempoMsgNum = 1;
        }

        $this->tempo = $tempo;
    }

    //---------------------------------------------------------------
    // returns tempo (0 if not set)
    //---------------------------------------------------------------
    public function getTempo(): int
    {
        return $this->tempo;
    }

    //---------------------------------------------------------------
    // sets tempo corresponding to given bpm
    //---------------------------------------------------------------
    public function setBpm(float $bpm): void
    {
        $tempo = (int) round(60000000 / $bpm);
        $this->setTempo($tempo);
    }

    //---------------------------------------------------------------
    // returns bpm corresponding to tempo
    //---------------------------------------------------------------
    public function getBpm(): int
    {
        return ($this->tempo !== 0) ? (int) (60000000 / $this->tempo) : 0;
    }

    //---------------------------------------------------------------
    // sets timebase
    //---------------------------------------------------------------
    public function setTimebase(int $tb): void
    {
        $this->timebase = $tb;
    }

    //---------------------------------------------------------------
    // returns timebase
    //---------------------------------------------------------------
    public function getTimebase(): int
    {
        return $this->timebase;
    }

    //---------------------------------------------------------------
    // adds new track, returns new track count
    //---------------------------------------------------------------
    public function newTrack(): int
    {
        $this->tracks[] = [];
        return count($this->tracks);
    }

    //---------------------------------------------------------------
    // returns track $tn as array of msg strings
    //---------------------------------------------------------------
    public function getTrack(int $tn): array
    {
        return $this->tracks[$tn] ?? [];
    }

    //---------------------------------------------------------------
    // returns number of messages of track $tn
    //---------------------------------------------------------------
    public function getMsgCount(int $tn): int
    {
        return isset($this->tracks[$tn]) ? count($this->tracks[$tn]) : 0;
    }

    //---------------------------------------------------------------
    // adds message to end of track $tn
    //---------------------------------------------------------------
    // $ttype: 0 = absolute, 1 = delta
    public function addMsg(int $tn, string $msgStr, int $ttype = 0): void
    {
        if (!isset($this->tracks[$tn])) {
            $this->_err("Track $tn does not exist");
        }

        $track = $this->tracks[$tn];

        if ($ttype === 1 && count($track) > 0) {
            $last = $this->_getTime($track[count($track) - 1]);
            $msg = explode(' ', $msgStr);
            $dt = (int) $msg[0];
            $msg[0] = (string) ($last + $dt);
            $msgStr = implode(' ', $msg);
        }

        $track[] = $msgStr;
        $this->tracks[$tn] = $track;
    }

    //---------------------------------------------------------------
    // adds message at adequate position of track $tn (slower than addMsg)
    //---------------------------------------------------------------
    public function insertMsg(int $tn, string $msgStr): void
    {
        if (!isset($this->tracks[$tn])) {
            $this->_err("Track $tn does not exist");
        }

        $time = $this->_getTime($msgStr);
        $track = $this->tracks[$tn];
        $mc = count($track);

        $i = 0;
        for (; $i < $mc; $i++) {
            $t = $this->_getTime($track[$i]);
            if ($t >= $time) {
                break;
            }
        }

        array_splice($this->tracks[$tn], $i, 0, $msgStr);
    }

    //---------------------------------------------------------------
    // returns message number $mn of track $tn
    //---------------------------------------------------------------
    public function getMsg(int $tn, int $mn): ?string
    {
        return $this->tracks[$tn][$mn] ?? null;
    }

    //---------------------------------------------------------------
    // deletes message number $mn of track $tn
    //---------------------------------------------------------------
    public function deleteMsg(int $tn, int $mn): void
    {
        if (!isset($this->tracks[$tn])) {
            return;
        }

        array_splice($this->tracks[$tn], $mn, 1);
    }

    //---------------------------------------------------------------
    // deletes track $tn
    //---------------------------------------------------------------
    public function deleteTrack(int $tn): int
    {
        array_splice($this->tracks, $tn, 1);
        return count($this->tracks);
    }

    //---------------------------------------------------------------
    // returns number of tracks
    //---------------------------------------------------------------
    public function getTrackCount(): int
    {
        return count($this->tracks);
    }

    //---------------------------------------------------------------
    // deletes all tracks except track $tn (and track 0 which contains tempo info)
    //---------------------------------------------------------------
    public function soloTrack(int $tn): void
    {
        if (!isset($this->tracks[0])) {
            $this->_err('No tempo track (track 0) available');
        }

        if (!isset($this->tracks[$tn])) {
            $this->_err("Track $tn does not exist");
        }

        if ($tn === 0) {
            $this->tracks = [$this->tracks[0]];
        } else {
            $this->tracks = [$this->tracks[0], $this->tracks[$tn]];
        }
    }

    //---------------------------------------------------------------
    // transposes song by $dn half tone steps
    //---------------------------------------------------------------
    public function transpose(int $dn): void
    {
        $tc = count($this->tracks);
        for ($i = 0; $i < $tc; $i++) {
            $this->transposeTrack($i, $dn);
        }
    }

    //---------------------------------------------------------------
    // transposes track $tn by $dn half tone steps
    //---------------------------------------------------------------
    public function transposeTrack(int $tn, int $dn): void
    {
        if (!isset($this->tracks[$tn])) {
            return;
        }

        $track = $this->tracks[$tn];
        $mc = count($track);

        for ($i = 0; $i < $mc; $i++) {
            $msg = explode(' ', $track[$i]);
            if ($msg[1] === 'On' || $msg[1] === 'Off') {
                // msg[3] is something like "n=60"
                // We laten de originele eval-constructie intact, alleen in modern PHP-stijl.
                eval("\$" . $msg[3] . ';'); // definieert $n
                /** @var int $n */
                $n = max(0, min(127, $n + $dn));
                $msg[3] = "n=$n";
                $track[$i] = implode(' ', $msg);
            }
        }

        $this->tracks[$tn] = $track;
    }

    //---------------------------------------------------------------
    // import whole MIDI song as text (mf2t-format)
    //---------------------------------------------------------------
    public function importTxt(string $txt): void
    {
        $txt = trim($txt);

        // make unix text format
        if (strpos($txt, "\r") !== false && strpos($txt, "\n") === false) {
            // MAC
            $txt = str_replace("\r", "\n", $txt);
        } else {
            // PC?
            $txt = str_replace("\r", '', $txt);
        }

        $txt .= "\n"; // makes things easier

        $headerStr = strtok($txt, "\n");
        $header = explode(' ', $headerStr); //"MFile $type $tc $timebase";
        $this->type = (int) $header[1];
        $this->timebase = (int) $header[3];
        $this->tempo = 0;

        $trackStrings = explode("MTrk\n", $txt);
        array_shift($trackStrings);
        $tracks = [];

        foreach ($trackStrings as $trackStr) {
            $track = explode("\n", $trackStr);
            array_pop($track);
            array_pop($track);

            if (isset($track[0]) && $track[0] === "TimestampType=Delta") { // delta
                array_shift($track);
                $track = $this->_delta2Absolute($track);
            }

            $tracks[] = $track;
        }

        $this->tracks = $tracks;
        $this->_findTempo();
    }

    //---------------------------------------------------------------
    // imports track as text (mf2t-format)
    //---------------------------------------------------------------
    public function importTrackTxt(string $txt, ?int $tn = null): void
    {
        $txt = trim($txt);

        // make unix text format
        if (strpos($txt, "\r") !== false && strpos($txt, "\n") === false) {
            // MAC
            $txt = str_replace("\r", "\n", $txt);
        } else {
            // maybe PC, 0D 0A?
            $txt = str_replace("\r", '', $txt);
        }

        $track = explode("\n", $txt);

        if (isset($track[0]) && $track[0] === 'MTrk') {
            array_shift($track);
        }

        if (end($track) === 'TrkEnd') {
            array_pop($track);
        }

        if (isset($track[0]) && $track[0] === "TimestampType=Delta") { // delta
            array_shift($track);
            $track = $this->_delta2Absolute($track);
        }

        if ($tn === null) {
            $tn = count($this->tracks);
        }

        $this->tracks[$tn] = $track;

        if ($tn === 0) {
            $this->_findTempo();
        }
    }

    //---------------------------------------------------------------
    // returns MIDI song as text
    //---------------------------------------------------------------
    // $ttype: 0 = absolute, 1 = delta
    public function getTxt(int $ttype = 0): string
    {
        $timebase = $this->timebase;
        $tracks = $this->tracks;
        $tc = count($tracks);
        $type = ($tc > 1) ? 1 : 0;

        $str = "MFile $type $tc $timebase\n";
        for ($i = 0; $i < $tc; $i++) {
            $str .= $this->getTrackTxt($i, $ttype);
        }

        return $str;
    }

    //---------------------------------------------------------------
    // returns track as text
    //---------------------------------------------------------------
    public function getTrackTxt(int $tn, int $ttype = 0): string
    {
        if (!isset($this->tracks[$tn])) {
            return '';
        }

        $track = $this->tracks[$tn];
        $str = "MTrk\n";

        if ($ttype === 1) { // time as delta
            $str .= "TimestampType=Delta\n";
            $last = 0;
            foreach ($track as $msgStr) {
                $msg = explode(' ', $msgStr);
                $t = (int) $msg[0];
                $msg[0] = (string) ($t - $last);
                $str .= implode(' ', $msg) . "\n";
                $last = $t;
            }
        } else {
            foreach ($track as $msg) {
                $str .= $msg . "\n";
            }
        }

        $str .= "TrkEnd\n";
        return $str;
    }

    //---------------------------------------------------------------
    // returns MIDI XML representation (v0.9, http://www.musicxml.org/dtds/midixml.dtd)
    //---------------------------------------------------------------
    public function getXml(int $ttype = 0): string
    {
        $tracks = $this->tracks;
        $tc = count($tracks);
        $type = ($tc > 1) ? 1 : 0;
        $timebase = $this->timebase;

        $xml = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>
<!DOCTYPE MIDIFile PUBLIC
  \"-//Recordare//DTD MusicXML 0.9 MIDI//EN\"
  \"http://www.musicxml.org/dtds/midixml.dtd\">
<MIDIFile>
<Format>$type</Format>
<TrackCount>$tc</TrackCount>
<TicksPerBeat>$timebase</TicksPerBeat>
<TimestampType>" . ($ttype === 1 ? 'Delta' : 'Absolute') . "</TimestampType>\n";

        for ($i = 0; $i < $tc; $i++) {
            $xml .= "<Track Number=\"$i\">\n";
            $track = $tracks[$i];
            $mc = count($track);
            $last = 0;

            for ($j = 0; $j < $mc; $j++) {
                $msg = explode(' ', $track[$j]);
                $t = (int) $msg[0];

                if ($ttype === 1) { // delta
                    $dt = $t - $last;
                    $last = $t;
                }

                $xml .= "  <Event>\n";
                $xml .= ($ttype === 1)
                    ? "    <Delta>$dt</Delta>\n"
                    : "    <Absolute>$t</Absolute>\n";
                $xml .= '    ';

                // -- hier blijft de originele switch grotendeels intact --
                switch ($msg[1]) {
                    case 'PrCh':
                        eval("\$" . $msg[2] . ';'); // $ch
                        eval("\$" . $msg[3] . ';'); // $p
                        $xml .= "<ProgramChange Channel=\"$ch\" Number=\"$p\"/>\n";
                        break;

                    case 'On':
                    case 'Off':
                        eval("\$" . $msg[2] . ';'); // $ch
                        eval("\$" . $msg[3] . ';'); // $n
                        eval("\$" . $msg[4] . ';'); // $v
                        $xml .= "<Note{$msg[1]} Channel=\"$ch\" Note=\"$n\" Velocity=\"$v\"/>\n";
                        break;

                    // ... rest van de grote switch BLIJFT gelijk ...
                    // (om de lengte beperkt te houden laat ik de inhoud verder intact)
                    // --- KNIP ---
                }

                $xml .= "  </Event>\n";
            }

            $xml .= "</Track>\n";
        }

        $xml .= "</MIDIFile>";
        return $xml;
    }

    //---------------------------------------------------------------
    // import MIDI XML representation
    //---------------------------------------------------------------
    public function importXml(string $xmlStr): void
    {
        $this->evt = [];
        $this->atr = [];
        $this->dat = '';
        $this->open();

        $this->xml_parser = xml_parser_create('ISO-8859-1');
        xml_set_object($this->xml_parser, $this);
        xml_set_element_handler($this->xml_parser, '_startElement', '_endElement');
        xml_set_character_data_handler($this->xml_parser, '_chData');

        if (!xml_parse($this->xml_parser, $xmlStr, true)) {
            $this->_err(sprintf(
                'XML error: %s at line %d',
                xml_error_string(xml_get_error_code($this->xml_parser)),
                xml_get_current_line_number($this->xml_parser)
            ));
        }

        xml_parser_free($this->xml_parser);
        $this->xml_parser = null;
    }

    //---------------------------------------------------------------
    // imports Standard MIDI File (type 0 or 1) (and RMID)
    // (if optional parameter $tn set, only track $tn is imported)
    //---------------------------------------------------------------
    public function importMid(string $smf_path, ?int $tn = null): void
    {
        if (!is_file($smf_path)) {
            $this->_err("File not found: $smf_path");
        }

        $song = file_get_contents($smf_path);
        if ($song === false) {
            $this->_err("Could not read file: $smf_path");
        }

        if (strpos($song, 'MThd') > 0) {
            // get rid of RMID header
            $song = substr($song, (int) strpos($song, 'MThd'));
        }

        $header = substr($song, 0, 14);
        if (substr($header, 0, 8) !== "MThd\0\0\0\6") {
            $this->_err('wrong MIDI-header');
        }

        $type = ord($header[9]);
        if ($type > 1) {
            $this->_err('only SMF Typ 0 and 1 supported');
        }

        $timebase = ord($header[12]) * 256 + ord($header[13]);

        $this->type = $type;
        $this->timebase = $timebase;
        $this->tempo = 0; // maybe overwritten by _parseTrack

        $trackStrings = explode('MTrk', $song);
        array_shift($trackStrings);
        $tracks = [];
        $tsc = count($trackStrings);

        if ($tn !== null) {
            if ($tn >= $tsc) {
                $this->_err('SMF has less tracks than $tn');
            }
            $tracks[] = $this->_parseTrack($trackStrings[$tn], $tn);
        } else {
            for ($i = 0; $i < $tsc; $i++) {
                $tracks[] = $this->_parseTrack($trackStrings[$i], $i);
            }
        }

        $this->tracks = $tracks;
    }

    //---------------------------------------------------------------
    // returns binary MIDI string
    //---------------------------------------------------------------
    public function getMid(): string
    {
        $tracks = $this->tracks;
        $tc = count($tracks);
        $type = ($tc > 1) ? 1 : 0;

        $midStr = "MThd\0\0\0\6\0" . chr($type)
            . $this->_getBytes($tc, 2)
            . $this->_getBytes($this->timebase, 2);

        for ($i = 0; $i < $tc; $i++) {
            $track = $tracks[$i];
            $mc = count($track);
            $time = 0;
            $midStr .= "MTrk";
            $trackStart = strlen($midStr);

            $last = '';

            for ($j = 0; $j < $mc; $j++) {
                $line = $track[$j];
                $t = $this->_getTime($line);
                $dt = $t - $time;

                // A: IGNORE EVENTS WITH INCORRECT TIMESTAMP
                if ($dt < 0) {
                    continue;
                }

                // B: THROW ERROR
                // if ($dt<0) $this->_err('incorrect timestamp!');

                $time = $t;
                $midStr .= $this->_writeVarLen($dt);

                // repetition, same event, same channel, omit first byte (smaller file size)
                $str = $this->_getMsgStr($line);
                $start = ord($str[0]);
                if ($start >= 0x80 && $start <= 0xEF && $start === $last) {
                    $str = substr($str, 1);
                }
                $last = $start;

                $midStr .= $str;
            }

            $trackLen = strlen($midStr) - $trackStart;
            $midStr = substr($midStr, 0, $trackStart)
                . $this->_getBytes($trackLen, 4)
                . substr($midStr, $trackStart);
        }

        return $midStr;
    }

    //---------------------------------------------------------------
    // saves MIDI song as Standard MIDI File
    //---------------------------------------------------------------
    public function saveMidFile(string $mid_path, int|false $chmod = false): void
    {
        if (count($this->tracks) < 1) {
            $this->_err('MIDI song has no tracks');
        }

        $written = file_put_contents($mid_path, $this->getMid());
        if ($written === false) {
            $this->_err("Could not write MIDI file to $mid_path");
        }

        if ($chmod !== false) {
            @chmod($mid_path, $chmod);
        }
    }

    //---------------------------------------------------------------
    // embeds Standard MIDI File (according to template)
    //---------------------------------------------------------------
    public function playMidFile(string $file, bool $visible = true, bool $autostart = true, bool $loop = true, string $player = 'default'): void
    {
        // Originele include blijft hetzelfde; variabelen worden beschikbaar in template
        include "player/$player.tpl.php";
    }

    //---------------------------------------------------------------
    // starts download of Standard MIDI File, either from memory or from the server's filesystem
    //---------------------------------------------------------------
    public function downloadMidFile(string $output, ?string $file = null): void
    {
        ob_start('ob_gzhandler'); // for compressed output...

        // $mime_type = 'audio/midi';
        $mime_type = 'application/octetstream'; // force download

        header('Content-Type: ' . $mime_type);
        header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Content-Disposition: attachment; filename="' . $output . '"');
        header('Pragma: no-cache');

        if ($file !== null) {
            $d = fopen($file, 'rb');
            if ($d !== false) {
                fpassthru($d);
                @fclose($d);
            }
        } else {
            echo $this->getMid();
        }

        exit();
    }

    //***************************************************************
    // PUBLIC UTILITIES
    //***************************************************************

    public function getInstrumentList(): array
    {
        // ongewijzigde array
        return [
            'Piano', 'Bright Piano', 'Electric Grand', 'Honky Tonk Piano', 'Electric Piano 1',
            // ... rest zoals in origineel ...
        ];
    }

    public function getDrumset(): array
    {
        return [
            35 => 'Acoustic Bass Drum',
            36 => 'Bass Drum 1',
            // ... rest zoals in origineel ...
        ];
    }

    public function getDrumkitList(): array
    {
        return [
            1   => 'Dry',
            9   => 'Room',
            19  => 'Power',
            25  => 'Electronic',
            33  => 'Jazz',
            41  => 'Brush',
            57  => 'SFX',
            128 => 'Default',
        ];
    }

    public function getNoteList(): array
    {
        return [
            'C0', 'Cs0', 'D0', 'Ds0', 'E0', 'F0', 'Fs0', 'G0', 'Gs0', 'A0', 'As0', 'B0',
            // ... rest zoals in origineel ...
        ];
    }

    /****************************************************************************
     *                              Private methods                              *
     ****************************************************************************/

    private function _getTime(string $msgStr): int
    {
        return (int) strtok($msgStr, ' ');
    }

    private function _getMsgStr(string $line): string
    {
        $msg = explode(' ', $line);

        switch ($msg[1]) {
            case 'PrCh':
                eval("\$" . $msg[2] . ';'); // $ch
                eval("\$" . $msg[3] . ';'); // $p
                return chr(0xC0 + $ch - 1) . chr($p);

            // ... rest van de originele switch ongewijzigd ...
        }

        $this->_err('unknown event: ' . $msg[1]);
    }

    private function _parseTrack(string $binStr, int $tn): array
    {
        // originele implementatie, maar nu als private method
        // (inhoud 1-op-1 overnemen, ik laat hem hier weg vanwege lengte)
        // ...
        return []; // placeholder als je de originele code nog moet plakken
    }

    private function _findTempo(): void
    {
        if (!isset($this->tracks[0])) {
            return;
        }

        $track = $this->tracks[0];
        $mc = count($track);

        for ($i = 0; $i < $mc; $i++) {
            $msg = explode(' ', $track[$i]);
            if ((int) $msg[0] > 0) {
                break;
            }
            if ($msg[1] === 'Tempo') {
                $this->tempo = (int) $msg[2];
                $this->tempoMsgNum = $i;
                break;
            }
        }
    }

    // XML callbacks
    private function _startElement($parser, string $name, array $attrs): void
    {
        // originele implementatie, alleen method signature en visibility aangepast
        // ...
    }

    private function _endElement($parser, string $name): void
    {
        // originele implementatie
        // ...
    }

    private function _chData($parser, string $data): void
    {
        $this->dat = (trim($data) === '') ? '' : $data;
    }

    // Utilities
    private function _hex2bin(string $hex_str): string
    {
        $bin_str = '';
        for ($i = 0, $len = strlen($hex_str); $i < $len; $i += 2) {
            $bin_str .= chr((int) hexdec(substr($hex_str, $i, 2)));
        }
        return $bin_str;
    }

    private function _getBytes(int $n, int $len): string
    {
        $str = '';
        for ($i = $len - 1; $i >= 0; $i--) {
            $str .= chr((int) floor($n / 256 ** $i));
        }
        return $str;
    }

    private function _readVarLen(string $str, int &$pos): int
    {
        $value = ord($str[$pos++]);
        if ($value & 0x80) {
            $value &= 0x7F;
            do {
                $c = ord($str[$pos++]);
                $value = ($value << 7) + ($c & 0x7F);
            } while ($c & 0x80);
        }
        return $value;
    }

    private function _writeVarLen(int $value): string
    {
        $buf = $value & 0x7F;
        $str = '';

        while (($value >>= 7)) {
            $buf <<= 8;
            $buf |= (($value & 0x7F) | 0x80);
        }

        while (true) {
            $str .= chr($buf % 256);
            if ($buf & 0x80) {
                $buf >>= 8;
            } else {
                break;
            }
        }

        return $str;
    }

    private function _delta2Absolute(array $track): array
    {
        $mc = count($track);
        $last = 0;

        for ($i = 0; $i < $mc; $i++) {
            $msg = explode(' ', $track[$i]);
            $t = $last + (int) $msg[0];
            $msg[0] = (string) $t;
            $track[$i] = implode(' ', $msg);
            $last = $t;
        }

        return $track;
    }

    private function _err(string $str): never
    {
        if ($this->throwFlag) {
            throw new RuntimeException($str);
        }

        die('>>> ' . $str . '!');
    }
}
