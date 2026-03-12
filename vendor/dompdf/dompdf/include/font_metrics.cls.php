<?php
/**
 * @package dompdf
 * @link    http://dompdf.github.com/
 * @author  Benj Carson <benjcarson@digitaljunkies.ca>
 * @author  Helmut Tischer <htischer@weihenstephan.org>
 * @author  Fabien Ménager <fabien.menager@gmail.com>
 * @license http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 */

require_once DOMPDF_LIB_DIR . "/class.pdf.php";

/**
 * Name of the font cache file
 *
 * This file must be writable by the webserver process only to update it
 * with save_font_families() after adding the .afm file references of a new font family
 * with Font_Metrics::save_font_families().
 * This is typically done only from command line with load_font.php on converting
 * ttf fonts to ufm with php-font-lib.
 *
 * Declared here because PHP5 prevents constants from being declared with expressions
 */
define('__DOMPDF_FONT_CACHE_FILE', DOMPDF_FONT_DIR . "dompdf_font_family_cache.php");

/**
 * The font metrics class
 *
 * This class provides information about fonts and text.  It can resolve
 * font names into actual installed font files, as well as determine the
 * size of text in a particular font and size.
 *
 * @static
 * @package dompdf
 */
class Font_Metrics {

  /**
   * @see __DOMPDF_FONT_CACHE_FILE
   */
  const CACHE_FILE = __DOMPDF_FONT_CACHE_FILE;
  
  /**
   * Underlying {@link Canvas} object to perform text size calculations
   *
   * @var Canvas
   */
  static protected $_pdf = null;

  /**
   * Array of font family names to font files
   *
   * Usually cached by the {@link load_font.php} script
   *
   * @var array
   */
  static protected $_font_lookup = array();
  
  
  /**
   * Class initialization
   *
   */
  static function init(Canvas $canvas = null) {
    if (!self::$_pdf) {
      if (!$canvas) {
        $canvas = Canvas_Factory::get_instance(new DOMPDF());
      }
      
      self::$_pdf = $canvas;
    }
  }

  /**
   * Calculates text size, in points
   *
   * @param string $text the text to be sized
   * @param string $font the desired font
   * @param float  $size the desired font size
   * @param float  $word_spacing
   * @param float  $char_spacing
   *
   * @internal param float $spacing word spacing, if any
   * @return float
   */
  static function get_text_width($text, $font, $size, $word_spacing = 0.0, $char_spacing = 0.0) {
    //return self::$_pdf->get_text_width($text, $font, $size, $word_spacing, $char_spacing);
    
    // @todo Make sure this cache is efficient before enabling it
    static $cache = array();
    
    if ( $text === "" ) {
      return 0;
    }
    
    // Don't cache long strings
    $use_cache = !isset($text[50]); // Faster than strlen
    
    $key = "$font/$size/$word_spacing/$char_spacing";
    
    if ( $use_cache && isset($cache[$key][$text]) ) {
      return $cache[$key]["$text"];
    }
    
    $width = self::$_pdf->get_text_width($text, $font, $size, $word_spacing, $char_spacing);
    
    if ( $use_cache ) {
      $cache[$key][$text] = $width;
    }
    
    return $width;
  }

  /**
   * Calculates font height
   *
   * @param string $font
   * @param float $size
   * @return float
   */
  static function get_font_height($font, $size) {
    return self::$_pdf->get_font_height($font, $size);
  }

