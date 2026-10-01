<?php

namespace App\Console\Commands;

use App\Imports\Importer;
use App\Imports\ImportRegistry;
use App\Services\ReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;

/**
 * Builds the User & Administrator Guide (.docx) from docs/guide/*.md.
 * Role, permission, report and import tables are generated from the
 * live configuration so they always match the installed system.
 */
class UserGuide extends Command
{
    protected $signature = 'emr:user-guide {--output= : Where to save the .docx (default docs/EMR-User-Guide.docx)}';

    protected $description = 'Generate the User & Administrator Guide as a Word document';

    protected const PARTS = [
        '01' => ['Part A', 'Getting started'],
        '10' => ['Part B', 'Using the modules'],
        '30' => ['Part C', 'Administration'],
        '40' => ['Part D', 'Technical reference'],
    ];

    protected const BRAND = '1F4E79';

    /** Plain-language summary of each built-in role. */
    protected const ROLE_SUMMARIES = [
        'Super Admin' => 'Unrestricted access to everything, including downloading backups. Reserve for the ICT officer and one senior manager.',
        'Administrator' => 'Runs the system day to day: settings, staff and roles, catalogues, prices, insurers, data import, integrations, reports, backups and the audit log; can also work in most clinical and finance areas.',
        'Doctor' => 'Consults patients: notes, diagnoses, lab/imaging orders and prescriptions; admits, writes ward notes and discharges; books and performs surgery; maternity care; dental, eye and physiotherapy records; clinical reports.',
        'Nurse' => 'Checks patients in, triages and records vital signs and nursing notes, moves patients through the queue, manages admissions on the ward, gives and charts medicines, theatre nursing, immunizations, store requisitions.',
        'Midwife' => 'Antenatal booking and visits, labour and partograph, delivery and baby registration, postnatal care and immunization, plus ward nursing duties.',
        'Anaesthetist' => 'Pre-operative assessment, WHO checklist and the anaesthesia record in theatre.',
        'Pharmacist' => 'Dispenses prescriptions, manages drug stock (receiving, adjustments, suppliers), raises purchase orders for drugs and requests store items.',
        'Lab Scientist' => 'Collects/rejects samples, enters and verifies lab results, prints lab reports, requests store items.',
        'Radiologist' => 'Performs examinations, writes and signs imaging reports.',
        'Radiographer' => 'Schedules and performs imaging examinations and uploads images.',
        'Dental Therapist' => 'Keeps the dental chart and treatment plans; manages the dental clinic queue.',
        'Optometrist' => 'Eye examinations and spectacle prescriptions; manages the eye clinic queue.',
        'Physiotherapist' => 'Physiotherapy assessments, sessions and discharges; manages the physiotherapy clinic queue.',
        'Records Officer' => 'Registers patients, books and checks in appointments, gives patient-portal access, records HMO pre-authorisation requests, operational reports.',
        'Cashier' => 'Takes payments and deposits, prints receipts and invoices, creates online payment links, records PA codes.',
        'Accountant' => 'Discounts, voids and payment reversals, price list, insurance claims and remittances, approves purchase orders, records and pays supplier invoices, financial reports.',
        'Storekeeper' => 'Runs the general store: receives and issues stock, handles requisitions, raises purchase orders and receives deliveries.',
    ];

