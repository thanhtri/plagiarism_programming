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
 * The language strings for the english version for this plugin.
 *
 * @package    plagiarism_programming
 * @copyright  2015 thanhtri, 2019 Benedikt Schneider (@Nullmann)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Access to internal script forbidden');

// Settings.
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
$string['moss_id_help_2'] = 'Or copy and paste the email content in the box below. The userid will then be extracted.';
$string['enable_global'] = 'Enable this plugin for the whole Moodle';
$string['enable_course'] = 'Enable this plugin at course level';

$string['proxy_config'] = 'Proxy configuration (if applicable)';
$string['proxy_host'] = 'Proxy address';
$string['proxy_port'] = 'Proxy port';
$string['proxy_user'] = 'Proxy login';
$string['proxy_pass'] = 'Proxy password';
$string['jplag_account_error'] = 'Invalid JPlag account - Please provide the correct username and password';
$string['jplag_account_expired'] = 'Your account has expired!';
$string['jplag_connection_error'] = 'Cannot connect to JPlag server - Please check the connection';
$string['moss_connection_error'] = 'Cannot connect to MOSS server on port 7690 - Please check the connection';
$string['proxy_connection_error'] = 'Cannot connect to MOSS through the specified proxy server';
$string['moss_account_error'] = 'MOSS credential is invalid. Please provide a valid MOSS userid in '
    .'Plugins -> Plagiarism Prevention -> Programming page';
$string['moss_send_error'] = 'An error occurred while sending the assignment to MOSS. '
    .'Please check: your userid, server internet connection or whether port 7690 to remote host is blocked';
$string['save_config_success'] = 'Configuration saved';
$string['username_missing'] = 'Please provide JPlag username';
$string['password_missing'] = 'Please provide JPlag password';
$string['moss_userid_missing'] = 'Please provide MOSS user id or email';
$string['account_instruction'] = 'The plugin uses MOSS and JPlag engine in the background. An account is required to use these engines';
$string['jplag_account_instruction'] = 'If you do not have a JPlag account, you can register at https://jplag.ipd.kit.edu/';
$string['moss_account_instruction'] = 'MOSS userid could be obtained by emailing moss@moss.stanford.edu. Instructions are provided at MOSS site: ';
$string['moss_userid_notfound'] = 'Cannot find userid in the provided email';
$string['proxy_port_missing'] = 'Proxy port must be provided along with the host';
$string['proxy_host_missing'] = 'Proxy host must be provided along with the port';
$string['proxy_user_missing'] = 'Proxy login must be provided along with the password';
$string['proxy_pass_missing'] = 'Proxy password must be provided along with the login';

// Form.
$string['plagiarism_header'] = 'Source code plagiarism detection';
$string['programmingYN'] = 'Code similarity checking';
$string['programming_language'] = 'Language';
$string['scan_date'] = 'Scan date';
$string['scan_date_finished'] = 'Scan date (finished)';
$string['new_scan_date'] = 'New date';
$string['detection_tools'] = 'Detection tools';
$string['detection_tool'] = 'Detection tool';
$string['jplag'] = 'JPlag';
$string['moss'] = 'MOSS';
$string['auto_publish'] = 'Publish similarity report';
$string['notification'] = 'Display notification';
$string['notification_text'] = 'Notification text';
$string['notification_text_default'] = 'This assignment will be scanned for code similarity';
$string['additional_code'] = 'Additional code to compare against (only .zip or .rar!)';

$string['programmingYN_hlp'] = '';
$string['programmingYN_hlp_help'] = 'Enable programming plagiarism detection for this assignment';
$string['programmingLanguage_hlp'] = '';
$string['programmingLanguage_hlp_help'] = 'The programming language used in this assignment (mandatory). '
    .'Not every language is supported by both detection tools';
$string['detection_tools_hlp'] = '';
$string['detection_tools_hlp_help'] = 'Select the detection tool(s) to use. Each tool uses a different matching algorithm. '
    .'You can select both. However, a tool may not support some languages (in this case it will be grayed out)';
