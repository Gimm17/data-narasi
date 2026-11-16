<?php

namespace App\Services\Analysis;

/**
 * ChartCanvas — primitif menggambar PNG memakai ekstensi GD.
 *
 * Menggantikan matplotlib. Digambar pada resolusi 2× lalu diperkecil
 * dengan bicubic saat disimpan, sehingga garis dan teks tampak
 * antialiased (GD tidak punya antialiasing untuk garis lurus).
 *
 * Semua koordinat dan ukuran font yang dipakai pemanggil berada di ruang
 * logis 1×; kelas ini yang mengalikannya dengan skala render.
 */
class ChartCanvas
{
    /** Faktor supersampling saat menggambar. */
    private const RENDER_SCALE = 2;

    /** Warna latar default donat (lubang tengah). */
    private const BG_COLOR = '#FAFBFC';

    public \GdImage $img;

    /** Lebar logis (bukan lebar piksel buffer). */
    public int $w;

    /** Tinggi logis (bukan tinggi piksel buffer). */
    public int $h;

    private int $s;

    /** @var array<string, int> cache warna agar tidak alokasi berulang */
    private array $colors = [];

    private string $fontRegular;

    private string $fontBold;

    public function __construct(int $width, int $height, ?string $background = null)
    {
        if (!extension_loaded('gd')) {
            throw new \RuntimeException('Ekstensi PHP GD belum aktif. Aktifkan extension=gd di php.ini.');
        }

        $this->w = $width;
        $this->h = $height;
        $this->s = self::RENDER_SCALE;

        $this->img = imagecreatetruecolor($width * $this->s, $height * $this->s);
        imagealphablending($this->img, true);
        imagesavealpha($this->img, true);

        $fontDir = resource_path('fonts');
        $this->fontRegular = $fontDir . DIRECTORY_SEPARATOR . 'DejaVuSans.ttf';
        $this->fontBold = $fontDir . DIRECTORY_SEPARATOR . 'DejaVuSans-Bold.ttf';

        if (!is_file($this->fontRegular)) {
            // Fallback ke font sistem bila aset project tidak tersedia
            $this->fontRegular = $this->systemFont(['arial.ttf', 'DejaVuSans.ttf', 'LiberationSans-Regular.ttf']);
            $this->fontBold = $this->systemFont(['arialbd.ttf', 'DejaVuSans-Bold.ttf', 'LiberationSans-Bold.ttf']);
        }

        $this->filledRect(0, 0, $width, $height, $background ?? '#FAFBFC');
    }

    /**
     * Cari font TTF di lokasi standar OS sebagai fallback.
     *
     * @param array<int, string> $candidates
     */
    private function systemFont(array $candidates): string
    {
        $dirs = [
            'C:\\Windows\\Fonts',
            '/usr/share/fonts/truetype/dejavu',
            '/usr/share/fonts/truetype/liberation',
            '/usr/share/fonts/TTF',
            '/usr/local/share/fonts',
            '/usr/share/fonts',
        ];

        foreach ($dirs as $dir) {
            foreach ($candidates as $file) {
                $path = $dir . DIRECTORY_SEPARATOR . $file;
                if (is_file($path)) {
                    return $path;
                }
            }
        }

        throw new \RuntimeException(
            'Font TTF tidak ditemukan. Pastikan resources/fonts/DejaVuSans.ttf ada dan ikut ter-upload ke server.'
        );
    }

    /**
     * Alokasi (atau ambil dari cache) warna dari kode hex.
     *
     * @param int|null $alpha 0 = opaque, 127 = transparan
     */
    public function color(string $hex, ?int $alpha = null): int
    {
        $key = $hex . '|' . ($alpha ?? 'x');
        if (isset($this->colors[$key])) {
            return $this->colors[$key];
        }

        [$r, $g, $b] = self::rgb($hex);

        $this->colors[$key] = $alpha === null
            ? imagecolorallocate($this->img, $r, $g, $b)
            : imagecolorallocatealpha($this->img, $r, $g, $b, max(0, min(127, $alpha)));

        return $this->colors[$key];
    }