    public function handle(): int
    {
        $files = glob(base_path('docs/guide/*.md'));
        sort($files);
        if (! $files) {
            $this->error('No chapters found in docs/guide.');

            return self::FAILURE;
        }

        // Escape &, < and > in all text, otherwise Word rejects the file.
        Settings::setOutputEscapingEnabled(true);
        $word = new PhpWord;
        $word->getSettings()->setUpdateFields(true); // Word refreshes the table of contents on opening
        $word->getDocInfo()->setTitle(setting('hospital_name').' — EMR User & Administrator Guide')->setCreator(setting('hospital_name'));
        $this->styles($word);

        $section = $this->cover($word);
        $section->addTitle('Contents', 1);
        $section->addTOC(['name' => 'Calibri', 'size' => 10], ['tabLeader' => 'dot'], 1, 2);

        foreach ($files as $file) {
            $prefix = substr(basename($file), 0, 2);
            if (isset(self::PARTS[$prefix])) {
                $section = $this->newSection($word);
                [$part, $title] = self::PARTS[$prefix];
                $section->addTextBreak(8);
                $section->addText($part, ['size' => 16, 'color' => '7F7F7F'], ['alignment' => Jc::CENTER]);
                $section->addText($title, ['size' => 30, 'bold' => true, 'color' => self::BRAND], ['alignment' => Jc::CENTER]);
            }
            $section = $this->newSection($word);
            $this->renderMarkdown($word, $section, (string) file_get_contents($file));
        }

        $output = $this->option('output') ?: base_path('docs/EMR-User-Guide.docx');
        IOFactory::createWriter($word, 'Word2007')->save($output);
        $this->info('Saved '.$output.' ('.count($files).' chapters).');

        return self::SUCCESS;
    }

    // ------------------------------------------------------------------ layout

    protected function styles(PhpWord $word): void
    {
        $word->setDefaultFontName('Calibri');
        $word->setDefaultFontSize(11);
        $word->addTitleStyle(1, ['size' => 20, 'bold' => true, 'color' => self::BRAND], ['spaceAfter' => 200, 'keepNext' => true]);
        $word->addTitleStyle(2, ['size' => 14, 'bold' => true, 'color' => self::BRAND], ['spaceBefore' => 240, 'spaceAfter' => 100, 'keepNext' => true]);
        $word->addTitleStyle(3, ['size' => 12, 'bold' => true, 'color' => '404040'], ['spaceBefore' => 160, 'spaceAfter' => 60, 'keepNext' => true]);
        $word->addParagraphStyle('body', ['spaceAfter' => 100, 'lineHeight' => 1.15]);
        $word->addParagraphStyle('note', ['spaceBefore' => 80, 'spaceAfter' => 160, 'shading' => ['fill' => 'EAF2FB'],
            'borderLeftSize' => 24, 'borderLeftColor' => '2A78D6', 'indentation' => ['left' => 120, 'right' => 120]]);
        $word->addNumberingStyle('bullets', ['type' => 'multilevel', 'levels' => [['format' => 'bullet', 'text' => '•', 'left' => 360, 'hanging' => 240]]]);
        $word->addTableStyle('grid', ['borderSize' => 4, 'borderColor' => 'BFBFBF', 'cellMargin' => 70], ['bgColor' => 'DCE6F2']);
    }

