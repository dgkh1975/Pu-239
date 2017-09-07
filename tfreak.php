<?php
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'bittorrent.php';
require_once INCL_DIR . 'user_functions.php';
check_user_status();

function rsstfreakinfo()
{
    require_once INCL_DIR . 'html_functions.php';
    global $INSTALLER09;
    $html = '';
    $use_limit = true;
    $limit = 5;
    $xml = file_get_contents('http://feed.torrentfreak.com/Torrentfreak/');
    $html .= "
                <div class='text-left'>";
    $icount = 1;
    $doc = new DOMDocument();
    @$doc->loadXML($xml);
    $items = $doc->getElementsByTagName('item');
    foreach ($items as $item) {
        $html .= "
                    <div class='bordered'>
                        <h3>
                            <u>" . $item->getElementsByTagName('title')->item(0)->nodeValue . "</u>
                        </h3>
                        <font class='small'>
                            by " . str_replace(['<![CDATA[', ']]>'], '', $item->getElementsByTagName('creator')->item(0)->nodeValue) . " on " . $item->getElementsByTagName('pubDate')->item(0)->nodeValue . "
                        </font>
                        <br>" .
                        str_replace(['<![CDATA[', ']]>'], '', $item->getElementsByTagName('description')->item(0)->nodeValue) . "
                        <a href='" . $item->getElementsByTagName('link')->item(0)->nodeValue . "' target='_blank'>
                            <font class='small'>
                                Read more
                            </font>
                        </a>
                    </div>";
        if ($use_limit && $icount == $limit) {
            break;
        }
        ++$icount;
    }
    $html = str_replace(['“', '”'], '"', $html);
    $html = str_replace(['’', '‘', '‘'], "'", $html);
    $html = str_replace('–', '-', $html);
    $html .= "
            </div>";

    return $html;
}
