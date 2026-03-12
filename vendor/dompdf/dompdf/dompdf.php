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
  
  // SECURITY FIX: Restrict CLI file operations to designated input directory
  if (!defined('CLI_INPUT_DIRECTORY')) {
      define('CLI_INPUT_DIRECTORY', DOMPDF_CHROOT . '/input/');
  }
  
  $file = $opts["filename"];
  
  // SECURITY FIX: Enforce directory containment for CLI operations
  $cliInputDir = realpath(CLI_INPUT_DIRECTORY);
  if ($cliInputDir === false) {
      die("Error: CLI input directory not configured properly\n");
  }
  
  // If relative path, prepend the CLI input directory
  if ($file[0] !== '/') {
      $file = $cliInputDir . DIRECTORY_SEPARATOR . $file;
  }
  
  $resolved = realpath($file);
  if ($resolved === false || strpos($resolved, $cliInputDir) !== 0) {
      die("Error: File must be in designated input directory: " . CLI_INPUT_DIRECTORY . "\n");
  }
  $file = $resolved;

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
    // SECURITY FIX: Use explode instead of deprecated split
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
  
  // SECURITY FIX: Implement token-based file access for web requests
  // Replace direct file path handling with secure token lookup
  if ( isset($_GET["file_token"]) ) {
    $file_token = $_GET["file_token"];
    $file = $dompdf->lookupFileByToken($file_token);
    
    if ($file === null) {
      throw new DOMPDF_Exception("Invalid file token provided");
    }
  } elseif ( isset($_GET["input_file"]) ) {
    // SECURITY FIX: Strict filename validation - reject path separators upfront
    $input = rawurldecode($_GET["input_file"]);
    
    if (strpos($input, '/') !== false || 
        strpos($input, '\\') !== false || 
        strpos($input, "\0") !== false) {
      throw new DOMPDF_Exception("Filename must not contain path separators");
    }
    
    // SECURITY FIX: Validate filename contains only safe characters
    $validatedFilename = $dompdf->validateFilenameCharacters($input);
    
    // SECURITY FIX: If base_path provided, validate it and construct path securely
    if ( isset($_GET["base_path"]) ) {
      $base_path_input = rawurldecode($_GET["base_path"]);
      $chroot = $dompdf->get_option("chroot");
      
      // Resolve base_path to absolute path
      $resolvedBasePath = realpath($base_path_input);
      if ($resolvedBasePath === false) {
        throw new DOMPDF_Exception("Invalid base_path: path does not exist");
      }
      
      // Ensure base_path is within chroot
      $normalizedChroot = rtrim(realpath($chroot), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
      if (strpos($resolvedBasePath . DIRECTORY_SEPARATOR, $normalizedChroot) !== 0) {
        throw new DOMPDF_Exception("Invalid base_path: outside allowed directory");
      }
      
      $base_path = $resolvedBasePath;
      $file = rtrim($base_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $validatedFilename;
    } else {
      // SECURITY FIX: Use chroot directory when no base_path specified
      $file = rtrim($dompdf->get_option("chroot"), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $validatedFilename;
    }
  } else {
    throw new DOMPDF_Exception("An input file or file token is required (i.e. file_token or input_file _GET variable).");
  }
  
  if ( isset($_GET["paper"]) )
    $paper = rawurldecode($_GET["paper"]);
  else
    $paper = DOMPDF_DEFAULT_PAPER_SIZE;
  
  if ( isset($_GET["orientation"]) )
    $orientation = rawurldecode($_GET["orientation"]);
  else
    $orientation = "portrait";
  
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
  if ( strtolower(DOMPDF_PDF_BACKEND) === "gd" )
    $outfile = str_replace(".pdf", ".png", $outfile);

  list($proto, $host, $path, $file) = explode_url($outfile);
  if ( $proto != "" )
    $outfile = $file;

  $outfile = realpath(dirname($outfile)) . DIRECTORY_SEPARATOR . basename($outfile);

  if ( strpos($outfile, DOMPDF_CHROOT) !== 0 )
    throw new DOMPDF_Exception("Permission denied.");

  file_put_contents($outfile, $dompdf->output( array("compress" => 0) ));
  exit(0);
}

if ( !headers_sent() ) {
  $dompdf->stream($outfile, $options);
}

