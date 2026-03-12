<?php
// SECURITY FIX: Add security headers with strict CSP using nonces
$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'nonce-{$nonce}';");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

// SECURITY FIX: Initialize autoloader
require_once '../vendor/autoload.php';

// SECURITY FIX: Helper function for safe HTML output using HTML Purifier
function safeToHTML($tableObject) {
    $config = HTMLPurifier_Config::createDefault();
    $config->set('HTML.Allowed', 'div,span,p,table,tr,td,th,pre,b,i,u,br,strong,em');
    $config->set('HTML.AllowedAttributes', '');
    $purifier = new HTMLPurifier($config);
    return $purifier->purify($tableObject->toHTML());
}

// SECURITY FIX: Helper function for rendering table data element-by-element
function renderTableDataSecurely($tableObject) {
    $tableData = $tableObject->getData();
    $output = "<table>";
    foreach ($tableData as $key => $value) {
        $output .= "<tr><td>" . htmlspecialchars($key, ENT_QUOTES | ENT_HTML5, 'UTF-8') . "</td>";
        $output .= "<td>" . htmlspecialchars(print_r($value, true), ENT_QUOTES | ENT_HTML5, 'UTF-8') . "</td></tr>";
    }
    $output .= "</table>";
    return $output;
}

require_once "../classes/Font.php";

$fontfile = null;
if (isset($_GET["fontfile"])) {
  // SECURITY FIX: Strict validation of fontfile parameter
  $fontfile = basename($_GET["fontfile"]);
  
  // SECURITY FIX: Whitelist validation - only allow alphanumeric, dash, underscore, and dot
  if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $fontfile)) {
    die("Invalid fontfile parameter");
  }
  
  $fontfile = "../fonts/$fontfile";
  
  // SECURITY FIX: Verify file exists and is within allowed directory (path traversal protection)
  $realPath = realpath($fontfile);
  $allowedPath = realpath("../fonts/");
  if (!$realPath || !$allowedPath || strpos($realPath, $allowedPath) !== 0) {
    die("Invalid font path");
  }
}

// SECURITY FIX: Strict type validation instead of sanitization
$unicodemap = isset($_GET["unicodemap"]) && $_GET["unicodemap"] === "1" ? "1" : "";

$t = microtime(true);

$font = Font::load($fontfile);

if ($font instanceof Font_TrueType_Collection) {
  $font = $font->getFont(0);
}

// SECURITY FIX: Prepare data for template with auto-escaping
$templateData = [
    'nonce' => $nonce,
    'fontfile' => $fontfile,
    'unicodemap' => $unicodemap,
    'filesize' => round(filesize($fontfile) / 1024, 3),
    'memory' => memory_get_peak_usage(true) / 1024,
    'time' => round(microtime(true) - $t, 4),
    'fontFullName' => $font->getFontFullName(),
    'fontVersion' => $font->getFontVersion(),
    'fontSubfamily' => $font->getFontSubfamilyID(),
    'requestUri' => filter_var($_SERVER['REQUEST_URI'] ?? '', FILTER_SANITIZE_URL),
    'headerData' => var_export($font->header->data, true),
    'tables' => []
];

if ($unicodemap) {
  $subtable = null;
  foreach($font->getData("cmap", "subtables") as $_subtable) {
    if ($_subtable["platformID"] == 3 && $_subtable["platformSpecificID"] == 1) {
      $subtable = $_subtable;
      break;
    }
  }
  
  $glyphsData = [];
  $empty = 0;
  $names = $font->getData("post", "names");
  
  for($c = 0; $c <= 0xFFFF; $c++) { 
    if (($c % 256 == 0 || $c == 0xFFFF) && $empty > 0) {
      $glyphsData[] = ['type' => 'empty', 'width' => $empty * 3];
      $empty = 0;
    }
    
    if (isset($subtable["glyphIndexArray"][$c])) {
      $g = $subtable["glyphIndexArray"][$c];
      
      if ($empty > 0) {
        $glyphsData[] = ['type' => 'empty', 'width' => $empty * 3];
        $empty = 0;
      }
      // SECURITY FIX: Store glyph data for auto-escaped template rendering
      $glyphName = isset($names[$g]) ? $names[$g] : sprintf("uni%04x", $c);
      $glyphsData[] = ['type' => 'glyph', 'code' => $c, 'name' => $glyphName];
    }
    else {
      $empty++;
    }
  }
  
  $templateData['glyphsData'] = $glyphsData;
}
else {
  $font->parse();
  
  // SECURITY FIX: Prepare table data for secure rendering
  foreach($font->getTable() as $entry) {
    $tag = $entry->tag;
    $data = $font->getData($tag);
    $safeTagId = preg_replace("/[^a-z0-9]/i", "_", $tag);
    
    $tableHtml = '';
    if ($data) {
      // SECURITY FIX: Use element-by-element rendering instead of toHTML()
      $tableHtml = renderTableDataSecurely($font->getTableObject($tag));
    } else {
      $tableHtml = "Not yet implemented";
    }
    
    $templateData['tables'][] = [
      'tag' => $tag,
      'safeTagId' => $safeTagId,
      'hasData' => (bool)$data,
      'content' => $tableHtml
    ];
  }
}

