<?php
require_once __DIR__ . '/includes/layout.php';
$user = require_admin();

$db = get_db();
$currentYear = (int)date('Y');
$currentMonth = (int)date('m');

// Show current month + 2 previous months (3 total)
$months = [];
for ($i = 0; $i < 3; $i++) {
    $m = $currentMonth - $i;
    $y = $currentYear;
    if ($m < 1) { $m += 12; $y--; }
    $months[] = ['year' => $y, 'month' => $m];
}

$monthNames = ['','Styczeń','Luty','Marzec','Kwiecień','Maj','Czerwiec','Lipiec','Sierpień','Wrzesień','Październik','Listopad','Grudzień'];
$dayNames = ['Nd','Pn','Wt','Śr','Cz','Pt','So'];

function pick_pdf_font_path(string $weight = 'regular'): ?string {
    $candidates = $weight === 'bold'
        ? ['/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf']
        : ['/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', '/usr/share/fonts/dejavu/DejaVuSans.ttf'];

    foreach ($candidates as $font) {
        if (is_file($font)) return $font;
    }
    return null;
}

function build_pdf_from_jpegs(array $images, int $imgWidth, int $imgHeight): string {
    $objects = [];
    $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";

    $pageIds = [];
    $contentIds = [];
    $imageIds = [];
    $nextId = 3;

    foreach ($images as $jpeg) {
        $imageId = $nextId++;
        $contentId = $nextId++;
        $pageId = $nextId++;

        $imageIds[] = $imageId;
        $contentIds[] = $contentId;
        $pageIds[] = $pageId;

        $objects[$imageId] = "<< /Type /XObject /Subtype /Image /Width {$imgWidth} /Height {$imgHeight} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($jpeg) . " >>\nstream\n" . $jpeg . "\nendstream";
        $stream = "q\n595 0 0 842 0 0 cm\n/Im" . $imageId . " Do\nQ";
        $objects[$contentId] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
        $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /XObject << /Im" . $imageId . " " . $imageId . " 0 R >> >> /Contents " . $contentId . " 0 R >>";
    }

    $kids = implode(' ', array_map(static fn($id) => $id . ' 0 R', $pageIds));
    $objects[2] = "<< /Type /Pages /Kids [ {$kids} ] /Count " . count($pageIds) . " >>";

    ksort($objects);
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $id => $body) {
        $offsets[$id] = strlen($pdf);
        $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $maxId = max(array_keys($objects));
    $pdf .= "xref\n0 " . ($maxId + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= $maxId; $i++) {
        $off = $offsets[$i] ?? 0;
        $pdf .= sprintf("%010d 00000 n \n", $off);
    }
    $pdf .= "trailer\n<< /Size " . ($maxId + 1) . " /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
}

function pdf_safe_text(string $text): string {
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    if ($ascii === false) {
        $ascii = $text;
    }
    return str_replace(["\\", "(", ")"], ["\\\\", "\(", "\)"], $ascii);
}

function build_text_only_pdf(array $lines, int $maxLinesPerPage = 48): string {
    $chunks = array_chunk($lines, $maxLinesPerPage);
    $objects = [];
    $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";

    $pageObjectIds = [];
    $contentObjectIds = [];
    $fontObjectId = 3;
    $nextObjectId = 4;

    foreach ($chunks as $chunk) {
        $stream = "BT\n/F1 10 Tf\n40 800 Td\n14 TL\n";
        foreach ($chunk as $line) {
            $stream .= '(' . pdf_safe_text($line) . ") Tj\nT*\n";
        }
        $stream .= "ET";

        $contentId = $nextObjectId++;
        $pageId = $nextObjectId++;
        $contentObjectIds[] = $contentId;
        $pageObjectIds[] = $pageId;
        $objects[$contentId] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
    }

    $kids = implode(' ', array_map(static fn($id) => $id . ' 0 R', $pageObjectIds));
    $objects[2] = "<< /Type /Pages /Kids [ {$kids} ] /Count " . count($pageObjectIds) . " >>";
    $objects[$fontObjectId] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";

    foreach ($pageObjectIds as $idx => $pageId) {
        $contentId = $contentObjectIds[$idx];
        $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 {$fontObjectId} 0 R >> >> /Contents {$contentId} 0 R >>";
    }

    ksort($objects);
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $id => $body) {
        $offsets[$id] = strlen($pdf);
        $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $maxId = max(array_keys($objects));
    $pdf .= "xref\n0 " . ($maxId + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= $maxId; $i++) {
        $off = $offsets[$i] ?? 0;
        $pdf .= sprintf("%010d 00000 n \n", $off);
    }
    $pdf .= "trailer\n<< /Size " . ($maxId + 1) . " /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
}

