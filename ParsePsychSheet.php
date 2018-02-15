<?php
include "PDF2Text.php";

class ParsePsychSheet {

    private $circleheats;
    private $lanes;
    private $file;
    private $minimumheatsize = 3;

    public function __construct($file, $circleheats, $lanes) {
        $this->file = $file;
        $this->circleheats = $circleheats;
        $this->lanes = $lanes;
    }
    
    public function parse() {

        if ($handle = fopen($this->file, 'r')) {
            
            $outputhandle = fopen($this->file.'.output', 'w');
            $currentEvent = null;
            $entries = array();
            while (($line = fgets($handle)) !== false) {
                if (strpos($line, "Event ") === 0) {
                    if ($entries) {
                        fwrite($outputhandle, "\n\n".$currentEvent."\n");
                        $this->output($this->processEntries($currentEvent, $entries), $outputhandle);
                        $entries = array();
                    }
                    $currentEvent = $line;
                } else if (preg_match('/^\d+ (.+)/', $line, $matches)) {
                    $entries[] = $matches[1];
                }
            }
            fwrite($outputhandle, "\n\n".$currentEvent."\n");
            $this->output($this->processEntries($currentEvent, $entries), $outputhandle);
            fclose($handle);
            fclose($outputhandle);
        }
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

        if (strpos($event, " Diving")) {
            return $entries;            
        }
        
        $heats = array_fill(0, count($entries) % $this->lanes, array());
        $heat = null;
        $i = 0;
        $circleheatoffset = 0;

        if ((count($entries) > $this->lanes * $this->circleheats) &&
            (count($entries) % $this->lanes < $this->minimumheatsize)) {
            $circleheatoffset = $this->minimumheatsize - count($entries) % $this->lanes;
        }
        var_dump($circleheatoffset);
        foreach ($entries as $entry) {
            //1/2/3 (1) 4/5/6 (2) 7/8/9 (3)

            if ($i < $this->lanes * $this->circleheats - $circleheatoffset) {
                $index = $i % $this->circleheats;
                $seed = (int)($i / $this->circleheats) + 1;
            } else {
                $index = (($i + $circleheatoffset) / $this->lanes);
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

$pps = new ParsePsychSheet($file, $circleheats, $lanes);
$pps->parse();

?>
