<?php
/**
 * Command line utility to use dompdf.
 * Can also be used with HTTP GET parameters
 * 
 * @package dompdf
 * @link    http://dompdf.github.com/
 * @author  Benj Carson <benjcarson@digitaljunkies.ca>
 * @license http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 */

/**
 * Display command line usage
 */
function dompdf_usage() {
  $default_paper_size = DOMPDF_DEFAULT_PAPER_SIZE;
  
  echo <<<EOD
  
Usage: {$_SERVER["argv"][0]} [options] html_file

html_file can be a filename, a url if fopen_wrappers are enabled, or the '-' character to read from standard input.

Options:
 -h             Show this message
 -l             List available paper sizes
 -p size        Paper size; something like 'letter', 'A4', 'legal', etc.  
                  The default is '$default_paper_size'
 -o orientation Either 'portrait' or 'landscape'.  Default is 'portrait'
 -b path        Set the 'document root' of the html_file.  
                  Relative urls (for stylesheets) are resolved using this directory.  
                  Default is the directory of html_file.
 -f file        The output filename.  Default is the input [html_file].pdf
 -v             Verbose: display html parsing warnings and file not found errors.
 -d             Very verbose: display oodles of debugging output: every frame 
                  in the tree printed to stdout.
 -t             Comma separated list of debugging types (page-break,reflow,split)
 
EOD;
exit;
}

/**
 * Parses command line options
 * 
 * @return array The command line options
 */
function getoptions() {

  $opts = array();

  if ( $_SERVER["argc"] == 1 )
    return $opts;

  $i = 1;
  while ($i < $_SERVER["argc"]) {

    switch ($_SERVER["argv"][$i]) {

    case "--help":
    case "-h":
      $opts["h"] = true;
      $i++;
      break;

    case "-l":
      $opts["l"] = true;
      $i++;
      break;

    case "-p":
      if ( !isset($_SERVER["argv"][$i+1]) )
        die("-p switch requires a size parameter\n");
      $opts["p"] = $_SERVER["argv"][$i+1];
      $i += 2;
      break;

    case "-o":
      if ( !isset($_SERVER["argv"][$i+1]) )
        die("-o switch requires an orientation parameter\n");
      $opts["o"] = $_SERVER["argv"][$i+1];
      $i += 2;
      break;

    case "-b":
      if ( !isset($_SERVER["argv"][$i+1]) )
        die("-b switch requires a path parameter\n");
      $opts["b"] = $_SERVER["argv"][$i+1];
      $i += 2;
      break;

    case "-f":
      if ( !isset($_SERVER["argv"][$i+1]) )
        die("-f switch requires a filename parameter\n");
      $opts["f"] = $_SERVER["argv"][$i+1];
      $i += 2;
      break;

    case "-v":
      $opts["v"] = true;
      $i++;
      break;

    case "-d":
      $opts["d"] = true;
      $i++;
      break;

    case "-t":
      if ( !isset($_SERVER['argv'][$i + 1]) )
        die("-t switch requires a comma separated list of types\n");
      $opts["t"] = $_SERVER['argv'][$i+1];
      $i += 2;
      break;

   default:
      $opts["filename"] = $_SERVER["argv"][$i];
      $i++;
      break;
    }

  }
  return $opts;
}

require_once("dompdf_config.inc.php");
global $_dompdf_show_warnings, $_dompdf_debug, $_DOMPDF_DEBUG_TYPES;

$sapi = php_sapi_name();
$options = array();

$dompdf = new DOMPDF();

