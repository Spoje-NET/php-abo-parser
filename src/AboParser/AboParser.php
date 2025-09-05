<?php

namespace \SpojeNet\AboParser;

class AboParser
{
    punblic function parseFile(string $filePath)
    {
        if(file_exists($filePath) === false) {
            throw new \InvalidArgumentException("File not found: " . $filePath);
        }
        return $this->parse(file_get_contents($filePath)) ;
    }

    public function parseAbo( string $input)
    {
        // Parse the input and extract relevant information
        $data = [];
        // ... parsing logic goes here ...
        return $data;
    }
}
