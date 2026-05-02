<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OcrController extends Controller
{
    /**
     * Accept a base64 passport image, run OCR (Tesseract via exec),
     * parse the MRZ to extract passport fields.
     *
     * POST /api/ocr/passport
     * Body: { "image": "data:image/jpeg;base64,..." }
     */
    public function passport(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'string'],
        ]);

        try {
            // Decode and save image temporarily
            $imageData = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $request->input('image')));
            $tmpFile   = tempnam(sys_get_temp_dir(), 'ocr_') . '.jpg';
            file_put_contents($tmpFile, $imageData);

            // Run Tesseract OCR — use full path for Homebrew
            $tesseract = '/opt/homebrew/bin/tesseract';
            if (!file_exists($tesseract)) {
                // Fallback: try system PATH
                $tesseract = trim(shell_exec('which tesseract 2>/dev/null') ?? 'tesseract');
            }
            $outBase = $tmpFile . '_ocr';
            exec($tesseract . " " . escapeshellarg($tmpFile) . " " . escapeshellarg($outBase) . " -l eng --psm 6 2>&1", $output, $exitCode);
            $ocrText = file_exists($outBase . '.txt') ? file_get_contents($outBase . '.txt') : '';

            // Clean up temp files
            @unlink($tmpFile);
            @unlink($outBase . '.txt');

            if (! $ocrText) {
                return response()->json(['success' => false, 'message' => 'OCR ไม่สามารถอ่านข้อมูลได้']);
            }

            $parsed = $this->parseMRZ($ocrText);

            return response()->json([
                'success' => true,
                'data'    => $parsed,
                'raw'     => $ocrText,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'OCR Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Parse MRZ (Machine Readable Zone) from OCR text.
     * Passport MRZ has 2 lines of 44 characters each.
     * Format: TD3 (international passport)
     *   Line 1: P<{country}{surname}<<{given_names}...
     *   Line 2: {passport_no}{check}{country}{dob}{check}{sex}{expiry}{check}{personal_no...
     */
    protected function parseMRZ(string $text): array
    {
        $result = [
            'firstName'   => '',
            'lastName'    => '',
            'nationality' => '',
            'dob'         => '',
            'expiry'      => '',
            'passportNo'  => '',
            'sex'         => '',
        ];

        // Find MRZ lines: look for 44-char lines that match MRZ pattern
        $lines = array_filter(array_map('trim', explode("\n", $text)));
        $mrzLines = [];
        foreach ($lines as $line) {
            $clean = preg_replace('/\s+/', '', $line);
            $clean = strtoupper($clean);
            if (strlen($clean) >= 40 && preg_match('/^[A-Z0-9<]{40,}$/', $clean)) {
                $mrzLines[] = $clean;
            }
        }

        if (count($mrzLines) >= 2) {
            $line1 = str_pad($mrzLines[0], 44, '<');
            $line2 = str_pad($mrzLines[count($mrzLines) - 1], 44, '<');

            // Line 1: P<{country}{surname}<<{given names}
            if (str_starts_with($line1, 'P')) {
                $namePart  = substr($line1, 5);
                $dblChevron = strpos($namePart, '<<');
                if ($dblChevron !== false) {
                    $result['lastName']  = str_replace('<', ' ', trim(substr($namePart, 0, $dblChevron)));
                    $result['firstName'] = str_replace('<', ' ', trim(substr($namePart, $dblChevron + 2)));
                }
                $result['nationality'] = substr($line1, 2, 3);
            }

            // Line 2: {doc_no 9}{check}{country 3}{dob 6}{check}{sex 1}{expiry 6}{check}
            $result['passportNo'] = rtrim(substr($line2, 0, 9), '<');
            $result['nationality'] = $result['nationality'] ?: rtrim(substr($line2, 10, 3), '<');

            $dob    = substr($line2, 13, 6);
            $expiry = substr($line2, 20, 6);
            $result['sex'] = substr($line2, 20, 1);

            // Convert YYMMDD → YYYY-MM-DD
            if (preg_match('/^\d{6}$/', $dob)) {
                $yy = (int) substr($dob, 0, 2);
                $mm = substr($dob, 2, 2);
                $dd = substr($dob, 4, 2);
                $yyyy = $yy <= (int) date('y') ? "20{$yy}" : "19{$yy}";
                $result['dob'] = "{$yyyy}-{$mm}-{$dd}";
            }
            if (preg_match('/^\d{6}$/', $expiry)) {
                $yy   = (int) substr($expiry, 0, 2);
                $mm   = substr($expiry, 2, 2);
                $dd   = substr($expiry, 4, 2);
                $yyyy = $yy >= 20 ? "20{$yy}" : "20{$yy}";
                $result['expiry'] = "{$yyyy}-{$mm}-{$dd}";
            }
        } else {
            // Fallback: try free-text extraction
            // Look for "Surname / Nom" pattern
            if (preg_match('/(?:surname|nom)[:\s]+([A-Z\s]+)/i', $text, $m)) {
                $result['lastName'] = trim($m[1]);
            }
            if (preg_match('/(?:given name|prénom)[:\s]+([A-Z\s]+)/i', $text, $m)) {
                $result['firstName'] = trim($m[1]);
            }
            if (preg_match('/(?:nationality|nationalité)[:\s]+([A-Z\s]+)/i', $text, $m)) {
                $result['nationality'] = trim($m[1]);
            }
            if (preg_match('/(?:passport no|numéro)[:\s]+([A-Z0-9]+)/i', $text, $m)) {
                $result['passportNo'] = trim($m[1]);
            }
        }

        // Clean up extra spaces
        foreach ($result as &$val) {
            $val = trim(preg_replace('/\s+/', ' ', $val));
        }

        return $result;
    }
}
