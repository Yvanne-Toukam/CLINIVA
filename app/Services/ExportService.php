<?php

declare(strict_types=1);

namespace Cliniva\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style\Font;

final class ExportService
{
    public function generatePrescriptionPdf(string $patientName, string $doctorName, array $items, string $outputPath): void
    {
        $html = $this->renderPrescriptionHtml($patientName, $doctorName, $items);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        file_put_contents($outputPath, $dompdf->output());
    }

    public function generateMedicalSummaryDocx(string $patientName, array $sections, string $outputPath): void
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();

        $section->addText('Résumé médical - ' . $patientName, ['bold' => true, 'size' => 20]);
        $section->addText('');

        foreach ($sections as $title => $content) {
            $section->addText($title, ['bold' => true, 'size' => 14]);
            $contentLines = is_array($content) ? $content : [$content];
            foreach ($contentLines as $line) {
                $section->addText((string) $line);
            }
            $section->addText('');
        }

        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($outputPath);
    }

    private function renderPrescriptionHtml(string $patientName, string $doctorName, array $items): string
    {
        $lines = '';
        foreach ($items as $item) {
            $lines .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                e($item['medicine_name'] ?? ''),
                e($item['dosage'] ?? ''),
                e($item['frequency'] ?? ''),
                e($item['duration'] ?? ''),
                e($item['instructions'] ?? '')
            );
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ordonnance Cliniva</title>
    <style>
        body { font-family: Arial, sans-serif; color: #1f2937; }
        .header { margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        th, td { border: 1px solid #d1d5db; padding: 8px; text-align: left; }
        th { background: #f3f4f6; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Cliniva - Ordonnance</h2>
        <p>Patient : <strong>{$patientName}</strong></p>
        <p>Médecin : <strong>{$doctorName}</strong></p>
    </div>
    <table>
        <thead>
            <tr><th>Médicament</th><th>Dosage</th><th>Posologie</th><th>Durée</th><th>Instructions</th></tr>
        </thead>
        <tbody>
            {$lines}
        </tbody>
    </table>
</body>
</html>
HTML;
    }
}