  /**
   * Resolves a font family & subtype into an actual font file
   * Subtype can be one of 'normal', 'bold', 'italic' or 'bold_italic'.  If
   * the particular font family has no suitable font file, the default font
   * ({@link DOMPDF_DEFAULT_FONT}) is used.  The font file returned
   * is the absolute pathname to the font file on the system.
   *
   * @param string $family_raw
   * @param string $subtype_raw
   *
   * @return string
   */
  static function get_font($family_raw, $subtype_raw = "normal") {
    static $cache = array();
    
    if ( isset($cache[$family_raw][$subtype_raw]) ) {
      return $cache[$family_raw][$subtype_raw];
    }
    
    /* Allow calling for various fonts in search path. Therefore not immediately
     * return replacement on non match.
     * Only when called with NULL try replacement.
     * When this is also missing there is really trouble.
     * If only the subtype fails, nevertheless return failure.
     * Only on checking the fallback font, check various subtypes on same font.
     */
    
    $subtype = strtolower($subtype_raw);
    
    if ( $family_raw ) {
      $family = str_replace( array("'", '"'), "", strtolower($family_raw));
      
      if ( isset(self::$_font_lookup[$family][$subtype]) ) {
        return $cache[$family_raw][$subtype_raw] = self::$_font_lookup[$family][$subtype];
      }
      
      return null;
    }

    $family = "serif";

    if ( isset(self::$_font_lookup[$family][$subtype]) ) {
      return $cache[$family_raw][$subtype_raw] = self::$_font_lookup[$family][$subtype];
    }
    
    if ( !isset(self::$_font_lookup[$family]) ) {
      return null;
    }
    
    $family = self::$_font_lookup[$family];

    foreach ( $family as $sub => $font ) {
      if (strpos($subtype, $sub) !== false) {
        return $cache[$family_raw][$subtype_raw] = $font;
      }
    }

    if ($subtype !== "normal") {
      foreach ( $family as $sub => $font ) {
        if ($sub !== "normal") {
          return $cache[$family_raw][$subtype_raw] = $font;
        }
      }
    }

    $subtype = "normal";

    if ( isset($family[$subtype]) ) {
      return $cache[$family_raw][$subtype_raw] = $family[$subtype];
    }
    
    return null;
  }
  
  static function get_family($family) {
    $family = str_replace( array("'", '"'), "", mb_strtolower($family));
    
    if ( isset(self::$_font_lookup[$family]) ) {
      return self::$_font_lookup[$family];
    }
    
    return null;
  }

  /**
   * Saves the stored font family cache
   *
   * The name and location of the cache file are determined by {@link
   * Font_Metrics::CACHE_FILE}.  This file should be writable by the
   * webserver process.
   *
   * @see Font_Metrics::load_font_families()
   */
  static function save_font_families() {
    // replace the path to the DOMPDF font directories with the corresponding constants (allows for more portability)
    $cache_data = sprintf("<?php return array (%s", PHP_EOL);
    foreach (self::$_font_lookup as $family => $variants) {
      $cache_data .= sprintf("  '%s' => array(%s", addslashes($family), PHP_EOL);
      foreach ($variants as $variant => $path) {
        $path = sprintf("'%s'", $path);
        $path = str_replace('\'' . DOMPDF_FONT_DIR , 'DOMPDF_FONT_DIR . \'' , $path);
        $path = str_replace('\'' . DOMPDF_DIR , 'DOMPDF_DIR . \'' , $path);
        $cache_data .= sprintf("    '%s' => %s,%s", $variant, $path, PHP_EOL);
      }
      $cache_data .= sprintf("  ),%s", PHP_EOL);
    }
    $cache_data .= ") ?>";
    file_put_contents(self::CACHE_FILE, $cache_data);
  }

  /**
   * Loads the stored font family cache
   *
   * @see save_font_families()
   */
  static function load_font_families() {
    $dist_fonts = require_once DOMPDF_DIR . "/lib/fonts/dompdf_font_family_cache.dist.php";
    
    // FIXME: temporary step for font cache created before the font cache fix
    if ( is_readable( DOMPDF_FONT_DIR . "dompdf_font_family_cache" ) ) {
      $old_fonts = require_once DOMPDF_FONT_DIR . "dompdf_font_family_cache";
      // If the font family cache is still in the old format
      if ( $old_fonts === 1 ) {
        $cache_data = file_get_contents(DOMPDF_FONT_DIR . "dompdf_font_family_cache");
        file_put_contents(DOMPDF_FONT_DIR . "dompdf_font_family_cache", "<"."?php return $cache_data ?".">");
        $old_fonts = require_once DOMPDF_FONT_DIR . "dompdf_font_family_cache";
      }
      $dist_fonts += $old_fonts;
    }
    
    if ( !is_readable(self::CACHE_FILE) ) {
      self::$_font_lookup = $dist_fonts;
      return;
    }
    
    $cache_data = require_once self::CACHE_FILE;
    
    // If the font family cache is still in the old format
    if ( self::$_font_lookup === 1 ) {
      $cache_data = file_get_contents(self::CACHE_FILE);
      file_put_contents(self::CACHE_FILE, "<"."?php return $cache_data ?".">");
      $cache_data = require_once self::CACHE_FILE;
    }
    
    self::$_font_lookup = array();
    foreach ($cache_data as $key => $value) {
      self::$_font_lookup[stripslashes($key)] = $value;
    }
    
    // Merge provided fonts
    self::$_font_lookup += $dist_fonts;
  }
  
