<?php

namespace GovStore\Experimentation\Services;

use Illuminate\Http\UploadedFile;

/** Plain fictional evidence for the packages' normal private-upload actions. */
class ExperimentEvidence
{
    public function withPdf(string $label, callable $callback): mixed
    {
        $label = preg_replace('/[^A-Za-z0-9 ._-]/', '', $label);
        $stream = 'BT /F1 12 Tf 40 750 Td (FICTIONAL SAMPLE - NO OFFICIAL AUTHORITY) Tj 0 -24 Td ('.$label.') Tj ET';
        $objects = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>', '<< /Length '.strlen($stream).">>\nstream\n".$stream."\nendstream"];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer << /Size 6 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
        $path = tempnam(sys_get_temp_dir(), 'gs-evidence-');
        try {
            file_put_contents($path, $pdf);

            return $callback(new UploadedFile($path, 'fictional-evidence.pdf', 'application/pdf', null, true));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
