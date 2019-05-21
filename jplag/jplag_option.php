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
 * @package    plagiarism_programming
 * @copyright  2015 thanhtri, 2019 Benedikt Schneider (@Nullmann)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die('Access to internal script forbidden');

define('INVALID_LANGUAGE_EXCEPTION', 2);

require_once(dirname(__FILE__) . '/../utils.php');

/**
 * Class for all options to pass to jplag.
 *
 * @package    plagiarism_programming
 * @copyright  2015 thanhtri, 2019 Benedikt Schneider (@Nullmann)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jplag_option{
    /**
     * @var $language
     */
    public $language; // Programming language.
    /**
     * @var $comparisonmode
     */
    public $comparisonmode = self::NORMAL_PAIRWISE_COMPARISON;
    /**
     * @var $minimummatchlength
     */
    public $minimummatchlength = 8; // Number of similar contiguos tokens to be considered a match.
    /**
     * @var $suffixes
     */
    public $suffixes = array(); // File suffixes to scan, depending on language.
    /**
     * @var $readsubdirs
     */
    public $readsubdirs = true;
    /**
     * @var $pathtofiles
     */
    public $pathtofiles = ''; // Specific directory in the zip file to look for.
    /**
     * @var $basecodedir
     */
    public $basecodedir = ''; // Directory containing the base code (the code provided to all students.
    /**
     * @var $storematches
     */
    public $storematches = '1%'; // The number of matches displayed.
    /**
     * @var $clustertype
     */
    public $clustertype = ''; // The type of cluster.
    /**
     * @var $countrylang
     */
    public $countrylang = 'en'; // Language used.
    /**
     * @var $title
     */
    public $title = ''; // Title of the assignment.
    /**
     * @var $originaldir
     */
    public $originaldir = ''; // Original directory.
    /**
     * @var JAVA
     */
    const JAVA = 'java';
    /**
     * @var C_CPLUS
     */
    const C_CPLUS = 'c';
    /**
     * @var TEXT
     */
    const TEXT = 'text';
    /**
     * @var CSHARP
     */
    const CSHARP = 'c#';
    /**
     * @var NORMAL_PAIRWISE_COMPARISON
     */
    const NORMAL_PAIRWISE_COMPARISON = 0; // Comparison mode value.
    /**
     * @var REVISION_ADJACENT_COMPARISON
     */
    const REVISION_ADJACENT_COMPARISON = 1; // Comparison mode value.
    /**
     * @var CLUSTER_NULL
     */
    const CLUSTER_NULL = 'null'; // Cluster type.
    /**
     * @var CLUSTER_MIN
     */
    const CLUSTER_MIN = 'min'; // Cluster type.
    /**
     * @var CLUSTER_MAX
     */
    const CLUSTER_MAX = 'max'; // Cluster type.
    /**
     * @var CLUSTER_AVG
     */
    const CLUSTER_AVG = 'avg';

    /**
     * Sets the programming language used.
     *
     * @param String $language
     * @throws Exception
     */
    public function set_language($language) {
        $supportedlanguages = jplag_tool::get_supported_language();
        if (isset($supportedlanguages[$language])) {
            $this->language = $supportedlanguages[$language];
            $this->suffixes = plagiarism_programming_get_file_extension($language);
        } else {
            throw new Exception('Invalid language', INVALID_LANGUAGE_EXCEPTION);
        }
    }
}