// Fetch kino entries for all 3 months
$allKino = [];
foreach ($months as $mp) {
    $y = $mp['year']; $m = $mp['month'];
    $dim = cal_days_in_month(CAL_GREGORIAN, $m, $y);
    $from = sprintf('%04d-%02d-01', $y, $m);
    $to = sprintf('%04d-%02d-%02d', $y, $m, $dim);
    $stmt = $db->prepare("SELECT se.*, u.full_name, u.department FROM schedule_entries se JOIN users u ON u.id=se.user_id WHERE se.shift_type='kino' AND se.entry_date BETWEEN ? AND ? ORDER BY se.entry_date, u.full_name");
    $stmt->execute([$from, $to]);
    $entries = $stmt->fetchAll();

    // Group by employee
    $byEmployee = [];
    foreach ($entries as $e) {
        $uid = $e['user_id'];
        if (!isset($byEmployee[$uid])) {
            $byEmployee[$uid] = ['name' => $e['full_name'], 'dept' => $e['department'], 'days' => [], 'total_hours' => 0];
        }
        $hours = calc_hours($e['shift_start'], $e['shift_end']);
        $byEmployee[$uid]['days'][] = [
            'date' => $e['entry_date'],
            'start' => $e['shift_start'],
            'end' => $e['shift_end'],
            'hours' => $hours
        ];
        $byEmployee[$uid]['total_hours'] += $hours;
    }

    $allKino[] = [
        'year' => $y, 'month' => $m, 'dim' => $dim,
        'label' => $monthNames[$m] . ' ' . $y,
        'employees' => $byEmployee,
        'total_entries' => count($entries)
    ];
}

