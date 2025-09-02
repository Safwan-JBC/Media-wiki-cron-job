<?php
return function () {

    $apiEndpoint = "http://bwiki.dirkmeyer.info/api.php";
    $cookieFile = __DIR__ . "/../cookies.txt";
    $logFile = __DIR__ . "/../mediawiki_api.log";

    // Example HTML input
    $htmlContent = <<<HTML
<div style="background-color: #f0f0f0; padding: 10px;">
    <h2 style="color: red;">Student Info</h2>
    <p>This is <b>bold</b>, <i>italic</i>, and <u>underlined</u>.</p>
    <a href="https://example.com">Visit Example</a>
    <img src="https://randomuser.me/api/portraits/men/46.jpg" alt="Logo">
    <table style="background-color: blue; color: white; width: 100%;">
        <tr>
            <th style="border:1px solid white;">ID</th>
            <th>Name</th>
            <th>Age</th>
            <th>Grade</th>
        </tr>
        <tr>
            <td>1</td><td>Alice</td><td>15</td><td>A</td>
        </tr>
        <tr>
            <td>2</td><td>Bob</td><td>16</td><td>B</td>
        </tr>
    </table>
</div>
HTML;

    $logMessage = function ($msg) use ($logFile) {
        $time = date("Y-m-d H:i:s");
        file_put_contents($logFile, "[$time] $msg\n", FILE_APPEND);
    };

    $postRequest = function ($url, $postFields, $cookieFile, $writeCookie = false) use ($logMessage) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
        if ($writeCookie)
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        $response = curl_exec($ch);
        if (curl_errno($ch))
            $logMessage("cURL Error: " . curl_error($ch));
        curl_close($ch);
        return json_decode($response, true);
    };

    // --------------------------
    // Recursive HTML → Wikitext converter
    // --------------------------
    function htmlToWikiText($html)
    {
        $dom = new DOMDocument();
        // Suppress warnings for malformed HTML
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);

        $body = $dom->getElementsByTagName('body')->item(0);

        if ($body) {
            return traverseNode($body);
        } else {
            // No <body>, traverse entire DOM
            return traverseNode($dom);
        }
    }

    function traverseNode(DOMNode $node)
    {
        $output = '';
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $output .= $child->nodeValue;
                continue;
            }
            if ($child->nodeType !== XML_ELEMENT_NODE)
                continue;

            $tag = strtolower($child->nodeName);
            $style = $child->getAttribute("style");
            $inner = traverseNode($child);

            switch ($tag) {
                case "b":
                case "strong":
                    $inner = "'''$inner'''";
                    break;
                case "i":
                case "em":
                    $inner = "''$inner''";
                    break;
                case "u":
                    $inner = "<u>$inner</u>";
                    break;
                case "sup":
                    $inner = "<sup>$inner</sup>";
                    break;
                case "sub":
                    $inner = "<sub>$inner</sub>";
                    break;
                case "a":
                    $href = $child->getAttribute("href");
                    $inner = "[$href $inner]";
                    break;
                case "img":
                    $src = $child->getAttribute("src");
                    $alt = $child->getAttribute("alt");
                    $inner = "[[File:$src|$alt]]";
                    break;
                case "h1":
                    $inner = "= $inner =";
                    break;
                case "h2":
                    $inner = "== $inner ==";
                    break;
                case "h3":
                    $inner = "=== $inner ===";
                    break;
                case "h4":
                    $inner = "==== $inner ====";
                    break;
                case "h5":
                    $inner = "===== $inner =====";
                    break;
                case "h6":
                    $inner = "====== $inner ======";
                    break;
                case "p":
                case "div":
                    $inner .= "\n\n";
                    break;
                case "blockquote":
                    $lines = explode("\n", $inner);
                    foreach ($lines as &$line)
                        $line = "> $line";
                    $inner = implode("\n", $lines) . "\n";
                    break;
                case "hr":
                    $inner = "----\n";
                    break;
                case "table":
                    $inner = convertTableToWiki($child);
                    break;
                default:
                    break;
            }

            if ($style && !in_array($tag, ['table'])) {
                $inner = "<span style=\"$style\">$inner</span>";
            }

            $output .= $inner;
        }
        return $output;
    }

    function convertTableToWiki(DOMElement $table)
    {
        $wikitable = "{| class=\"wikitable\"";
        if ($table->hasAttribute("style"))
            $wikitable .= " style=\"" . $table->getAttribute("style") . "\"";
        $wikitable .= "\n";

        foreach ($table->getElementsByTagName("tr") as $row) {
            $wikitable .= "|-\n";
            foreach ($row->childNodes as $cell) {
                if ($cell->nodeType !== XML_ELEMENT_NODE)
                    continue;
                $tag = strtolower($cell->tagName);
                $text = trim(traverseNode($cell));
                $style = $cell->getAttribute("style");
                $stylePart = $style ? " style=\"$style\"" : "";
                if ($tag === "th")
                    $wikitable .= "!$stylePart | $text\n";
                else
                    $wikitable .= "|$stylePart | $text\n";
            }
        }
        $wikitable .= "|}\n";
        return $wikitable;
    }

    // --------------------------
    // Convert HTML → Wikitext and log
    // --------------------------
    $pageContent = htmlToWikiText($htmlContent);
    $logMessage("Converted Wikitext (to append as new section):\n" . $pageContent);

    // --------------------------
    // MediaWiki API: APPEND NEW SECTION
    // --------------------------
    $pageTitle = "My_Existing_Page";
    $sectionTitle = "New HTML Section";

    $logMessage("=== Getting Edit Token for section addition ===");
    $ch = curl_init($apiEndpoint . "?action=query&prop=info&intoken=edit&titles=" . urlencode($pageTitle) . "&format=json");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    $response = curl_exec($ch);
    curl_close($ch);
    $responseData = json_decode($response, true);

    $pages = $responseData['query']['pages'] ?? [];
    $editToken = null;
    foreach ($pages as $page) {
        if (isset($page['edittoken'])) {
            $editToken = $page['edittoken'];
            break;
        }
    }
    if (!$editToken) {
        $logMessage("No edit token found, exiting.");
        return;
    }

    $cleanEditToken = substr($editToken, 0, -1);
    $finalToken = $cleanEditToken . '\\';
    $logMessage("finalToken Edit Token: " . $finalToken);

    // Append as new section
    $response = $postRequest($apiEndpoint, [
        'action' => 'edit',
        'title' => $pageTitle,
        'section' => 'new',
        'sectiontitle' => $sectionTitle,
        'text' => $pageContent,
        'token' => $finalToken,
        'summary' => 'Appending new section via HTML → Wikitext conversion',
        'format' => 'json',
        'contentmodel' => 'wikitext'
    ], $cookieFile, true);

    $logMessage("Append Section Response: " . json_encode($response));
    $logMessage("=== MediaWiki Section Append Job Finished ===");
};
