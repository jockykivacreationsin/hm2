<?php

namespace App\Services\Pdf;

use Illuminate\Support\Facades\DB;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use App\Support\CurrentUser;

/**
 * The one mPDF entry point for every PDF report: explicit paper size in mm, stylesheets from public/assets/css pushed
 * through WriteHTML(css, HEADER_CSS), then the markup. Relative "assets/..." image paths resolve against public/.
 * Reports use standard() / standardView() (A4 or HALF, shared report-standard.css). See docs/PDF_REPORTS.md.
 */
class LegacyReportRenderer
{
    public const A4   = [210, 297];
    public const HALF = [148, 210];

    /**
     * @param  string|array<int,string>  $html  one document, or several written one after another
     * @param  array<int,string>         $css   stylesheet file names inside public/assets/css, in load order
     * @param  array<string,mixed>       $config extra mPDF constructor options
     */
    public function render(string|array $html, array $css = ['reports.css'], array|string $format = self::A4, array $config = []): string
    {
        $mpdf = $this->make($format, $config);

        foreach ($css as $file) {
            $path = public_path('assets/css/' . $file);
            if (is_file($path)) {
                $mpdf->WriteHTML(file_get_contents($path), HTMLParserMode::HEADER_CSS);
            }
        }

        foreach ((array) $html as $chunk) {
            $mpdf->WriteHTML($chunk);
        }

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /**
     * Standard report: A4, 10mm margins, report-standard.css (the shared font sizes/colours for every PDF report).
     * Use with views that extend 'pdf.layouts.standard'. See docs/PDF_REPORTS.md.
     */
    public function standard(string $html, array $config = []): string
    {
        return $this->render($html, ['report-standard.css'], self::A4, array_merge([
            'margin_left' => 10, 'margin_right' => 10, 'margin_top' => 10, 'margin_bottom' => 15,
            'margin_footer' => 5,   // "Page x of y" footer printed by the standard layout
        ], $config));
    }

    /**
     * Render a Blade view that extends 'pdf.layouts.standard' straight to PDF bytes: adds $settings (hospital header) and
     * the signer, then runs it through standard(). Used by the ABDM FHIR PDFs, which have no controller/session
     * (the signer is then empty). See docs/PDF_REPORTS.md.
     *
     * @param  array<string,mixed>  $data
     * @param  array<string,mixed>  $config  extra mPDF options (e.g. ['format' => self::HALF])
     */
    public function standardView(string $view, array $data = [], array $config = []): string
    {
        $html = view($view, array_merge(
            ['settings' => (array) $this->settings()],
            $data,
            $this->signer(),
        ))->render();

        return $this->standard($html, $config);
    }

    public function make(array|string $format = self::A4, array $config = []): Mpdf
    {
        $tempDir = storage_path('app/mpdf');
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }

        // Extra font folder: resources/fonts holds Lohit Devanagari (OFL; the bundled DejaVu fonts have no Hindi glyphs).
        // Use it in CSS as font-family: lohitdevanagari (see .rpt-hi in report-standard.css). OTL shaping is required
        // for Devanagari conjuncts and matras. (Noto Sans Devanagari was tried: mPDF cannot parse its GPOS lookups.)
        $defaultFontDirs = (new ConfigVariables())->getDefaults()['fontDir'];
        $defaultFontData = (new FontVariables())->getDefaults()['fontdata'];

        $mpdf = new Mpdf(array_merge([
            'mode'        => 'utf-8',
            'format'      => $format,
            'orientation' => 'P',
            'tempDir'     => $tempDir,
            'fontDir'     => array_merge($defaultFontDirs, [resource_path('fonts')]),
            'fontdata'    => $defaultFontData + [
                'lohitdevanagari' => [
                    'R' => 'Lohit-Devanagari.ttf',
                    'B' => 'Lohit-Devanagari.ttf',
                    'I' => 'Lohit-Devanagari.ttf',
                    'BI' => 'Lohit-Devanagari.ttf',
                    'useOTL' => 0xFF,
                ],
            ],
        ], $config));

        // A missing logo/image must not abort the whole report.
        $mpdf->showImageErrors = false;
        $mpdf->SetBasePath(public_path() . DIRECTORY_SEPARATOR);

        return $mpdf;
    }

    /** Signature block values of the logged-in user (legacy db.php: $sign_name / $sign_designation). */
    public function signer(): array
    {
        $id = (int) CurrentUser::id() ?? 0;
        $row = $id > 0
            ? DB::table('staff_members')
                ->join('designations', 'designations.id', '=', 'staff_members.designation_id')
                ->where('staff_members.id', $id)
                ->first(['staff_members.staff_name', 'designations.designation'])
            : null;

        return [
            'sign_name'        => $row->staff_name ?? '',
            'sign_designation' => $row->designation ?? '',
        ];
    }

    /** Hospital settings row (legacy db.php: $hospital_name, $hospital_address, $phone_number, $mobile_number ...). */
    public function settings(): object
    {
        return DB::table('settings')->where('id', 1)->first() ?? (object) [
            'hospital_name' => '', 'address' => '', 'phone_number' => '', 'mobile_number' => '',
            'slogan_hospital' => '', 'logo' => '',
        ];
    }

    /**
     * Rich-text editor content (symptoms / remarks / notes) is stored HTML-escaped; legacy printed it with
     * html_entity_decode() and no filtering. Decode it, but only keep basic formatting tags so stored markup can't
     * pull remote/local resources into the PDF.
     */
    public static function richText(?string $html): string
    {
        $decoded = html_entity_decode((string) $html, ENT_QUOTES, 'UTF-8');

        return strip_tags($decoded, '<p><br><b><strong><i><em><u><ul><ol><li><span><div><h1><h2><h3><h4><h5><h6><table><thead><tbody><tr><td><th><sub><sup><hr>');
    }
}
