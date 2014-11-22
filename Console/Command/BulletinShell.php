<?php

App::uses('Shell', 'Console');
App::uses('HttpSocket', 'Network/Http');
App::uses('String', 'Utility');

class BulletinShell extends AppShell {

    public $uses = array();
    public $links = array();
    public $currentUrlBase = '';
    public $titlePrefix = '';
    public $s;

    public function main() {
        $this->b2014();
    }

    public function b2014() {
        $this->s = new HttpSocket();
        $cachePath = TMP . '103';
        if (!file_exists($cachePath)) {
            mkdir($cachePath);
        }
        $baseUrl = 'http://103bulletin.cec.gov.tw/103/';
        $this->currentUrlBase = $baseUrl;
        $baseCache = $cachePath . '/' . md5($baseUrl);
        if (!file_exists($baseCache)) {
            file_put_contents($baseCache, file_get_contents($baseUrl));
        }
        $baseContent = file_get_contents($baseCache);
        $this->treeLinks($baseContent, '', $baseUrl);
        $csvPath = __DIR__ . '/data';
        $pdfPath = $csvPath . '/pdf_103';
        if (!file_exists($pdfPath)) {
            mkdir($pdfPath, 0777, true);
        }
        $uuidStack = array();
        $csvFile = $csvPath . '/bulletin_103.csv';
        if (file_exists($csvFile)) {
            $csvFh = fopen($csvFile, 'r');
            while ($line = fgetcsv($csvFh, 2048)) {
                $uuidStack[$line[1]] = $line[2];
            }
            fclose($csvFh);
        }
        $csvFh = fopen($csvFile, 'w');
        foreach ($this->links AS $url => $link) {
            if ($link['isPdf'] === true) {
                if (isset($uuidStack[$url])) {
                    $uuid = $uuidStack[$url];
                } else {
                    $uuid = String::uuid();
                }
                $pdfFile = "{$pdfPath}/{$uuid}.pdf";
                if (!file_exists($pdfFile)) {
                    copy(TMP . '103/' . md5($url), $pdfFile);
                }
                fputcsv($csvFh, array(
                    $link['title'],
                    $url,
                    $uuid,
                ));
            } else {
                unlink(TMP . '103/' . md5($url));
            }
        }
        fclose($csvFh);
    }

