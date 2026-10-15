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

declare(strict_types=1);

namespace local_custompage\local\helpers;

use stdClass;

/**
 * The read confirmations report as table data, CSV / Excel, or a PDF an auditor can file.
 *
 * @package     local_custompage
 * @copyright   2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ack_export {
    /** @var string Date format in exports: sortable, with the time. */
    private const DATEFORMAT = '%Y-%m-%d %H:%M';

    /**
     * Column headings.
     *
     * @param string $view 'status' or 'history'
     * @return string[]
     */
    public static function columns(string $view): array {
        $s = static fn(string $key) => get_string($key, 'local_custompage');
        $tz = \core_date::get_user_timezone();
        $confirmed = get_string('ackr_confirmedon', 'local_custompage') . ' (' . $tz . ')';
        if ($view === 'history') {
            return [$confirmed, $s('ackr_staff'), get_string('email'), get_string('department'), $s('ackr_roles'),
                $s('ackr_document'), $s('ackr_hub'), $s('standards'), $s('ackr_versionconfirmed'), $s('ackr_stillcurrent')];
        }
        return [$s('ackr_staff'), get_string('email'), get_string('department'), $s('ackr_roles'), $s('ackr_document'),
            $s('ackr_hub'), $s('standards'), $s('ackr_version'), $s('ackr_status'), $confirmed];
    }

    /**
     * One row as plain values, in column order.
     *
     * @param stdClass $row From acknowledge::report_rows() or history_rows()
     * @param string $view
     * @param string $dateformat
     * @return string[]
     */
    public static function values(stdClass $row, string $view, string $dateformat = self::DATEFORMAT): array {
        $date = $row->timeconfirmed ? userdate($row->timeconfirmed, $dateformat) : '';
        $text = static fn($v) => reader::plain_text((string) $v);
        if ($view === 'history') {
            return [$date, fullname($row->user), $row->user->email, (string) $row->user->department, $row->roles,
                $text($row->pagename), $text($row->hubname), $row->standards, (string) $row->version,
                $row->current ? get_string('yes') : get_string('no')];
        }
        return [fullname($row->user), $row->user->email, (string) $row->user->department, $row->roles, $text($row->pagename),
            $text($row->hubname), $row->standards, (string) $row->version, self::state_label($row->state), $date];
    }

    /**
     * Words for a state.
     *
     * @param string $state
     * @return string
     */
    public static function state_label(string $state): string {
        return get_string('ack_state_' . $state, 'local_custompage');
    }

    /**
     * Send CSV or Excel to the browser.
     *
     * @param string $format 'csv' or 'excel'
     * @param stdClass[] $rows
     * @param string $view
     */
    public static function download_data(string $format, array $rows, string $view): void {
        $filename = clean_filename(get_string('ackr_filename', 'local_custompage') . '_' . userdate(time(), '%Y-%m-%d_%H%M'));
        $values = array_map(static fn($row) => self::values($row, $view), $rows);
        \core\dataformat::download_data($filename, $format, self::columns($view), new \ArrayIterator($values));
    }

    /**
     * Send the report as a PDF: title, who ran it and when, the filters, totals per document, then every row.
     *
     * @param stdClass[] $rows
     * @param string $view
     * @param string[] $filterlabels Filters in use, as "Name: value"
     */
    public static function download_pdf(array $rows, string $view, array $filterlabels): void {
        global $CFG, $SITE, $USER;
        require_once($CFG->libdir . '/pdflib.php');
        raise_memory_limit(MEMORY_EXTRA);
        \core_php_time_limit::raise(300);

        $title = get_string($view === 'history' ? 'ackr_historytitle' : 'ackr_title', 'local_custompage');
        $pdf = new \pdf('L', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetTitle($title);
        $pdf->SetCreator(format_string($SITE->fullname));
        $pdf->SetAuthor(fullname($USER));
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->setFooterFont(['helvetica', '', 7]);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 14);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->AddPage();

        $e = static fn($v) => s((string) $v);
        $html = '<h1 style="font-size:16pt;color:#1f2733;">' . $e($title) . '</h1>'
            . '<p style="font-size:9pt;color:#444;">' . $e(format_string($SITE->fullname)) . '<br>'
            . $e(get_string('ackr_generated', 'local_custompage', (object) [
                'date' => userdate(time(), get_string('strftimedatetime', 'langconfig')),
                'name' => fullname($USER),
                'timezone' => \core_date::get_user_timezone(),
            ])) . '<br>'
            . $e(get_string('ackr_filters', 'local_custompage') . ': '
                . ($filterlabels ? implode('; ', $filterlabels) : get_string('ackr_nofilters', 'local_custompage')))
            . '</p>';

        if ($view !== 'history') {
            $summary = acknowledge::summary($rows);
            $html .= '<h2 style="font-size:11pt;">' . $e(get_string('ackr_summary', 'local_custompage')) . '</h2>'
                . '<table cellpadding="3" border="0.3" style="font-size:8pt;"><thead>'
                . '<tr style="background-color:#eef1f6;font-weight:bold;">'
                . '<th width="31%">' . $e(get_string('ackr_document', 'local_custompage')) . '</th>'
                . '<th width="25%">' . $e(get_string('standards', 'local_custompage')) . '</th>'
                . '<th width="7%" align="center">' . $e(get_string('ackr_version', 'local_custompage')) . '</th>'
                . '<th width="8%" align="center">' . $e(get_string('ackr_staffcount', 'local_custompage')) . '</th>'
                . '<th width="9%" align="center">' . $e(self::state_label(acknowledge::CURRENT)) . '</th>'
                . '<th width="9%" align="center">' . $e(self::state_label(acknowledge::OUTDATED)) . '</th>'
                . '<th width="11%" align="center">' . $e(self::state_label(acknowledge::NONE)) . '</th></tr></thead><tbody>';
            foreach ($summary as $s) {
                $name = reader::plain_text($s['name']) . ($s['hub'] !== '' ? ' (' . reader::plain_text($s['hub']) . ')' : '');
                $html .= '<tr><td width="31%">' . $e($name) . '</td><td width="25%">' . $e($s['standards']) . '</td>'
                    . '<td width="7%" align="center">' . $s['version'] . '</td>'
                    . '<td width="8%" align="center">' . $s['total'] . '</td>'
                    . '<td width="9%" align="center">' . $s[acknowledge::CURRENT] . ' (' . $s['percent'] . '%)</td>'
                    . '<td width="9%" align="center">' . $s[acknowledge::OUTDATED] . '</td>'
                    . '<td width="11%" align="center">' . $s[acknowledge::NONE] . '</td></tr>';
            }
            $html .= '</tbody></table>';
        }
        $pdf->writeHTML($html, true, false, true, false, '');

        $pdf->writeHTML('<h2 style="font-size:11pt;">' . $e(get_string('ackr_detail', 'local_custompage')) . ' ('
            . count($rows) . ')</h2>', true, false, true, false, '');
        $columns = self::columns($view);
        $widths = $view === 'history' ? [10, 12, 14, 8, 10, 13, 9, 14, 5, 5] : [12, 14, 8, 10, 13, 9, 14, 5, 7, 8];
        $head = '<tr style="background-color:#eef1f6;font-weight:bold;">';
        foreach ($columns as $i => $column) {
            $head .= '<th width="' . $widths[$i] . '%">' . $e($column) . '</th>';
        }
        $head .= '</tr>';
        $colours = [acknowledge::CURRENT => '#146c2e', acknowledge::OUTDATED => '#8a5a00', acknowledge::NONE => '#b42318'];
        // Write in chunks: one huge HTML table is slow in TCPDF.
        foreach (array_chunk($rows, 150) as $chunk) {
            $body = '';
            foreach ($chunk as $row) {
                $values = self::values($row, $view);
                $body .= '<tr nobr="true">';
                foreach ($values as $i => $value) {
                    $style = ($view !== 'history' && $i === 8)
                        ? ' style="color:' . $colours[$row->state] . ';font-weight:bold;"' : '';
                    $body .= '<td width="' . $widths[$i] . '%"' . $style . '>' . $e($value) . '</td>';
                }
                $body .= '</tr>';
            }
            $pdf->writeHTML('<table cellpadding="2.5" border="0.3" style="font-size:7pt;"><thead>' . $head . '</thead><tbody>'
                . $body . '</tbody></table>', false, false, true, false, '');
        }
        if (!$rows) {
            $pdf->writeHTML('<p>' . $e(get_string('ackr_norows', 'local_custompage')) . '</p>', true, false, true, false, '');
        }
        $filename = clean_filename(get_string('ackr_filename', 'local_custompage') . '_' . userdate(time(), '%Y-%m-%d_%H%M'))
            . '.pdf';
        $pdf->Output($filename, 'D');
    }
}
