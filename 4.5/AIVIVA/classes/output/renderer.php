<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Output renderer for mod_aiviva.
 *
 * @package    mod_aiviva
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aiviva\output;

/**
 * Activity module renderer for mod_aiviva.
 */
class renderer extends \plugin_renderer_base {
    /** @var string[] Breakdown key => language string naming the step. */
    private const STEPS = [
        'step1_pdf'      => 'step1_title',
        'step2_video'    => 'step2_title',
        'step3_tribunal' => 'step3_title',
    ];

    /**
     * Renders a submission status as a badge.
     *
     * @param string $status Submission status string.
     * @return string HTML badge.
     */
    public function render_status_badge(string $status): string {
        $classmap = [
            'draft'     => 'secondary',
            'step1'     => 'info',
            'step2'     => 'info',
            'step3'     => 'warning',
            'submitted' => 'warning',
            'grading'   => 'warning',
            'graded'    => 'success',
        ];
        if (!isset($classmap[$status])) {
            return \html_writer::span(s($status), 'badge bg-secondary');
        }
        return \html_writer::span(get_string('status_' . $status, 'mod_aiviva'), 'badge bg-' . $classmap[$status]);
    }

    /**
     * Renders the AI's per-step grade breakdown as a table.
     *
     * The breakdown is produced by a language model, so every value is escaped
     * and only the three known steps are read from it.
     *
     * @param array $breakdown Decoded grade_breakdown JSON.
     * @return string HTML table ('' if there is nothing to show).
     */
    public function render_grade_breakdown(array $breakdown): string {
        $table = new \html_table();
        $table->head = [
            get_string('breakdown_step', 'mod_aiviva'),
            get_string('breakdown_score', 'mod_aiviva'),
            get_string('breakdown_weight', 'mod_aiviva'),
            get_string('feedback', 'mod_aiviva'),
        ];
        $table->attributes['class'] = 'generaltable aiviva-breakdown';

        foreach (self::STEPS as $step => $stringid) {
            if (!isset($breakdown[$step]) || !is_array($breakdown[$step])) {
                continue;
            }
            $data = $breakdown[$step];
            $table->data[] = [
                get_string($stringid, 'mod_aiviva'),
                format_float((float)($data['score'] ?? 0), 1) . ' / 100',
                format_float((float)($data['weight'] ?? 0) * 100, 0) . '%',
                format_text((string)($data['feedback'] ?? ''), FORMAT_PLAIN),
            ];
        }

        return $table->data ? \html_writer::table($table) : '';
    }
}
