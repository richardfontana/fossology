<?php
/*
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
 */

/**
* \brief Include file that describes the user functional tests to run
*
* @version "$Id: userTests.php 4243 2011-05-21 01:07:20Z rrando $"
*
* Created on May 19, 2011 by Mark Donohoe
*/

// List of tests to run, Add your test at the end.
$utests = array(
  'addUserTest.php',
  'dupUserTest.php',
  'noEmailUserTest.php',
  'userEditAnyTest.php',
  );

$suiteName = 'Core Functional Tests';
// Test path is relative to ....fossology/tests/
$userTestPath = '../Users';

$userTests = array(
  'suiteName' => $suiteName,
  'testPath'  => $userTestPath,
  'tests' => $utests,
);
?>