<?php
/** This is a test entry to test individual functions only
 *  Must be dropped in release.
 */

require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/jplag_tool.php');
require_once(__DIR__.'/jplag/jplag_parser.php');
$parser = new jplag_parser(16);
$parser->get_similar_parts();