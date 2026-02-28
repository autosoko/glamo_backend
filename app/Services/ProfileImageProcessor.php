<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class ProfileImageProcessor
{
    private const OUTPUT_SIZE = 1080;

    /**
     * Soft pink background sampled close to the reference image.
     */
    private const BG_COLOR = [232, 200, 214];

    public function processStoredProfileImage(?string $path, string $mode = 'auto_remove', string $disk = 'public'): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return $path;
        }

        if (!extension_loaded('gd')) {
            return $path;
        }

        $storage = Storage::disk($disk);
        if (! $storage->exists($path)) {
            return $path;
        }

        try {
            $absolutePath = $storage->path($path);
        } catch (\Throwable $e) {
            return $path;
        }

        $raw = @file_get_contents($absolutePath);
        if ($raw === false) {
            return $path;
        }

        $source = @imagecreatefromstring($raw);
        if (! is_resource($source) && ! ($source instanceof \GdImage)) {
            return $path;
        }

        $canvas = $this->squareCropAndResize($source, self::OUTPUT_SIZE);
        imagedestroy($source);

        $mode = strtolower(trim($mode));
        if ($mode === 'auto_remove') {
            $this->removeBackgroundFromCorners($canvas, 46.0);
            $composited = $this->compositeOnSolidBackground($canvas, self::BG_COLOR);
            imagedestroy($canvas);
            $canvas = $composited;
        }

        ob_start();
        imagejpeg($canvas, null, 90);
        $jpeg = (string) ob_get_clean();
        imagedestroy($canvas);

        if ($jpeg === '') {
            return $path;
        }

        $outPath = $this->toJpegPath($path);
        $storage->put($outPath, $jpeg);

        if ($outPath !== $path && $storage->exists($path)) {
            $storage->delete($path);
        }

        return $outPath;
    }

    /**
     * @param resource|\GdImage $source
     * @return resource|\GdImage
     */
    private function squareCropAndResize($source, int $targetSize)
    {
        $srcW = max(1, (int) imagesx($source));
        $srcH = max(1, (int) imagesy($source));
        $side = min($srcW, $srcH);

        $srcX = (int) floor(($srcW - $side) / 2);
        $srcY = (int) floor(($srcH - $side) / 2);

        $dst = imagecreatetruecolor($targetSize, $targetSize);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);

        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefill($dst, 0, 0, $transparent);

        imagecopyresampled(
            $dst,
            $source,
            0,
            0,
            $srcX,
            $srcY,
            $targetSize,
            $targetSize,
            $side,
            $side
        );

        return $dst;
    }

    /**
     * Corner-based flood fill background removal for simple portrait backgrounds.
     *
     * @param resource|\GdImage $img
     */
    private function removeBackgroundFromCorners($img, float $threshold): void
    {
        $w = max(1, (int) imagesx($img));
        $h = max(1, (int) imagesy($img));

        $bg = $this->averageCornerColor($img, 16);
        $pixelCount = $w * $h;
        $visited = str_repeat("\0", $pixelCount);
        $queue = new \SplQueue();

        $enqueueIfBg = function (int $x, int $y) use (&$queue, &$visited, $w, $h, $bg, $img, $threshold): void {
            if ($x < 0 || $y < 0 || $x >= $w || $y >= $h) {
                return;
            }

            $idx = $y * $w + $x;
            if ($visited[$idx] !== "\0") {
                return;
            }

            $rgb = $this->getRgbAt($img, $x, $y);
            if ($this->colorDistance($rgb, $bg) > $threshold) {
                return;
            }

            $visited[$idx] = "\1";
            $queue->enqueue($idx);
        };

        for ($x = 0; $x < $w; $x++) {
            $enqueueIfBg($x, 0);
            $enqueueIfBg($x, $h - 1);
        }

        for ($y = 0; $y < $h; $y++) {
            $enqueueIfBg(0, $y);
            $enqueueIfBg($w - 1, $y);
        }

        while (! $queue->isEmpty()) {
            $idx = (int) $queue->dequeue();
            $x = $idx % $w;
            $y = (int) floor($idx / $w);
            $enqueueIfBg($x + 1, $y);
            $enqueueIfBg($x - 1, $y);
            $enqueueIfBg($x, $y + 1);
            $enqueueIfBg($x, $y - 1);
        }

        imagealphablending($img, false);
        imagesavealpha($img, true);
        $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);

        for ($y = 0; $y < $h; $y++) {
            $rowOffset = $y * $w;
            for ($x = 0; $x < $w; $x++) {
                if ($visited[$rowOffset + $x] !== "\0") {
                    imagesetpixel($img, $x, $y, $transparent);
                }
            }
        }
    }

    /**
     * @param resource|\GdImage $img
     * @return resource|\GdImage
     */
    private function compositeOnSolidBackground($img, array $bgColor)
    {
        [$r, $g, $b] = $bgColor;
        $w = (int) imagesx($img);
        $h = (int) imagesy($img);

        $out = imagecreatetruecolor($w, $h);
        $bg = imagecolorallocate($out, (int) $r, (int) $g, (int) $b);
        imagefill($out, 0, 0, $bg);

        imagealphablending($out, true);
        imagecopy($out, $img, 0, 0, 0, 0, $w, $h);

        return $out;
    }

    /**
     * @param resource|\GdImage $img
     * @return array{0:int,1:int,2:int}
     */
    private function averageCornerColor($img, int $box): array
    {
        $w = (int) imagesx($img);
        $h = (int) imagesy($img);

        $box = max(1, min($box, (int) floor(min($w, $h) / 3)));

        $regions = [
            [0, 0],
            [$w - $box, 0],
            [0, $h - $box],
            [$w - $box, $h - $box],
        ];

        $sumR = 0;
        $sumG = 0;
        $sumB = 0;
        $count = 0;

        foreach ($regions as [$startX, $startY]) {
            for ($y = $startY; $y < $startY + $box; $y++) {
                for ($x = $startX; $x < $startX + $box; $x++) {
                    $rgb = $this->getRgbAt($img, $x, $y);
                    $sumR += $rgb[0];
                    $sumG += $rgb[1];
                    $sumB += $rgb[2];
                    $count++;
                }
            }
        }

        if ($count <= 0) {
            return [240, 220, 230];
        }

        return [
            (int) round($sumR / $count),
            (int) round($sumG / $count),
            (int) round($sumB / $count),
        ];
    }

    /**
     * @param resource|\GdImage $img
     * @return array{0:int,1:int,2:int}
     */
    private function getRgbAt($img, int $x, int $y): array
    {
        $value = imagecolorat($img, $x, $y);

        return [
            ($value >> 16) & 0xFF,
            ($value >> 8) & 0xFF,
            $value & 0xFF,
        ];
    }

    /**
     * @param array{0:int,1:int,2:int} $a
     * @param array{0:int,1:int,2:int} $b
     */
    private function colorDistance(array $a, array $b): float
    {
        $dr = $a[0] - $b[0];
        $dg = $a[1] - $b[1];
        $db = $a[2] - $b[2];

        return sqrt(($dr * $dr) + ($dg * $dg) + ($db * $db));
    }

    private function toJpegPath(string $path): string
    {
        $dir = trim(str_replace('\\', '/', (string) pathinfo($path, PATHINFO_DIRNAME)), '.');
        $name = (string) pathinfo($path, PATHINFO_FILENAME);

        $out = ($dir !== '' ? $dir . '/' : '') . $name . '.jpg';
        return ltrim($out, '/');
    }
}