    /** @return array{0:int, 1:int, 2:int} */
    public static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Apakah warna cukup terang sehingga teks di atasnya harus gelap?
     */
    public static function isLightColor(string $hex): bool
    {
        [$r, $g, $b] = self::rgb($hex);

        return (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255 > 0.62;
    }

    /**
     * Tick sumbu "bulat" (1/2/5 × 10^n) untuk rentang tertentu.
     *
     * @return array<int, float>
     */
    public static function ticks(float $min, float $max, int $count = 5): array
    {
        if ($max <= $min) {
            $max = $min + 1;
        }

        $rawStep = ($max - $min) / max($count, 1);
        $magnitude = 10 ** floor(log10(max($rawStep, 1e-9)));
        $normalized = $rawStep / $magnitude;

        $step = match (true) {
            $normalized <= 1 => 1.0,
            $normalized <= 2 => 2.0,
            $normalized <= 5 => 5.0,
            default => 10.0,
        } * $magnitude;

        $start = floor($min / $step) * $step;
        $ticks = [];
        for ($v = $start; $v <= $max + $step * 0.5; $v += $step) {
            $ticks[] = round($v, 10);
        }

        return $ticks;
    }

    /**
     * Irisan donat: busur luar + potongan cincin dalam.
     *
     * @param float $startDeg sudut mulai (0° = jam 3, searah jarum jam)
     * @param float $endDeg sudut akhir
     */
    public function donutSlice(
        float $cx,
        float $cy,
        float $outerRadius,
        float $innerRadius,
        float $startDeg,
        float $endDeg,
        string $hex
    ): void {
        $s = $this->s;
        $color = $this->color($hex);

        imagefilledarc(
            $this->img,
            (int) round($cx * $s),
            (int) round($cy * $s),
            (int) round($outerRadius * 2 * $s),
            (int) round($outerRadius * 2 * $s),
            (int) round($startDeg),
            (int) round($endDeg),
            $color,
            IMG_ARC_PIE
        );

        imagefilledarc(
            $this->img,
            (int) round($cx * $s),
            (int) round($cy * $s),
            (int) round($innerRadius * 2 * $s),
            (int) round($innerRadius * 2 * $s),
            (int) round($startDeg),
            (int) round($endDeg),
            $this->color(self::BG_COLOR),
            IMG_ARC_PIE
        );
    }

    public function fontPath(bool $bold = false): string
    {
        return $bold ? $this->fontBold : $this->fontRegular;
    }

    /**
     * Lebar teks dalam piksel logis.
     */
    public function textWidth(string $text, int $size, bool $bold = false): float
    {
        if ($text === '') {
            return 0.0;
        }

        $box = imagettfbbox($size * $this->s, 0, $this->fontPath($bold), $text);
        if ($box === false) {
            return strlen($text) * $size * 0.55;
        }

        return abs($box[2] - $box[0]) / $this->s;
    }

    /**
     * Tinggi teks dalam piksel logis (dipakai untuk tata letak vertikal).
     */
    public function textHeight(int $size): float
    {
        $box = imagettfbbox($size * $this->s, 0, $this->fontPath(false), 'Ag');

        return $box === false ? (float) $size : abs($box[1] - $box[7]) / $this->s;
    }

    /**
     * Tulis teks. ($x, $y) adalah baseline; $align = left|center|right.
     */
    public function text(
        int $size,
        int|float $x,
        int|float $y,
        string $text,
        string $hex,
        string $align = 'left',
        bool $bold = false
    ): void {
        if ($text === '') {
            return;
        }

        $px = (int) round($x * $this->s);
        if ($align !== 'left') {
            $width = $this->textWidth($text, $size, $bold) * $this->s;
            $px -= (int) round($align === 'center' ? $width / 2 : $width);
        }

        imagettftext(
            $this->img,
            $size * $this->s,
            0,
            $px,
            (int) round($y * $this->s),
            $this->color($hex),
            $this->fontPath($bold),
            $text
        );
    }

    /**
     * Tulis teks yang dipotong dengan elipsis jika melebihi lebar maksimum.
     */
    public function textTruncated(
        int $size,
        int|float $x,
        int|float $y,
        string $text,
        string $hex,
        float $maxWidth,
        string $align = 'left',
        bool $bold = false
    ): void {
        $this->text($size, $x, $y, $this->truncate($text, $size, $maxWidth, $bold), $hex, $align, $bold);
    }

    /**
     * Potong teks agar muat dalam lebar tertentu, tambahkan "…" bila terpotong.
     */
    public function truncate(string $text, int $size, float $maxWidth, bool $bold = false): string
    {
        if ($maxWidth <= 0 || $this->textWidth($text, $size, $bold) <= $maxWidth) {
            return $text;
        }

        $ellipsis = '…';
        $budget = $maxWidth - $this->textWidth($ellipsis, $size, $bold);
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        while ($chars !== [] && $this->textWidth(implode('', $chars), $size, $bold) > $budget) {
            array_pop($chars);
        }

        return rtrim(implode('', $chars)) . $ellipsis;
    }

    /**
     * Label sumbu-x yang diputar 45° dan berakhir tepat di titik tick.
     *
     * Teks membaca dari kiri-bawah ke kanan-atas, dengan ujung kanan
     * menyentuh tick agar tidak menabrak area plot.
     */
    public function rotatedLabel(int $size, float $tickX, float $bottomY, string $text, string $hex, float $maxWidth = 150.0): void
    {
        if ($text === '') {
            return;
        }

        $text = $this->truncate($text, $size, $maxWidth);
        $width = $this->textWidth($text, $size);

        // Ujung teks diletakkan di bawah tick; teks memanjang ke kiri-bawah
        $startX = $tickX - $width * 0.707;
        $startY = $bottomY + $width * 0.707 + $size * 0.35;

        $s = $this->s;
        imagettftext(
            $this->img,
            $size * $s,
            45,
            (int) round($startX * $s),
            (int) round($startY * $s),
            $this->color($hex),
            $this->fontPath(false),
            $text
        );
    }

    /**
     * Tulis teks sumbu-y yang diputar 90° menghadap ke luar (judul sumbu).
     */
    public function verticalAxisTitle(int $size, float $centerX, float $centerY, string $text, string $hex): void
    {
        if ($text === '') {
            return;
        }

        $s = $this->s;
        $width = $this->textWidth($text, $size);

        imagettftext(
            $this->img,
            $size * $s,
            90,
            (int) round(($centerX + $size * 0.35) * $s),
            (int) round(($centerY + $width / 2) * $s),
            $this->color($hex),
            $this->fontPath(true),
            $text
        );
    }

    public function line(int|float $x1, int|float $y1, int|float $x2, int|float $y2, string $hex, int $thickness = 1): void    {
        $s = $this->s;
        imagesetthickness($this->img, max(1, $thickness * $s));
        imageline(
            $this->img,
            (int) round($x1 * $s),
            (int) round($y1 * $s),
            (int) round($x2 * $s),
            (int) round($y2 * $s),
            $this->color($hex)
        );
        imagesetthickness($this->img, 1);
    }

    public function dashedLine(
        int|float $x1,
        int|float $y1,
        int|float $x2,
        int|float $y2,
        string $hex,
        int $dash = 7,
        int $gap = 5
    ): void {
        $length = sqrt(($x2 - $x1) ** 2 + ($y2 - $y1) ** 2);
        if ($length <= 0) {
            return;
        }

        $ux = ($x2 - $x1) / $length;
        $uy = ($y2 - $y1) / $length;
        $pos = 0.0;

        while ($pos < $length) {
            $end = min($pos + $dash, $length);
            $this->line($x1 + $ux * $pos, $y1 + $uy * $pos, $x1 + $ux * $end, $y1 + $uy * $end, $hex);
            $pos = $end + $gap;
        }
    }

    public function filledRect(
        int|float $x1,
        int|float $y1,
        int|float $x2,
        int|float $y2,
        string $hex,
        ?int $alpha = null
    ): void {
        $s = $this->s;
        imagefilledrectangle(
            $this->img,
            (int) round($x1 * $s),
            (int) round($y1 * $s),
            (int) round($x2 * $s),
            (int) round($y2 * $s),
            $this->color($hex, $alpha)
        );
    }

    /**
     * Persegi panjang dengan sudut membulat (bar chart modern).
     */
    public function roundedRect(
        int|float $x1,
        int|float $y1,
        int|float $x2,
        int|float $y2,
        float $radius,
        string $hex,
        ?int $alpha = null
    ): void {
        $x1 *= $this->s;
        $y1 *= $this->s;
        $x2 *= $this->s;
        $y2 *= $this->s;
        $r = $radius * $this->s;
        $color = $this->color($hex, $alpha);

        $r = min($r, abs($x2 - $x1) / 2, abs($y2 - $y1) / 2);

        imagefilledrectangle($this->img, (int) ($x1 + $r), (int) $y1, (int) ($x2 - $r), (int) $y2, $color);
        imagefilledrectangle($this->img, (int) $x1, (int) ($y1 + $r), (int) $x2, (int) ($y2 - $r), $color);

        foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) {
            imagefilledellipse($this->img, (int) $cx, (int) $cy, (int) ($r * 2), (int) ($r * 2), $color);
        }
    }

