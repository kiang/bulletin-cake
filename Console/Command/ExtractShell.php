<?php

App::uses('Shell', 'Console');

class ExtractShell extends AppShell {

    public $uses = array();

    public function main() {
        $csvPath = __DIR__ . '/data';
        $pdfPath = $csvPath . '/pdf';
        $csvFh = fopen($csvPath . '/bulletin.csv', 'r');
        while ($line = fgetcsv($csvFh, 2048)) {
            $pdfFile = "{$pdfPath}/{$line[3]}.pdf";
            if (file_exists($pdfFile)) {
                exec("/usr/bin/pdf2htmlEX --embed-css 0 --embed-font 0 --embed-image 0 --embed-javascript 0 --embed-outline 0 --dest-dir " . TMP . "txt/{$line[3]} {$pdfFile}");
                copy(TMP . "txt/{$line[3]}/{$line[3]}.html", TMP . "txt/{$line[3]}.html");
            }
        }
    }

}
