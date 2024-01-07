<?php

error_reporting(E_ALL);
ini_set("display_errors", 1);

/*
 action:
  - empty
  - scan
  - scanning
  - scanned  
  - cancel
*/

class Scanner
{
    public static $IP = "10.0.0.14";
    public static $ScanRequestUrl = "http://{IP}:8080/eSCL/ScanJobs";
    public static $StatusUrl = "http://{IP}:8080/eSCL/ScannerStatus";
    
    public static $MaxWidth = 2550;
    public static $MaxHeight = 3508;
    public static $MaxXScanRes = 600;
    public static $MaxYScanRes = 600;       
    public static $DocumentFormatExt = "application/pdf"; // "image/jpeg"
    
    public static $ScanRequestXML = '<?xml version="1.0" encoding="utf-8"?>
            			<escl:ScanSettings xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:pwg="http://www.pwg.org/schemas/2010/12/sm" xmlns:escl="http://schemas.hp.com/imaging/escl/2011/05/03">
                                    <pwg:Version>2.63</pwg:Version>
                                    <pwg:ScanRegions pwg:MustHonor="false">
                                            <pwg:ScanRegion>
                                                    <pwg:ContentRegionUnits>escl:ThreeHundredthsOfInches</pwg:ContentRegionUnits>
                                                    <pwg:XOffset>0</pwg:XOffset>
                                                    <pwg:YOffset>0</pwg:YOffset>
                                                    <pwg:Width>{maxWidth}</pwg:Width>
                                                    <pwg:Height>{maxHeight}</pwg:Height>
                                            </pwg:ScanRegion>
                                    </pwg:ScanRegions>
                                    <escl:DocumentFormatExt>{documentFormatExt}</escl:DocumentFormatExt>
                                    <pwg:InputSource>Platen</pwg:InputSource>
                                    <escl:XResolution>{maxXScanRes}</escl:XResolution>
                                    <escl:YResolution>{maxYScanRes}</escl:YResolution>
                                    <escl:ColorMode>RGB24</escl:ColorMode>
            			</escl:ScanSettings>';
                                

    public static function DoScan()
    {
        echo "Scanning " . self::$IP . "<br/>\n";
          
        $url = str_replace("{IP}", self::$IP, self::$ScanRequestUrl);
        
        $request = str_replace("{maxWidth}", self::$MaxWidth, self::$ScanRequestXML);
        $request = str_replace("{maxHeight}", self::$MaxHeight, $request);
        $request = str_replace("{documentFormatExt}", self::$DocumentFormatExt, $request);
        
        $request = str_replace("{maxXScanRes}", self::$MaxXScanRes, $request);
        $request = str_replace("{maxYScanRes}", self::$MaxYScanRes, $request);
        
        
        echo "Url: " . $url . "<br/>\n";      
        
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
    }    
    
    // https://stackoverflow.com/questions/14953867/how-to-get-page-content-using-curl
    public static function GetPageResponse($url)
    {
        $user_agent='Mozilla/5.0 (Windows NT 6.1; rv:8.0) Gecko/20100101 Firefox/8.0';

        $options = array(

            CURLOPT_CUSTOMREQUEST  =>"GET",        //set request type post or get
            CURLOPT_POST           =>false,        //set to GET
            CURLOPT_USERAGENT      => $user_agent, //set user agent
            CURLOPT_COOKIEFILE     =>"cookie.txt", //set cookie file
            CURLOPT_COOKIEJAR      =>"cookie.txt", //set cookie jar
            CURLOPT_RETURNTRANSFER => true,     // return web page
            CURLOPT_HEADER         => false,    // don't return headers
            CURLOPT_FOLLOWLOCATION => true,     // follow redirects
            CURLOPT_ENCODING       => "",       // handle all encodings
            CURLOPT_AUTOREFERER    => true,     // set referer on redirect
            CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
            CURLOPT_TIMEOUT        => 120,      // timeout on response
            CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
        );

        $ch      = curl_init( $url );
        curl_setopt_array( $ch, $options );
        $content = curl_exec( $ch );        
        curl_close( $ch );        
        return $content;
    }       
    
    public static function GetTextBetweenTag($str, $tag)
    {        
       $startPos = strpos($str, "<$tag>");
        if ($startPos === false) 
        {
            Die('Error');
        } 
        $endPos = strpos($str, "</$tag>");
        if ($endPos === false) 
        {
            Die('Error');
        }        
        
        return substr($str,$startPos+strlen($tag) + 2,$endPos-$startPos-(strlen($tag) + 2));
    }
    
    public static function GetScanStatus()
    {        
        $url = str_replace("{IP}", self::$IP, self::$StatusUrl);
        
        $responseXML = self::GetPageResponse($url);
        
        $state = self::GetTextBetweenTag($responseXML,"pwg:JobState"); 
        
        return $state;
    }
    
    public static function GetScanImageToTransferStatus()
    {        
        $url = str_replace("{IP}", self::$IP, self::$StatusUrl);
        
        $responseXML = self::GetPageResponse($url);
        
        $complete = self::GetTextBetweenTag($responseXML,"pwg:ImagesToTransfer"); 
        
        return $complete;
    }    
    
