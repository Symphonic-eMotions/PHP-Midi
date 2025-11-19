<?php

require __DIR__ . '/src/midi.class.php';
require __DIR__ . '/src/midi_duration.class.php';
require __DIR__ . '/src/midi_conversion.class.php';

echo "<pre>";

// 1. Basis: een bestaand MIDI-bestand inladen
$sourceMid = __DIR__ . '/moon-left.mid';
if (!file_exists($sourceMid)) {
    echo "Bron-MIDI niet gevonden: $sourceMid\n";
    exit(1);
}

echo "== Stap 1: importMid ==\n";
$midi = new Midi();              // constructor
$midi->importMid($sourceMid);    // importeer standaard MIDI

echo "Tempo (µs/quarter): " . $midi->getTempo() . "\n";
echo "BPM: " . $midi->getBpm() . "\n";
echo "Timebase: " . $midi->getTimebase() . "\n";
echo "Tracks: " . $midi->getTrackCount() . "\n\n";

// 2. Tempo / BPM / Timebase setters + getters
echo "== Stap 2: setTempo / getTempo / setBpm / getBpm / setTimebase / getTimebase ==\n";

$midi->setTempo(500000); // ~120 bpm
echo "Na setTempo(500000) => Tempo: " . $midi->getTempo() . ", BPM: " . $midi->getBpm() . "\n";

$midi->setBpm(90);
echo "Na setBpm(90) => Tempo: " . $midi->getTempo() . ", BPM: " . $midi->getBpm() . "\n";

$midi->setTimebase(960);
echo "Na setTimebase(960) => Timebase: " . $midi->getTimebase() . "\n\n";

// 3. Tracks toevoegen/bewerken: newTrack, addMsg, insertMsg, getTrack, getMsgCount, getMsg, deleteMsg, deleteTrack, soloTrack
echo "== Stap 3: Track-bewerking ==\n";

// Nieuwe lege song om het schoon te houden
$m2 = new Midi();
$m2->open(480);

echo "Na open(480): Timebase = " . $m2->getTimebase() . ", Tracks = " . $m2->getTrackCount() . "\n";

// Nieuwe track aanmaken
$trackIndex = $m2->newTrack() - 1; // newTrack() geeft count; index is count-1
echo "newTrack() => trackIndex = $trackIndex, totaal tracks = " . $m2->getTrackCount() . "\n";

// Een paar berichten toevoegen (absolute tijd)
$m2->addMsg($trackIndex, "0 PrCh ch=1 p=1");
$m2->addMsg($trackIndex, "0 On ch=1 n=60 v=100");
$m2->addMsg($trackIndex, "480 Off ch=1 n=60 v=0");

// insertMsg: een note-on eerder erin stoppen
$m2->insertMsg($trackIndex, "240 On ch=1 n=67 v=100");

$track = $m2->getTrack($trackIndex);
echo "Track $trackIndex heeft " . $m2->getMsgCount($trackIndex) . " berichten.\n";
echo "Eerste bericht via getMsg(): " . $m2->getMsg($trackIndex, 0) . "\n";

// deleteMsg: verwijder 2e bericht
$m2->deleteMsg($trackIndex, 1);
echo "Na deleteMsg(1) => MsgCount: " . $m2->getMsgCount($trackIndex) . "\n";

// Nog een track toevoegen, dan soloTrack en deleteTrack proberen
$otherIndex = $m2->newTrack() - 1;
$m2->addMsg($otherIndex, "0 Meta TrkName \"Extra Track\"");
echo "Na extra track => Tracks: " . $m2->getTrackCount() . "\n";

$m2->soloTrack($trackIndex);
echo "Na soloTrack($trackIndex) => Tracks: " . $m2->getTrackCount() . "\n";

$remainingTrackCount = $m2->deleteTrack(0);
echo "Na deleteTrack(0) => Tracks: $remainingTrackCount\n\n";

// 4. Transpose / transposeTrack
echo "== Stap 4: transpose / transposeTrack ==\n";
$m3 = new Midi();
$m3->open(480);
$ti = $m3->newTrack() - 1;
$m3->addMsg($ti, "0 On ch=1 n=60 v=100");
$m3->addMsg($ti, "480 Off ch=1 n=60 v=0");

echo "Originele track: \n";
print_r($m3->getTrack($ti));

$m3->transposeTrack($ti, 2);
echo "\nNa transposeTrack(+2): \n";
print_r($m3->getTrack($ti));

$m3->transpose(-2);
echo "\nNa transpose(-2) (song-breed): \n";
print_r($m3->getTrack($ti));
echo "\n";