$string['date_selector_hlp'] = '';
$string['date_selector_hlp_help'] = 'Select the date that the submissions will be scanned. You can select multiple dates. '.
    'To allow draft submission, you can select some dates before the due date so that students can see the report and modify '.
    'their assignments. Alternatively, you can manually trigger the scanning by pressing the Scan button on the assignment page';
$string['auto_publish_hlp'] = '';
$string['auto_publish_hlp_help'] = 'Allow the students to see the plagiarism report.'
    .' They can see the similarity percentage but neither their code of nor their names.';
$string['notification_hlp'] = '';
$string['notification_hlp_help'] = 'Notify the student that their submission will be scanned for plagiarism';
$string['programming_language_missing'] = 'Programming language is required';
$string['notification_text_hlp'] = '';
$string['notification_text_hlp_help'] = 'Set the notification text to be displayed';
$string['additional_code_hlp'] = 'Code seeding';
$string['additional_code_hlp_help'] = 'Upload other codes to compare against (e.g. code found on the internet or past assigments). '
    .'Only zip and rar files are supported. A compressed file must contain a number of directories or compressed files, each correspond to an assignment';

$string['jplag_credential_missing'] = "Attention: JPlag account hasn't been provided";
$string['moss_credential_missing'] = "Attention: MOSS account hasn't been provided";
$string['credential_missing_instruction'] = 'Please enter the required credential in Administrator -> Plugin -> Plagiarism -> Programming assignment';
$string['no_tool_selected_error'] = 'You must select at least one tool!';
$string['invalid_submit_date_error'] = 'Submit date must not be in the past';
$string['pending'] = 'not started';
$string['extract'] = 'Extracting the assignment';
$string['pending_start'] = 'Preparing to send the assignment';
$string['uploading'] = 'Sending the assignment';
$string['scanning'] = 'Checking for similarities';
$string['downloading'] = 'Downloading similarities result';
$string['scanning_done'] = 'Scanning finished on server';
$string['inqueue_on_server'] = 'Waiting in server queue';
$string['parsing_on_server'] = 'Parsing the submissions on server';
$string['generating_report_on_server'] = 'Generating the report on server';
$string['error_bad_language'] = 'Bad language error';
$string['error_not_enough_submission'] = 'Not enough submission to scan. This is probably too many submissions cannot be parsed. '.
        'Please check your language configuration';
$string['jplag_cancel_error'] = 'Cannot cancel submission';

$string['start_scanning'] = 'Scan now';
$string['rescanning'] = 'Rescan';
$string['no_tool_selected'] = 'No detector was selected. Please select at least one among MOSS and JPlag';
$string['not_enough_submission'] = 'Not enough submissions to scan! At least 2 are needed';
$string['scheduled_scanning'] = 'The next scanning is scheduled on';
$string['no_scheduled_scanning'] = 'There is no scanning scheduled!';
$string['latestscan'] = 'Latest scanning occurred at ';
$string['manual_scheduling_help'] = 'If you want to trigger the scanning immediately (in case of late submissions, extension...), '
    .'please click the button below!';
$string['credential_not_provided'] = 'Credential not provided. Please provide this information in '
    .'Administrator -> Plugin -> Plagiarism -> Programming assignment';
$string['unexpected_error_extract'] = 'An unexpected error occurred while extracting the assignments! This may be due to corrupted data or unsupported format...';
$string['unexpected_error_upload'] = 'An unexpected error occurred while sending the assignments! This may be due to broken connection or remote server downtime'
    .' Please try again latter!';
$string['unexpected_error_download'] = 'An unexpected error occurred while downloading and parsing the result! This may be due to connection broken or corrupted data...'
    .' Please try again latter!';
$string['general_user_error'] = 'Errors occured due to corrupted report data';
$string['scanning_in_progress'] = 'Scanning may take a longtime depending on server load! Please feel free to navigate away from this page';
$string['unexpected_error'] = 'An unexpected error has occurred! Please contact administrator!';
$string['invalid_file_type'] = 'Submissions must have file extensions: ';

