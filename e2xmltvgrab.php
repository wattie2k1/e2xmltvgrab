<?php
stream_filter_register('xmlutf8', 'ValidUTF8XMLFilter');

class ValidUTF8XMLFilter extends php_user_filter
{
    protected static $pattern = '/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u';

    function filter($in, $out, &$consumed, $closing)
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            $bucket->data = preg_replace(self::$pattern, '', $bucket->data);
            $consumed += $bucket->datalen;
            stream_bucket_append($out, $bucket);
        }
        return PSFS_PASS_ON;
    }
}

if (!file_exists("config.inc.php")) {
  exit("Failed to open config file!\n");
  } 
else {
  include ("config.inc.php");    
}
 
$channels = new XMLReader;
if (!$channels->open("channels.xml")) {
    die("Failed to open 'channels.xml'");
}

$xw = new XMLWriter();
$xw->openURI($xmltv_path);
$xw->setIndent(true);
$xw->startDocument("1.0", "UTF-8");

$xw->startElement("tv");
$xw->startAttribute("generator-info-name");
$xw->text("e2xmltvgrab v0.1 by wattie");
$xw->endAttribute();
$xw->startAttribute("generator-info-url");
$xw->text("http://www.greyhole.de");
$xw->endAttribute();

while($channels->read()) {
  if ($channels->nodeType == XMLReader::ELEMENT && $channels->name == 'channel') {
    $xw->startElement('channel');
    $xw->startAttribute('id');
    $xw->text($channels->getAttribute('id'));
    $xw->endAttribute();
    
    $xw->startElement('display-name');
    $xw->startAttribute('lang');
    $xw->text($lang);
    $xw->endAttribute();
    $xw->text($channels->getAttribute('name'));
    $xw->endElement();

    $xw->startElement('url');
    $xw->text($channels->getAttribute('url'));
    $xw->endElement();

    $xw->startElement('icon');
    $xw->startAttribute('src');
    $xw->text('http://'.$e2ip.'/picon/'.str_replace(":", "_", rtrim($channels->getAttribute('e2id'), ":")).'.png');
    $xw->endAttribute();
    $xw->endElement();
    
    $xw->endElement();        
    
    echo "Adding station - ".$channels->getAttribute('name')." - to XMLTV\n";
    flush();
  }
}

$channels->close();

$channels = new XMLReader;
if (!$channels->open("channels.xml")) {
    die("Failed to open 'channels.xml'");
}
while($channels->read()) {
  if ($channels->nodeType == XMLReader::ELEMENT && $channels->name == 'channel') {
      
    $programme = simplexml_load_file("php://filter/read=xmlutf8/resource=http://".$e2ip."/web/epgservice?sRef=".$channels->getAttribute('e2id')); 
   
    echo "\n\nUpdating epg data for channel: ".$channels->getAttribute('name')."\n";
    flush();
    
    foreach( $programme->xpath( '//e2event' ) as $node ) {
      echo "o";
      flush();
      $xw->startElement('programme');
      $xw->startAttribute('start');
      $xw->text(date('YmdHis', (float)$node->e2eventstart) . ' +0000');
      $xw->endAttribute();
      $xw->startAttribute('stop');
      $xw->text(date('YmdHis', (float)$node->e2eventstart+(float)$node->e2eventduration) . ' +0000');
      $xw->endAttribute();
      $xw->startAttribute('channel');
      $xw->text($node->e2eventservicename);
      $xw->endAttribute();
      $xw->startElement('title');
      $xw->startAttribute('lang');
      $xw->text($lang);
      $xw->endAttribute();
      $xw->text($node->e2eventtitle);
      $xw->endElement();
      $xw->startElement('sub-title');
      $xw->startAttribute('lang');
      $xw->text($lang);
      $xw->endAttribute();
      $xw->text($node->e2eventdescription);
      $xw->endElement();
      $xw->startElement('desc');
      $xw->startAttribute('lang');
      $xw->text($lang);
      $xw->endAttribute();
      $xw->text($node->e2eventdescriptionextended);
      $xw->endElement();
      $xw->startElement('category');
      $xw->startAttribute('lang');
      $xw->text($lang);
      $xw->endAttribute();
      $xw->text($node->e2eventdescription);
      $xw->endElement();
      $xw->endElement();
      
      $dupe_start = $node->e2eventstart;
    }
  }
}

$channels->close();
$xw->endElement();

$xw->endDocument();
$xw->flush();
echo "\n\ndone!\n";
?>
