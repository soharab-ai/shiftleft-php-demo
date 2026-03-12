<?php
// SECURITY FIX: Add output buffering control to prevent header injection
ob_start();
ini_set('display_errors', '0');

// SECURITY FIX: Add explicit Content-Type header before CSP to prevent charset-based XSS
header("Content-Type: text/html; charset=UTF-8");
// SECURITY FIX: Add Content Security Policy header for defense-in-depth
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; font-src 'self';");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

// SECURITY FIX: Implement Twig template engine with auto-escaping
require_once "../vendor/autoload.php";
require_once "../classes/Font.php";

$loader = new \Twig\Loader\FilesystemLoader('../templates');
$twig = new \Twig\Environment($loader, [
    'autoescape' => 'html',
    'strict_variables' => true
]);

// SECURITY FIX: Implement whitelist-based validation for font files
$allowedFonts = [
    'arial.ttf', 'times.ttf', 'courier.ttf', 'helvetica.ttf', 
    'verdana.ttf', 'georgia.ttf', 'trebuchet.ttf', 'impact.ttf',
    'comicsans.ttf', 'palatino.ttf'
];

$fontfile = null;
if (isset($_GET["fontfile"])) {
    $fontfileInput = basename($_GET["fontfile"]);
    
    // SECURITY FIX: Validate against whitelist to prevent directory traversal and injection
    if (in_array($fontfileInput, $allowedFonts)) {
        $fontfile = "../fonts/" . $fontfileInput;
    } else {
        ob_end_clean();
        die("Error: Invalid font file specified. Allowed fonts: " . 
            htmlspecialchars(implode(', ', $allowedFonts), ENT_QUOTES, 'UTF-8'));
    }
}

// SECURITY FIX: Validate and sanitize unicodemap parameter
$unicodemap = false;
if (isset($_GET["unicodemap"]) && $_GET["unicodemap"] === '1') {
    $unicodemap = true;
}

$t = microtime(true);

$font = Font::load($fontfile);

if ($font instanceof Font_TrueType_Collection) {
    $font = $font->getFont(0);
}

// SECURITY FIX: Separate business logic from presentation - collect all data first
$viewData = [
    'fontFullName' => $font->getFontFullName(),
    'fontVersion' => $font->getFontVersion(),
    'fileSize' => round(filesize($fontfile) / 1024, 3),
    'memoryUsage' => memory_get_peak_usage(true) / 1024,
    'executionTime' => round(microtime(true) - $t, 4),
    'fontfile' => $fontfile,
    'fontSubfamily' => $font->getFontSubfamilyID(),
    'requestUri' => $_SERVER['REQUEST_URI'],
    'unicodemap' => $unicodemap,
    'tables' => [],
    'unicodeMapData' => [],
    'headerData' => var_export($font->header->data, true)
];

if ($unicodemap) {
    // SECURITY FIX: Extract unicode map data instead of generating HTML inline
    $subtable = null;
    foreach($font->getData("cmap", "subtables") as $_subtable) {
        if ($_subtable["platformID"] == 3 && $_subtable["platformSpecificID"] == 1) {
            $subtable = $_subtable;
            break;
        }
    }
    
    $names = $font->getData("post", "names");
    
    for($c = 0; $c <= 0xFFFF; $c++) { 
        if (isset($subtable["glyphIndexArray"][$c])) {
            $g = $subtable["glyphIndexArray"][$c];
            $glyphName = isset($names[$g]) ? $names[$g] : sprintf("uni%04x", $c);
            
            $viewData['unicodeMapData'][] = [
                'char' => $c,
                'glyphName' => $glyphName,
                'isEmpty' => false
            ];
        } else {
            // Track empty slots for spacing
            if (count($viewData['unicodeMapData']) > 0 && !$viewData['unicodeMapData'][count($viewData['unicodeMapData'])-1]['isEmpty']) {
                $viewData['unicodeMapData'][] = [
                    'char' => null,
                    'glyphName' => null,
                    'isEmpty' => true,
                    'emptyCount' => 1
                ];
            } elseif (count($viewData['unicodeMapData']) > 0) {
                $viewData['unicodeMapData'][count($viewData['unicodeMapData'])-1]['emptyCount']++;
            }
        }
    }
} else {
    $font->parse();
    
    // SECURITY FIX: Extract raw data from font objects instead of using toHTML()
    foreach($font->getTable() as $entry) {
        $tag = $entry->tag;
        $data = $font->getData($tag);
        
        $tableData = null;
        if ($data) {
            // SECURITY FIX: Extract raw data instead of calling toHTML() which may not escape properly
            $tableObject = $font->getTableObject($tag);
            if ($tableObject && method_exists($tableObject, 'getData')) {
                $tableData = var_export($data, true);
            } else {
                $tableData = var_export($data, true);
            }
        }
        
        $viewData['tables'][] = [
            'tag' => $tag,
            'tagId' => preg_replace("/[^a-z0-9]/i", "_", $tag),
            'data' => $tableData,
            'exists' => (bool)$data
        ];
    }
}

// SECURITY FIX: Clear output buffer and render template with auto-escaping
ob_end_clean();

if ($unicodemap) {
    echo $twig->render('font_info_unicode_map.html.twig', $viewData);
} else {
    echo $twig->render('font_info.html.twig', $viewData);
}


</html>