// Options for displaying results.
$string['option_header'] = 'Options';
$string['threshold'] = 'Lower threshold (%)';
$string['similarity_type'] = 'Similarity type';
$string['detectors'] = 'Detector';
$string['display_mode'] = 'Display';
$string['display_group'] = 'Matrix';
$string['display_table'] = 'Ordered table';
$string['version'] = 'History';
$string['similarity_history'] = 'History of similarity rate';
$string['submit'] = 'Filter';
$string['showHideLabel'] = 'Show plagiarism options';

$string['permission_denied'] = "You don't have permission to see this page";
$string['report_not_available'] = 'No reports available';

$string['lower_threshold_hlp'] = '';
$string['lower_threshold_hlp_help'] = 'Display only the pairs having similarity rate above this value';

$string['rate_type_hlp'] = '';
$string['rate_type_hlp_help'] = 'Since two assignments can have substantially different lengths, the ratio of the similar parts '
    .'over each one is different. "Average similarity" takes the average rate of the two as the similarity rate of the pair, '
    .'while "Maximum similarity" takes the maximum one';

$string['tool_hlp'] = '';
$string['tool_hlp_help'] = 'Select the tool to show the result';
$string['display_mode_hlp'] = '';
$string['display_mode_hlp_help'] = 'Select the display mode. "Matrix" mode shows all the students similar with one '
    .'student in one row. "Ordered table" shows a list of pairs with descending similarity rate';
$string['version_hlp'] = '';
$string['version_hlp_help'] = 'See the report of previous scans';
$string['pair'] = 'Amount of pairs';

// In the report.
$string['yours'] = 'Own submission';
$string['another'] = 'Another submission';
$string['chart_legend'] = 'Similarity rate distribution of the whole course';
$string['result'] = 'Similarity scanning result';
$string['comparison_title'] = 'Similarities';
$string['comparison'] = 'Comparison';

$string['plagiarism_action'] = 'Action';
$string['mark_select_title'] = 'Mark this pair as';
$string['mark_suspicious'] = 'suspicious';
$string['mark_nonsuspicious'] = 'normal';
$string['show_similarity_to_others'] = 'Show similarity of "{student}" with other students';
$string['history_char'] = 'Show similarity history';

// Notification.
$string['high_similarity_warning'] = 'Your assignment was found to be similar with some others\'';
$string['report'] = 'Report';
$string['max_similarity'] = 'Max similarity';
$string['avg_similarity'] = 'Average similarity';
$string['suspicious'] = 'suspicious';
$string['no_similarity'] = 'No similarity';

$string['scanning_complete_email_notification_subject'] =
    '{$a->course_short_name} {$a->assignment_name}: similarity scanning available';
$string['scanning_complete_email_notification_body_html'] = 'Dear {$a->recipientname}, <br/>'
.'This is a notification that the code similarity scanning of "{$a->assignment_name}" in {$a->course_name}'
.'has finished at {$a->time}.'
.'You could access the similarity report by following this link: <a href="{$a->report_link}">{$a->report_link}</a>.';
$string['scanning_complete_email_notification_body_txt'] = 'Dear {$a->recipientname},'
.'This is a notification that the code similarity scanning of "{$a->assignment_name}" in {$a->course_name}'
.'has finished at {$a->time}.'
.'You could access the similarity report by using this link: {$a->report_link}';

$string['similarity_report'] = 'Similarity result report';
$string['include_repository'] = 'Include additional code (is presented as "library")';
$string['course_select'] = 'Select courses using code plagiarism scanning';
$string['by_name'] = 'By name';
$string['search'] = 'Search';
$string['search_by_category'] = 'Course search by category';

// Capabilites
$string['programming:enable'] = 'Enable the plugin in the settings of a submission';
$string['programming:manualscan'] = 'Manually trigger a scan by pressing the "Scan"-Button in grading overview';
$string['programming:markpairs'] = 'Mark two pairs as either normal of suspicious';
