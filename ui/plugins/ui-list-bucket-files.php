<?php
/***********************************************************
 Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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

/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
global $GlobalReady;
if (!isset($GlobalReady)) { exit; }

/*************************************************
 This plugin is used to:
   List files for a given bucket in a given uploadtree.
 The following are passed in:
   bapk   bucketagent_pk
   napk   nomosagent_pk
   item   uploadtree_pk
   bpk    bucket_pk
   bp     bucketpool_pk
 *************************************************/

define("TITLE_list_bucket_files", _("List Files for Bucket"));

class list_bucket_files extends FO_Plugin
{
  var $Name       = "list_bucket_files";
  var $Title      = TITLE_list_bucket_files;
  var $Version    = "1.0";
  var $Dependency = array("db","nomoslicense");
  var $DBaccess   = PLUGIN_DB_READ;
  var $LoginFlag  = 0;

  /***********************************************************
   RegisterMenus(): Customize submenus.
   ***********************************************************/
  function RegisterMenus()
  { 
    if ($this->State != PLUGIN_STATE_READY) { return(0); }

    // micro-menu
	$bucketagent_pk = GetParm("bapk",PARM_INTEGER);
	$uploadtree_pk = GetParm("item",PARM_INTEGER);
	$bucket_pk = GetParm("bpk",PARM_INTEGER);
	$bucketpool_pk = GetParm("bp",PARM_INTEGER);
	$nomosagent_pk = GetParm("napk",PARM_INTEGER);
	$Page = GetParm("page",PARM_INTEGER);

    $URL = $this->Name . "&bapk=$bucketagent_pk&item=$uploadtree_pk&bpk=$bucket_pk&bp=$bucketpool_pk&napk=$nomosagent_pk&page=-1";
$text = _("Show All Files");
    menu_insert($this->Name."::Show All",0, $URL, $text);

  } // RegisterMenus()
      

  /***********************************************************
   Output(): 
   Display all the files for a bucket in this subtree.
   ***********************************************************/
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    global $Plugins;
    global $DB, $PG_CONN;

    // make sure there is a db connection since I've pierced the core-db abstraction
    if (!$PG_CONN) { $dbok = $DB->db_init(); if (!$dbok) echo "NO DB connection"; }

    /*  Input parameters */
	$bucketagent_pk = GetParm("bapk",PARM_INTEGER);
	$uploadtree_pk = GetParm("item",PARM_INTEGER);
	$bucket_pk = GetParm("bpk",PARM_INTEGER);
	$bucketpool_pk = GetParm("bp",PARM_INTEGER);
	$nomosagent_pk = GetParm("napk",PARM_INTEGER);
	$BinNoSrc = GetParm("bns",PARM_INTEGER);  // 1 if requesting binary with no src
    $Excl = GetParm("excl",PARM_RAW);

	if (empty($uploadtree_pk) || empty($bucket_pk) || empty($bucketpool_pk)) 
    {
$text = _("is missing required parameters.");
      echo $this->Name . " $text";
      return;
    }
	$Page = GetParm("page",PARM_INTEGER);
	if (empty($Page)) { $Page=0; }

    $V="";
    $Time = time();
    $Max = 200;

    // Create cache of bucket_pk => bucket_name 
    // Since we are going to do a lot of lookups
    $sql = "select bucket_pk, bucket_name from bucket_def where bucketpool_fk=$bucketpool_pk";
    $result_name = pg_query($PG_CONN, $sql);
    DBCheckResult($result_name, $sql, __FILE__, __LINE__);
    $bucketNameCache = array();
    while ($name_row = pg_fetch_assoc($result_name))
      $bucketNameCache[$name_row['bucket_pk']] = $name_row['bucket_name'];
    pg_free_result($result_name);