    public function rect(int|float $x1, int|float $y1, int|float $x2, int|float $y2, string $hex): void
    {
        $s = $this->s;
        imagerectangle(
            $this->img,
            (int) round($x1 * $s),
            (int) round($y1 * $s),
            (int) round($x2 * $s),
            (int) round($y2 * $s),
            $this->color($hex)
        );
    }

    public function dot(int|float $x, int|float $y, float $radius, string $hex, ?int $alpha = null): void
    {
        $s = $this->s;
        $d = (int) round($radius * 2 * $s);
        imagefilledellipse($this->img, (int) round($x * $s), (int) round($y * $s), $d, $d, $this->color($hex, $alpha));
    }

    /**
     * Lingkaran berongga (untuk penanda titik pada scatter).
     */
    public function ring(int|float $x, int|float $y, float $radius, string $hex): void
    {
        $s = $this->s;
        $d = (int) round($radius * 2 * $s);
        imageellipse($this->img, (int) round($x * $s), (int) round($y * $s), $d, $d, $this->color($hex));
    }

    /**
     * Isi area poligon (dipakai untuk area fill di bawah garis tren).
     *
     * @param array<int, array{0: float, 1: float}> $points
     */
    public function filledPolygon(array $points, string $hex, ?int $alpha = null): void
    {
        if (count($points) < 3) {
            return;
        }

        $flat = [];
        foreach ($points as $p) {
            $flat[] = (int) round($p[0] * $this->s);
            $flat[] = (int) round($p[1] * $this->s);
        }

        imagefilledpolygon($this->img, $flat, $this->color($hex, $alpha));
    }