    public function orig() {
        $cachePath = TMP . 'cq';
        if (!file_exists($cachePath)) {
            mkdir($cachePath);
        }
        $baseUrl = 'http://bulletin.cec.gov.tw/Communique_QueryResult.aspx';
        $baseCache = $cachePath . '/' . md5($baseUrl);
        if (!file_exists($baseCache)) {
            file_put_contents($baseCache, file_get_contents($baseUrl));
        }
        $baseContent = file_get_contents($baseCache);
        $this->extractLinks($baseContent);

        for ($i = 0; $i < 5; $i++) {
            foreach ($this->links AS $url => $link) {
                $this->titlePrefix = $link['title'] . ' > ';
                $targetUrl = 'http://bulletin.cec.gov.tw/' . $url;
                $targetUrlCache = $cachePath . '/' . md5($targetUrl);
                if (!file_exists($targetUrlCache)) {
                    file_put_contents($targetUrlCache, file_get_contents($targetUrl));
                }
                $targetUrlContent = file_get_contents($targetUrlCache);
                $this->extractLinks($targetUrlContent);
            }
        }

        $HttpSocket = new HttpSocket();

        $csvPath = __DIR__ . '/data';
        if (!file_exists($csvPath)) {
            mkdir($csvPath);
        }

        $csvFh = fopen($csvPath . '/bulletin.csv', 'w');

        foreach ($this->links AS $url => $link) {
            $this->titlePrefix = $link['title'] . ' > ';
            $targetUrl = 'http://bulletin.cec.gov.tw/' . $url;
            $targetUrlCache = $cachePath . '/' . md5($targetUrl);
            if (!file_exists($targetUrlCache)) {
                file_put_contents($targetUrlCache, file_get_contents($targetUrl));
            }
            $targetUrlContent = file_get_contents($targetUrlCache);
            $files = array();

            if (false !== strpos($targetUrlContent, 'pdf\',\'FS_Viewer\'')) {
                $files = $this->extractPdf($targetUrlContent);
                if (!empty($files)) {
                    foreach ($files AS $file) {
                        $file[] = $link['title'];
                        $file[1] = str_replace('../CECData/', 'http://bulletin.cec.gov.tw/CECData/', $file[1]);
                        krsort($file);
                        fputcsv($csvFh, $file);
                    }
                }
            } else {
                if (false !== strpos($targetUrlContent, 'ctl00$cphMainBody$DropDownList_TownShip')) {
                    $cities = $this->extractCities($targetUrlContent);
                    foreach ($cities AS $cityId => $city) {
                        $cityCache = $targetUrlCache . $cityId;
                        if (!file_exists($cityCache)) {
                            $form = $this->extractValues($targetUrlContent);
                            $form['ctl00$cphMainBody$DropDownList_City'] = $cityId;
                            $form['ctl00$cphMainBody$DropDownList_TownShip'] = '0';
                            //$form['ctl00$cphMainBody$cpe2_ClientState'] = 'false';
                            $result = $HttpSocket->post('http://bulletin.cec.gov.tw/' . $url, $form);
                            if (false !== strpos($result, 'pdf\',\'FS_Viewer\'')) {
                                file_put_contents($cityCache, $result);
                            } else {
                                echo $result;
                                print_r($form);
                                echo 'http://bulletin.cec.gov.tw/' . $url;
                                exit();
                                $newForm = $this->extractValues($result);
                                $form = array_merge($form, $newForm);
                                $result = $HttpSocket->post('http://bulletin.cec.gov.tw/' . $url, $form);
                                if (false !== strpos($result, 'pdf\',\'FS_Viewer\'')) {
                                    file_put_contents($cityCache, $result);
                                } else {
                                    print_r($form);
                                    echo 'http://bulletin.cec.gov.tw/' . $url;
                                    exit();
                                }
                            }
                        } else {
                            $files = $this->extractPdf(file_get_contents($cityCache));
                            if (!empty($files)) {
                                foreach ($files AS $file) {
                                    $file[] = $link['title'];
                                    $file[1] = str_replace('../CECData/', 'http://bulletin.cec.gov.tw/CECData/', $file[1]);
                                    krsort($file);
                                    fputcsv($csvFh, $file);
                                }
                            }
                        }
                    }
                } elseif (false !== strpos($targetUrlContent, 'ctl00$cphMainBody$DropDownList_City')) {
                    $cities = $this->extractCities($targetUrlContent);
                    foreach ($cities AS $cityId => $city) {
                        $cityCache = $targetUrlCache . $cityId;
                        if (!file_exists($cityCache)) {
                            $form = $this->extractValues($targetUrlContent);
                            $form['ctl00$cphMainBody$DropDownList_City'] = $cityId;
                            $result = $HttpSocket->post('http://bulletin.cec.gov.tw/' . $url, $form);
                            if (false !== strpos($result, 'pdf\',\'FS_Viewer\'')) {
                                file_put_contents($cityCache, $result);
                            } else {
                                $newForm = $this->extractValues($result);
                                $form = array_merge($form, $newForm);
                                $result = $HttpSocket->post('http://bulletin.cec.gov.tw/' . $url, $form);
                                if (false !== strpos($result, 'pdf\',\'FS_Viewer\'')) {
                                    file_put_contents($cityCache, $result);
                                } else {
                                    echo $result;
                                    print_r($form);
                                    echo 'http://bulletin.cec.gov.tw/' . $url;
                                    exit();
                                }
                            }
                        } else {
                            $files = $this->extractPdf(file_get_contents($cityCache));
                            if (!empty($files)) {
                                foreach ($files AS $file) {
                                    $file[] = $link['title'];
                                    $file[1] = str_replace('../CECData/', 'http://bulletin.cec.gov.tw/CECData/', $file[1]);
                                    krsort($file);
                                    fputcsv($csvFh, $file);
                                }
                            }
                        }
                    }
                } else {
                    //echo "{$link['title']}\n";
                }
            }
        }

        fclose($csvFh);
    }

