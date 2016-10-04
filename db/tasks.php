<?php

/**
 * Taks for plagiarism programming
 *
 * @package
 * @subpackage
 * @copyright  &copy; 2016 Kineo Pacific {@link http://kineo.com.au}
 * @author     tri.le
 * @version    1.0
 */

defined('MOODLE_INTERNAL') || die();

$tasks = array(
    array(
        'classname' => 'plagiarism_programming\task\auto_scan_task',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '0',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
        'disabled' => 1
    )
);

