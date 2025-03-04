<?php namespace App\Models;


class RandomPastelColorGenerator
{
  
    
    public function getNext() : string
    {
        // Create lighter colors by taking a random integer between 0 & 128
        // and then adding 127 to make the color lighter
        $colorBytes = [
            'r' =>strtoupper(dechex(rand(0, 127) + 127)),
            'g' => strtoupper(dechex(rand(0, 127) + 127)),
            'b' => strtoupper(dechex(rand(0, 127) + 127)),
            'o' => strtoupper(dechex(255)) // Fully opaque
        ];

        return "#".$colorBytes["r"].$colorBytes["g"].$colorBytes["b"];//.$colorBytes["o"] ;
    }
}
