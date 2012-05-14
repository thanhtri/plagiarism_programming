<?php
/** This is a test entry to test individual functions only
 *  Must be dropped in release.
 */

include_once __DIR__.'/../../config.php';
include_once __DIR__.'/moss/moss_parser.php';
$parser = new moss_parser(5);
//$parser->parse();
$parser->get_similar_parts();
//$returned = $parser->reconstruct_file(11,29);
//echo $returned['content'];
//print_r($returned['list']);
//$file = fopen('/tmp/result.html', 'w');
//fwrite($file, $content);
//fclose($file);