    public static function GetScannedDocUrl()
    {        
        $url = str_replace("{IP}", self::$IP, self::$StatusUrl);
        
        $responseXML = self::GetPageResponse($url);
        
        return "http://" . self::$IP . ":8080" . self::GetTextBetweenTag($responseXML,"pwg:JobUri") . "/NextDocument";
    }     
}

$redirectTag = "";
$showHTMLHeader = true;

$act = "";
if (isset($_GET["action"]))
{
    $act = $_GET["action"];
}

if ($act == "scanning")
{ 
    $state = Scanner::GetScanStatus();
    
    if (strcmp($state, "Aborted") !== 0) 
    {}
    else 
    {
      $url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);        
      $redirectTag = "<meta http-equiv=\"refresh\" content=\"0; url=$url\" />";
    }
    
    if (strcmp($state, "Completed") !== 0) 
    {}
    else 
    {
      $url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);        
      $redirectTag = "<meta http-equiv=\"refresh\" content=\"0; url=$url\" />";
    }        
    
    $completed = Scanner::GetScanImageToTransferStatus();
    
    if (strcmp($completed, "1") !== 0) 
    {}
    else 
    {
        $url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) . "?action=scanned";        
        $redirectTag = "<meta http-equiv=\"refresh\" content=\"0; url=$url\" />";
    }
     
} else
if ($act == "scan")
{
    Scanner::DoScan();
    $url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) . "?action=scanning";    
    $redirectTag = "<meta http-equiv=\"refresh\" content=\"0; url=$url\" />";     
}
else    
if ($act == "scanned")
{
    $showHTMLHeader = false;
    $url = Scanner::GetScannedDocUrl();       
    
/*    
    ?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>HP HTML Scanner</title>
        <meta name="description" content="HP HTML Scanner">
        <meta name="keywords" content="HP HTML Scanner">
        <meta name="author" content="Petr Janoušek">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">        
    </head>
    <body>               
        <iframe width="800" height="600" src="<?php echo $url ?>"></iframe>
    </body>
</html>           
       
    <?php   
 */    
    
    header('Content-Type: application/pdf');      
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT,0); 
    curl_setopt($ch, CURLOPT_TIMEOUT,0);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.11 (KHTML, like Gecko) Chrome/23.0.1271.1 Safari/537.11');
    $res = curl_exec($ch);
    
    curl_close($ch) ;
    echo $res;
} 
if ($showHTMLHeader)
{
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>HP HTML Scanner</title>

        <meta name="description" content="HP HTML Scanner">
        <meta name="keywords" content="HP HTML Scanner">
        <meta name="author" content="Petr Janoušek">
        <? if ($redirectTag != "") echo $redirectTag ?>

        <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">

        <script type="text/javascript">
            var SimpleTimer = function()
            {
                // Property: Frequency of elapse event of the timer in millisecond
                this.Interval = 1000;

                // Property: Whether the timer is enable or not
                this.Enable = new Boolean(false);

                // Event: Timer tick
                this.Tick;

                // Member variable: Hold interval id of the timer
                var timerId = 0;

                // Member variable: Hold instance of this class
                var thisObject;

                // Function: Start the timer
                this.Start = function()
                {
                    this.Enable = new Boolean(true);

                    thisObject = this;
                    if (thisObject.Enable)
                    {
                        thisObject.timerId = setInterval(
                        function()
                        {
                            thisObject.Tick();
                        }, thisObject.Interval);
                    }
                };

                // Function: Stops the timer
                this.Stop = function()
                {
                    thisObject.Enable = new Boolean(false);
                    clearInterval(thisObject.timerId);
                };

            };            
        </script>
    </head>
    <body>       
        <h1>HP HTML Scanner</h1>
<?php 

if ($act == "scanning")
{
?>
         <script type="text/javascript">
          <!--
          
            ScanProgressTick = function()
            {
                var scanProgressDiv = document.getElementById("scanProgressDiv");

                /*
                ◴25f4
                ◵25f5
                ◶25f6
                ◷25f7
                */

                var part = Date.now() % 4000;
                var ch = "\u25f4";
                if (part>3000)
                {
                    ch = "\u25f7";
                } else
                if (part>2000)
                {
                    ch = "\u25f4";
                } else
                if (part>1000)
                {
                    ch = "\u25f5";
                } else
                {
                    ch = "\u25f6";
                }
                scanProgressDiv.innerHTML = ch;
            } 
            
            ReloadTick = function()
            {
                location.reload();
            }             
            
            progressTimer = new SimpleTimer();            
            progressTimer.Interval = 100;
            progressTimer.Tick = ScanProgressTick;
            progressTimer.Enable = true;
            progressTimer.Start();             
            
            setTimeout( () => { location.reload();}, 2000);         
            
           -->
          </script>
             
        <div id="scanProgressDiv" style="float:left;display:inline;"></div>
        
        <br/>
        <br/>
                
        <form id="cancelScanRequestForm" method="GET" action="">
           <input type="submit" name="action" value="cancel" title="Cancel" style="display:inline;width:325px;"/>
        </form>                
         
<?php
} else
{
?>           
        <form id="scanRequestForm" method="GET" action="">
           <input type="submit" name="action" value="scan" title="Scan" style="display:inline;width:325px;"/>
        </form>   
<?php
}
?>             
    </body>
</html>    
<?php
}
?>