<?php
 
 $ds = DIRECTORY_SEPARATOR;
 $EzPath = dirname(__FILE__);
 if ($dh = opendir("{$EzPath}{$ds}lib")){
    while (($file = readdir($dh)) !== false){
      if($file!='.' && $file!='..'){
      	require_once("{$EzPath}{$ds}lib{$ds}{$file}");
      }
    }
    closedir($dh);
 }




