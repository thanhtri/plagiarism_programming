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
$string['moss_id'] = 'User id';
$string['moss_id_help'] = 'Locate the line $userid=some number in the reply email from MOSS and put that number in the box below';
$string['moss_id_help_2'] = 'Or copy and paste the email content in the box below';
$string['enable_global'] = 'Enable this plugin for the whole Moodle';
$string['enable_course'] = 'Enable this plugin at course level';
$string['jplag_account_error'] = 'Invalid JPlag account - Please provide the correct username and password';
$string['jplag_account_expired'] = 'Your account has expired!';
$string['connection_error'] = 'Cannot connect to JPlag server - Please check the connection';
$string['save_config_success'] = 'Configuration saved';
$string['username_missing'] = 'Please provide JPlag username';
$string['password_missing'] = 'Please provide JPlag password';
$string['moss_userid_missing'] = 'Please provide MOSS user id or email';
$string['account_instruction'] = 'The plugin uses MOSS and JPlag engine in the background. An account is required to use these engines';
$string['jplag_account_instruction'] = 'If you do not have a JPlag account, you can register at ';/* + jplag_link*/
$string['moss_account_instruction'] = 'MOSS userid could be obtained by emailing moss@moss.stanford.edu. Instructions are provided at MOSS site: ';
$string['moss_userid_notfound'] = 'Cannot find userid in the provided email';
// form
$string['plagiarism_header'] = 'Source code plagiarism detection';
$string['programmingYN'] = 'Programming assignment';
$string['programming_language'] = 'Programming language';
$string['scan_date'] = 'Submit date';
$string['detection_tools'] = 'Detection tools';
$string['jplag'] = 'JPlag';
$string['moss'] = 'MOSS';
$string['auto_publish'] = 'Publish scanning result to students';
$string['notification'] = 'Display notification';
$string['notification_text'] = 'Notification';

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
$string['programming_language_missing'] = 'Programming language is required';

$string['jplag_credential_missing'] = "Attention: JPlag account hasn't been provided";
$string['moss_credential_missing'] =  "Attention: MOSS account hasn't been provided";

$string['start_scanning'] = 'Scan now';
$string['rescanning'] = 'Rescan';
$string['no_tool_selected'] = 'No detector was selected. Please select at least one among MOSS and JPlag';
$string['not_enough_submission'] = 'Not enough submissions to scan!';

// options for displaying results
$string['option_header'] = 'Options';
$string['threshold'] = 'Similarity filter (%)';
$string['similarity_type'] = 'Similarity type';
$string['detectors'] = 'Detector';
$string['display_mode'] = 'Display';
$string['submit'] = 'Filter';
$string['showHideLabel'] = 'Show plagiarism options';

// in the report
$string['yours'] = 'Yours';
$string['another'] = "Someone's";
$string['chart_legend'] = 'Similarity rate distribution of the whole course';
$string['result'] = 'Similarity scanning result';
$string['comparison_title'] = 'Similarities';
$string['comparison'] = 'Comparison';

$string['plagiarism_action'] = 'Action';
$string['mark_suspicious'] = 'Mark this pair as suspicious';
$string['mark_nonsuspicious'] = 'Mark this pair as normal';

// notification
$string['high_similarity_warning'] = 'Your assignment was found to be similar with some others\'';
$string['report'] = 'Report';
$string['suspicious'] = 'suspicious';