    public function extractPdf($c) {
        $files = array();
        $pos = strpos($c, '<div id="ctl00_cphMainBody_Panel_Img">');
        if (false !== $pos) {
            $c = substr($c, $pos, strpos($c, '</div>', $pos) - $pos);
            $blocks = explode('</td>', $c);
            foreach ($blocks AS $block) {
                $parts = explode('window.open(\'', $block);
                if (count($parts) === 5) {
                    $fileName = substr($parts[4], 0, strpos($parts[4], '\''));
                    $fileTitle = strip_tags(substr($parts[4], strpos($parts[4], '>') + 1));
                    $files[] = array(String::uuid(), $fileName, $fileTitle);
                }
            }
        }
        return $files;
    }

    public function extractValues($c) {
        $pos = strpos($c, '"hidden"');
        $form = array(
            'ctl00$cphMainBody$ImageButton_Search.x' => '10',
            'ctl00$cphMainBody$ImageButton_Search.y' => '10',
            'ctl00_cphMainBody_OrgTreeView_ExpandState' => 'ecnnnnnccnnncncnnncnnnnnnnnnnnnncnnncnnncnnncnnncnnncnnnnnennnnncnnnnncnncncnncnncncnccnnnccncncn',
            'ctl00_cphMainBody_OrgTreeView_SelectedNode' => 'ctl00_cphMainBody_OrgTreeViewt15',
        );
        while (false !== $pos) {
            $posEnd = strpos($c, '/>', $pos);
            $parts = explode('"', substr($c, $pos, $posEnd - $pos));
            if (isset($parts[7])) {
                $form[$parts[3]] = $parts[7];
            } else {
                if (false !== strpos($parts[3], 'ClientState')) {
                    $form[$parts[3]] = 'false';
                } else {
                    $form[$parts[3]] = '';
                }
            }
            $pos = strpos($c, '<input type="hidden"', $posEnd);
        }
        if (false !== strpos($c, 'ctl00$scriptManager1')) {
            $form['ctl00$scriptManager1'] = 'ctl00$cphMainBody$UpdatePanel2|ctl00$cphMainBody$ImageButton_Search';
        }
        return $form;
    }

    public function extractLinks($c) {
        $key = 'Communique_QueryResult.aspx?ID=';
        $pos = strpos($c, $key);
        while (false !== $pos) {
            $posEnd = strpos($c, '</a>', $pos);
            $part = substr($c, $pos, $posEnd - $pos);
            $url = substr($part, 0, strpos($part, '"'));
            if (!isset($this->links[$url])) {
                $title = strip_tags(substr($part, strrpos($part, '">') + 2));
                if (false === strpos($title, '公投')) {
                    $this->links[$url] = array(
                        'title' => $this->titlePrefix . $title,
                        'processed' => false,
                    );
                }
            }
            $pos = strpos($c, $key, $posEnd);
        }
    }

    public function extractCities($c) {
        $cities = array();
        $pos = strpos($c, 'ctl00$cphMainBody$DropDownList_City');
        if (false !== $pos) {
            $pos = strpos($c, '<option', $pos);
            $posEnd = strpos($c, '</select>', $pos);
            $options = explode('</option>', substr($c, $pos, $posEnd - $pos));
            foreach ($options AS $k => $v) {
                $vPos = strpos($v, 'value="');
                if (false !== $vPos) {
                    $vPos += 7;
                    $vPosEnd = strpos($v, '"', $vPos);
                    $cities[substr($v, $vPos, $vPosEnd - $vPos)] = trim(strip_tags($v));
                }
            }
        }
        return $cities;
    }

