<?php

/**
 * Master dispatcher — detects the file type by extension and routes to
 * the correct extractor. Returns a plain-text string, or empty string
 * on failure.
 *
 * @param string $filePath  Absolute path to the uploaded file on disk.
 * @return string           Extracted plain text.
 */
function edm_extract_text(string $filePath): string
{
    if (!file_exists($filePath)) {
        return '';
    }

    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

    switch ($ext) {
        case 'pdf':
            return edm_extract_pdf($filePath);
        case 'docx':
            return edm_extract_docx($filePath);
        case 'xlsx':
        case 'xls':
            return edm_extract_xlsx($filePath);
        case 'txt':
        case 'csv':
            return edm_extract_plaintext($filePath);
        default:
            return '';
    }
}

/**
 * Extracts text from a PDF using the system's pdftotext binary (poppler-utils).
 * Falls back to a basic byte-scrape if pdftotext is not installed.
 *
 * @param string $filePath  Absolute path to the PDF file.
 * @return string           Extracted plain text.
 */
function edm_extract_pdf(string $filePath): string
{
    $pdftotext = trim(shell_exec('which pdftotext 2>/dev/null') ?? '');

    if ($pdftotext !== '') {
        $escaped = escapeshellarg($filePath);
        $output  = shell_exec("$pdftotext -enc UTF-8 $escaped - 2>/dev/null");
        if ($output !== null && trim($output) !== '') {
            return $output;
        }
    }

    // Fallback: scrape readable strings directly from the PDF bytes
    $content = file_get_contents($filePath);
    if ($content === false) {
        return '';
    }

    $text = '';
    preg_match_all('/BT\s+(.*?)\s+ET/s', $content, $matches);
    foreach ($matches[1] as $block) {
        preg_match_all('/\((.*?)\)/', $block, $strings);
        foreach ($strings[1] as $str) {
            $text .= $str . ' ';
        }
    }

    preg_match_all('/[^\x00-\x1F\x80-\xFF]{4,}/', $content, $plain);
    $text .= implode(' ', $plain[0]);

    return trim($text);
}

/**
 * Extracts text from a .docx file by unzipping it and reading
 * word/document.xml, then stripping all XML tags.
 *
 * @param string $filePath  Absolute path to the .docx file.
 * @return string           Extracted plain text.
 */
function edm_extract_docx(string $filePath): string
{
    $zip = new ZipArchive();

    if ($zip->open($filePath) !== true) {
        return '';
    }

    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    if ($xml === false) {
        return '';
    }

    $xml  = preg_replace('/<\/w:p>/', "\n", $xml);
    $xml  = preg_replace('/<w:br[^>]*>/', ' ', $xml);
    $text = strip_tags($xml);

    return trim(preg_replace('/\s+/', ' ', $text));
}

/**
 * Extracts text from a .xlsx file by reading the shared string table
 * and all worksheet cell values from the zip archive.
 *
 * @param string $filePath  Absolute path to the .xlsx file.
 * @return string           Extracted plain text.
 */
function edm_extract_xlsx(string $filePath): string
{
    $zip = new ZipArchive();

    if ($zip->open($filePath) !== true) {
        return '';
    }

    $strings   = [];
    $text      = '';

    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $xml = simplexml_load_string($sharedXml);
        if ($xml) {
            foreach ($xml->si as $si) {
                $strings[] = strip_tags($si->asXML());
            }
        }
    }

    $i = 0;
    while (($sheetXml = $zip->getFromName("xl/worksheets/sheet{$i}.xml")) !== false ||
           ($sheetXml = $zip->getFromName("xl/worksheets/sheet" . ($i + 1) . ".xml")) !== false) {
        $xml = simplexml_load_string($sheetXml);
        if ($xml) {
            foreach ($xml->sheetData->row ?? [] as $row) {
                foreach ($row->c ?? [] as $cell) {
                    $type  = (string)($cell['t'] ?? '');
                    $value = (string)($cell->v ?? '');

                    if ($type === 's' && isset($strings[(int)$value])) {
                        $text .= $strings[(int)$value] . ' ';
                    } elseif ($type === 'inlineStr') {
                        $text .= strip_tags($cell->is->asXML()) . ' ';
                    } else {
                        $text .= $value . ' ';
                    }
                }
            }
        }
        $i++;
        if ($i > 100) break;
    }

    $zip->close();

    return trim(preg_replace('/\s+/', ' ', $text . ' ' . implode(' ', $strings)));
}

/**
 * Extracts text from plain text files (.txt, .csv).
 *
 * @param string $filePath  Absolute path to the text file.
 * @return string           File contents as a string.
 */
function edm_extract_plaintext(string $filePath): string
{
    $content = file_get_contents($filePath);
    return $content !== false ? trim($content) : '';
}