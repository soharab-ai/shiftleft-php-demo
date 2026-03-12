<?php
// FIX: Implement secure session configuration for defense-in-depth protection
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');

$fontfile = null;
// FIX: Implement whitelist-based validation for fontfile parameter
$allowed_fonts = ['arial.ttf', 'helvetica.ttf', 'times.ttf', 'roboto.ttf', 'opensans.ttf'];
if (isset($_GET["fontfile"])) {
  $fontfile = basename($_GET["fontfile"]);
  if (!in_array($fontfile, $allowed_fonts, true)) {
    http_response_code(400);
    die("Invalid font file specified");
  }
  $fontfile = "../fonts/$fontfile";
}

if (!file_exists($fontfile)) {
  http_response_code(404);
  die("Font file not found");
}

// FIX: Secure name handling with validation, default value, and length restriction
$name = isset($_GET["name"]) ? $_GET["name"] : "Font Subset Tool";
// FIX: Validate name parameter to allow only alphanumeric and safe characters
if (!preg_match('/^[a-zA-Z0-9\s\-_]+$/', $name)) {
    $name = "Font Subset Tool";
}
// FIX: Limit length to prevent abuse
$name = substr($name, 0, 100);

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
  
  // FIX: Sanitize filename to prevent header injection attacks
  $safe_filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $new_filename);
  
  header("Content-Type: font/truetype");
  // FIX: Apply proper encoding for Content-Disposition header to prevent header injection
  header("Content-Disposition: attachment; filename=\"" . addslashes($safe_filename) . "\"");
  
  $tmp = tempnam(sys_get_temp_dir(), "fnt");
  $font->open($tmp, Font_Binary_Stream::modeWrite);
  $font->encode(array("OS/2"));
  $font->close();
  
  ob_end_clean();
  
  readfile($tmp);
  unlink($tmp);
  
  return;
}

// FIX: Add Content Security Policy header for defense-in-depth
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline';");
// FIX: Add X-Content-Type-Options to prevent MIME-sniffing attacks
header("X-Content-Type-Options: nosniff");
// FIX: Add X-Frame-Options to prevent clickjacking attacks
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
  <!-- FIX: Apply htmlspecialchars with ENT_QUOTES | ENT_HTML5 flags to prevent XSS in HTML content context -->
  <h1><?php echo htmlspecialchars($name, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></h1>
  <!-- FIX: Use hidden form field instead of URL parameter to eliminate query manipulation risks -->
  <form name="make-subset" method="post" action="make_subset.php">
    <input type="hidden" name="fontfile" value="<?php echo htmlspecialchars($fontfile, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>" />
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