  static function get_type($type) {
    if (preg_match("/bold/i", $type)) {
      if (preg_match("/italic|oblique/i", $type)) {
        $type = "bold_italic";
      }
      else {
        $type = "bold";
      }
    }
    elseif (preg_match("/italic|oblique/i", $type)) {
      $type = "italic";
    }
    else {
      $type = "normal";
    }
      
    return $type;
  }
  
  static function install_fonts($files) {
    $names = array();
    
    foreach($files as $file) {
      $font = Font::load($file);
      $records = $font->getData("name", "records");
      $type = self::get_type($records[2]);
      $names[mb_strtolower($records[1])][$type] = $file;
    }
    
    return $names;
  }
  
  static function get_system_fonts() {
    $files = glob("/usr/share/fonts/truetype/*.ttf") +
             glob("/usr/share/fonts/truetype/*/*.ttf") +
             glob("/usr/share/fonts/truetype/*/*/*.ttf") +
             glob("C:\\Windows\\fonts\\*.ttf") + 
             glob("C:\\WinNT\\fonts\\*.ttf") + 
             glob("/mnt/c_drive/WINDOWS/Fonts/");
    
    return self::install_fonts($files);
  }

  /**
   * Returns the current font lookup table
   *
   * @return array
   */
  static function get_font_families() {
    return self::$_font_lookup;
  }

