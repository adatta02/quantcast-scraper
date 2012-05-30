<?php 

error_reporting(E_ALL);
require_once 'phpQuery-onefile.php';

if( file_exists(dirname(__FILE__) . "/proc.lock") ){
  exit(0);
}

$files = glob( dirname(__FILE__) . "/uploaded_files/*.process" );

if( count($files) == 0 ){
  exit(0);
}

touch( dirname(__FILE__) . "/proc.lock" );

$stopWordsFile = explode("\n", file_get_contents( dirname(__FILE__) . "/stopWords.txt") );

$file = array_pop( $files );
$lines = file($file);
$notificationEmailAddress = array_shift( $lines );

$base = str_replace(".process", "", basename( $file ));

$newFilename = dirname(__FILE__) . "/uploaded_files/" . $base . ".inprogress";
$targetFilename = dirname(__FILE__) . "/uploaded_files/" . $base . ".csv";
$targetAffinityFilename = dirname(__FILE__) . "/uploaded_files/" . $base . "_urls.csv";

rename( $file, $newFilename );

$scrapedData = array();
$affinityData = array();

$csvOutputHeader = array(
  "hostname", "has_stop_word", "monthly_reach", "us_people_per_month", "us_visits_per_month", "us_page_views_per_month",
  "int_people_per_month", "int_visits_per_month", "int_page_views_per_month",
  "us_visits_per_month_online", "int_visits_per_month_online", "us_visits_per_month_mobile", "int_visits_per_month_mobile",
  "us_page_views_per_month_online", "int_page_views_per_month_online", "us_page_views_per_month_mobile", "int_page_views_per_month_mobile"
);

$csvOutputData = array();
$csvOutputData[] = join(",", $csvOutputHeader);

$ch = curl_init ();
$count = 0;
$start = time();

echo "Processing " . $file . " for " . $notificationEmailAddress . "\n";

foreach( $lines as $ln ){
  
  $ln = strtolower( trim( $ln ) );
  
  if( strpos($ln, "http://") === false || strpos($ln, "https://") === false ){
    $ln = "http://" . $ln;
  }
  
  $ln = str_replace( "www.", "", $ln );  
  $hostname = parse_url( trim($ln), PHP_URL_HOST );  
     
  if( array_key_exists($hostname, $scrapedData) || !strlen($hostname) ){
    continue;
  }
  
  curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.1.2) Gecko/20090729 Firefox/3.5.2 GTB5');
  curl_setopt ( $ch, CURLOPT_URL, "http://www.quantcast.com/" . $hostname );
  curl_setopt ( $ch, CURLOPT_FAILONERROR, false );
  curl_setopt ( $ch, CURLOPT_FOLLOWLOCATION, 1 );
  curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
  curl_setopt ( $ch, CURLOPT_TIMEOUT, 300 );
  curl_setopt ( $ch, CURLOPT_POST, 0 );
  $result = curl_exec ( $ch );
  
  $count ++;  
  $doc = phpQuery::newDocument( $result );
  
  $dataArray = array();
  $rowCount = 0;
  foreach( $doc[".traffic"]->find("tr:gt(0)") as $row ){

    $usVal = str_replace(",", "", pq($row)->find(".digit:eq(0)")->text());
    $intVal = str_replace(",", "", pq($row)->find(".digit:eq(2)")->text());
    
    $csvBase = null;
    
    switch( $rowCount ){
      case 0: $csvBase = "people_per_month"; break;
      case 1: $csvBase = "visits_per_month"; break;
      case 2: $csvBase = "visits_per_month_online"; break;
      case 3: $csvBase = "visits_per_month_mobile"; break;
      case 4: $csvBase = "page_views_per_month"; break;
      case 5: $csvBase = "page_views_per_month_online"; break;
      case 6: $csvBase = "page_views_per_month_mobile"; break;      
      default: break;
    }
    
    if( $csvBase ){
      $dataArray[ "us_" . $csvBase ] = $usVal;
      $dataArray[ "int_" . $csvBase ] = $intVal;
    }
    
    $rowCount ++;
  }
  
  $dataArray[ "monthly_reach" ] = "";
  foreach( $doc[".reach"] as $eq ){
    
    if( strlen( trim(pq($eq)->attr("id")) ) ){
      $label = pq($eq)->find(".label")->text();
      $val = trim(str_replace( $label, "", pq($eq)->text() ));
      
      if( strpos($val, "M") ){
        $val = $val * 1000000;  
      }
      
      if( strpos($val, "K") ){
        $val = $val * 1000;
      }
      
      $dataArray[ "monthly_reach" ] = $val;
    }
    
  }
    
  foreach( $doc[".c-affinity-quiet"] as $el ){
    $host = ltrim( pq($el)->attr("href"), "/" );
    if( strlen($host) && !in_array($host, $affinityData) ){
      $affinityData[] = $host;
    }
  }
  
  $dataArray[ "has_stop_word" ] = array();
  $dataArray[ "hostname" ] = $hostname;
  $scrapedData[ $hostname ] = $dataArray;

  $chx = curl_init ();
  curl_setopt ( $chx, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.1.2) Gecko/20090729 Firefox/3.5.2 GTB5');
  curl_setopt ( $chx, CURLOPT_URL, "http://" . $hostname );
  curl_setopt ( $chx, CURLOPT_FAILONERROR, false );
  curl_setopt ( $chx, CURLOPT_FOLLOWLOCATION, 1 );
  curl_setopt ( $chx, CURLOPT_RETURNTRANSFER, 1 );
  curl_setopt ( $chx, CURLOPT_TIMEOUT, 300 );
  curl_setopt ( $chx, CURLOPT_POST, 0 );
  $urlResult = curl_exec ( $chx );
  
  foreach( $stopWordsFile as $word ){
    if( strpos($urlResult, $word) !== false ){
      $dataArray[ "has_stop_word" ][] = $word;
    }
  }
  
  if( count($dataArray[ "has_stop_word" ]) ){
    $dataArray[ "has_stop_word" ] = join(";", $dataArray[ "has_stop_word" ]);
  }else{
    $dataArray[ "has_stop_word" ] = "";
  }  
  
  $csvData = array( );
  foreach( $csvOutputHeader as $k ){ 
    
    if( array_key_exists($k, $dataArray) ){
      $csvData[] = $dataArray[ $k ];
    }else{
      $csvData[] = "";
    } 
    
  }
  $csvOutputData[] = join(", ", $csvData);
  
  file_put_contents($targetFilename, join("\n", $csvOutputData));
  file_put_contents($targetAffinityFilename, join("\n", $affinityData));
    
  echo $hostname . "\n";
  
  sleep(1);
}

curl_close ( $ch );

$sec = time() - $start;
$url = "http://hypervipr.com/quantcast-scraper/uploaded_files/" . $base . ".csv";
$moreUrls = "http://hypervipr.com/quantcast-scraper/uploaded_files/" . $base . "_urls.csv";

@mail( $notificationEmailAddress, 
	  "Your Quantcast.com enhanced file is ready!", 
"Processed $count URLs in $sec seconds. 
Download the files at $url
$moreUrls \n");

unlink( dirname(__FILE__) . "/proc.lock" );