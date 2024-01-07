<?php
// remove all non printable characters
function clean_string($string)
{
    $s = trim($string);
    $s = iconv("UTF-8", "UTF-8//IGNORE", $s); // drop all non utf-8 characters

    // this is some bad utf-8 byte sequence that makes mysql complain - control and formatting i think
    $s = preg_replace('/(?>[\x00-\x1F]|\xC2[\x80-\x9F]|\xE2[\x80-\x8F]{2}|\xE2\x80[\xA4-\xA8]|\xE2\x81[\x9F-\xAF])/', ' ', $s);

    $s = preg_replace('/\s+/', '', $s); // remove spaces
    return $s;
}
?>