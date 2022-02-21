<?php
//include "PDF2Text.php";

class ParsePsychSheet {

    private $circleheats;
    private $lanes;
    private $file;
    private $minimumheatsize = 3;
    private $cap;

    public function __construct($file, $circleheats, $lanes, $cap) {
        $this->file = $file;
        $this->circleheats = $circleheats;
        $this->lanes = $lanes;
        $this->cap = $cap;
    }
    
    public function parse() {

        if ($handle = fopen($this->file, 'r')) {
            
            $outputhandle = fopen($this->file.'.output', 'w');
            $currentEvent = null;
            $entries = array();
            while (($line = fgets($handle)) !== false) {
                if (strpos(trim($line), "Event ") === 0) {
                    $timecount = 0;
                    $schoolcount = 0;
                    if ($entries) {
                        fwrite($outputhandle, "\n\n".$currentEvent."\n");
                        $this->output($this->processEntries($currentEvent, $entries), $outputhandle);
                        $entries = array();
                    }
                    $currentEvent = $line;
                } else if (preg_match('/^\s*\d\d?\s+([\w ,\-\':\.]+)/', $line, $matches)) {
                    $entries[] = trim(preg_replace('/\s+/', " ", $matches[1]));
                } else if (count($entries) > 0) {
                    if (preg_match('/^\d*:?\d+\.\d+#*/', $line, $matches)) {
                        $fudge = strpos($currentEvent, " Diving") ? (strlen("".($timecount + 1)) - 1) : 0;
                        $padding = $this->createblanks(50 - strlen($entries[$timecount]) - strlen($matches[0]) - $fudge);
                        $entries[$timecount] .= "$padding $matches[0] _______________";
                        $timecount++;
                    } else if (preg_match('/^([\w ]+)\s+NT/', $line, $matches)) {
                        //adaptive
                        $entries[$schoolcount] .= ", " . trim($matches[1]);
                        $padding = $this->createblanks(50 - strlen($entries[$schoolcount]) - strlen("NT"));
                        $entries[$schoolcount] .= "$padding NT _______________";
                        $timecount++;
                        $schoolcount++;
                    } else if ($timecount == 0 && preg_match('/^[\w ]+$/', $line) && strlen(trim($line)) > 0) {
                        $schools = $this->getschools(trim($line));
                        foreach ($schools as $school) {
                            $entries[$schoolcount] .= ", $school";
                            $schoolcount++;
                        }
                    }
                } 
            }
            fwrite($outputhandle, "\n\n".$currentEvent."\n");
            $this->output($this->processEntries($currentEvent, $entries), $outputhandle);
            fclose($handle);
            fclose($outputhandle);
        }
    }

    function createblanks($len) {
        $ret = "";
        while ($len-- >= 0) {
            $ret .= " ";
        }
        return $ret;
    }

    function getschools($line) {
        $doubles = ["Mount", "Moses", "Battle", "Kennedy", "Glacier", "Lake", "South", "North"];
        $words = preg_split('/\s+/', trim($line));
        $ret = [];
        $tmp = "";
        foreach ($words as $word) {
            if (!$tmp && in_array($word, $doubles)) {
                $tmp = $word;
            } else {
                $ret[] = trim("$tmp $word");
                $tmp = "";
            }
        }
        return $ret;
    }
 
    function output($entries, $handle) {
        $i = 0;
        foreach ($entries as $entry) {
            $i++;
            if (is_array($entry)) {
                fwrite($handle, "Heat $i\n");                
                $this->output($entry, $handle);
            } else {
                fwrite($handle, "$i. $entry\n");
            }
        }
    }
    
    function processEntries($event, $entries) {

        if (count($entries) > $this->cap) {
            $entries = array_slice($entries, 0, $this->cap);
        }

        if (strpos($event, " Diving")) {
            return $entries;            
        }
        
        $heats = array_fill(0, ceil(count($entries) / $this->lanes), array());
        $heat = null;
        $i = 0;
        $circleheatoffset = 0;
        $event_circleheats = $this->circleheats <= count($heats) ? $this->circleheats : count($heats);
        if ((count($entries) > $this->lanes * $event_circleheats) &&
            (count($entries) % $this->lanes < $this->minimumheatsize)) {
            $circleheatoffset = $this->minimumheatsize - count($entries) % $this->lanes;
        }
        if ($event_circleheats == 0) {
            $circleheatoffset = 0;
        }
        foreach ($entries as $entry) {
            //1/2/3 (1) 4/5/6 (2) 7/8/9 (3)

            if ($i < $this->lanes * $event_circleheats - $circleheatoffset) {
                $index = $i % $event_circleheats;
                $seed = (int)($i / $event_circleheats) + 1;
            } else {
                $index = (int)(($i + $circleheatoffset) / $this->lanes);
                $seed = ($i + $circleheatoffset) % $this->lanes + 1;
            }
            $lane = (int)($this->lanes / 2) + ($seed % 2 ? -1 : 1) * (int)(($seed) / 2);
            if (!$heats[$index]) {
                $heats[$index] = array_fill(1, $this->lanes, null);
            }
            
            $heats[$index][$lane] = $entry;
            $i++;
        }
        return array_reverse($heats);
    }

}

$file = $argv[1];
$circleheats = (isset($argv[2]) ? $argv[2] : 3);
$lanes = (isset($argv[3]) ? $argv[3] : 8);
$cap = (isset($argv[4]) ? $argv[4] : 100);

$pps = new ParsePsychSheet($file, $circleheats, $lanes, $cap);
$pps->parse();

?>
