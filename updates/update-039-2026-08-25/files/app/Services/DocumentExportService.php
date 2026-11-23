<?php

namespace App\Services;

use Illuminate\Support\Str;

class DocumentExportService
{
    /**
     * Render a Markdown document for download in the given format.
     *
     * @return array{0: string, 1: string} [body, mime type]
     */
    public function export(string $markdown, string $title, string $format): array
    {
        return match ($format) {
            'txt' => [$markdown, 'text/plain; charset=UTF-8'],
            'md' => [$markdown, 'text/markdown; charset=UTF-8'],
            'doc' => [$this->toDocHtml($markdown, $title), 'application/msword; charset=UTF-8'],
            'docx' => [$this->toDocx($markdown), 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            default => throw new \InvalidArgumentException("Unsupported export format: {$format}"),
        };
    }

    /** Word opens HTML directly when served with a .doc extension/mimetype — no binary format needed. */
    protected function toDocHtml(string $markdown, string $title): string
    {
        $html = Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
        $escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="utf-8">
<title>{$escapedTitle}</title>
<!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom><w:DoNotOptimizeForBrowser/></w:WordDocument></xml><![endif]-->
<style>body{font-family:Calibri,Arial,sans-serif;font-size:11pt;} h1,h2,h3{font-family:Calibri,Arial,sans-serif;}</style>
</head>
<body>
{$html}
</body>
</html>
HTML;
    }

    /** Builds a genuine minimal OOXML .docx via PHP's built-in ZipArchive — no external library. */
    protected function toDocx(string $markdown): string
    {
        $bodyXml = $this->markdownToDocxParagraphs($markdown);

        $documentXml = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
<w:body>
{$bodyXml}
<w:sectPr>
<w:pgSz w:w="12240" w:h="15840"/>
<w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/>
</w:sectPr>
</w:body>
</w:document>
XML;

        $contentTypesXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;

        $relsXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        // sys_get_temp_dir() can point somewhere unwritable/nonexistent on a portable
        // Windows PHP install, which makes tempnam() fall back silently and emit a
        // warning Laravel turns into a fatal ErrorException — use our own guaranteed-
        // writable scratch dir under storage/ instead.
        $tmpDir = storage_path('app/tmp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }

        $tmpPath = tempnam($tmpDir, 'docx');

        $zip = new \ZipArchive();
        $zip->open($tmpPath, \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', $contentTypesXml);
        $zip->addFromString('_rels/.rels', $relsXml);
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();

        $bytes = file_get_contents($tmpPath);
        unlink($tmpPath);

        return $bytes;
    }

    /** Line-based Markdown -> OOXML paragraph conversion (headings, checklists, bullets, code, inline bold). */
    protected function markdownToDocxParagraphs(string $markdown): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $markdown);
        $paragraphs = [];
        $inCode = false;

        foreach ($lines as $line) {
            if (preg_match('/^\s*```/', $line)) {
                $inCode = ! $inCode;
                continue;
            }

            if (trim($line) === '') {
                continue;
            }

            if ($inCode) {
                $paragraphs[] = $this->paragraphXml([$this->runXml($line, false, null, true)]);
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.*)$/', $line, $m)) {
                $sizes = [1 => 32, 2 => 28, 3 => 26, 4 => 24, 5 => 22, 6 => 20];
                $sz = $sizes[strlen($m[1])];
                $runs = array_map(fn ($r) => $this->runXml($r['text'], true, $sz), $this->parseInlineRuns($m[2]));
                $paragraphs[] = $this->paragraphXml($runs);
                continue;
            }

            if (preg_match('/^\s*-\s+\[( |x|X)\]\s+(.*)$/', $line, $m)) {
                $mark = strtolower($m[1]) === 'x' ? '☑ ' : '☐ ';
                $runs = [$this->runXml($mark, false), ...$this->runsFor($m[2])];
                $paragraphs[] = $this->paragraphXml($runs, indent: true);
                continue;
            }

            if (preg_match('/^\s*[-*]\s+(.*)$/', $line, $m)) {
                $runs = [$this->runXml('• ', false), ...$this->runsFor($m[1])];
                $paragraphs[] = $this->paragraphXml($runs, indent: true);
                continue;
            }

            $paragraphs[] = $this->paragraphXml($this->runsFor($line));
        }

        return implode("\n", $paragraphs);
    }

    /** @return string[] */
    protected function runsFor(string $text): array
    {
        return array_map(fn ($r) => $this->runXml($r['text'], $r['bold']), $this->parseInlineRuns($text));
    }

    /** Splits on **bold** markers, returning alternating normal/bold text segments. */
    protected function parseInlineRuns(string $text): array
    {
        $parts = preg_split('/\*\*(.+?)\*\*/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $runs = [];
        foreach ($parts as $i => $part) {
            if ($part === '') {
                continue;
            }
            $runs[] = ['text' => $part, 'bold' => $i % 2 === 1];
        }

        return $runs ?: [['text' => '', 'bold' => false]];
    }

    protected function runXml(string $text, bool $bold, ?int $sz = null, bool $mono = false): string
    {
        $rPr = '';
        if ($bold || $sz || $mono) {
            $rPr .= '<w:rPr>';
            if ($bold) {
                $rPr .= '<w:b/>';
            }
            if ($mono) {
                $rPr .= '<w:rFonts w:ascii="Consolas" w:hAnsi="Consolas"/>';
            }
            if ($sz) {
                $rPr .= "<w:sz w:val=\"{$sz}\"/><w:szCs w:val=\"{$sz}\"/>";
            }
            $rPr .= '</w:rPr>';
        }

        $escaped = htmlspecialchars($text, ENT_XML1 | ENT_COMPAT, 'UTF-8');

        return "<w:r>{$rPr}<w:t xml:space=\"preserve\">{$escaped}</w:t></w:r>";
    }

    /** @param  string[]  $runsXml */
    protected function paragraphXml(array $runsXml, bool $indent = false): string
    {
        $pPr = '<w:pPr><w:spacing w:after="120"/>' . ($indent ? '<w:ind w:left="360"/>' : '') . '</w:pPr>';

        return '<w:p>' . $pPr . implode('', $runsXml) . '</w:p>';
    }
}
