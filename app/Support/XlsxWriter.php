<?php

namespace App\Support;

use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * สร้างไฟล์ Excel (.xlsx) แบบง่าย ๆ สำหรับ export รายงาน
 *
 * ไม่ได้ใช้ PhpSpreadsheet เพราะต้องการแค่ตารางธรรมดา ลงไลบรารีเพิ่มแล้วต้องไปรัน
 * composer บนเซิร์ฟเวอร์อีกรอบ ไฟล์ xlsx เป็นแค่ XML หลายไฟล์ zip รวมกัน เขียนเองได้
 *
 * แถวแรกของทุกชีตเป็นหัวตาราง (ตัวหนา มีพื้นหลัง ตรึงไว้เวลาเลื่อน และมีปุ่มกรอง)
 * ค่าที่เป็น int/float ลงเป็นตัวเลข ที่เหลือลงเป็นข้อความ — เบอร์โทรจึงไม่เสียเลข 0 นำหน้า
 */
class XlsxWriter
{
    /** @var list<array{name: string, rows: list<list<mixed>>, widths: list<int>}> */
    private array $sheets = [];

    /**
     * @param  list<list<mixed>>  $rows  แถวแรกเป็นหัวตาราง
     * @param  list<int>  $widths  ความกว้างคอลัมน์ (หน่วยตัวอักษร) ไม่ระบุ = ค่าตั้งต้นของ Excel
     */
    public function addSheet(string $name, array $rows, array $widths = []): self
    {
        // ชื่อชีตห้ามมีอักขระพิเศษบางตัวและยาวไม่เกิน 31 ตัว
        $name = mb_substr(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $name), 0, 31);
        $this->sheets[] = ['name' => $name, 'rows' => $rows, 'widths' => $widths];

        return $this;
    }

    /** ส่งไฟล์ให้เบราว์เซอร์ดาวน์โหลด แล้วลบไฟล์ชั่วคราวทิ้ง */
    public function download(string $filename): BinaryFileResponse
    {
        return response()->download($this->save(), $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /** เขียนไฟล์ลงโฟลเดอร์ชั่วคราว คืนค่า path */
    public function save(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('สร้างไฟล์ Excel ไม่สำเร็จ');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());

        foreach ($this->sheets as $i => $sheet) {
            $zip->addFromString('xl/worksheets/sheet'.($i + 1).'.xml', $this->worksheet($sheet));
        }

        $zip->close();

        return $path;
    }

    private function contentTypes(): string
    {
        $sheets = '';
        foreach (array_keys($this->sheets) as $i) {
            $sheets .= '<Override PartName="/xl/worksheets/sheet'.($i + 1).'.xml" '
                .'ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$sheets
            .'</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbook(): string
    {
        $sheets = '';
        $names = '';
        foreach ($this->sheets as $i => $sheet) {
            $sheets .= '<sheet name="'.$this->escape($sheet['name']).'" sheetId="'.($i + 1).'" r:id="rId'.($i + 1).'"/>';

            // ปุ่มกรองบนหัวตาราง — Excel ต้องมีชื่อช่วงนี้ไว้ด้วยถึงจะจำได้
            if (count($sheet['rows']) > 1) {
                $names .= '<definedName name="_xlnm._FilterDatabase" localSheetId="'.$i.'" hidden="1">'
                    ."'".$this->escape(str_replace("'", "''", $sheet['name']))."'!".$this->filterRange($sheet, true)
                    .'</definedName>';
            }
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheets.'</sheets>'
            .($names ? '<definedNames>'.$names.'</definedNames>' : '')
            .'</workbook>';
    }

    private function workbookRels(): string
    {
        $rels = '';
        foreach (array_keys($this->sheets) as $i) {
            $rels .= '<Relationship Id="rId'.($i + 1).'" '
                .'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" '
                .'Target="worksheets/sheet'.($i + 1).'.xml"/>';
        }
        $rels .= '<Relationship Id="rId'.(count($this->sheets) + 1).'" '
            .'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>';
    }

    /** สไตล์ 0 = ปกติ, 1 = หัวตาราง (ตัวหนา พื้นฟ้าอ่อน มีเส้นใต้) */
    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2">'
            .'<font><sz val="11"/><name val="Tahoma"/></font>'
            .'<font><b/><sz val="11"/><name val="Tahoma"/></font>'
            .'</fonts>'
            .'<fills count="3">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFE8EEFB"/><bgColor indexed="64"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="2">'
            .'<border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left/><right/><top/><bottom style="thin"><color rgb="FFB8C4DE"/></bottom><diagonal/></border>'
            .'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"><alignment vertical="top"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment vertical="center"/></xf>'
            .'</cellXfs>'
            .'</styleSheet>';
    }

    /** @param array{name: string, rows: list<list<mixed>>, widths: list<int>} $sheet */
    private function worksheet(array $sheet): string
    {
        $cols = '';
        foreach ($sheet['widths'] as $i => $width) {
            $cols .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$width.'" customWidth="1"/>';
        }

        $rows = '';
        foreach ($sheet['rows'] as $r => $row) {
            $cells = '';
            foreach (array_values($row) as $c => $value) {
                $cells .= $this->cell($this->column($c).($r + 1), $value, $r === 0 ? 1 : 0);
            }
            $rows .= '<row r="'.($r + 1).'">'.$cells.'</row>';
        }

        // ตรึงหัวตารางไว้ เลื่อนลงไปไกลแค่ไหนก็ยังเห็นชื่อคอลัมน์
        $views = '<sheetViews><sheetView workbookViewId="0">'
            .'<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
            .'</sheetView></sheetViews>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .$views
            .($cols ? '<cols>'.$cols.'</cols>' : '')
            .'<sheetData>'.$rows.'</sheetData>'
            .(count($sheet['rows']) > 1 ? '<autoFilter ref="'.$this->filterRange($sheet).'"/>' : '')
            .'</worksheet>';
    }

    private function cell(string $ref, mixed $value, int $style): string
    {
        $s = $style ? ' s="'.$style.'"' : '';

        if ($value === null || $value === '') {
            return $style ? '<c r="'.$ref.'"'.$s.'/>' : '';
        }

        if (is_int($value) || is_float($value)) {
            return '<c r="'.$ref.'"'.$s.'><v>'.$value.'</v></c>';
        }

        return '<c r="'.$ref.'"'.$s.' t="inlineStr"><is><t xml:space="preserve">'
            .$this->escape((string) $value).'</t></is></c>';
    }

    /** @param array{rows: list<list<mixed>>} $sheet */
    private function filterRange(array $sheet, bool $absolute = false): string
    {
        $last = $this->column(max(count($sheet['rows'][0]), 1) - 1);
        $height = count($sheet['rows']);

        return $absolute ? '$A$1:$'.$last.'$'.$height : 'A1:'.$last.$height;
    }

    /** เลขคอลัมน์ (เริ่ม 0) → ตัวอักษร A, B, ..., Z, AA, ... */
    private function column(int $index): string
    {
        $name = '';
        for ($index++; $index > 0; $index = intdiv($index - 1, 26)) {
            $name = chr(65 + ($index - 1) % 26).$name;
        }

        return $name;
    }

    /** ตัดอักขระควบคุมที่ XML ไม่ยอมรับออก ไม่งั้น Excel เปิดไฟล์ไม่ขึ้น */
    private function escape(string $text): string
    {
        $text = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text) ?? '';

        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
