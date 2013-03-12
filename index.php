<?php 

$isPost = false;
$error = null;
$isReset = false;

if( $_REQUEST["reset"] ){
    $memcache = memcache_connect('localhost', 11211);
    memcache_set($memcache, "quantcast_reset", true);
    $isReset = true;
}

if($_SERVER['REQUEST_METHOD'] == 'POST'):

  $isPost = true;

  if( !$_FILES["dataFile"] ){
    $error = "Sorry! You must upload a file!";
  }
  
  if( $_FILES["dataFile"] ){
    $targetFile = dirname(__FILE__) . "/uploaded_files/" . time() . ".process";
    $data = $_REQUEST["email"] . "\n" . file_get_contents( $_FILES["dataFile"]["tmp_name"] );
    file_put_contents($targetFile, $data);
  }
  
endif;

?>

<html>
<head>
    <meta charset="utf-8">
    <title>qunatcast scraper</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta content="" name="description">
    <meta content="" name="author">

    <!-- Le styles -->
    <link rel="stylesheet" href="bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="bootstrap/css/bootstrap-responsive.min.css">
    <link rel="stylesheet" href="css/style.css">
    
    <!-- Le HTML5 shim, for IE6-8 support of HTML5 elements -->
    <!--[if lt IE 9]>
      <script src="//html5shim.googlecode.com/svn/trunk/html5.js"></script>
    <![endif]-->

  </head>

  <body>
    
    <div class="navbar navbar-fixed-top">
      <div class="navbar-inner">
        <div class="container">
          <a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </a>
          <a class="brand" href="#">&nbsp;</a>
          <div class="nav-collapse">
            <ul class="nav">
              <li class="active"><a href="#">&nbsp;</a></li>
            </ul>
          </div><!--/.nav-collapse -->
        </div>
      </div>
    </div>
    
   <div id="body" class="container">
        
        <div class="span7" style="margin: auto; float: none">
            <div class="box centered">
              <h2>Enhance with Quantcast.com</h2>
              
              <?php if( $isPost ): ?>
                <div class="alert alert-success">
                  Your request has been queued! You'll receive an email when the run is complete.
                </div>
              <?php else: ?>
                <p>Upload a file with ONE URL per line and specify a notification email address.</p>
                
                <?php if( $isReset ): ?>
                    <div class="alert alert-info">
                        Everything has been cleared. Submit another file.
                    </div>
                <?php endif; ?>
                
                <form action="" method="POST" enctype="multipart/form-data">
                  <table class="table table-striped">
                    <tbody>
                      <tr><td><input type="file" name="dataFile"></td></tr>
                      <tr><td><input type="text" name="email" placeholder="enter notification email address..."></td></tr>
                      <tr><td><input type="submit" class="btn-primary btn" value="Process"></td></tr>
                    </tbody>
                  </table>
                </form>
              <?php endif; ?>
              
              <div style="padding-top: 40px;">
                  <a id="resetIt" href="index.php?reset=true" class="btn btn-large btn-danger">Reset Everything!</a>
              </div>
              
            </div>
        </div>
        
    </div>
    
  </body>
</html>