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
 * uploadWEmail
 *
 * Upload files as a user with email notification
 *
 * Note: you must have at least local email delivery working on the system
 * that this test is run on.
 *
 * @version "$Id: uploadWEmailTest.php 4311 2011-06-01 18:48:01Z rrando $"
 *
 * Created on April 3, 2009
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

class uploadWEMailTest extends fossologyTestCase {

  public $mybrowser;

  public function setUp() {
    global $URL;

    $this->Login();
    $this->CreateFolder(1, 'Enote', 'Folder for Email notification uploads');
  }

  public function testUploadWEmail() {

    global $URL;

    print "Starting UploadWEmail test...\n";
    /* login fosstester*/
    //$this->Login('fosstester','fosstester');
    $page = $this->mybrowser->get($URL);

    $File = '/home/fosstester/licenses/Apache-v1.1';
    $Filedescription = "The Apache License Version 1.1 from the Apache site";

    $Url = 'http://www.apache.org/licenses/LICENSE-2.0';
    $Urldescription = "The Apache License Version 2.0 from www.apache.org/licenses/LICENSE-2.0";

    $Srv = '/home/fosstester/archives/foss23D1F1L.tar.bz2';
    $Srvdescription = "fossology test archive";

    $this->uploadFile('Enote', $File, $Filedescription, null, '1');
    $this->uploadUrl('Enote', $Url, $Urldescription, null, '2');
    $this->uploadServer('Enote', $Srv, $Srvdescription, null, 'all');
    // need to get the upload id's of the files just uploaded.
    sleep(60);   // wait for 2 min for jobs to start then check they got started
    // use fossjobs to get the upload id
    $jobs = $this->parseFossjobs();

    /* verify, use the basename as the upload name is stored as the basename of
     * the upload path
     */
    print "Verifying jobs exist\n";
    $srvFile = basename($Srv);
    if(array_key_exists($srvFile,$jobs)) {
      $this->pass();
    }
    else {
      $this->fail("upload $Srv not found\n");
    }
    $urlFile = basename($Url);
    if(array_key_exists($urlFile,$jobs)) {
      $this->pass();
    }
    else {
      $this->fail("upload $Url not found\n");
    }
    /* upload from file only stores the filename */
    $FileName = basename($File);
    if(array_key_exists($FileName,$jobs)) {
      $this->pass();
    }
    else {
      $this->fail("upload $FileName not found\n");
    }
    /*
     * uploads exist, but still, need to check email when they finish....
     */
    print "waiting for jobs to finish\n";
    $this->wait4jobs();
    print "verifying correct email was received\n";
    $results = $this->checkEmailNotification(3);
    if(is_array($results)) {
      $this->fail("checkEmailNotification failed,\n" .
      "There were either errors in the results or" .
      " email may not be configured on the system.\n" .
      " Results returned from the check are:\n");
      print_r($results) . "\n";
    }
  }
};
?>