    public function treeLinks($c, $titlePrefix = '', $urlPrefix = '') {
        $linksFound = array();
        $key = '<a href=';
        $pos = strpos($c, $key);
        while (false !== $pos) {
            $pos = strpos($c, '=', $pos) + 1;
            $posEnd = strpos($c, '</a', $pos);
            $part = explode('>', substr($c, $pos, $posEnd - $pos));
            $part[0] = str_replace(array('\'', '"'), array('', ''), $part[0]);
            if (false !== strpos($part[0], '?')) {
                switch ($part[0]) {
                    case '員林鎮大?里里長.pdf':
                        $part[0] = '員林鎮大峯里里長.pdf';
                        $part[1] = '員林鎮大峯里里長';
                        break;
                    case '埔心鄉南?村村長.pdf':
                        $part[0] = '埔心鄉南舘村村長.pdf';
                        $part[1] = '埔心鄉南舘村村長';
                        break;
                    case '埔心鄉埤?村村長.pdf':
                        $part[0] = '埔心鄉埤脚村村長.pdf';
                        $part[1] = '埔心鄉埤脚村村長';
                        break;
                    case '埔心鄉新?村村長.pdf':
                        $part[0] = '埔心鄉新舘村村長.pdf';
                        $part[1] = '埔心鄉新舘村村長';
                        break;
                    case '埔心鄉舊?村村長.pdf':
                        $part[0] = '埔心鄉舊舘村村長.pdf';
                        $part[1] = '埔心鄉舊舘村村長';
                        break;
                    case '埔鹽鄉?子村村長.pdf':
                        $part[0] = '埔鹽鄉廍子村村長.pdf';
                        $part[1] = '埔鹽鄉廍子村村長';
                        break;
                    case '埔鹽鄉瓦?村村長.pdf':
                        $part[0] = '埔鹽鄉瓦磘村村長.pdf';
                        $part[1] = '埔鹽鄉瓦磘村村長';
                        break;
                    case '彰化市下?里里長.pdf':
                        $part[0] = '彰化市下廍里里長.pdf';
                        $part[1] = '彰化市下廍里里長';
                        break;
                    case '彰化市寶?里里長.pdf':
                        $part[0] = '彰化市寶廍里里長.pdf';
                        $part[1] = '彰化市寶廍里里長';
                        break;
                    case '彰化市磚?里里長.pdf':
                        $part[0] = '彰化市磚磘里里長.pdf';
                        $part[1] = '彰化市磚磘里里長';
                        break;
                    case '芳苑鄉頂?村村長.pdf':
                        $part[0] = '芳苑鄉頂廍村村長.pdf';
                        $part[1] = '芳苑鄉頂廍村村長';
                        break;
                    case '萬華區糖?里里長.PDF':
                        $part[0] = '萬華區糖廍里里長.PDF';
                        $part[1] = '萬華區糖廍里里長';
                        break;
                    case '北屯區?子里里長.PDF':
                        $part[0] = '北屯區廍子里里長.PDF';
                        $part[1] = '北屯區廍子里里長';
                        break;
                    case '土城區延寮、金城、?廷、延吉、延壽、延祿、平和、安和、延和里長.pdf':
                        $part[0] = '土城區延寮、金城、峯廷、延吉、延壽、延祿、平和、安和、延和里長.pdf';
                        $part[1] = '土城區延寮、金城、峯廷、延吉、延壽、延祿、平和、安和、延和里長';
                        break;
                    case '永和區永成、忠義、文化、仁愛、上溪、信義、後溪、頂溪、河堤、新?里里長.pdf':
                        $part[0] = '永和區永成、忠義、文化、仁愛、上溪、信義、後溪、頂溪、河堤、新廍里里長.pdf';
                        $part[1] = '永和區永成、忠義、文化、仁愛、上溪、信義、後溪、頂溪、河堤、新廍里里長';
                        break;
                    case '外埔區?子里里長.pdf':
                        $part[0] = '外埔區廍子里里長.pdf';
                        $part[1] = '外埔區廍子里里長';
                        break;
                    case '大安區龜?里里長.pdf':
                        $part[0] = '大安區龜壳里里長.pdf';
                        $part[1] = '大安區龜壳里里長';
                        break;
                    case '清水區?榔里里長.pdf':
                        $part[0] = '清水區槺榔里里長.pdf';
                        $part[1] = '清水區槺榔里里長';
                        break;
                    case '西區公?里里長.pdf':
                        $part[0] = '西區公舘里里長.pdf';
                        $part[1] = '西區公舘里里長';
                        break;
                    case '西區?龍里里長.pdf':
                        $part[0] = '西區双龍里里長.pdf';
                        $part[1] = '西區双龍里里長';
                        break;
                    case '中埔鄉中埔村、??村、裕民村、灣潭村、隆興村、義仁村、社口村長.pdf':
                        $part[0] = '中埔鄉中埔村、塩舘村、裕民村、灣潭村、隆興村、義仁村、社口村長.pdf';
                        $part[1] = '中埔鄉中埔村、塩舘村、裕民村、灣潭村、隆興村、義仁村、社口村長';
                        break;
                    case '中埔鄉龍門村、石?村、東興村、中崙村、頂埔村、同仁村、瑞豐村、深坑村、三層村、沄水村長.pdf':
                        $part[0] = '中埔鄉龍門村、石硦村、東興村、中崙村、頂埔村、同仁村、瑞豐村、深坑村、三層村、沄水村長.pdf';
                        $part[1] = '中埔鄉龍門村、石硦村、東興村、中崙村、頂埔村、同仁村、瑞豐村、深坑村、三層村、沄水村長';
                        break;
                    case '朴子市?溪里、溪口里、德興里、仁和里、大鄉里、大葛里、佳禾里、新寮里長.pdf':
                        $part[0] = '朴子市双溪里、溪口里、德興里、仁和里、大鄉里、大葛里、佳禾里、新寮里長.pdf';
                        $part[1] = '朴子市双溪里、溪口里、德興里、仁和里、大鄉里、大葛里、佳禾里、新寮里長';
                        break;
                    case '民雄鄉豊收村、三興村、東興村、鎮北村、北斗村、?福村、大崎村、秀林村、松山村、福樂村長.pdf':
                        $part[0] = '民雄鄉豊收村、三興村、東興村、鎮北村、北斗村、双福村、大崎村、秀林村、松山村、福樂村長.pdf';
                        $part[1] = '民雄鄉豊收村、三興村、東興村、鎮北村、北斗村、双福村、大崎村、秀林村、松山村、福樂村長';
                        break;
                    case '水上鄉水上村、水頭村、粗溪村、下?村、三和村、回歸村長.pdf':
                        $part[0] = '水上鄉水上村、水頭村、粗溪村、下寮村、三和村、回歸村長.pdf';
                        $part[1] = '水上鄉水上村、水頭村、粗溪村、下寮村、三和村、回歸村長';
                        break;
                    case '溪口鄉溪東村、溪西村、溪北村、?溝村、疊溪村、妙崙村、坪頂村長.pdf':
                        $part[0] = '溪口鄉溪東村、溪西村、溪北村、柳溝村、疊溪村、妙崙村、坪頂村長.pdf';
                        $part[1] = '溪口鄉溪東村、溪西村、溪北村、柳溝村、疊溪村、妙崙村、坪頂村長';
                        break;
                    case '左營區北?里里長.pdf':
                        $part[0] = '左營區北廍里里長.pdf';
                        $part[1] = '左營區北廍里里長';
                        break;
                    case '左營區南?里里長.pdf':
                        $part[0] = '左營區南廍里里長.pdf';
                        $part[1] = '左營區南廍里里長';
                        break;
                    case '三峽區三峽、秀川、八張、中埔、弘道、永?、鳶山、龍埔、龍恩、龍學、礁溪、安溪、介壽、中正里里長.pdf':
                        $part[0] = '三峽區三峽、秀川、八張、中埔、弘道、永舘、鳶山、龍埔、龍恩、龍學、礁溪、安溪、介壽、中正里里長.pdf';
                        $part[1] = '三峽區三峽、秀川、八張、中埔、弘道、永舘、鳶山、龍埔、龍恩、龍學、礁溪、安溪、介壽、中正里里長';
                        break;
                    case '中和區景安、景文、錦和、錦昌、灰?、積穗、民享、員山、嘉穗、文元里里長.pdf':
                        $part[0] = '中和區景安、景文、錦和、錦昌、灰磘、積穗、民享、員山、嘉穗、文元里里長.pdf';
                        $part[1] = '中和區景安、景文、錦和、錦昌、灰磘、積穗、民享、員山、嘉穗、文元里里長';
                        break;
                    case '中和區福美、福祥、瓦?、信和、佳和、新南、南山、平河、福和里里長.pdf':
                        $part[0] = '中和區福美、福祥、瓦磘、信和、佳和、新南、南山、平河、福和里里長.pdf';
                        $part[1] = '中和區福美、福祥、瓦磘、信和、佳和、新南、南山、平河、福和里里長';
                        break;
                    case '梅山鄉?溪村、大南村、安靖村、永興村、半天村.pdf':
                        $part[0] = '梅山鄉双溪村、大南村、安靖村、永興村、半天村.pdf';
                        $part[1] = '梅山鄉双溪村、大南村、安靖村、永興村、半天村';
                        break;
                    case '梅山鄉太平村、太興村、龍眼村、碧湖村、瑞?村、瑞里村、太和村.pdf':
                        $part[0] = '梅山鄉太平村、太興村、龍眼村、碧湖村、瑞峯村、瑞里村、太和村.pdf';
                        $part[1] = '梅山鄉太平村、太興村、龍眼村、碧湖村、瑞峯村、瑞里村、太和村';
                        break;
                    case '竹崎鄉龍山村、文?村、金獅村、復金村、緞繻村、仁壽村、中和村、光華村.pdf':
                        $part[0] = '竹崎鄉龍山村、文峯村、金獅村、復金村、緞繻村、仁壽村、中和村、光華村.pdf';
                        $part[1] = '竹崎鄉龍山村、文峯村、金獅村、復金村、緞繻村、仁壽村、中和村、光華村';
                        break;
                    case '板橋區港德、民權、民安、漢生、公?、新民里里長.pdf':
                        $part[0] = '板橋區港德、民權、民安、漢生、公舘、新民里里長.pdf';
                        $part[1] = '板橋區港德、民權、民安、漢生、公舘、新民里里長';
                        break;
                    default:
                        echo "{$part[0]}\n\n";
                        exit();
                        $part[0] = '';
                        $part[1] = '';
                }
            }
            $url = $urlPrefix . $part[0];
            $slashPos = strrpos($url, '/') + 1;
            $finalPart = rawurlencode(substr($url, $slashPos));
            $isPdf = true;
            $ext = substr(strtolower($finalPart), -3);
            if ($ext !== 'pdf') {
                if ($ext === 'mp3') {
                    continue;
                }
                $finalPart .= '/';
                $isPdf = false;
            }
            $url = substr($url, 0, $slashPos) . $finalPart;
            if (!isset($this->links[$url]) && isset($part[1])) {
                $title = $part[1];
                $this->links[$url] = array(
                    'title' => $titlePrefix . $title,
                    'isPdf' => $isPdf,
                );
                $linksFound[] = array(
                    'url' => $url,
                    'title' => $titlePrefix . $title,
                );
            }
            $pos = strpos($c, $key, $posEnd);
        }
        if (!empty($linksFound)) {
            foreach ($linksFound AS $link) {
                $cCache = TMP . '103/' . md5($link['url']);
                if (!file_exists($cCache)) {
                    $cap = urldecode($link['url']);
                    echo "downloading {$cap}\n";
                    file_put_contents($cCache, $this->s->get($link['url']));
                }
                $cContent = file_get_contents($cCache);
                $this->treeLinks($cContent, "{$link['title']} > ", $link['url']);
            }
        }
    }

}
