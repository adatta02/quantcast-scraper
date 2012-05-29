<?php 

error_reporting(E_ALL);
require_once 'phpQuery-onefile.php';

// just run this on a crontab and it'll pick up the files to scrape

$files = glob( dirname(__FILE__) . "/uploaded_files/*.process" );

if( count($files) == 0 ){
  exit(0);
}

$file = array_pop( $files );
$lines = file($file);
$notificationEmailAddress = array_shift( $lines );

$newFilename = dirname(__FILE__) . "/uploaded_files/" . time() . ".result";
// rename( $file, $newFilename );

$scrapedData = array();

$ch = curl_init ();

foreach( $lines as $ln ){
  
  $hostname = str_replace("www.", "", parse_url( trim($ln), PHP_URL_HOST ));
  
  if( array_key_exists($hostname, $scrapedData) ){
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
  
  $scrapedData[ $hostname ] = $dataArray;
  
  print_r( $scrapedData );
  
  die();
}

curl_close ( $ch );