    switch($this->OutputType)
    {
      case "XML":
	break;
      case "HTML":
      // micro menus
      $V .= menu_to_1html(menu_find($this->Name, $MenuDepth),0);

	/* Get all the files under this uploadtree_pk with this bucket */
	$V .= _("The following files are in bucket: '<b>");
	$V .= $bucketNameCache[$bucket_pk];
	$V .= "</b>'.\n";
$text = _("Display");
$text1 = _("excludes");
$text2 = _("files with these licenses");
    if (!empty($Excl)) $V .= "<br>$text <b>$text1</b> $text2: $Excl";

	$Offset = ($Page < 0) ? 0 : $Page*$Max;
    $order = "";
    $PkgsOnly = false;

    // Get bounds of subtree (lft, rgt) for this uploadtree_pk
    $sql = "SELECT lft,rgt,upload_fk FROM uploadtree 
              WHERE uploadtree_pk = $uploadtree_pk";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $lft = $row["lft"];
    $rgt = $row["rgt"];
    $upload_pk = $row["upload_fk"];
    pg_free_result($result);

    /* If $BinNoSrc, then only list binary packages in this subtree
     * that do not have Source packages.
     * Else list files in the asked for bucket.
     */
    if ($BinNoSrc)
    {
    }
    else
    {
    $limit = ($Page < 0) ? "":" limit $Offset+$Max";
    // Get all the uploadtree_pk's with this bucket (for this agent and bucketpool)
    // in this subtree.  Some of these might be containers, some not.
    $sql = "select uploadtree.*, bucket_file.nomosagent_fk as nomosagent_fk
               from uploadtree, bucket_file, bucket_def
               where upload_fk=$upload_pk and uploadtree.lft between $lft and $rgt
                 and ((ufile_mode & (1<<28)) = 0)
                 and uploadtree.pfile_fk=bucket_file.pfile_fk
                 and agent_fk=$bucketagent_pk
                 and bucket_fk=$bucket_pk
                 and bucketpool_fk=$bucketpool_pk
                 and bucket_pk=bucket_fk offset $Offset $limit";
    $fileresult = pg_query($PG_CONN, $sql);
    DBCheckResult($fileresult, $sql, __FILE__, __LINE__);
    $Count = pg_num_rows($fileresult);
    }

    if ($Count < (1.25 * $Max)) $Max = $Count;
    if ($Max < 1) $Max = 1;  // prevent div by zero in corner case of no files
    $order = " order by ufile_name asc";

	/* Get the page menu */
	if (($Count >= $Max) && ($Page >= 0))
	{
	  $VM = "<P />\n" . MenuEndlessPage($Page,intval((($Count+$Offset)/$Max))) . "<P />\n";
	  $V .= $VM;
	}
	else
	{
	  $VM = "";
	}

    // base url
    $baseURL = "?mod=" . $this->Name . "&bapk=$bucketagent_pk&item=$uploadtree_pk&bpk=$bucket_pk&bp=$bucketpool_pk&napk=$nomosagent_pk&page=-1";

    // for each uploadtree rec ($fileresult), find all the licenses in it and it's children
    $ShowBox = 1;
    $ShowMicro=NULL;
    $RowNum = $Offset;
    $Header = "";
    $LinkLast = "list_bucket_files&bapk=$bucketagent_pk";

    /* file display loop/table */
    $V .= "<table>";
$text = _("File");
    $V .= "<tr><th>$text</th><th>&nbsp";
    $ExclArray = explode(":", $Excl);
    $ItemNumb = 1;
if ($Count > 0)
    while ($row = pg_fetch_assoc($fileresult, $RowNum))
    {
      // get all the licenses in this subtree (bucket uploadtree_pk)
      $pfile_pk = $row['pfile_fk'];
      $licstring = GetFileLicenses_string($nomosagent_pk, $row['pfile_fk'], $row['uploadtree_pk']);
      $URLlicstring = urlencode($licstring);

      /* Allow user to exclude files with this exact license list */
      if (!empty($Excl))
        $URL = $baseURL ."&excl=".urlencode($Excl).":".$URLlicstring;
      else
        $URL = $baseURL ."&excl=$URLlicstring";
$text = _("Exclude files with license");
      $Header = "<a href=$URL>$text: $licstring.</a>";

      $ok = true;
      if ($Excl)
      {
        if (in_array($licstring, $ExclArray)) $ok = false;
      }
      if ($ok)
      {
        $nomosagent_pk = $row['nomosagent_fk'];
        $LinkLast = "view-license&bapk=$bucketagent_pk&napk=$nomosagent_pk";
        $V .= "<tr><td>";
        $V .= Dir2Browse("browse", $row['uploadtree_pk'], $LinkLast, $ShowBox, 
                         $ShowMicro, $ItemNumb++, $Header);
        $V .= "</td>";
        $V .= "<td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>";  // spaces to seperate licenses

        // show the entire license list as a single string with links to the files
        // in this container with that license.
        $V .= "<td>$licstring</td></tr>";
        $V .= "<tr><td colspan=3><hr></td></tr>";  // separate files
        if ($Count == $RowNum) break;
      }
      $RowNum++;
      if ($Count == $RowNum) break;
    }
    $V .= "</table>";
	if (!empty($VM)) { $V .= $VM . "\n"; }
	$V .= "<hr>\n";
	$Time = time() - $Time;
$text = _("Elapsed time");
$text1 = _("seconds");
	$V .= "<small>$text: $Time $text1</small>\n";
	break;
      case "Text":
	break;
      default:
	break;
      }
    if (!$this->OutputToStdout) { return($V); }
    print($V);
    return;
  } // Output()


};
$NewPlugin = new list_bucket_files;
$NewPlugin->Initialize();

?>
