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

defined('MOODLE_INTERNAL') || die;

/**
 * This is the filter itself.
 *
 * @package    filter_h5p
 * @copyright  2018 Digital Education Society (http://www.dibig.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_h5p extends moodle_text_filter {
    /**
     * Function filter replaces any h5p-sources.
     */
    public function filter($text, array $options = array()) {
        global $CFG, $DB, $COURSE, $OUTPUT;

        if (empty($COURSE->id) || $COURSE->id == 0) {
            return $text;
        }
        if (strpos($text, '{h5p:') === false) {
            return $text;
        }

        $modinfo = get_fast_modinfo($COURSE);
        $cms = $modinfo->get_cms();

        foreach ($cms as $cm) {
            if ($cm->modname != 'hvp') {
                continue;
            }
            $params = (object) array(
                'id' => $cm->id,
                'name' => $cm->modname,
                'url' => $cm->url,
                'wwwroot' => $CFG->wwwroot,
            );

            //$link = $OUTPUT->render_from_template('filter_h5p/link', $params);
            $embed = $OUTPUT->render_from_template('filter_h5p/embed', $params);

            $text = str_replace('{h5p:' . $cm->name . '}', $embed, $text);
        }

        return $text;
    }
}