if (($_GET['download'] ?? '') === 'pdf') {
    $fontRegular = pick_pdf_font_path('regular');
    $fontBold = pick_pdf_font_path('bold') ?? $fontRegular;

    $canRenderPrettyPdf = $fontRegular && $fontBold && function_exists('imagecreatetruecolor') && function_exists('imagettftext');

    if (!$canRenderPrettyPdf) {
        $lines = [
            'Rozliczenie kina - podsumowanie (ostatnie 3 miesiace)',
            'Wygenerowano: ' . date('d.m.Y H:i'),
            ''
        ];

        foreach ($allKino as $mk) {
            $monthTotalHours = array_sum(array_column($mk['employees'], 'total_hours'));
            $lines[] = $mk['label'] . ' | pracownicy: ' . count($mk['employees']) . ' | zmiany: ' . $mk['total_entries'] . ' | godziny: ' . $monthTotalHours;
            if (empty($mk['employees'])) {
                $lines[] = '  - Brak zmian kinowych';
                $lines[] = '';
                continue;
            }

            foreach ($mk['employees'] as $emp) {
                $lines[] = '  * ' . $emp['name'] . ' (' . $emp['dept'] . ') - ' . $emp['total_hours'] . ' godz., ' . count($emp['days']) . ' zmian';
                foreach ($emp['days'] as $di => $day) {
                    $dt = strtotime($day['date']);
                    $dow = $dayNames[(int)date('w', $dt)];
                    $hours = $day['start'] && $day['end'] ? short_time($day['start']) . '-' . short_time($day['end']) : 'nieustalone';
                    $lines[] = '      ' . ($di + 1) . '. ' . date('d.m.Y', $dt) . ' (' . $dow . ') | ' . $hours . ' | ' . $day['hours'] . 'h';
                }
            }
            $lines[] = '';
        }

        $pdf = build_text_only_pdf($lines);
        $filename = 'rozliczenie_kina_' . date('Ymd_His') . '.pdf';

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    $pageW = 1240;
    $pageH = 1754;
    $margin = 64;
    $cursorY = 0;
    $images = [];

    $newPage = static function () use ($pageW, $pageH, &$cursorY) {
        $img = imagecreatetruecolor($pageW, $pageH);
        imageantialias($img, true);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefill($img, 0, 0, $white);
        $cursorY = 110;
        return $img;
    };

    $img = $newPage();

    $cText = imagecolorallocate($img, 28, 25, 23);
    $cMuted = imagecolorallocate($img, 120, 113, 108);
    $cPrimary = imagecolorallocate($img, 124, 58, 237);
    $cSoft = imagecolorallocate($img, 245, 243, 255);
    $cBorder = imagecolorallocate($img, 232, 232, 235);

    $flushPage = static function ($img) use (&$images) {
        ob_start();
        imagejpeg($img, null, 90);
        $images[] = ob_get_clean();
        imagedestroy($img);
    };

    imagettftext($img, 38, 0, $margin, 90, $cPrimary, $fontBold, 'Rozliczenie kina');
    imagettftext($img, 16, 0, $margin, 124, $cMuted, $fontRegular, 'Podsumowanie zmian Kino — ostatnie 3 miesiące');
    imagettftext($img, 14, 0, $pageW - 360, 124, $cMuted, $fontRegular, 'Wygenerowano: ' . date('d.m.Y H:i'));

    $cursorY = 170;

    foreach ($allKino as $mk) {
        $employeesCount = count($mk['employees']);
        $totalHours = (int)array_sum(array_column($mk['employees'], 'total_hours'));

        $required = 70 + max(1, $employeesCount) * 44;
        if ($cursorY + $required > $pageH - 80) {
            $flushPage($img);
            $img = $newPage();
            $cText = imagecolorallocate($img, 28, 25, 23);
            $cMuted = imagecolorallocate($img, 120, 113, 108);
            $cPrimary = imagecolorallocate($img, 124, 58, 237);
            $cSoft = imagecolorallocate($img, 245, 243, 255);
            $cBorder = imagecolorallocate($img, 232, 232, 235);
        }

        imagefilledrectangle($img, $margin, $cursorY - 18, $pageW - $margin, $cursorY + 28, $cSoft);
        imagettftext($img, 18, 0, $margin + 16, $cursorY + 10, $cPrimary, $fontBold, $mk['label']);
        imagettftext($img, 13, 0, $margin + 260, $cursorY + 10, $cMuted, $fontRegular, $employeesCount . ' pracowników | ' . $mk['total_entries'] . ' zmian | ' . $totalHours . ' godz.');
        $cursorY += 56;

        if (empty($mk['employees'])) {
            imagettftext($img, 13, 0, $margin + 10, $cursorY + 6, $cMuted, $fontRegular, 'Brak zmian kinowych');
            $cursorY += 34;
            continue;
        }

        foreach ($mk['employees'] as $emp) {
            if ($cursorY + 38 > $pageH - 80) {
                $flushPage($img);
                $img = $newPage();
                $cText = imagecolorallocate($img, 28, 25, 23);
                $cMuted = imagecolorallocate($img, 120, 113, 108);
                $cPrimary = imagecolorallocate($img, 124, 58, 237);
                $cSoft = imagecolorallocate($img, 245, 243, 255);
                $cBorder = imagecolorallocate($img, 232, 232, 235);
            }

            imagerectangle($img, $margin, $cursorY - 18, $pageW - $margin, $cursorY + 16, $cBorder);
            imagettftext($img, 13, 0, $margin + 10, $cursorY + 2, $cText, $fontBold, $emp['name'] . ' (' . $emp['dept'] . ')');
            imagettftext($img, 12, 0, $pageW - 360, $cursorY + 2, $cPrimary, $fontRegular, $emp['total_hours'] . ' godz. | ' . count($emp['days']) . ' zmian');
            $cursorY += 36;

            foreach ($emp['days'] as $di => $day) {
                if ($cursorY + 28 > $pageH - 80) {
                    $flushPage($img);
                    $img = $newPage();
                    $cText = imagecolorallocate($img, 28, 25, 23);
                    $cMuted = imagecolorallocate($img, 120, 113, 108);
                    $cPrimary = imagecolorallocate($img, 124, 58, 237);
                    $cSoft = imagecolorallocate($img, 245, 243, 255);
                    $cBorder = imagecolorallocate($img, 232, 232, 235);
                }

                $dt = strtotime($day['date']);
                $dow = $dayNames[(int)date('w', $dt)];
                $hours = $day['start'] && $day['end'] ? short_time($day['start']) . ' - ' . short_time($day['end']) : 'nieustalone';
                $line = ($di + 1) . '. ' . date('d.m.Y', $dt) . ' (' . $dow . ') | ' . $hours . ' | ' . $day['hours'] . 'h';
                imagettftext($img, 12, 0, $margin + 24, $cursorY + 2, $cMuted, $fontRegular, $line);
                $cursorY += 24;
            }

            $cursorY += 10;
        }

        $cursorY += 6;
    }

    $flushPage($img);

    $pdf = build_pdf_from_jpegs($images, $pageW, $pageH);
    $filename = 'rozliczenie_kina_' . date('Ymd_His') . '.pdf';

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

layout_start('Rozliczenie kina');
?>

<style>
.kin-wrap{max-width:1000px;margin:0 auto;padding:16px 24px 40px}
.kin-header{display:flex;align-items:center;gap:12px;margin-bottom:28px}
.kin-header-main{display:flex;align-items:center;gap:12px;flex:1}
.kin-hicon{width:44px;height:44px;border-radius:14px;background:linear-gradient(135deg,#7c3aed,#a855f7);display:flex;align-items:center;justify-content:center}
.kin-hicon svg{width:22px;height:22px;stroke:#fff;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.kin-htitle{font-size:26px;font-weight:900;color:#1c1917;letter-spacing:-.03em}
.kin-hsub{font-size:12px;color:#a8a29e}

/* Month card */
.kin-month{margin-bottom:28px;opacity:0;animation:kinSlide .5s ease-out forwards}
@keyframes kinSlide{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
.kin-month-head{display:flex;align-items:center;gap:12px;margin-bottom:14px;padding-bottom:10px;border-bottom:2px solid #7c3aed}
.kin-month-badge{background:linear-gradient(135deg,#7c3aed,#a855f7);color:#fff;font-size:13px;font-weight:800;padding:6px 16px;border-radius:10px;letter-spacing:-.01em}
.kin-month-stats{font-size:12px;color:#78716c;display:flex;gap:16px}
.kin-month-stats strong{color:#1c1917}

/* Employee block */
.kin-emp{background:#fff;border:1.5px solid #f5f5f4;border-radius:16px;padding:16px 20px;margin-bottom:10px;transition:border-color .2s,box-shadow .2s}
.kin-emp:hover{border-color:#e9d5ff;box-shadow:0 4px 16px rgba(124,58,237,.06)}
.kin-emp-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;flex-wrap:wrap;gap:8px}
.kin-emp-name{font-size:15px;font-weight:800;color:#1c1917}
.kin-emp-dept{font-size:11px;color:#a8a29e;margin-left:8px}
.kin-emp-total{font-size:13px;font-weight:800;color:#7c3aed;background:#f5f3ff;padding:4px 12px;border-radius:8px}

/* Days table */
.kin-table{width:100%;border-collapse:collapse}
.kin-table th{padding:6px 10px;font-size:10px;font-weight:700;color:#78716c;text-transform:uppercase;letter-spacing:.04em;text-align:left;border-bottom:1px solid #f5f5f4}
.kin-table td{padding:6px 10px;font-size:12px;color:#1c1917;border-bottom:1px solid #fafaf9}
.kin-table tbody tr:nth-child(even) td{background:#fafaf9}
.kin-table tbody tr:hover td{background:#f5f3ff}
.kin-day-name{color:#a8a29e;font-size:11px}
.kin-hours{font-weight:700;color:#7c3aed}

.kin-empty{text-align:center;padding:32px;color:#a8a29e;font-size:13px;background:#fff;border:1.5px dashed #e7e5e4;border-radius:16px}
.kin-empty-icon{font-size:32px;margin-bottom:8px;opacity:.4}

.kin-pdf-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border:none;border-radius:10px;background:linear-gradient(135deg,#7c3aed,#9333ea);color:#fff;font-size:12px;font-weight:700;text-decoration:none;transition:transform .15s,box-shadow .2s;box-shadow:0 4px 12px rgba(124,58,237,.28)}
.kin-pdf-btn:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(124,58,237,.32)}
</style>

<div class="kin-wrap">
    <div class="kin-header">
        <div class="kin-header-main">
            <div class="kin-hicon">
                <svg viewBox="0 0 24 24"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M7 3v18"/><path d="M3 7.5h4"/><path d="M3 12h4"/><path d="M3 16.5h4"/><path d="M17 3v18"/><path d="M17 7.5h4"/><path d="M17 12h4"/><path d="M17 16.5h4"/></svg>
            </div>
            <div>
                <div class="kin-htitle">Rozliczenie kina</div>
                <div class="kin-hsub">Podsumowanie zmian Kino na podstawie harmonogramu — ostatnie 3 miesiące</div>
            </div>
        </div>
        <a class="kin-pdf-btn" href="cinema.php?download=pdf">Pobierz PDF</a>
    </div>

    <?php foreach ($allKino as $mi => $mk): ?>
    <div class="kin-month" style="animation-delay:<?=$mi*0.15?>s">
        <div class="kin-month-head">
            <div class="kin-month-badge"><?=h($mk['label'])?></div>
            <div class="kin-month-stats">
                <span><strong><?=count($mk['employees'])?></strong> pracowników</span>
                <span><strong><?=$mk['total_entries']?></strong> zmian</span>
                <span><strong><?=array_sum(array_column($mk['employees'],'total_hours'))?></strong> godz. łącznie</span>
            </div>
        </div>

        <?php if (empty($mk['employees'])): ?>
        <div class="kin-empty">
            <div class="kin-empty-icon">🎬</div>
            Brak zmian kinowych w <?=h($mk['label'])?>
        </div>
        <?php else: ?>
        <?php foreach ($mk['employees'] as $uid => $emp): ?>
        <div class="kin-emp">
            <div class="kin-emp-head">
                <div>
                    <span class="kin-emp-name"><?=h($emp['name'])?></span>
                    <span class="kin-emp-dept"><?=h($emp['dept'])?></span>
                </div>
                <div class="kin-emp-total"><?=$emp['total_hours']?> godz. · <?=count($emp['days'])?> zmian</div>
            </div>
            <table class="kin-table">
                <thead><tr><th>Lp.</th><th>Data</th><th>Dzień</th><th>Godziny</th><th>Czas</th></tr></thead>
                <tbody>
                <?php foreach ($emp['days'] as $di => $day):
                    $dt = strtotime($day['date']);
                    $dow = $dayNames[(int)date('w', $dt)];
                ?>
                <tr>
                    <td><?=$di+1?></td>
                    <td><strong><?=date('d.m.Y', $dt)?></strong></td>
                    <td><span class="kin-day-name"><?=$dow?></span></td>
                    <td><?=$day['start']&&$day['end'] ? short_time($day['start']).' – '.short_time($day['end']) : 'nieustalone'?></td>
                    <td><span class="kin-hours"><?=$day['hours']?>h</span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<?php layout_end(); ?>
