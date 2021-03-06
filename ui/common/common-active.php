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

/************************************************************
 These are common functions for performing active HTTP requests.
 (Used in place of AJAX or ActiveX.)
 ************************************************************/

/*************************************************
 PopupAlert(): Generate a popup alert window.
 *************************************************/
function PopupAlert	($Message) {
  if (!empty($_SESSION['NoPopup']) && ($_SESSION['NoPopup'] == 1))
    {
    $HTML = "<H3>" . htmlentities($Message,ENT_QUOTES) . "</H3>\n";
    }
  else
    {
    $HTML  = "<script language='javascript'>\n";
    $HTML .= "alert('" . htmlentities($Message,ENT_QUOTES) . "');\n";
    $HTML .= "</script>";
    }
  return($HTML);
} // PopupAlert()

/**
 * displayMessage
 *
 * Display a message.  This is used to convey the results of button push like
 * upload, submit, analyze, create, etc.
 *
 * @param string $Message the message to display
 * @param string $keep a safe text string NOT run through htmlentities
 * @return string $HTML the html to display (with embeded javascript)
 */
function displayMessage($Message,$keep=NULL) {

  $HTML = NULL;
  $HTML .= "\n<div id='dmessage'>";
  $text = _("Close");
  $HTML .= "<button name='eraseme' value='close' onclick='rmMsg()'> $text</button>\n";
  $HTML .= htmlentities($Message,ENT_QUOTES) . "\n";
  $HTML .= $keep . "\n</p>";
  $HTML .= "  <hr>\n";
  $HTML .= "</div>\n";
  $HTML .= "<script type='text/javascript'>\n" .
           "function rmMsg(){\n" .
           "  var div = document.getElementById('dmessage');\n" .
           "  var parent = div.parentNode;\n" .
           "  parent.removeChild(div);\n" .
           "}\n" .
           "</script>\n";
    return($HTML);
}

/*************************************************
 ActiveHTTPscript(): Given a function name, create the
 JavaScript needed for doing the request.
 The JavaScript takes a URL and returns the data.
 The JavaScript is Asynchronous (no wait while the request goes on).
 The $RequestName is the JavaScript variable name to use.
 The javascript function is named "${RequestName}_Get"
 The javascript function "${RequestName}_Reply" must be defined for
 handling the reply.  (You will need to make this Javascript function.)
 The javascript variable "${RequestName}.status" contains the
 reply's HTTP return code (200 means "OK") and "${RequestName}.readyState"
 is the handle's state (4 = "loaded").
 References:
   http://www.w3schools.com/xml/xml_http.asp
 *************************************************/
function ActiveHTTPscript	($RequestName,$IncludeScriptTags=1)
{
  $HTML="";

  if ($IncludeScriptTags)
    {
    $HTML="<script language='javascript'>\n<!--\n";
    }

  $HTML .= "var $RequestName=null;\n";
  /* Check for browser support. */
  $HTML .= "function ${RequestName}_Get(Url)\n";
  $HTML .= "{\n";
  $HTML .= "if (window.XMLHttpRequest)\n";
  $HTML .= "  {\n";
  $HTML .= "  $RequestName=new XMLHttpRequest();\n";
  $HTML .= "  }\n";
  /* Check for IE5 and IE6 */
  $HTML .= "else if (window.ActiveXObject)\n";
  $HTML .= "  {\n";
  $HTML .= "  $RequestName=new ActiveXObject('Microsoft.XMLHTTP');\n";
  $HTML .= "  }\n";

  $HTML .= "if ($RequestName!=null)\n";
  $HTML .= "  {\n";
  $HTML .= "  $RequestName.onreadystatechange=${RequestName}_Reply;\n";
  /***
   'true' means asynchronous request.
   Rather than waiting for the reply, the reply is
   managed by the onreadystatechange event handler.
   ***/
  $HTML .= "  $RequestName.open('GET',Url,true);\n";
  $HTML .= "  $RequestName.send(null);\n";
  $HTML .= "  }\n";
  $HTML .= "else\n";
  $HTML .= "  {\n";
  $HTML .= "  alert('Your browser does not support XMLHTTP.');\n";
  $HTML .= "  return;\n";
  $HTML .= "  }\n";
  $HTML .= "}\n";

  if ($IncludeScriptTags)
    {
    $HTML .= "\n// -->\n</script>\n";
    }

  return($HTML);
} // ActiveHTTPscript()

?>
