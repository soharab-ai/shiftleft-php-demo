<?php
// FIX: Strengthen fontfile validation with whitelist pattern and path traversal prevention
$fontfile = null;
if (isset($_GET["fontfile"])) {
    $requested_file = basename($_GET["fontfile"]);
    
    // FIX: Whitelist - Only allow .ttf, .otf extensions with safe characters
    if (preg_match('/^[a-zA-Z0-9_-]+\.(ttf|otf)$/i', $requested_file)) {
        $fontfile = "../fonts/$requested_file";
        
        // FIX: Verify the resolved path is within the fonts directory to prevent directory traversal
        $real_fontfile = realpath($fontfile);
        $real_fonts_dir = realpath("../fonts/");
        
        if ($real_fontfile === false || strpos($real_fontfile, $real_fonts_dir) !== 0) {
            $fontfile = null;
            return;
        }
    } else {
        return;
    }
}

if (!file_exists($fontfile)) {
  return;
}

// FIX: Implement strict input validation for name parameter using whitelist pattern
$name = null;
if (isset($_GET["name"])) {
    // FIX: Validate that name contains only alphanumeric, spaces, hyphens, and underscores
    if (preg_match('/^[a-zA-Z0-9\s\-_]{1,100}$/u', $_GET["name"])) {
        $name = $_GET["name"];
    } else {
        // FIX: Reject invalid input and use default
        $name = "Invalid Font Name";
    }
}

if (isset($_POST["subset"])) {
  $subset = $_POST["subset"];
  
  ob_start();
  
  require_once "../classes/Font.php";
  
  $font = Font::load($fontfile);
  $font->parse();
  
  $font->setSubset($subset);
  $font->reduce();

  $new_filename = basename($fontfile);
  $new_filename = substr($new_filename, 0, -4)."-subset.".substr($new_filename, -3);
  
  header("Content-Type: font/truetype");
  header("Content-Disposition: attachment; filename=\"$new_filename\"");
  
  $tmp = tempnam(sys_get_temp_dir(), "fnt");
  $font->open($tmp, Font_Binary_Stream::modeWrite);
  $font->encode(array("OS/2"));
  $font->close();
  
  ob_end_clean();
  
  readfile($tmp);
  unlink($tmp);
  
  return;
}

// FIX: Add Content Security Policy headers for browser-level XSS protection
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; object-src 'none'; base-uri 'self'; form-action 'self';");
// FIX: Add additional security headers to prevent MIME-sniffing and clickjacking
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Subset maker</title>
  <link rel="stylesheet" href="css/style.css" />
</head>
<body>
  <!-- FIX: Output encoding with null coalescing operator and ENT_HTML5 flag for robust XSS prevention -->
  <h1><?php echo htmlspecialchars($name ?? 'Unknown Font', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></h1>
  <!-- FIX: Properly escape fontfile parameter in form action attribute to prevent XSS -->
  <form name="make-subset" method="post" action="?fontfile=<?php echo htmlspecialchars(basename($fontfile), ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>">
    <label>
      Insert the text from which you want the glyphs in the subsetted font: <br />
      <textarea name="subset" cols="50" rows="20"></textarea>
    </label>
    <br />
    <button type="submit">Make subset!</button>
  </form>
</body>
</html>

</body>
</html>