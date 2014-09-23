<?php

App::uses('Shell', 'Console');

class DownloadShell extends AppShell {

    public $uses = array();

    public function main() {
        $csvPath = __DIR__ . '/data';
        $pdfPath = $csvPath . '/pdf';
        if (!file_exists($pdfPath)) {
            mkdir($pdfPath, 0777, true);
        }
        $csvFh = fopen($csvPath . '/bulletin.csv', 'r');
        while ($line = fgetcsv($csvFh, 2048)) {
            $pos = strrpos($line[2], '/') + 1;
            $line[2] = substr($line[2], 0, $pos) . urlencode(substr($line[2], $pos));
            if (count($line) !== 4) {
                if (!file_exists("{$pdfPath}/{$line[2]}.pdf")) {
                    file_put_contents("{$pdfPath}/{$line[2]}.pdf", file_get_contents($line[1]));
                }
            } else {
                if (!file_exists("{$pdfPath}/{$line[3]}.pdf")) {
                    file_put_contents("{$pdfPath}/{$line[3]}.pdf", file_get_contents($line[2]));
                }
            }
        }
    }

}
