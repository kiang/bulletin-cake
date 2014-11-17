<?php

App::uses('Shell', 'Console');

class ExtractShell extends AppShell {

    public $uses = array();

    public function main() {
        $this->pdf103();
    }

    public function pdf103() {
        $csvPath = __DIR__ . '/data';
        $pdfPath = $csvPath . '/pdf_103';
        $resultPath = $csvPath . '/txt_103';
        $csvFh = fopen($csvPath . '/bulletin_103.csv', 'r');
        $htmlPath = TMP . 'txt_103';
        if (!file_exists($htmlPath)) {
            mkdir($htmlPath, 0777, true);
        }
        if (!file_exists($resultPath)) {
            mkdir($resultPath, 0777, true);
        }
        while ($line = fgetcsv($csvFh, 2048)) {
            $pdfFile = "{$pdfPath}/{$line[2]}.pdf";
            if (file_exists($pdfFile) && !file_exists($htmlPath . '/' . $line[2])) {
                exec("/usr/bin/pdf2htmlEX --embed-css 0 --embed-font 0 --embed-image 0 --embed-javascript 0 --embed-outline 0 --dest-dir {$htmlPath}/{$line[2]} {$pdfFile}");
            }
            if (!file_exists($htmlPath . '/' . $line[2] . '/' . $line[2] . '.html')) {
                continue;
            }
            $htmlContent = file_get_contents($htmlPath . '/' . $line[2] . '/' . $line[2] . '.html');
            if (false !== strpos($htmlContent, '日前上傳')) {
                
            } else {
                $rFh = fopen("{$resultPath}/{$line[2]}.csv", 'w');
                fputcsv($rFh, array(
                    'page #',
                    'x',
                    'y',
                    'text',
                ));
                $ref = array();
                if (file_exists($htmlPath . '/' . $line[2] . '/' . $line[2] . '.css')) {
                    $cssFh = fopen($htmlPath . '/' . $line[2] . '/' . $line[2] . '.css', 'r');

                    while ($line = fgets($cssFh, 1024)) {
                        $line = substr($line, 1, -5);
                        if (false !== strpos($line, '{bottom:')) {
                            $item = explode('{bottom:', $line);
                            $ref[$item[0]] = $item[1];
                        } elseif (false !== strpos($line, '{left:')) {
                            $item = explode('{left:', $line);
                            $ref[$item[0]] = $item[1];
                        }
                    }
                    fclose($cssFh);
                }

                $pos = strpos($htmlContent, '<div id="pf');
                while (false !== $pos) {
                    $pageNoPos = strpos($htmlContent, 'data-page-no="', $pos) + 14;
                    $pageNo = substr($htmlContent, $pageNoPos, strpos($htmlContent, '"', $pageNoPos) - $pageNoPos);
                    $textPos = $pos;
                    $posEnd = strpos($htmlContent, '<div id="pf', $pos + 1);
                    if (false === $posEnd) {
                        $posEnd = strlen($htmlContent);
                        $pos = false;
                    }
                    $textPos = strpos($htmlContent, '<div class="t', $textPos);
                    while (false !== $textPos && $textPos < $posEnd) {
                        $xPos = strpos($htmlContent, 'x', $textPos);
                        $x = substr($htmlContent, $xPos, strpos($htmlContent, ' ', $xPos) - $xPos);
                        $yPos = strpos($htmlContent, 'y', $textPos);
                        $y = substr($htmlContent, $yPos, strpos($htmlContent, ' ', $yPos) - $yPos);
                        $textPosEnd = strpos($htmlContent, '</div>', $textPos);
                        fputcsv($rFh, array(
                            $pageNo,
                            $ref[$x],
                            $ref[$y],
                            trim(strip_tags(substr($htmlContent, $textPos, $textPosEnd - $textPos))),
                        ));

                        $textPos = strpos($htmlContent, '<div class="t', $textPosEnd);
                    }
                    if (false !== $pos) {
                        $pos = $posEnd;
                    }
                }
                fclose($rFh);
            }
        }
    }

}
