<?php
/***********************************************************
 Copyright (C) 2008 Hewlett-Packard Development Company, L.P.

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
 * Driver to generate a report of this months test runs, well sorta!
 * really is just my first try at using smarty. needs a lot of work.
 *
 * @param
 *
 * @return
 *
 * @version "$Id: report.php 2227 2009-06-05 05:50:20Z rrando $"
 *
 * Created on Dec 12, 2008
 */

 require_once('reportClass.php');

 $report = new TestReport();
 $report->readResultsFiles();
 exit(0);
?>