// 5. getTxt / getTrackTxt / importTxt / importTrackTxt
echo "== Stap 5: getTxt / getTrackTxt / importTxt / importTrackTxt ==\n";

$txt = $midi->getTxt(0); // absolute tijden
echo "Volledige song als tekst (eerste 10 regels):\n";
$lines = explode("\n", $txt);
foreach (array_slice($lines, 0, 10) as $l) {
    echo $l . "\n";
}

$track0Txt = $midi->getTrackTxt(0, 0);
echo "\nTrack 0 tekst (eerste 10 regels):\n";
$tLines = explode("\n", $track0Txt);
foreach (array_slice($tLines, 0, 10) as $l) {
    echo $l . "\n";
}

// importTxt op nieuwe instantie
$m4 = new Midi();
$m4->importTxt($txt);
echo "\nNa importTxt() => Tracks: " . $m4->getTrackCount() . ", Timebase: " . $m4->getTimebase() . "\n";

// importTrackTxt op nieuwe instantie
$m5 = new Midi();
$m5->open(480);
$m5->importTrackTxt($track0Txt, 0);
echo "Na importTrackTxt(track0, 0) => Tracks: " . $m5->getTrackCount() . ", Msgs in track 0: " . $m5->getMsgCount(0) . "\n\n";

// 6. getXml / importXml
echo "== Stap 6: getXml / importXml ==\n";
$xml = $midi->getXml(0);
echo "XML-preview (eerste 20 regels):\n";
$xmlLines = explode("\n", $xml);
foreach (array_slice($xmlLines, 0, 20) as $l) {
    echo $l . "\n";
}

$m6 = new Midi();
$m6->importXml($xml);
echo "\nNa importXml() => Tracks: " . $m6->getTrackCount() . ", Timebase: " . $m6->getTimebase() . "\n\n";

// 7. getMid / saveMidFile
echo "== Stap 7: getMid / saveMidFile ==\n";

$bin = $midi->getMid();
echo "Binary MIDI lengte (bytes) via getMid(): " . strlen($bin) . "\n";

$outFile = __DIR__ . '/output-test.mid';
$midi->saveMidFile($outFile);
echo "saveMidFile() geschreven naar: $outFile\n\n";

echo "Deze methodes zijn alleen in commentaar gezet omdat ze de uitvoer/headers beïnvloeden.\n\n";

// 9. Utility-lijsten: getInstrumentList / getDrumset / getDrumkitList / getNoteList
echo "== Stap 9: Utility-methoden ==\n";

$instrumentList = $midi->getInstrumentList();
echo "getInstrumentList(): " . count($instrumentList) . " instrumenten.\n";
echo "  Eerste 5: " . implode(', ', array_slice($instrumentList, 0, 5)) . "\n";

$drumset = $midi->getDrumset();
echo "getDrumset(): " . count($drumset) . " drum-notes.\n";
echo "  Enkele entries:\n";
foreach (array_slice($drumset, 0, 5, true) as $note => $name) {
    echo "    $note => $name\n";
}

$drumkits = $midi->getDrumkitList();
echo "getDrumkitList(): " . count($drumkits) . " kits.\n";
echo "getDrumkitList(): " . count($drumkits) . " kits.\n";
foreach ($drumkits as $prog => $name) {
    echo "    Program $prog => $name\n";
}

$noteList = $midi->getNoteList();
echo "getNoteList(): " . count($noteList) . " noten.\n";
echo "  Eerste 12: " . implode(', ', array_slice($noteList, 0, 12)) . "\n\n";

echo "== Extra: MidiDuration ==\n";

$durationMidi = new MidiDuration();
$durationMidi->importMid($sourceMid);

$durationSeconds = $durationMidi->getDuration();
$durationRounded = round($durationSeconds, 2);

echo "Duur (seconden): " . $durationRounded . "\n";
echo "Duur (mm:ss): " . gmdate('i:s', (int) round($durationSeconds)) . "\n\n";

echo "\n== Extra: MidiConversion ==\n";

$convMidi = new MidiConversion();
$convMidi->importMid($sourceMid);

// Voor / na track-aantallen bekijken
$tracksBefore = $convMidi->getTrackCount();
$convMidi->convertToType0();
$tracksAfter  = $convMidi->getTrackCount();

echo "Tracks vóór convertToType0(): $tracksBefore\n";
echo "Tracks ná  convertToType0(): $tracksAfter\n";

// Kleine preview van de geconverteerde type-0 tekst
$type0Txt = $convMidi->getTxt(0);
$type0Lines = explode("\n", $type0Txt);

echo "Type-0 tekst (eerste 10 regels):\n";
foreach (array_slice($type0Lines, 0, 10) as $l) {
    echo $l . "\n";
}


echo "</pre>";