  static function set_font_family($fontname, $entry) {
    self::$_font_lookup[mb_strtolower($fontname)] = $entry;
  }
  
static function register_font($style, $remote_file, $context = null, $expected_hash = null) {
    // Validate and sanitize font family name - prevents path traversal and injection attacks
    $fontname = preg_replace('/[^a-zA-Z0-9_-]/', '', mb_strtolower($style["family"]));
    if (empty($fontname)) {
      throw new Exception("Invalid font family name");
    }
    
    // Validate URL scheme - only allow HTTPS to prevent SSRF attacks
    $allowed_schemes = ['https'];
    $parsed_url = parse_url($remote_file);
    
    if (!$parsed_url || !isset($parsed_url['scheme']) || !in_array($parsed_url['scheme'], $allowed_schemes)) {
      throw new Exception("Invalid or unsupported URL scheme. Only HTTPS is allowed.");
    }
    
    // Whitelist trusted domains - prevent SSRF to arbitrary hosts
    $allowed_domains = ['fonts.googleapis.com', 'fonts.gstatic.com', 'fonts.adobe.com', 'use.typekit.net'];
    if (!isset($parsed_url['host']) || !in_array($parsed_url['host'], $allowed_domains)) {
      throw new Exception("Untrusted font source. Only whitelisted domains are allowed.");
    }
    
    // Validate font family length - prevent resource exhaustion
    if (strlen($style["family"]) > 255) {
      throw new Exception("Font family name exceeds maximum allowed length");
    }
    
    $families = Font_Metrics::get_font_families();
    
    $entry = array();
    if ( isset($families[$fontname]) ) {
      $entry = $families[$fontname];
      
      // FIX: Verify cached file integrity to prevent exploitation of tampered cached fonts
      $weight = preg_replace('/[^a-zA-Z0-9_-]/', '', $style['weight']);
      $styleParam = preg_replace('/[^a-zA-Z0-9_-]/', '', $style['style']);
      $style_string = Font_Metrics::get_type("{$weight} {$styleParam}");
      
      if (isset($entry[$style_string]) && file_exists($entry[$style_string] . '.ttf')) {
          $cached_file_hash = hash_file('sha256', $entry[$style_string] . '.ttf');
          $stored_hash = Font_Metrics::get_stored_hash($fontname, $style_string);
          if ($stored_hash !== null && $cached_file_hash !== $stored_hash) {
              // Cache corruption detected, re-download required
              unset($entry[$style_string]);
          }
      }
    }
    
    // Sanitize weight and style parameters - prevent SQL injection
    $weight = preg_replace('/[^a-zA-Z0-9_-]/', '', $style['weight']);
    $styleParam = preg_replace('/[^a-zA-Z0-9_-]/', '', $style['style']);
    
    $style_string = Font_Metrics::get_type("{$weight} {$styleParam}");
    
    if ( !isset($entry[$style_string]) ) {
      // Download the remote file with timeout protection
      $download_context = $context;
      if ($download_context === null) {
        $download_context = stream_context_create([
          'http' => [
            'timeout' => 30,  // Prevent resource exhaustion with reasonable timeout
            'follow_location' => 0  // Prevent redirect-based SSRF
          ]
        ]);
      }
      
      $remote_content = @file_get_contents($remote_file, false, $download_context);
      if ($remote_content === false) {
        throw new Exception("Failed to download font file from remote source");
      }
      
      // FIX: Verify downloaded content integrity using SHA-256 hash to prevent MITM attacks
      if ($expected_hash !== null) {
          $downloaded_hash = hash('sha256', $remote_content);
          if ($downloaded_hash !== $expected_hash) {
              throw new Exception("Font file integrity check failed - hash mismatch");
          }
      }
      
      // FIX: Hash the actual content instead of URL string to prevent cache poisoning
      $hash = hash('sha256', $remote_content);
      $random_suffix = bin2hex(random_bytes(8));
      $local_file = DOMPDF_FONT_DIR . $hash . "_" . $random_suffix;
      $local_temp_file = DOMPDF_TEMP_DIR . "/" . $hash . "_" . $random_suffix;
      $cache_entry = $local_file;
      $local_file .= ".ttf";
      
      file_put_contents($local_temp_file, $remote_content);
      
      // Validate file type - ensure it's a legitimate font file
      if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $local_temp_file);
        finfo_close($finfo);
        
        $allowed_mime_types = [
          'application/x-font-ttf', 
          'font/ttf', 
          'application/font-sfnt',
          'application/vnd.ms-fontobject',
          'font/otf',
          'application/x-font-otf'
        ];
        
        if (!in_array($mime, $allowed_mime_types)) {
          unlink($local_temp_file);
          throw new Exception("Invalid font file type. Expected TTF or OTF font.");
        }
      }
      
      // Validate file signature (magic bytes) for additional security
      $file_handle = fopen($local_temp_file, 'rb');
      $magic_bytes = fread($file_handle, 4);
static function get_stored_hash($fontname, $style_string) {
    // Retrieve stored hash for cached font file integrity verification
    $hash_file = DOMPDF_FONT_DIR . "font_hashes.json";
    
    if (!file_exists($hash_file)) {
        return null;
    }
    
    $hashes = json_decode(file_get_contents($hash_file), true);
    if (!is_array($hashes)) {
        return null;
    }
    
    return isset($hashes[$fontname][$style_string]) ? $hashes[$fontname][$style_string] : null;
static function store_hash($fontname, $style_string, $hash) {
    // Store hash of font file for future integrity verification
    $hash_file = DOMPDF_FONT_DIR . "font_hashes.json";
    
    $hashes = array();
    if (file_exists($hash_file)) {
        $hashes = json_decode(file_get_contents($hash_file), true);
        if (!is_array($hashes)) {
            $hashes = array();
        }
    }
    
    if (!isset($hashes[$fontname])) {
        $hashes[$fontname] = array();
    }
    
    $hashes[$fontname][$style_string] = $hash;
    
    file_put_contents($hash_file, json_encode($hashes, JSON_PRETTY_PRINT));
}

      file_put_contents($local_file, $remote_content);
      
      // FIX: Store the hash of the saved file for future integrity verification
      $final_file_hash = hash_file('sha256', $local_file);
      Font_Metrics::store_hash($fontname, $style_string, $final_file_hash);
      
      $entry[$style_string] = $cache_entry;
      Font_Metrics::set_font_family($fontname, $entry);
      Font_Metrics::save_font_families();
    }
    
    return true;
  }
