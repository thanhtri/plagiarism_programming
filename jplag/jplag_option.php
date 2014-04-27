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
 * The option class defined by JPlag wsdl file - see jplag.wsdl
 *
 * @package    plagiarism
 * @subpackage programming
 * @author     thanhtri
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Access to internal script forbidden');

define('INVALID_LANGUAGE_EXCEPTION', 2);

require_once(dirname(__FILE__).'/../utils.php');

class jplag_option {

    public $language;   //programming language
    public $comparisonMode = self::NORMAL_PAIRWISE_COMPARISON;
    public $minimumMatchLength = 8;         //number of similar contiguos tokens to be considered a match
    public $suffixes = array();             //file suffixes to scan, depending on language
    public $readSubdirs = true;             // true or false
    public $pathToFiles = '';               // specific directory in the zip file to look for
    public $baseCodeDir = '';               // directory containing the base code (the code provided to all students
    public $storeMatches= '1%';               // the number of matches displayed
    public $clustertype = '';               // the type of cluster
    public $countryLang = 'en';             // language used
    public $title = '';                     // title of the assignment
    public $originalDir = '';               // original directory

    const JAVA = 'java';
    const C_CPLUS ='c';
    const TEXT = 'text';
    const CSHARP = 'c#';

    const NORMAL_PAIRWISE_COMPARISON = 0;     //comparison mode value
    const REVISION_ADJACENT_COMPARISON = 1;   //comparison mode value

    const CLUSTER_NULL = 'null';    // cluster type
    const CLUSTER_MIN  = 'min';    // cluster type
    const CLUSTER_MAX = 'max';    // cluster type
    const CLUSTER_AVG = 'avg';     // cluster type

    public function set_language($language) {
        $supported_languages = jplag_tool::get_supported_language();
        if (isset($supported_languages[$language])) {
            $this->language = $supported_languages[$language];
            $this->suffixes = plagiarism_programming_get_file_extension($language);
        } else {
            throw new Exception('Invalid language', INVALID_LANGUAGE_EXCEPTION);
        }
    }
}