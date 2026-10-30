<?php

namespace App\Services\Payroll;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

/**
 * A payslip as a PDF.
 *
 * The slip itself is the legacy page, so the PDF is the very same document the
 * screen shows rather than a second rendering that could drift from it. Only
 * the print layout is adjusted: the page is built for a browser at 860px, which
 * is wider than A4 and would otherwise spill onto a second sheet.
 */
class PayslipDocument
{

    /** The slip's own HTML, as the screen renders it. */
    public function html(int $payslipId): string
    {
        $get = $_GET;
        $post = $_POST;
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        $_GET = ['id' => $payslipId];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        try {
            ob_start();
            include base_path('payslips.php');

            return (string) ob_get_clean();
        } finally {
            $_GET = $get;
            $_POST = $post;
            $_SERVER['REQUEST_METHOD'] = $method;
        }
    }

    public function pdf(int $payslipId)
    {
        return Pdf::loadHTML($this->localFonts($this->localImages($this->printable($this->html($payslipId)))))
            ->setPaper('a4')
            ->setOption('isRemoteEnabled', true)
            ->setOption('dpi', 96);
    }

    /**
     * Point the Open Sans faces at the files on disk, for the same reason as the
     * images below: outside a browser request the fetch over HTTP fails, and the
     * PDF would fall back to DomPDF's own font.
     */
    protected function localFonts(string $html): string
    {
        return preg_replace_callback("#url\(['\"]?([^'\")]*fonts/open-sans/([\w-]+\.ttf))['\"]?\)#i", function ($match) {
            $file = public_path('fonts/open-sans/' . $match[2]);

            return is_file($file) ? "url('" . str_replace('\\', '/', $file) . "')" : $match[0];
        }, $html);
    }

    /**
     * Point the logo and stamp at the files on disk.
     *
     * Rendered in a browser request the images come over HTTP, but an e-mailed
     * payslip is built outside one — the fetch fails and the slip comes out
     * without its logo. Reading them from disk works either way.
     */
    protected function localImages(string $html): string
    {
        return preg_replace_callback('#src="([^"]+)"#i', function ($match) {
            $url = $match[1];

            if (str_starts_with($url, 'data:')) {
                return $match[0];
            }

            // Anything under this installation's public folder, however it is addressed.
            $relative = ltrim((string) parse_url($url, PHP_URL_PATH), '/');
            $relative = preg_replace('#^' . preg_quote(trim(parse_url(url('/'), PHP_URL_PATH) ?? '', '/'), '#') . '/#', '', $relative);
            $file = public_path($relative);

            if (!is_file($file)) {
                return $match[0];
            }

            return 'src="' . str_replace('\\', '/', $this->scaled($file)) . '"';
        }, $html);
    }

    /**
     * A copy of an image at a sensible size for print.
     *
     * The logo and the stamp are several megapixels; embedded as they are, one
     * payslip came to 2 MB, which is no size for an e-mail attachment. They are
     * scaled once and kept, and rebuilt if the original changes.
     */
    protected function scaled(string $file, int $maxWidth = 600): string
    {
        [$width, $height] = @getimagesize($file) ?: [0, 0];

        if ($width <= $maxWidth || !extension_loaded('gd')) {
            return $file;
        }

        $cacheDir = storage_path('app/payslip-assets');

        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }

        $cached = $cacheDir . '/' . md5($file . filemtime($file) . $maxWidth) . '.png';

        if (is_file($cached)) {
            return $cached;
        }

        $source = @imagecreatefromstring((string) file_get_contents($file));

        if (!$source) {
            return $file;
        }

        $targetHeight = (int) round($height * ($maxWidth / $width));
        $target = imagecreatetruecolor($maxWidth, $targetHeight);

        // Keep transparency, or a logo gains a black background.
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $maxWidth, $targetHeight, $width, $height);
        imagepng($target, $cached, 9);
        imagedestroy($target);
        imagedestroy($source);

        return is_file($cached) ? $cached : $file;
    }

    /** What to call the file: the payslip number, else the employee and period. */
    public function filename(int $payslipId): string
    {
        $slip = DB::table('payslips')->find($payslipId);

        if (!$slip) {
            return 'payslip.pdf';
        }

        $name = $slip->payslip_number
            ? str_replace('/', '-', $slip->payslip_number)
            : trim(($slip->emp_name ?? 'payslip') . ' ' . ($slip->period ?? ''));

        return preg_replace('/[^A-Za-z0-9 _.-]/', '', $name) . '.pdf';
    }

    /**
     * The same document, laid out for paper: the slip fills the sheet instead of
     * overflowing it, and the screen's grey surround is dropped.
     */
    protected function printable(string $html): string
    {
        // A template that sets its own page size has been laid out for paper
        // already, so the rules below — written for the one that was not —
        // would only fight it: they replace its @page margin and force an 11px
        // body over a design set at 10px.
        //
        // What it does need is the screen decoration flattened. DomPDF applies
        // @media screen as readily as anything else, so a tinted desk and a
        // fixed-width sheet with its own inner margin reach the PDF and push a
        // one-page payslip onto two. This is the only place that knows the
        // output is paper.
        if (preg_match('/@page\s*\{[^}]*size\s*:/i', $html)) {
            $flatten = '<style>'
                . '.desk{background:#fff !important;padding:0 !important}'
                . '.sheet{width:auto !important;margin:0 !important;padding:0 !important;box-shadow:none !important}'
                . '</style>';

            return str_contains($html, '</head>')
                ? str_replace('</head>', $flatten . '</head>', $html)
                : $flatten . $html;
        }

        $print = <<<'CSS'
<style>
    @page { margin: 8mm; }
    body { background: #fff !important; padding: 0 !important; margin: 0 !important; font-size: 11px; }
    .payslip { width: 100% !important; max-width: 100% !important; border-width: 1px !important; }
    .actions, .no-print { display: none !important; }
    table { page-break-inside: auto; }
    tr { page-break-inside: avoid; }
</style>
CSS;

        return str_contains($html, '</head>')
            ? str_replace('</head>', $print . '</head>', $html)
            : $print . $html;
    }

}