    /**
     * Tick sumbu "bulat" (1/2/5 × 10^n) untuk rentang tertentu.
     *
     * @return array<int, float>
     */
    public function niceTicks(float $min, float $max, int $count = 5): array
    {
        if ($max <= $min) {
            $max = $min + 1;
        }

        $rawStep = ($max - $min) / max($count, 1);
        $magnitude = 10 ** floor(log10(max($rawStep, 1e-9)));
        $normalized = $rawStep / $magnitude;

        $step = match (true) {
            $normalized <= 1 => 1.0,
            $normalized <= 2 => 2.0,
            $normalized <= 5 => 5.0,
            default => 10.0,
        } * $magnitude;

        $start = floor($min / $step) * $step;
        $ticks = [];
        for ($v = $start; $v <= $max + $step * 0.5; $v += $step) {
            $ticks[] = round($v, 10);
        }

        return $ticks;
    }

    /**
     * Simpan PNG. Buffer 2× diperkecil ke ukuran logis agar antialiased.
     */
    public function save(string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        $final = imagescale($this->img, $this->w, $this->h, IMG_BICUBIC);
        if ($final === false) {
            $final = $this->img;
        }

        imagepng($final, $path, 6);

        if ($final !== $this->img) {
            imagedestroy($final);
        }
        imagedestroy($this->img);
    }
}
