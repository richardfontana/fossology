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
 * Test to check that the search menu exists and the page loads
 *
 * @version "$Id: SearchMenuTest.php 4312 2011-06-01 20:00:39Z rrando $"
 *
 * Created on Jul 23, 2008
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
global $USER;
global $PASSWORD;

class SearchMenuTest extends fossologyTestCase
{
  public $mybroswer;

  function testSearchMenu()
  {
    global $URL;
    print "starting SearchMenuTest\n";
    $browser = & new SimpleBrowser();
    $this->setBrowser($browser);
    $page = $this->mybrowser->get($URL);
    $this->assertTrue($page);
    $this->myassertText($page, '/Welcome to FOSSology/');
    $page = $this->mybrowser->click('Search');
    $this->myassertText($page, '/Search for File/');
    $this->myassertText($page, '/Enter the filename to find/');
  }
}
?>