    protected function cover(PhpWord $word): Section
    {
        $section = $word->addSection(['marginTop' => Converter::cmToTwip(2.5)]);
        $logo = setting('logo') ? public_path(setting('logo')) : null;
        if ($logo && is_file($logo) && in_array(strtolower(pathinfo($logo, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg'], true)) {
            $section->addImage($logo, ['height' => 80, 'alignment' => Jc::CENTER]);
        } else {
            $section->addTextBreak(3);
        }
        $section->addTextBreak(2);
        $section->addText((string) setting('hospital_name'), ['size' => 22, 'bold' => true, 'color' => self::BRAND], ['alignment' => Jc::CENTER]);
        $section->addText('Electronic Medical Records & Hospital Management System', ['size' => 14, 'color' => '595959'], ['alignment' => Jc::CENTER]);
        $section->addTextBreak(2);
        $section->addText('User & Administrator Guide', ['size' => 28, 'bold' => true], ['alignment' => Jc::CENTER]);
        $section->addTextBreak(6);
        $section->addText('Software version '.config('emr.version'), ['color' => '595959'], ['alignment' => Jc::CENTER]);
        $section->addText('Generated '.now()->format('j F Y'), ['color' => '595959'], ['alignment' => Jc::CENTER]);
        $section->addText(collect([setting('address'), setting('city'), setting('state')])->filter()->implode(', '), ['color' => '595959'], ['alignment' => Jc::CENTER]);
        $section->addPageBreak();

        return $section;
    }

    protected function newSection(PhpWord $word, bool $landscape = false): Section
    {
        $section = $word->addSection($landscape ? ['orientation' => 'landscape'] : []);
        $section->addHeader()->addText(setting('hospital_name').' — EMR User & Administrator Guide', ['size' => 8, 'color' => '7F7F7F'], ['alignment' => Jc::RIGHT]);
        $section->addFooter()->addPreserveText('Page {PAGE} of {NUMPAGES}', ['size' => 8, 'color' => '7F7F7F'], ['alignment' => Jc::CENTER]);

        return $section;
    }

    // ------------------------------------------------------------------ markdown

    protected function renderMarkdown(PhpWord $word, Section &$section, string $markdown): void
    {
        $markdown = strtr($markdown, ['{{hospital}}' => (string) setting('hospital_name'), '{{url}}' => rtrim((string) config('app.url'), '/')]);
        $lines = preg_split('/\r\n|\n/', $markdown);
        $table = [];
        $numbered = 0;

        $flushTable = function () use (&$table, &$section) {
            if ($table) {
                $this->wordTable($section, array_shift($table), $table);
                $table = [];
            }
        };

        foreach ($lines as $line) {
            $trim = trim($line);

            if (str_starts_with($trim, '|')) {
                if (! preg_match('/^\|[\s\-:|]+\|$/', $trim)) {
                    $table[] = array_map('trim', explode('|', trim($trim, '|')));
                }

                continue;
            }
            $flushTable();

            if ($trim === '') {
                $numbered = 0;

                continue;
            }

            if (preg_match('/^\{\{([a-z-]+)\}\}$/', $trim, $m)) {
                $this->block($word, $section, $m[1]);

                continue;
            }

            if (preg_match('/^(#{1,3}) (.+)$/', $trim, $m)) {
                $section->addTitle($this->plain($m[2]), strlen($m[1]));

                continue;
            }

            if (str_starts_with($trim, '> ')) {
                $this->inline($section->addTextRun('note'), substr($trim, 2));

                continue;
            }

            if (preg_match('/^[-*] (.+)$/', $trim, $m)) {
                $this->inline($section->addListItemRun(0, 'bullets', ['spaceAfter' => 40]), $m[1]);

                continue;
            }

            if (preg_match('/^(\d+)\. (.+)$/', $trim, $m)) {
                // Numbers are written out, so each list restarts at its own first number.
                $run = $section->addTextRun(['indentation' => ['left' => 360, 'hanging' => 300], 'spaceAfter' => 40]);
                $run->addText($m[1].'.  ', ['bold' => true, 'color' => self::BRAND]);
                $this->inline($run, $m[2]);
                $numbered++;

                continue;
            }

            $this->inline($section->addTextRun('body'), $trim);
        }
        $flushTable();
    }

    /**
     * **bold** and `code` inside a line.
     */
    protected function inline(TextRun $run, string $text, array $font = []): void
    {
        foreach (preg_split('/(\*\*[^*]+\*\*|`[^`]+`)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $part) {
            if (str_starts_with($part, '**') && str_ends_with($part, '**')) {
                $run->addText(substr($part, 2, -2), $font + ['bold' => true]);
            } elseif (str_starts_with($part, '`') && str_ends_with($part, '`')) {
                $run->addText(substr($part, 1, -1), $font + ['name' => 'Consolas', 'size' => 9.5, 'color' => '7A2E0E']);
            } else {
                $run->addText($part, $font);
            }
        }
    }

    protected function plain(string $text): string
    {
        return str_replace(['**', '`'], '', $text);
    }

    /**
     * @param  list<string>  $header
     * @param  list<list<string>>  $rows
     */
    protected function wordTable(Section $section, array $header, array $rows, int $fontSize = 10): Table
    {
        $width = (int) (Converter::cmToTwip(16) / max(1, count($header)));
        $table = $section->addTable('grid');
        $table->addRow(null, ['tblHeader' => true]);
        foreach ($header as $cell) {
            $this->inline($table->addCell($width, ['bgColor' => 'DCE6F2'])->addTextRun(['spaceAfter' => 0]), $cell, ['bold' => true, 'size' => $fontSize]);
        }
        foreach ($rows as $row) {
            $table->addRow(null, ['cantSplit' => true]);
            foreach (array_pad($row, count($header), '') as $cell) {
                $this->inline($table->addCell($width)->addTextRun(['spaceAfter' => 0]), $cell, ['size' => $fontSize]);
            }
        }
        $section->addTextBreak(1);

        return $table;
    }

    // ------------------------------------------------------------------ generated blocks

    protected function block(PhpWord $word, Section &$section, string $name): void
    {
        match ($name) {
            'role-summary' => $this->roleSummary($section),
            'permission-matrix' => $this->permissionMatrix($word, $section),
            'import-types' => $this->importTypes($section),
            'reports' => $this->reports($section),
            default => $section->addText("[{$name}]"),
        };
    }

    protected function roleSummary(Section $section): void
    {
        $groups = config('emr.permissions');
        foreach (config('emr.roles') as $role => $permissions) {
            $section->addTitle($role, 3);
            $section->addText(self::ROLE_SUMMARIES[$role] ?? 'Custom role.', [], 'body');
            if ($role === config('emr.super_admin_role')) {
                continue;
            }
            foreach ($groups as $group => $list) {
                $mine = array_values(array_intersect_key($list, array_flip($permissions)));
                if ($mine) {
                    $run = $section->addListItemRun(0, 'bullets', ['spaceAfter' => 20]);
                    $run->addText($group.': ', ['bold' => true, 'size' => 10]);
                    $run->addText(implode('; ', $mine), ['size' => 10]);
                }
            }
        }
    }

    /**
     * Landscape section: permissions down, roles across.
     */
    protected function permissionMatrix(PhpWord $word, Section &$section): void
    {
        $roles = array_keys(config('emr.roles'));
        $super = config('emr.super_admin_role');
        $section = $this->newSection($word, true);

        $first = Converter::cmToTwip(7.2);
        $col = (int) ((Converter::cmToTwip(25.5) - $first) / count($roles));
        $table = $section->addTable('grid');
        $table->addRow(Converter::cmToTwip(3), ['tblHeader' => true]);
        $table->addCell($first, ['bgColor' => 'DCE6F2', 'valign' => 'bottom'])->addText('Permission', ['bold' => true, 'size' => 8]);
        foreach ($roles as $role) {
            $table->addCell($col, ['bgColor' => 'DCE6F2', 'textDirection' => 'btLr', 'valign' => 'center'])->addText($role, ['bold' => true, 'size' => 7.5]);
        }

        foreach (config('emr.permissions') as $group => $permissions) {
            $table->addRow(null, ['cantSplit' => true]);
            $table->addCell($first + $col * count($roles), ['gridSpan' => count($roles) + 1, 'bgColor' => 'F2F2F2'])->addText($group, ['bold' => true, 'size' => 8, 'color' => self::BRAND]);
            foreach ($permissions as $key => $label) {
                $table->addRow(null, ['cantSplit' => true]);
                $table->addCell($first)->addText($label, ['size' => 7.5]);
                foreach ($roles as $role) {
                    $has = $role === $super || in_array($key, config("emr.roles.{$role}", []), true);
                    $table->addCell($col, ['valign' => 'center'])->addText($has ? '✓' : '', ['size' => 8, 'bold' => true, 'color' => '1E7B34'], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
                }
            }
        }

        $section = $this->newSection($word); // back to portrait for the rest
    }

    protected function importTypes(Section $section): void
    {
        $rows = ImportRegistry::all()->values()->map(fn (Importer $i, int $n) => [
            (string) ($n + 1),
            '**'.$i->title().'**',
            $i->description(),
            collect($i->dependsOn())->map(fn ($k) => ImportRegistry::find($k)?->title())->filter()->implode(', ') ?: '—',
        ])->all();

        $this->wordTable($section, ['Step', 'Import', 'What it brings in', 'Import first'], $rows, 9);
    }

    protected function reports(Section $section): void
    {
        $rows = collect(ReportService::REPORTS)->map(fn ($r) => [$r[1], '**'.$r[0].'**', $r[3]])->sortBy(0)->values()->all();
        $this->wordTable($section, ['Group', 'Report', 'What it shows'], $rows, 9);
    }
}