switch ( $sapi ) {

 case "cli":

  $opts = getoptions();

  if ( isset($opts["h"]) || (!isset($opts["filename"]) && !isset($opts["l"])) ) {
    dompdf_usage();
    exit;
  }

  if ( isset($opts["l"]) ) {
    echo "\nUnderstood paper sizes:\n";

    foreach (array_keys(CPDF_Adapter::$PAPER_SIZES) as $size)
      echo "  " . mb_strtoupper($size) . "\n";
    exit;
  }
  $file = $opts["filename"];

  if ( isset($opts["p"]) )
    $paper = $opts["p"];
  else
    $paper = DOMPDF_DEFAULT_PAPER_SIZE;

  if ( isset($opts["o"]) )
    $orientation = $opts["o"];
  else
    $orientation = "portrait";

  if ( isset($opts["b"]) )
    $base_path = $opts["b"];

  if ( isset($opts["f"]) )
    $outfile = $opts["f"];
  else {
    if ( $file === "-" )
      $outfile = "dompdf_out.pdf";
    else
      $outfile = str_ireplace(array(".html", ".htm"), "", $file) . ".pdf";
  }

  if ( isset($opts["v"]) )
    $_dompdf_show_warnings = true;

  if ( isset($opts["d"]) ) {
    $_dompdf_show_warnings = true;
    $_dompdf_debug = true;
  }

  if ( isset($opts['t']) ) {
    $arr = explode(',',$opts['t']);
    $types = array();
    foreach ($arr as $type)
      $types[ trim($type) ] = 1;
    $_DOMPDF_DEBUG_TYPES = $types;
  }
  
  $save_file = true;

  break;

 default:

  $dompdf->set_option('enable_php', false);
  
  // SECURITY FIX: Validate and sanitize input_file parameter with iterative decoding
  if ( isset($_GET["input_file"]) ) {
    $file = rawurldecode($_GET["input_file"]);
    
    // SECURITY FIX: Iterative decoding to prevent double-encoding attacks
    $decoded = $file;
    $iteration = 0;
    while ($decoded !== ($next_decode = rawurldecode($decoded)) && $iteration < 3) {
      $decoded = $next_decode;
      $iteration++;
    }
    if ($iteration >= 3) {
      throw new DOMPDF_Exception("Multiple encoding layers detected in input_file parameter.");
    }
    $file = $decoded;
    
    // SECURITY FIX: Remove null bytes to prevent null byte injection
    $file = str_replace(chr(0), '', $file);
    
    // SECURITY FIX: Normalize Unicode characters
    if (class_exists('Normalizer')) {
      $file = Normalizer::normalize($file, Normalizer::FORM_C);
    }
    
    // SECURITY FIX: Check for any ".." occurrence to catch all traversal patterns
    if (strpos($file, '..') !== false) {
      throw new DOMPDF_Exception("Invalid file path: directory traversal detected in input_file parameter.");
    }
    
    // SECURITY FIX: Reject URL-encoded traversal patterns
    if (preg_match('/\.\.%/', $file)) {
      throw new DOMPDF_Exception("Invalid file path: encoded directory traversal detected in input_file parameter.");
    }
    
    // SECURITY FIX: Early chroot validation if chroot is defined
    if (defined('DOMPDF_CHROOT')) {
      $file_real = realpath($file);
      if ($file_real !== false) {
        $chroot_real = realpath(DOMPDF_CHROOT);
        if (strpos($file_real, $chroot_real) !== 0) {
          throw new DOMPDF_Exception("File outside chroot boundary.");
        }
      }
    }
  } else {
    throw new DOMPDF_Exception("An input file is required (i.e. input_file _GET variable).");
  }
  
  if ( isset($_GET["paper"]) )
    $paper = rawurldecode($_GET["paper"]);
  else
    $paper = DOMPDF_DEFAULT_PAPER_SIZE;
  
  if ( isset($_GET["orientation"]) )
    $orientation = rawurldecode($_GET["orientation"]);
  else
    $orientation = "portrait";
  
  // SECURITY FIX: Validate and sanitize base_path parameter
  if ( isset($_GET["base_path"]) ) {
    $base_path = rawurldecode($_GET["base_path"]);
    
    // SECURITY FIX: Remove null bytes from base_path
    $base_path = str_replace(chr(0), '', $base_path);
    
    // SECURITY FIX: Normalize Unicode in base_path
    if (class_exists('Normalizer')) {
      $base_path = Normalizer::normalize($base_path, Normalizer::FORM_C);
    }
    
    // SECURITY FIX: Validate base_path doesn't contain traversal sequences
    if (strpos($base_path, '..') !== false) {
      throw new DOMPDF_Exception("Invalid base path: directory traversal detected in base_path parameter.");
    }
    
    // SECURITY FIX: Secure path concatenation with proper validation
    $combined_path = realpath($base_path . DIRECTORY_SEPARATOR . $file);
    if ($combined_path === false) {
      throw new DOMPDF_Exception("Invalid file path after concatenation.");
    }
    
    $base_real = realpath($base_path);
    if ($base_real === false || strpos($combined_path, $base_real) !== 0) {
      throw new DOMPDF_Exception("Invalid file path after concatenation: file outside base path.");
    }
    
    $file = $combined_path;
  }  
  
  if ( isset($_GET["options"]) ) {
    $options = $_GET["options"];
  }
  
  $file_parts = explode_url($file);
  
  $outfile = "dompdf_out.pdf"; # Don't allow them to set the output file
  $save_file = false; # Don't save the file
  
  break;
}

if ( $file === "-" ) {
  $str = "";
  while ( !feof(STDIN) )
    $str .= fread(STDIN, 4096);

  $dompdf->load_html($str);

} else
  $dompdf->load_html_file($file);

if ( isset($base_path) ) {
  $dompdf->set_base_path($base_path);
}

$dompdf->set_paper($paper, $orientation);

$dompdf->render();

if ( $_dompdf_show_warnings ) {
  global $_dompdf_warnings;
  foreach ($_dompdf_warnings as $msg)
    echo $msg . "\n";
  echo $dompdf->get_canvas()->get_cpdf()->messages;
  flush();
}

if ( $save_file ) {
//   if ( !is_writable($outfile) )
//     throw new DOMPDF_Exception("'$outfile' is not writable.");
  if ( strtolower(DOMPDF_PDF_BACKEND) === "gd" )
    $outfile = str_replace(".pdf", ".png", $outfile);

  list($proto, $host, $path, $file) = explode_url($outfile);
  if ( $proto != "" ) // i.e. not file://
    $outfile = $file; // just save it locally, FIXME? could save it like wget: ./host/basepath/file

  $outfile = realpath(dirname($outfile)) . DIRECTORY_SEPARATOR . basename($outfile);

  if ( strpos($outfile, DOMPDF_CHROOT) !== 0 )
    throw new DOMPDF_Exception("Permission denied.");

  file_put_contents($outfile, $dompdf->output( array("compress" => 0) ));
  exit(0);
}

if ( !headers_sent() ) {
  $dompdf->stream($outfile, $options);
}

