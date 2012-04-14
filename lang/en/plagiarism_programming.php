<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package    plagiarism
 * @subpackage programming
 * @author     thanhtri
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// settings
$string['pluginname'] = 'Source-code Plagiarism Plugin';
$string['programming'] = 'Programming Assignment';
$string['programmingexplain'] = 'This is the configuration for Programming Plagiarism Detection plugin';
$string['use_programming'] = 'Use source code plagiarism detection';
$string['jplag'] = 'JPlag global config';
$string['jplag_username'] = 'JPlag Username';
$string['jplag_password'] = 'JPlag Password';
$string['jplag_modify_account'] = 'Change JPlag account';
$string['moss'] = 'MOSS global config';
$string['enable_global'] = 'Enable this plugin for the whole Moodle';
$string['enable_course'] = 'Enable this plugin at course level';

// form
$string['plagiarism_header'] = 'Source code plagiarism detection';
$string['programmingYN'] = 'Programming assignment';
$string['programmingLanguage'] = 'Programming language';
$string['scanDate'] = 'Submit date';
$string['detectionTools'] = 'Detection tools';
$string['jplag'] = 'JPlag';
$string['moss'] = 'MOSS';
$string['auto_publish'] = 'Publish scanning result to students';
$string['notification'] = 'Display notification';
$string['notification_text'] = 'Notification text';

$string['programmingYN_hlp'] = '';
$string['programmingYN_hlp_help'] = 'Enable programming plagiarism detection for this assignment';
$string['programmingLanguage_hlp'] = '';
$string['programmingLanguage_hlp_help'] = 'The programming language used in this assignment (mandatory)';
$string['date_selector_hlp'] = '';
$string['date_selector_hlp_help'] = 'Select the date that the submissions will be scanned';
$string['auto_publish_hlp'] = '';
$string['auto_publish_hlp_help'] = 'Allowing the students to see the plagiarism report';
$string['notification_hlp'] = '';
$string['notification_hlp_help'] = 'Notify the student that their submission will be scanned for plagiarism';
$string['rescan_text'] = 'Rescan this assignment';
$string['rescan_hlp'] = '';
$string['rescan_hlp_help'] = 'If there are some changes after the scanning, e.g. new submissions or update, you can select this option to perform a rescan. The rescanning of the report often be available the next hour';

$string['start_scanning'] = 'Scan now';
$string['rescanning'] = 'Rescan';
$string['result'] = 'Similarity scanning result';

// options for displaying results
$string['option_header'] = 'Options';
$string['threshold'] = 'Similarity filter (%)';
$string['similarity_type'] = 'Similarity type';
$string['detectors'] = 'Detector';
$string['display_mode'] = 'Display';
$string['submit'] = 'Submit';
$string['showHideLabel'] = 'Show plagiarism options';
$string['savedconfigsuccess'] = 'Configuration saved';