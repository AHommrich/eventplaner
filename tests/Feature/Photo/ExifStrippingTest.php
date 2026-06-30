<?php

namespace Tests\Feature\Photo;

use App\Services\PhotoSanitizer;
use Tests\TestCase;

class ExifStrippingTest extends TestCase
{
    public function test_sanitizer_returns_a_jpeg_without_exif_segment(): void
    {
        $sanitizer = new PhotoSanitizer;

        // Build a minimal JPEG that carries an APP1 (EXIF) segment with a known
        // marker so we can prove it's gone after the sanitizer runs.
        $tmpPath = tempnam(sys_get_temp_dir(), 'exif-').'.jpg';

        $image = imagecreatetruecolor(20, 20);
        imagefilledrectangle($image, 0, 0, 20, 20, imagecolorallocate($image, 200, 100, 50));
        imagejpeg($image, $tmpPath, 90);
        imagedestroy($image);

        $raw = file_get_contents($tmpPath);
        $marker = 'CLAUDE-EXIF-PROBE-'.bin2hex(random_bytes(4));
        $exifApp1 = "\xFF\xE1".pack('n', strlen($marker) + 8)."Exif\x00\x00".$marker;
        $injected = substr($raw, 0, 2).$exifApp1.substr($raw, 2);
        file_put_contents($tmpPath, $injected);

        $this->assertStringContainsString($marker, file_get_contents($tmpPath), 'fixture should carry the EXIF probe');

        $sanitized = $sanitizer->toJpegWithoutExif($tmpPath);

        $this->assertStringStartsWith("\xFF\xD8", $sanitized, 'output should still be a valid JPEG');
        $this->assertStringNotContainsString($marker, $sanitized, 'EXIF probe must not survive sanitization');
        $this->assertStringNotContainsString('Exif', $sanitized, 'EXIF marker must not survive sanitization');

        unlink($tmpPath);
    }
}
