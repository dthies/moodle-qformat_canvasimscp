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
 * Question Import format for a Canvas IMS Content Package
 *
 * @package    qformat_canvasimscp
 * @copyright  2020 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/xmlize.php');

/**
 * Question Import for H5P Quiz content type
 *
 * @copyright  2020 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qformat_canvasimscp extends qformat_canvas {
    public function provide_import() {
        return true;
    }

    public function provide_export() {
        return false;
    }

    public function mime_type() {
        return 'application/zip';
    }

    public function export_file_extension() {
        return '.zip';
    }

    /**
     * Return the content of a file given by its path in the tempdir directory.
     *
     * @param string $path path to the file inside tempdir
     * @return mixed contents array or false on failure
     */
    public function get_filecontent($path) {
        $fullpath = $this->tempdir . '/' . $path;
        if (is_file($fullpath) && is_readable($fullpath)) {
            return file_get_contents($fullpath);
        }
        return false;
    }


    /**
     * Return content of all files containing questions,
     * as an array one element for each file found,
     * For each file, the corresponding element is an array of lines.
     *
     * @param string $filename name of file
     * @return mixed contents array or false on failure
     */
    public function readdata($filename) {
        global $CFG;

        $uniquecode = time();
        $this->tempdir = make_temp_directory('canvas_import/' . $uniquecode);
        if (is_readable($filename)) {
            if (!copy($filename, $this->tempdir . '/content.zip')) {
                $this->error(get_string('cannotcopybackup', 'question'));
                fulldelete($this->tempdir);
                return false;
            }
            $packer = get_file_packer('application/zip');
            if ($packer->extract_to_pathname($this->tempdir . '/content.zip', $this->tempdir)) {
                $lines = $this->get_filecontent('imsmanifest.xml');

                // This converts xml to big nasty data structure
                // the 0 means keep white space as it is (important for markdown format).
                try {
                    $xml = xmlize($lines, 0, 'UTF-8', true);
                } catch (xml_format_exception $e) {
                    $this->error($e->getMessage(), '');
                    return false;
                }
                unset($lines); // No need to keep this in memory.
                $resources = $xml['manifest']['#']['resources'][0]['#']['resource'];
                $files = array();
		        foreach ($resources as $resource) {
                    if ($resource['@']['type'] === 'imsqti_xmlv1p2') {
                        $files[] = array(
                            $this->get_filecontent(
                                $resource['#']['file'][0]['@']['href']
                            )
                        );
                    }
                }

                return $files;
            } else {
                $this->error(get_string('cannotunzip', 'question'));
                fulldelete($this->temp_dir);
            }
        } else {
            $this->error(get_string('cannotreaduploadfile', 'error'));
            fulldelete($this->tempdir);
        }
        return false;
    }

    /**
     * Parses an array of lines into an array of questions,
     * where each item is a question object as defined by
     * readquestion().   
     *
     * In this case the line is the entire content of an xml file defining
     * one or more question items. These are passed to the same method or
     * parent to parse one at a time and the results returned in an array.
     *
     * @param array lines array of lines from readdata
     * @return array array of question objects
     */
    public function readquestions($lines) {
        $questions = array();

        foreach ($lines as $line) {
            $new = parent::readquestions($line);
            $questions = array_merge($questions, $new);
        }

        return $questions;
    }

}
