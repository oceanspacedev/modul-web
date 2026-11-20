<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Penulis file Word (.docx) sederhana untuk ekspor Dokumen Builder: judul, paragraf, poin, langkah bernomor, dan
 * tabel dua kolom. Cukup untuk dokumen teks satu kolom tanpa perlu paket tambahan; file kecil, jadi aman dibuat di
 * dalam request.
 */
final class DocxWriter
{
    /** @var list<string> */
    private array $body = [];

    public function heading(string $text, int $size = 28, string $align = 'left'): self
    {
        $this->body[] = $this->p($this->runs($text, bold: true, size: $size), "<w:keepNext/><w:spacing w:before=\"240\" w:after=\"120\"/><w:jc w:val=\"{$this->align($align)}\"/>");

        return $this;
    }

    public function paragraph(string $text, bool $bold = false, int $size = 22, string $align = 'both'): self
    {
        $this->body[] = $this->p($this->runs($text, $bold, $size), "<w:spacing w:after=\"120\"/><w:jc w:val=\"{$this->align($align)}\"/>");

        return $this;
    }

    public function bullet(string $text): self
    {
        $this->body[] = $this->p($this->runs("•\t".$text), '<w:tabs><w:tab w:val="left" w:pos="720"/></w:tabs><w:spacing w:after="60"/><w:ind w:left="720" w:hanging="360"/>');

        return $this;
    }

    /** Butir berjudul, mis. "Peran: tanggung jawab"; bernomor bila $number diisi. */
    public function item(string $title, string $text, ?int $number = null): self
    {
        $marker = $number !== null ? $number.'.' : '•';
        $runs = $this->runs($marker."\t");

        if ($title !== '') {
            $runs .= $this->runs($title.($text !== '' ? ': ' : ''), bold: true);
        }
        if ($text !== '') {
            $runs .= $this->runs($text);
        }

        $this->body[] = $this->p($runs, '<w:tabs><w:tab w:val="left" w:pos="720"/></w:tabs><w:spacing w:after="80"/><w:ind w:left="720" w:hanging="360"/><w:jc w:val="both"/>');

        return $this;
    }

    /**
     * Tabel dua kolom berbingkai (label tebal di kiri).
     *
     * @param  array<string, string>  $rows
     */
    public function table(array $rows): self
    {
        $border = '<w:top w:val="single" w:sz="6" w:color="000000"/><w:left w:val="single" w:sz="6" w:color="000000"/><w:bottom w:val="single" w:sz="6" w:color="000000"/><w:right w:val="single" w:sz="6" w:color="000000"/><w:insideH w:val="single" w:sz="6" w:color="000000"/><w:insideV w:val="single" w:sz="6" w:color="000000"/>';

        $xml = '<w:tbl><w:tblPr><w:tblW w:w="5000" w:type="pct"/><w:tblBorders>'.$border.'</w:tblBorders><w:tblCellMar><w:left w:w="100" w:type="dxa"/><w:right w:w="100" w:type="dxa"/></w:tblCellMar></w:tblPr>'
            .'<w:tblGrid><w:gridCol w:w="2800"/><w:gridCol w:w="6200"/></w:tblGrid>';

        foreach ($rows as $label => $value) {
            $xml .= '<w:tr>'
                .'<w:tc><w:tcPr><w:tcW w:w="2800" w:type="dxa"/></w:tcPr>'.$this->p($this->runs((string) $label, bold: true, size: 20)).'</w:tc>'
                .'<w:tc><w:tcPr><w:tcW w:w="6200" w:type="dxa"/></w:tcPr>'.$this->p($this->runs($value, size: 20)).'</w:tc>'
                .'</w:tr>';
        }

        $this->body[] = $xml.'</w:tbl>';
        $this->body[] = $this->p('');

        return $this;
    }

    public function save(string $path): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('File Word tidak bisa dibuat.');
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            .'</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'</Relationships>');

        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');

        $zip->addFromString('word/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Calibri" w:eastAsia="Calibri"/><w:sz w:val="22"/><w:szCs w:val="22"/><w:lang w:val="id-ID"/></w:rPr></w:rPrDefault>'
            .'<w:pPrDefault><w:pPr><w:spacing w:after="0" w:line="276" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
            .'</w:styles>');

        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
            .implode('', $this->body)
            .'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="708" w:footer="708" w:gutter="0"/></w:sectPr>'
            .'</w:body></w:document>');

        $zip->close();
    }

    private function p(string $runs, string $properties = ''): string
    {
        return '<w:p>'.($properties !== '' ? "<w:pPr>{$properties}</w:pPr>" : '').$runs.'</w:p>';
    }

    /** Teks satu run; baris baru jadi <w:br/>, tab jadi <w:tab/>. */
    private function runs(string $text, bool $bold = false, ?int $size = null): string
    {
        $props = ($bold ? '<w:b/>' : '').($size ? "<w:sz w:val=\"{$size}\"/><w:szCs w:val=\"{$size}\"/>" : '');
        $props = $props !== '' ? "<w:rPr>{$props}</w:rPr>" : '';

        $parts = [];
        foreach (preg_split('/\R/', $text) as $i => $line) {
            $segments = array_map(
                fn ($segment) => '<w:t xml:space="preserve">'.$this->escape($segment).'</w:t>',
                explode("\t", $line)
            );
            $parts[] = ($i > 0 ? '<w:br/>' : '').implode('<w:tab/>', $segments);
        }

        return "<w:r>{$props}".implode('', $parts).'</w:r>';
    }

    private function escape(string $text): string
    {
        // Buang karakter kontrol yang tidak sah di XML.
        $text = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text) ?? '';

        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function align(string $align): string
    {
        return in_array($align, ['left', 'center', 'right', 'both'], true) ? $align : 'left';
    }
}
