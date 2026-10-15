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

/**
 * Card images: keep them light, because every card on a hub page downloads its image.
 *
 * @package     local_custompage
 * @copyright   2024 BitAscii Solutions <bitascii.dev@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class card_image {
    /** @var int Widest a card image needs to be (cards are at most about 800px wide on a large screen, 2x for sharp screens). */
    public const MAX_WIDTH = 1600;

    /** @var int Images smaller than this are left alone. */
    private const SMALL = 400 * 1024;

    /** @var int Images with more pixels than this are left alone (too much memory to resize). */
    private const MAX_PIXELS = 40000000;

    /**
     * Shrink the page's card image when it is large: at most 1600px wide, and photos saved as JPEG.
     *
     * Images with transparency stay PNG. Nothing changes when the result would not be smaller.
     *
     * @param int $contextid The page's context
     */
    public static function shrink(int $contextid): void {
        if (!function_exists('imagecreatefromstring')) {
            return;
        }
        $fs = get_file_storage();
        foreach ($fs->get_area_files($contextid, 'local_custompage', 'cardimage', 0, 'id', false) as $file) {
            $mimetype = $file->get_mimetype();
            if (!in_array($mimetype, ['image/png', 'image/jpeg'], true) || $file->get_filesize() < self::SMALL) {
                continue;
            }
            $info = $file->get_imageinfo();
            if (!$info || empty($info['width']) || $info['width'] * $info['height'] > self::MAX_PIXELS) {
                continue;
            }
            raise_memory_limit(MEMORY_EXTRA);
            $source = @imagecreatefromstring($file->get_content());
            if (!$source) {
                continue;
            }
            [$width, $height] = [(int) $info['width'], (int) $info['height']];
            $scale = min(1, self::MAX_WIDTH / $width);
            $newwidth = max(1, (int) round($width * $scale));
            $newheight = max(1, (int) round($height * $scale));
            $transparent = $mimetype === 'image/png' && self::has_transparency($source, $width, $height);

            $target = imagecreatetruecolor($newwidth, $newheight);
            if ($transparent) {
                imagealphablending($target, false);
                imagesavealpha($target, true);
            } else {
                imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
            }
            imagecopyresampled($target, $source, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
            ob_start();
            if ($transparent) {
                imagepng($target, null, 9);
            } else {
                imagejpeg($target, null, 82);
            }
            $content = (string) ob_get_clean();
            imagedestroy($source);
            imagedestroy($target);
            if ($content === '' || strlen($content) >= $file->get_filesize()) {
                continue;
            }
            $filename = $transparent ? $file->get_filename()
                : preg_replace('/\.[^.]+$/', '', $file->get_filename()) . '.jpg';
            $record = [
                'contextid' => $file->get_contextid(),
                'component' => 'local_custompage',
                'filearea' => 'cardimage',
                'itemid' => 0,
                'filepath' => $file->get_filepath(),
                'filename' => $filename,
                'userid' => $file->get_userid(),
                'author' => $file->get_author(),
                'license' => $file->get_license(),
            ];
            $file->delete();
            $fs->create_file_from_string($record, $content);
        }
    }

    /**
     * Whether an image has see-through pixels (checks a grid of points, which is enough for a card image).
     *
     * @param \GdImage $image
     * @param int $width
     * @param int $height
     * @return bool
     */
    private static function has_transparency($image, int $width, int $height): bool {
        $step = max(1, (int) floor(min($width, $height) / 64));
        for ($x = 0; $x < $width; $x += $step) {
            for ($y = 0; $y < $height; $y += $step) {
                if (((imagecolorat($image, $x, $y) >> 24) & 0x7F) > 0) {
                    return true;
                }
            }
        }
        return false;
    }
}