?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Font information</title>
  <link rel="stylesheet" href="css/style.css" />
  <link rel="stylesheet" href="css/blitzer/jquery-ui-1.8.14.custom.css" />
  <script type="text/javascript" src="js/jquery-1.5.1.min.js"></script>
  <script type="text/javascript" src="js/jquery-ui-1.8.14.custom.min.js"></script>
  <script type="text/javascript" src="js/glyph.js?v=5"></script>
  <script type="text/javascript" nonce="<?php echo htmlspecialchars($nonce, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>">
    $(function() {
      $("#tabs").tabs({
        select: function(event, ui) {
          if (ui.panel.id == "tabs-unicode-map") {
            // SECURITY FIX: Use JSON encoding for JavaScript context
            $(ui.panel).load(<?php echo json_encode($templateData['requestUri'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> + "&unicodemap=1");
          }
        }
      });
    });
  </script>
</head>
<body>
<?php 

if ($templateData['unicodemap']) {
  ?>
<style type="text/css" nonce="<?php echo htmlspecialchars($nonce, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>">
@font-face {
  font-family: unicode-map; 
  font-weight: normal;
  font-style: normal;
  font-variant: normal;
  src: url('<?php echo htmlspecialchars($templateData['fontfile'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>'); 
}
</style>
<div class="unicode-map">
  <?php 
  // SECURITY FIX: Auto-escaped template rendering for glyphs
  foreach ($templateData['glyphsData'] as $glyphData) {
    if ($glyphData['type'] === 'empty') {
      echo "<b style=\"width:" . htmlspecialchars($glyphData['width'], ENT_QUOTES | ENT_HTML5, 'UTF-8') . "px\"></b>";
    } else {
      $safeCode = htmlspecialchars($glyphData['code'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
      $safeName = htmlspecialchars($glyphData['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
      echo "<i><s>&#" . $safeCode . ";<br /><div class=\"info\">" . $safeCode . "<br />" . $safeName . "</div></s></i>";
    }
  }
  ?>
</div>

<?php
} 
else { 
  ?>
<span style="float: right;">
  File size: <?php echo htmlspecialchars($templateData['filesize'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>KB &mdash;
  Memory: <?php echo htmlspecialchars($templateData['memory'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>KB &mdash;
  Time: <?php echo htmlspecialchars($templateData['time'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>s
  <br />
  <a href="make_subset.php?fontfile=<?php echo htmlspecialchars($templateData['fontfile'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>&amp;name=<?php echo urlencode($templateData['fontSubfamily']); ?>">Make a subset of this font</a>
</span>

<h1><?php echo htmlspecialchars($templateData['fontFullName'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></h1>
<h3><?php echo htmlspecialchars($templateData['fontVersion'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></h3>
<hr />

<div id="tabs">
  <ul>
    <li><a href="#tabs-header">Header</a></li>

    <?php foreach($templateData['tables'] as $table) { ?>
      <li>
        <a <?php if (!$table['hasData']) { ?> style="color: #ccc;" <?php } ?> href="#tabs-<?php echo htmlspecialchars($table['safeTagId'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>"><?php echo htmlspecialchars($table['tag'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></a>
      </li>
    <?php } ?>
    <li><a href="#tabs-unicode-map">Unicode map</a></li>
  </ul>

  <div id="tabs-header"><pre><?php echo htmlspecialchars($templateData['headerData'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></pre></div>
  
  <?php foreach($templateData['tables'] as $table) { ?>
    <div id="tabs-<?php echo htmlspecialchars($table['safeTagId'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>">
      <?php 
      // SECURITY FIX: Output pre-rendered secure HTML
      echo $table['content'];
      ?>
    </div>
  <?php } ?>
    
  <div id="tabs-unicode-map"></div>
</div>

<?php } ?>
</body>
</html>
