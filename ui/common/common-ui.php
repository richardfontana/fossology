<?php
/***********************************************************
 Copyright (C) 2009-2011 Hewlett-Packard Development Company, L.P.

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
***********************************************************/

/*****************************************
 DB2KeyValArray: 
   Create an associative array by using table
   rows to source the key/value pairs.

 Params:
   $Table   tablename
   $KeyCol  Key column name in $Table
   $ValCol  Value column name in $Table
   $Where   SQL where clause (optional)
            This can really be any clause following the
            table name in the sql

 Returns:
   Array[Key] = Val for each row in the table
   May be empty if no table rows or Where results
   in no rows.
 *****************************************/
function DB2KeyValArray($Table, $KeyCol, $ValCol, $Where="")
{
  global $PG_CONN;

  $ResArray = array();

  $sql = "SELECT $KeyCol, $ValCol from $Table $Where";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);

  while ($row = pg_fetch_assoc($result))
  {
    $ResArray[$row[$KeyCol]] = $row[$ValCol];
  }
  return $ResArray;
}


/*****************************************
 Array2SingleSelect: Build a single choice select pulldown

 Params:
   $KeyValArray   Assoc array.  Use key/val pairs for list
   $SLName        Select list name (default is "unnamed"),
   $SelectedVal   Initially selected value or key, depends 
                  on $SelElt
   $FirstEmpty    True if the list starts off with an empty choice
                  (default is false)
   $SelElt        True (default) if $SelectedVal is a value
                  False if $SelectedVal is a key
   $Options       Optional.  Options to add to the select statment.
                  For example, "id=myid onclick= ..."
 *****************************************/
function Array2SingleSelect($KeyValArray, $SLName="unnamed", $SelectedVal= "", 
                            $FirstEmpty=false, $SelElt=true, $Options="")
{
  $str ="\n<select name='$SLName' $Options>\n";
  if ($FirstEmpty) $str .= "<option value='' > \n";
  foreach ($KeyValArray as $key => $val)
  {
    if ($SelElt == true)
      $SELECTED = ($val == $SelectedVal) ? "SELECTED" : "";
    else
      $SELECTED = ($key == $SelectedVal) ? "SELECTED" : "";
    $str .= "<option value='$key' $SELECTED>$val\n";
  }
  $str .= "</select>";
  return $str;
}


/*****************************************
 DBCheckResult: 
   Check the postgres result for unexpected errors.
   If found, treat them as fatal.

 Params:
   $result  command result object
   $sql     SQL command (optional)
   $filenm  File name (__FILE__)
   $lineno  Line number of the caller (__LINE__)

 Returns:
   None, prints error, sql and line number, then exits(1)
 *****************************************/
function DBCheckResult($result, $sql="", $filenm, $lineno)
{
  global $PG_CONN;

  if (!$result)
  {
    echo "<hr>File: $filenm, Line number: $lineno<br>";
    if (pg_connection_status($PG_CONN) === PGSQL_CONNECTION_OK)
      echo pg_last_error($PG_CONN);
    else
      echo "FATAL: DB connection lost.";
    echo "<br> $sql";
    debugbacktrace();
    echo "<hr>";
    exit(1);
  }
}


/*****************************************
 Fatal: 
   Write message to stdout and die.

 Params:
   $msg     Message to write
   $filenm  File name (__FILE__)
   $lineno  Line number of the caller (__LINE__)

 Returns:
   None, prints error, file, and line number, then exits(1)
 *****************************************/
function Fatal($msg, $filenm, $lineno)
{
  echo "<hr>FATAL error, File: $filenm, Line number: $lineno<br>";
  echo "$msg<hr>";
  debugbacktrace();
  exit(1);
}


function debugbacktrace()
{
  echo "<pre>";
  debug_print_backtrace();
  echo "</pre>";
}

function debugprint($val, $title)
{
  echo $title, "<pre>";
  print_r($val);
  echo "</pre>";
}

function HumanSize( $bytes )
{
    $types = array( 'B', 'KB', 'MB', 'GB', 'TB' );
    for( $i = 0; $bytes >= 1024 && $i < ( count( $types ) -1 ); $bytes /= 1024, $i++ );
    return( round( $bytes, 2 ) . " " . $types[$i] );
}

/************************************
 Return File Extension (text after last period)
 ************************************/
function GetFileExt($fname)
{
  $extpos = strrpos($fname, '.') + 1;
  $extension = strtolower(substr($fname, $extpos, strlen($fname) - $extpos));
  return $extension;
}


/*
 * Return an array value, or "" if the array key does not exist
 */
function GetArrayVal($Key, $Arr)
{
  if (!is_array($Arr)) return "";
  if (array_key_exists($Key, $Arr))
    return ($Arr[$Key]);
  else
    return "";
}


/* send the download file to the user 
   Returns True on success, error message on failure.
 */
function DownloadFile($path, $name) 
{
    $regfile = file_exists($path);
    if (!$regfile) return _("File does not exist");

    $regfile = is_file($path);
    if (!$regfile) return _("Not a regular file");

    $connstat = connection_status();
    if ($connstat != 0) return _("Lost connection.");

    session_write_close();
    ob_end_clean();
//    header("Cache-Control: no-store, no-cache, must-revalidate");
//    header("Cache-Control: post-check=0, pre-check=0", false);
//    header("Pragma: no-cache");
    header("Expires: ".gmdate("D, d M Y H:i:s", mktime(date("H")+2, date("i"), date("s"), date("m"), date("d"), date("Y")))." GMT");
    header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");
    header('Content-Description: File Transfer');
    header("Content-Type: application/octet-stream");
    header("Content-Length: ".(string)(filesize($path)));
    header("Content-Disposition: attachment; filename=$name");
    header("Content-Transfer-Encoding: binary\n");

    /* read/write in chunks to optimize large file downloads */
    if ($file = fopen($path, 'rb')) 
    {
        while(!feof($file) and (connection_status()==0)) 
        {
            print(fread($file, 1024*8));
            flush();
        }
        fclose($file);
    }
    if ((connection_status()==0) and !connection_aborted()) return True;
    return _("Lost connection.");
}

?>
