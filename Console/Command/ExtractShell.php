<?php

App::uses('Shell', 'Console');

class ExtractShell extends AppShell {

    public $uses = array();

    public function main() {
        //$this->pdf();
        $this->block_txt103();
    }

    public function block_txt103() {
        $csvPath = __DIR__ . '/data';
        $blockPath = $csvPath . '/block_103';
        $txtPath = $csvPath . '/txt_103';
        $resultPath = $csvPath . '/block_txt_103';
        $csvFh = fopen($csvPath . '/bulletin_103.csv', 'r');
        if (!file_exists($resultPath)) {
            mkdir($resultPath, 0777, true);
        }
        while ($line = fgetcsv($csvFh, 2048)) {
            $txtFile = "{$txtPath}/{$line[2]}.csv";
            if (file_exists($txtFile) && filesize($txtFile) > 0) {
                echo "processing {$line[2]}\n";
                $fh = fopen($txtFile, 'r');
                fgets($fh, 512);
                $pageText = array();
                while ($txtLine = fgetcsv($fh, 2048)) {
                    if (!isset($pageText[$txtLine[0]])) {
                        $pageText[$txtLine[0]] = array();
                    }
                    $pageText[$txtLine[0]][] = $txtLine;
                }
                fclose($fh);
                if (!empty($pageText)) {
                    $blockTxt = array();
                    foreach (glob("{$blockPath}/{$line[2]}*") AS $bgFile) {
                        if (filesize($bgFile) > 0) {
                            $bgPos = strrpos($bgFile, 'bg') + 2;
                            $pageNo = substr($bgFile, $bgPos);
                            if (isset($pageText[$pageNo])) {
                                $blockTxt[$pageNo] = array();
                                $fh = fopen($bgFile, 'r');
                                while ($block = fgetcsv($fh, 2048, ' ')) {
                                    $block[2] += $block[0];
                                    $block[3] += $block[1];
                                    $blockId = implode(' ', $block);
                                    $blockTxt[$pageNo][$blockId] = array();
                                    foreach ($pageText[$pageNo] AS $txt) {
                                        if ($txt[1] > $block[0] && $txt[1] < $block[2] && $txt[2] > $block[1] && $txt[2] < $block[3]) {
                                            $blockTxt[$pageNo][$blockId][] = $txt[3];
                                        }
                                    }
                                }
                                fclose($fh);
                            }
                        }
                    };
                    file_put_contents("{$resultPath}/{$line[2]}.json", json_encode($blockTxt, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                }
            }
        }
    }

    public function block103() {
        $csvPath = __DIR__ . '/data';
        $resultPath = $csvPath . '/block_103';
        $csvFh = fopen($csvPath . '/bulletin_103.csv', 'r');
        $htmlPath = TMP . 'txt_103';
        if (!file_exists($htmlPath)) {
            mkdir($htmlPath, 0777, true);
        }
        if (!file_exists($resultPath)) {
            mkdir($resultPath, 0777, true);
        }
        while ($line = fgetcsv($csvFh, 2048)) {
            $htmlFile = "{$htmlPath}/{$line[2]}/{$line[2]}.html";
            if (file_exists($htmlFile)) {
                $content = file_get_contents($htmlFile);
                $pos = strpos($content, 'src="bg');
                while (false !== $pos) {
                    $pos = strpos($content, '"', $pos) + 1;
                    $posEnd = strpos($content, '"', $pos);
                    $bgImage = substr($content, $pos, $posEnd - $pos);
                    $bgImagePath = "{$htmlPath}/{$line[2]}/" . $bgImage;
                    if (file_exists($bgImagePath)) {
                        exec("/home/kiang/bin/locate_block < {$bgImagePath}  > {$resultPath}/{$line[2]}_" . substr($bgImage, 0, strpos($bgImage, '.')));
                    }
                    $pos = strpos($content, 'src="bg', $posEnd);
                }
            }
        }
    }

    public function pdf() {
        $csvPath = __DIR__ . '/data';
        $pdfPath = $csvPath . '/pdf';
        $resultPath = $csvPath . '/txt';
        $csvFh = fopen($csvPath . '/bulletin.csv', 'r');
        $htmlPath = TMP . 'txt';
        if (!file_exists($htmlPath)) {
            mkdir($htmlPath, 0777, true);
        }
        if (!file_exists($resultPath)) {
            mkdir($resultPath, 0777, true);
        }
        while ($line = fgetcsv($csvFh, 2048)) {
            $pdfFile = "{$pdfPath}/{$line[3]}.pdf";
            if (file_exists($pdfFile) && !file_exists($htmlPath . '/' . $line[3])) {
                exec("/usr/bin/pdf2htmlEX --embed-css 0 --embed-font 0 --embed-image 0 --embed-javascript 0 --embed-outline 0 --dest-dir {$htmlPath}/{$line[3]} {$pdfFile}");
            }
            if (!file_exists($htmlPath . '/' . $line[3] . '/' . $line[3] . '.html')) {
                continue;
            }
            $htmlContent = file_get_contents($htmlPath . '/' . $line[3] . '/' . $line[3] . '.html');
            if (false === strpos($htmlContent, '日前上傳')) {
                $ref = array();
                if (file_exists($htmlPath . '/' . $line[3] . '/' . $line[3] . '.css')) {
                    $cssFh = fopen($htmlPath . '/' . $line[3] . '/' . $line[3] . '.css', 'r');

                    while ($cssLine = fgets($cssFh, 1024)) {
                        $cssLine = substr($cssLine, 1, -5);
                        if (false !== strpos($cssLine, '{bottom:')) {
                            $item = explode('{bottom:', $cssLine);
                            $ref[$item[0]] = $item[1];
                        } elseif (false !== strpos($cssLine, '{left:')) {
                            $item = explode('{left:', $cssLine);
                            $ref[$item[0]] = $item[1];
                        }
                    }
                    fclose($cssFh);
                }

                if (empty($ref)) {
                    continue;
                }

                $rFh = fopen("{$resultPath}/{$line[3]}.csv", 'w');
                fputcsv($rFh, array(
                    'page #',
                    'x',
                    'y',
                    'text',
                ));

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

    public function pdf103() {
        $csvPath = __DIR__ . '/data';
        $pdfPath = $csvPath . '/pdf_103';
        $resultPath = $csvPath . '/txt_103';
        //$csvFh = fopen($csvPath . '/bulletin_103_ptec.csv', 'r');
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
                $ref = array();
                if (file_exists($htmlPath . '/' . $line[2] . '/' . $line[2] . '.css')) {
                    $cssFh = fopen($htmlPath . '/' . $line[2] . '/' . $line[2] . '.css', 'r');
                    while ($cssLine = fgets($cssFh, 1024)) {
                        $cssLine = substr($cssLine, 1, -5);
                        if (false !== strpos($cssLine, '{bottom:')) {
                            $item = explode('{bottom:', $cssLine);
                            $ref[$item[0]] = $item[1];
                        } elseif (false !== strpos($cssLine, '{left:')) {
                            $item = explode('{left:', $cssLine);
                            $ref[$item[0]] = $item[1];
                        } elseif (false !== strpos($cssLine, '{width:')) {
                            $item = explode('{width:', $cssLine);
                            $ref[$item[0]] = $item[1];
                        } elseif (false !== strpos($cssLine, '{height:')) {
                            $item = explode('{height:', $cssLine);
                            $ref[$item[0]] = $item[1];
                        }
                    }
                    fclose($cssFh);
                }
                $rFh = fopen("{$resultPath}/{$line[2]}.csv", 'w');
                fputcsv($rFh, array(
                    'page #',
                    'x',
                    'y',
                    'text',
                ));

                $pos = strpos($htmlContent, '<div id="pf');
                while (false !== $pos) {
                    $pageNoPos = strpos($htmlContent, 'data-page-no="', $pos) + 14;
                    $pageNo = substr($htmlContent, $pageNoPos, strpos($htmlContent, '"', $pageNoPos) - $pageNoPos);
                    $imgPos = strpos($htmlContent, '<img class');
                    $imgPos = strpos($htmlContent, ' w', $imgPos);
                    $bgWidth = $ref[substr($htmlContent, $imgPos + 1, strpos($htmlContent, ' ', $imgPos + 1) - $imgPos - 1)];
                    $imgPos = strpos($htmlContent, ' h', $imgPos);
                    $bgHeight = $ref[substr($htmlContent, $imgPos + 1, strpos($htmlContent, '"', $imgPos + 1) - $imgPos - 1)];
                    if (file_exists("{$htmlPath}/{$line[2]}/bg{$pageNo}.png")) {
                        $imgSize = getimagesize("{$htmlPath}/{$line[2]}/bg{$pageNo}.png");
                    } else {
                        $imgSize = array(
                            0 => 0,
                            1 => 0,
                        );
                    }

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
                            $ref[$x] * $imgSize[0] / $bgWidth,
                            $imgSize[1] - ($ref[$y] * $imgSize[1] / $bgHeight),
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
