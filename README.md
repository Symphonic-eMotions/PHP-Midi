# PHP-Midi

A modernized and extended fork of the original PHP MIDI library created by  
**Valentin Schmidt** (http://valentin.dasdeck.com/php/midi/)  
Forked at version **1.7.8**, now updated for **PHP 8.1+**, with strict typing, PSR-12 compatibility, Composer support, and several helper classes.

This package allows you to:

- Read Standard MIDI Files (SMF type 0 & 1)
- Extract tempo, BPM, timing, duration and track information
- Convert MIDI to text format (mf2t-style)
- Convert text back to MIDI
- Convert MIDI to MusicXML (subset)
- Manipulate tracks, events, timing, and metadata
- Apply transpose, trimming, channel volume changes
- Re-export a MIDI file as valid binary `.mid`

Ideal for **music analysis**, **DAW tools**, **web dashboards**, **generative music systems**, and **Symfony/PHP backend processing**.

---

## 🎵 Features

### ✔ MIDI Import & Export
- `importMid()` — read .mid files (Type 0 & 1)
- `getMid()` — create binary MIDI output
- `saveMidFile()` — save `.mid` to disk
- `getTxt()` — export to mf2t-style text
- `importTxt()` — parse same format back

### ✔ Metadata Extraction
- Tempo (µs/quarter)
- BPM calculation
- Timebase (ticks per quarter note)
- Track count
- KeySig, TimeSig, SMPTE, Meta events

### ✔ Song Manipulation
- Transpose (whole song or per track)
- Insert or delete MIDI messages
- Trim songs by timestamp
- Convert Type-1 → Type-0
- Modify channel volumes

### ✔ Helpers Included
- `MidiDuration` – calculate duration in seconds
- `MidiTrim` – trim regions from a MIDI
- `MidiConversion` – merge tracks to Type-0
- `MidiVolume` – set channel volumes
- Fully modern PHP-8 syntax

---

## 📦 Installation

You can install this package directly from GitHub using Composer:

### **1. Add repository to your project (if not using Packagist)**

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/Symphonic-eMotions/PHP-Midi.git"
        }
    ]
}
```
### 2. Require it
```
composer require symphonic-emotions/php-midi:dev-master
```
The library will now be available via:
```
vendor/symphonic-emotions/php-midi/
```

### 🚀 Quick Usage Example
```
<?php
use Midi;
use MidiDuration;

$midi = new Midi();
$midi->importMid('example.mid');

echo 'Tempo: ' . $midi->getTempo() . PHP_EOL;
echo 'BPM: ' . $midi->getBpm() . PHP_EOL;
echo 'Timebase: ' . $midi->getTimebase() . PHP_EOL;
echo 'Tracks: ' . $midi->getTrackCount() . PHP_EOL;

$duration = new MidiDuration();
$duration->importMid('example.mid');

echo 'Duration: ' . $duration->getDuration() . ' sec' . PHP_EOL;
```

Forked from http://valentin.dasdeck.com/php/midi/ with permission from Valentin Schmidt. The fork was made at version 1.7.8.

## Licence

```txt
            DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
                    Version 2, December 2004

 Copyright (C) 2013 Valentin Schmidt <fluxus at freenet dot de>

 Everyone is permitted to copy and distribute verbatim or modified
 copies of this license document, and changing it is allowed as long
 as the name is changed.

            DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
   TERMS AND CONDITIONS FOR COPYING, DISTRIBUTION AND MODIFICATION

  0. You just DO WHAT THE FUCK YOU WANT TO.
```
