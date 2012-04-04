<?php
include_once dirname(__FILE__).'/../../config.php';
include_once dirname(__FILE__).'/utils.php';
include_once dirname(__FILE__).'/moss_tool.php';

global $DB;

$assignment = $DB->get_record('programming_plagiarism',array('id'=>5));
$moss_param = $DB->get_record('programming_moss',array('settingid'=>5));

$moss_tool = new moss_tool();
$moss_tool->download_result($assignment, $moss_param);