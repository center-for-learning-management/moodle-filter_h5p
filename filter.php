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
            if ($cm->modname != 'hvp' && $cm->modname != 'h5pactivity') {
                continue;
            }
            $params = (object) array(
                'id' => $cm->id,
                'name' => $cm->modname,
                'url' => $cm->url,
                'wwwroot' => $CFG->wwwroot,
            );
            switch ($cm->modname) {
                case 'hvp':
                    $embed = $OUTPUT->render_from_template('filter_h5p/embed-hvp', $params);
                break;
                case 'h5pactivity':
                    // This part is from core-Moodle /mod/h5pactivity/view.php
                    // --------------------------
                    $manager = \mod_h5pactivity\local\manager::create_from_coursemodule($cm);
                    $moduleinstance = $manager->get_instance();
                    $context = $manager->get_context();
                    // Convert display options to a valid object.
                    $factory = new core_h5p\factory();
                    $core = $factory->get_core();
                    $config = \core_h5p\helper::decode_display_options($core, $moduleinstance->displayoptions);

                    // Instantiate player.
                    $fs = get_file_storage();
                    $files = $fs->get_area_files($context->id, 'mod_h5pactivity', 'package', 0, 'id', false);
                    $file = reset($files);
                    $fileurl = \moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(),
                                        $file->get_filearea(), $file->get_itemid(), $file->get_filepath(),
                                        $file->get_filename(), false);

                    $h5pparams = [
                            'url' => $fileurl,
                            'preventredirect' => true,
                            'component' => '', //$component,
                        ];

                    $optparams = ['frame', 'export', 'embed', 'copyright'];
                    foreach ($optparams as $optparam) {
                        if (!empty($config->$optparam)) {
                            $h5pparams[$optparam] = $config->$optparam;
                        }
                    }
                    $fileurl = new \moodle_url('/h5p/embed.php', $h5pparams);
                    $params->embedurl = $fileurl->out(false);

                    // --------------------------
                    // This is again the filter plugin.
                    $embed = $OUTPUT->render_from_template('filter_h5p/embed-h5p', $params);
                break;
            }

            //$link = $OUTPUT->render_from_template('filter_h5p/link', $params);


            $text = str_replace('{h5p:' . $cm->name . '}', $embed, $text);
        }

        return $text;
    }
}
