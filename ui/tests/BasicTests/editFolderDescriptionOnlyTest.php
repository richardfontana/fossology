<?php
/***********************************************************
 Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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

/**
 * edit only the description test
 *
 * @param
 *
 * @return
 *
 * @version "$Id: editFolderDescriptionOnlyTest.php 4309 2011-06-01 18:30:42Z rrando $"
 *
 * Created on Aug 1, 2008
 */

$where = dirname(__FILE__);
if(preg_match('!/home/jenkins.*?tests.*!', $where, $matches))
{
  //echo "running from jenkins....fossology/tests\n";
  require_once('../../tests/fossologyTestCase.php');
  require_once ('../../tests/TestEnvironment.php');
}
else
{
  //echo "using requires for running outside of jenkins\n";
  require_once('../../../tests/fossologyTestCase.php');
  require_once ('../../../tests/TestEnvironment.php');
}

global $URL;

class EditFolderDescriptionOnlyTest extends fossologyTestCase
{
  public $folder_name;
  public $mybrowser;

  function setUp()
  {
    global $URL;

    $this->Login();
    /* create a folder, which is edited below */
    $page = $this->mybrowser->get("$URL?mod=folder_create");
    $this->assertTrue($this->myassertText($page, '/Create a new Fossology folder/'));
    /* select the folder to create this folder under */
    $FolderId = $this->getFolderId('Basic-Testing', $page, 'parentid');
    $this->assertTrue($this->mybrowser->setField('parentid', $FolderId));
    $pid = getmypid();
    $this->folder_name = "EditDescription-$pid";
    $this->assertTrue($this->mybrowser->setField('newname', $this->folder_name));
    $desc = 'Folder created by EditFolderDesctriptionOnlyTest as subfolder of Testing';
    $this->assertTrue($this->mybrowser->setField('description', "$desc"));
    $page = $this->mybrowser->clickSubmit('Create!');
    $this->assertTrue($page);
    $this->assertTrue($this->myassertText($page, "/Folder $this->folder_name Created/"),
                      "FAIL! Folder $this->folder_name Created not found\n");
  }

  function testEditFolderDescriptionOnly()
  {
    global $URL;

    print "starting EditFolderDescriptoinOnlytest\n";
    $loggedIn = $this->mybrowser->get($URL);
    $this->assertTrue($this->myassertText($loggedIn, '/Organize/'),
                      "FAIL! Could not find Organize menu\n");
    $this->assertTrue($this->myassertText($loggedIn, '/Folders /'));
    $this->assertTrue($this->myassertText($loggedIn, '/Edit Properties/'));
    /* ok, this proves the text is on the page, let's see if we can
     * go to the page and delete a folder
     */
    $page = $this->mybrowser->get("$URL?mod=folder_properties");
    $this->assertTrue($this->myassertText($page, '/Edit Folder Properties/'));
    $pid = getmypid();
    $desc = "Folder description changed by EditFolderDescriptionOnlyTest-$pid as subfolder of Testing";
    $this->assertTrue($this->mybrowser->setField('newdesc', "$desc"),
                      "FAIL! Could not set description 'newdesc'\n");
    $page = $this->mybrowser->clickSubmit('Edit!');
    $this->assertTrue($page);
    $this->assertTrue($this->myassertText($page, "/Folder Properties changed/"),
                      "FAIL! Folder Properties changed not found\n");
    /* check the browse page */
    $page = $this->mybrowser->get("$URL?mod=browse");
    $this->assertTrue($this->myassertText($page, "/$desc/"),
                       "FAIL! Folder $desc not found\n");
    //print "************ page after Folder Delete! *************\n$page\n";
  }
  function tearDown()
  {
    global $URL;
    $page = $this->mybrowser->get("$URL?mod=admin_folder_delete");
    $this->assertTrue($this->myassertText($page, '/Delete Folder/'));
    $FolderId = $this->getFolderId($this->folder_name, $page, 'folder');
    $this->assertTrue($this->mybrowser->setField('folder', $FolderId));
    $page = $this->mybrowser->clickSubmit('Delete!');
    $this->assertTrue($page);
    $this->assertTrue($this->myassertText($page, "/Deletion of folder $this->folder_name/"),
                      "EditFolderDescriptionOnlyTest tearDown FAILED! Deletion of $this->folder_name not found\n");
  }
}

?>
