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
 Developer notes:

 The confidence is a number used to identify how good the template is.
 Values are:
   NULL (not present) = use the template's name.
   0 = high confidence. Use the template's name.  (Ignore canonical.)
   1 = medium confidence. Most of the template matched.  Call it 'style'.
   2 = low confidence. Part of the template matched.  Call it 'partial'.
   3 = no confidence. The canonical identifier must be present and
       will be used.

 The canonical is the canonical name (licterm_pk from licterm).
 When the confidence is 3, the canonical name will be used.
 ************************************************************/

/************************************************************
 LicenseNormalizeName(): Given a name, remove all of the
 extraneous text.
 ************************************************************/
function LicenseNormalizeName	($LicName,$Confidence,$CanonicalName)
{
  /* Find the right name to use */
  $Name = $CanonicalName;
  if ($Confidence < 3)
    {
    if (!empty($CanonicalName)) { $Name = $CanonicalName; }
    else
      {
      $Name = $LicName;
      $Name = preg_replace("@.*/@","",$Name);
      $Name = preg_replace("/ part.*/","",$Name);
      $Name = preg_replace("/ short.*/","",$Name);
      $Name = preg_replace("/ variant.*/","",$Name);
      $Name = preg_replace("/ reference.*/","",$Name);
      $Name = preg_replace("/ \(.*/","",$Name);
      }
    if ($Confidence == 1) { $Name = "'$Name'-style"; }
    else if ($Confidence == 2) { $Name = "'$Name'-partial"; }
    }
  return($Name);
} // LicenseNormalizeName()

/************************************************************
 LicenseGetName(): Given a meta id (agent_lic_meta_pk), return
 the license name (mapped to canonical name).
 ************************************************************/
$LicenceGetName_Prepared=0;
function LicenseGetName(&$MetaId, $IncludePhrase=0)
{
  global $DB;
  global $LicenceGetName_Prepared;
  if (!$LicenceGetName_Prepared)
    {
    $DB->Prepare("LicenseGetName_Raw",'SELECT licterm.licterm_name,lic_name,phrase_text,lic_id
	FROM agent_lic_raw
	INNER JOIN agent_lic_meta ON agent_lic_meta_pk = $1
	AND lic_fk = lic_pk
	INNER JOIN licterm_maplic ON licterm_maplic.lic_fk = lic_id
        INNER JOIN licterm ON licterm_fk = licterm_pk
	;');

    $DB->Prepare("LicenseGetName_CanonicalName",'SELECT licterm_name_confidence,licterm_name
	FROM licterm
	INNER JOIN licterm_name ON agent_lic_meta_fk = $1
	AND licterm_fk = licterm_pk
	UNION
	SELECT licterm_name_confidence,' . "''" . '
	FROM licterm_name
	WHERE agent_lic_meta_fk = $1 AND licterm_fk IS NULL
	;');
    $LicenceGetName_Prepared=1;
    }

  $CanonicalList =  $DB->Execute("LicenseGetName_CanonicalName",array($MetaId));
  $RawList =  $DB->Execute("LicenseGetName_Raw",array($MetaId));

  $LastConfidence = $CanonicalList[0]['licterm_name_confidence'];
  $Phrase = $RawList[0]['phrase_text'];
  $FullName = '';
  foreach($CanonicalList as $C)
    {
    if (empty($C)) { continue; }
    /* Get the components */
    $Confidence = $C['licterm_name_confidence'];
    $LicTerm = $C['licterm_name'];

    /* Normalize the name */
    $Name = $RawList[0]['licterm_name'];

    if (!empty($Phrase) && ($Confidence < 3))
      {
      $Name = "Phrase";
      if ($IncludePhrase) { $Name .= ": $Phrase"; }
      }

    /* Store it */
    if (!empty($FullName))
	{
	if (empty($LastConfidence) || ($LastConfidence < 3) && ($Confidence >= 3) ) { $FullName .= " + "; }
	else { $FullName .= ", "; }
	}
    $FullName .= $Name;
    $LastConfidence = $Confidence;
    }

  if (empty($FullName))
    {
    $Name = $RawList[0]['licterm_name'];
    if (!empty($Phrase))
      {
      $Name = "Phrase";
      if ($IncludePhrase) { $Name .= ": $Phrase"; }
      }
    $FullName .= $Name;
    }

  return($FullName);
} // LicenseGetName()

/************************************************************
 LicenseGet(): Return licenses for a pfile.
 May return empty array if there is no license.
 ************************************************************/
$LicenseGet_Prepared=0;
function LicenseGet(&$PfilePk, &$Lics, $GetField=0)
{
  global $LicenseGet_Prepared;
  global $DB;
  if (empty($DB)) { return; }
  if (!$LicenseGet_Prepared)
    {
    $DB->Prepare("LicenseGet_Raw1",'SELECT licterm.licterm_name,lic_id,phrase_text,agent_lic_meta_pk
	FROM agent_lic_meta
	INNER JOIN agent_lic_raw ON lic_fk = lic_pk AND pfile_fk = $1
	INNER JOIN licterm_maplic ON licterm_maplic.lic_fk = lic_id
        INNER JOIN licterm ON licterm_fk = licterm_pk
	;');
    $DB->Prepare("LicenseGet_Raw2",'SELECT lic_name as licterm_name,lic_id,phrase_text,agent_lic_meta_pk
	FROM agent_lic_meta
	INNER JOIN agent_lic_raw ON lic_fk = lic_pk AND pfile_fk = $1
	;');
    $DB->Prepare("LicenseGet_Canonical",'SELECT licterm.licterm_name,licterm_name_confidence,lic_name,phrase_text,lic_id,agent_lic_meta_pk
	FROM agent_lic_meta
	INNER JOIN agent_lic_raw ON lic_fk = lic_pk AND pfile_fk = $1
	INNER JOIN licterm_name ON agent_lic_meta_fk = agent_lic_meta_pk
	INNER JOIN licterm ON licterm_fk = licterm_pk
	UNION
	SELECT '."''".',licterm_name_confidence,lic_name,phrase_text,lic_id,agent_lic_meta_pk
	FROM agent_lic_meta
	INNER JOIN agent_lic_raw ON lic_fk = lic_pk AND pfile_fk = $1
	INNER JOIN licterm_name ON agent_lic_meta_fk = agent_lic_meta_pk
	AND licterm_fk IS NULL
	;');
    $LicenseGet_Prepared=1;
    }
  if (empty($Lics[' Total '])) { $Lics[' Total ']=0; }

  /* Prepare map */
  $Results = $DB->Action("SELECT * FROM licterm_maplic
	INNER JOIN licterm ON licterm_fk = licterm_pk
	;");
  $MapLic = array();
  for($i=0; !empty($Results[$i]['licterm_maplic_pk']); $i++)
    {
    $MapLic[$Results[$i]['lic_fk']] = $Results[$i]['licterm_name'];
    }

  $CanonicalList =  $DB->Execute("LicenseGet_Canonical",array($PfilePk));
  $RawList = $DB->Execute("LicenseGet_Raw1",array($PfilePk));
  if (empty($RawList)) { $RawList = $DB->Execute("LicenseGet_Raw2",array($PfilePk)); }
  $Results=array();
  $PfileList=array(); /* used to omit duplicates */
  foreach($CanonicalList as $R)
    {
    $PfileList[$R['agent_lic_meta_pk']] = 1;
    $Results[] = $R;
    }
  foreach($RawList as $R)
    {
    $R['licterm_name'] = LicenseNormalizeName($R['licterm_name'],0,"");
    if (empty($PfileList[$R['agent_lic_meta_pk']]))
      {
      $PfileList[$R['agent_lic_meta_pk']] = 1;
      $Results[] = $R;
      }
    }

  if (!empty($Results) && (count($Results) > 0))
    {
    /* Got canonical name */
    foreach($Results as $Name)
      {
      $LicName="";
      if ($Name['licterm_name_confidence'] == 3) { $LicName = $Name['licterm_name']; }
      if (empty($LicName)) { $LicName = LicenseNormalizeName($Name['lic_name'],$Name['licterm_name_confidence'],$MapLic[$Name['lic_id']]); }
      if (empty($LicName)) { $LicName = LicenseNormalizeName($Name['lic_name'],$Name['licterm_name_confidence'],$Name['licterm_name']); }

      if (!empty($LicName))
        {
	if (!empty($GetField)) { $Lics[]=$Name; }
	else
	  {
	  if (empty($Lics[$LicName])) { $Lics[$LicName]=1; }
	  else { $Lics[$LicName]++; }
	  $Lics[' Total ']++;
	  }
	}
      }
    }
  return;
} // LicenseGet()

/************************************************************
 LicenseCount(): Return license count for a uploadtree_pk.
 If uploadtree_pk is a file, the # of licenses in that file 
    is returned.
 If uploadtree_pk is a container, the # of licenses contained
    in that container (and children) is returned.
 ************************************************************/
function LicenseCount($UploadtreePk)
{
  global $Plugins;
  global $DB;
  global $LicenseCount_Prepared;

  if (empty($DB)) { return 0; }
  if (empty($UploadtreePk)) { return 0; }

  $SQL = "SELECT lft,rgt,upload_fk FROM uploadtree WHERE uploadtree_pk = $UploadtreePk;";
  $Results = $DB->Action($SQL);
  $Lft = $Results[0]['lft'];
  $Rgt = $Results[0]['rgt'];
  $UploadFk = $Results[0]['upload_fk'];

  if (!$LicenseCount_Prepared)
    {
    $DB->Prepare("LicenseCount",'SELECT sum(pfile_liccount) AS count
	FROM uploadtree AS ut1
	INNER JOIN pfile ON ut1.pfile_fk = pfile_pk
	  AND ut1.upload_fk = $1
	  AND ut1.lft BETWEEN $2 AND $3
	WHERE pfile_liccount IS NOT NULL
	;');
    $LicenseCount_Prepared=1;
    }
  $Results = $DB->Execute("LicenseCount",array($UploadFk,$Lft,$Rgt));
  return($Results[0]['count']);
} // LicenseCount()

/************************************************************
 LicenseGetForFile(): Given an uploadtree_pk, return each
 license record and canonical name.
 ************************************************************/
function LicenseGetForFile(&$UploadtreePk)
{
  global $DB;

  /* Get every real item */
  $SQL = "SELECT
	CASE
	  WHEN lic_tokens IS NULL THEN licterm_name
	  WHEN tok_match = lic_tokens THEN licterm_name
	  ELSE '''' || licterm_name || '''-style'
	END AS licterm_name,
	agent_lic_meta.*,
	lic_tokens
	FROM uploadtree AS UT1,
	  licterm_name, licterm, agent_lic_meta, agent_lic_raw
	WHERE
	  uploadtree_pk = $UploadtreePk
	  AND licterm_name.pfile_fk = UT1.pfile_fk
	  AND licterm_pk=licterm_name.licterm_fk
	  AND agent_lic_meta_pk = licterm_name.agent_lic_meta_fk
	  AND agent_lic_meta.lic_fk = agent_lic_raw.lic_pk
	  AND (lic_tokens IS NULL OR
	    CAST(tok_match AS numeric)/CAST(lic_tokens AS numeric) > 0.5)
	  AND licterm_name_confidence != 3
	ORDER BY agent_lic_meta_pk,licterm_name
	;";
  $Results = $DB->Action($SQL);

  /* Get every item found by term */
  $SQL = "SELECT
	  licterm_name,
	  agent_lic_meta.*,
	  lic_tokens
	  FROM uploadtree AS UT1,
	  licterm_name, licterm, agent_lic_meta, agent_lic_raw
	WHERE
	  uploadtree_pk = $UploadtreePk
	  AND licterm_name.pfile_fk = UT1.pfile_fk
	  AND licterm_pk=licterm_name.licterm_fk
	  AND agent_lic_meta_pk = licterm_name.agent_lic_meta_fk
	  AND agent_lic_meta.lic_fk = agent_lic_raw.lic_pk
	  AND (lic_tokens IS NULL OR
	    CAST(tok_match AS numeric)/CAST(lic_tokens AS numeric) > 0.5)
	  AND licterm_name_confidence = 3
	ORDER BY agent_lic_meta_pk,licterm_name
	;";
  $R2 = $DB->Action($SQL);

  /* Combine terms by name */
  for($i=0; !empty($R2[$i]['licterm_name']); $i++)
    {
    if ($R2[$i]['agent_lic_meta_pk'] == $R2[$i+1]['agent_lic_meta_pk'])
      {
      $R2[$i+1]['licterm_name'] = $R2[$i]['licterm_name'] . ', ' . $R2[$i+1]['licterm_name'];
      }
    else
      {
      $Results[] = $R2[$i];
      }
    }
  return($Results);
} // LicenseGetForFile()

/************************************************************
 LicenseSearch(): Given a license name and uploadtree_pk,
 return each file containing the license.
 ************************************************************/
function LicenseSearch	(&$UploadtreePk, $WantLic=NULL, $Offset=-1, $Max=0)
{
  global $DB;

  if (empty($DB)) { return; }
  if (empty($UploadtreePk)) { return NULL; }

  /* Get the range */
  $SQL = "SELECT lft,rgt,upload_fk FROM uploadtree WHERE uploadtree_pk = $UploadtreePk;";
  $Results = $DB->Action($SQL);
  $Lft = $Results[0]['lft'];
  $Rgt = $Results[0]['rgt'];
  $UploadFk = $Results[0]['upload_fk'];

  /* Determine the license name */
  $LicName = preg_replace("/'(.*)'-style/",'${1}',$WantLic);
  $LicName = str_replace("'","''",$LicName);
  if ($LicName == $WantLic)
    {
    /* Absolute name match */
    $SQL = "SELECT
	CASE
	  WHEN lic_tokens IS NULL THEN licterm_name
	  WHEN tok_match = lic_tokens THEN licterm_name
	  ELSE '''' || licterm_name || '''-style'
	END AS licterm_name,
	agent_lic_meta.*,
	lic_tokens,
	UT1.pfile_fk AS pfile,
	UT1.*
	FROM uploadtree AS UT1,
	  licterm_name, licterm, agent_lic_meta, agent_lic_raw
	WHERE
	  UT1.lft BETWEEN $Lft AND $Rgt
	  AND licterm.licterm_name = '$LicName'
	  AND UT1.upload_fk=$UploadFk
	  AND licterm_name.pfile_fk=UT1.pfile_fk
	  AND licterm_pk=licterm_name.licterm_fk
	  AND agent_lic_meta_pk = licterm_name.agent_lic_meta_fk
	  AND agent_lic_meta.lic_fk = agent_lic_raw.lic_pk
	  AND (lic_tokens IS NULL OR
	    tok_match = lic_tokens)
	ORDER BY pfile,agent_lic_meta_pk,ufile_name";
    if ($Offset > 0) { $SQL .= " OFFSET $Offset"; }
    if ($Max > 0) { $SQL .= " LIMIT $Max"; }
    $SQL .= ";";
    }
  else
    {
    /* Match for style */
    $SQL = "SELECT
	UT1.*,phrase_text
	FROM uploadtree AS UT1,
	  licterm_name, licterm, agent_lic_meta, agent_lic_raw
	WHERE
	  UT1.lft BETWEEN $Lft and $Rgt
	  AND UT1.upload_fk=$UploadFk
	  AND licterm.licterm_name = '$LicName'
	  AND licterm_name.pfile_fk=UT1.pfile_fk
	  AND licterm_pk=licterm_name.licterm_fk
	  AND agent_lic_meta_pk = licterm_name.agent_lic_meta_fk
	  AND agent_lic_meta.lic_fk = agent_lic_raw.lic_pk
	  AND lic_tokens IS NOT NULL
	  AND tok_match != lic_tokens
	  AND CAST(tok_match AS numeric)/CAST(lic_tokens AS numeric) > 0.5
	  ORDER BY pfile_fk,ufile_name";
    if ($Offset > 0) { $SQL .= " OFFSET $Offset"; }
    if ($Max > 0) { $SQL .= " LIMIT $Max"; }
    $SQL .= ";";
    }
  $Results = $DB->Action($SQL);
  return($Results);
} // LicenseSearch()

/************************************************************
 LicenseGetAll(): Return licenses for a uploadtree_pk.
 Array returned looks like Array[license_name] = count.
 An array is always returned unless the db is not open 
 or no uploadtreepk is passed in.
 $Max: $Max # of returned records
 $Offset: offset into $Results of first returned rec
 Returns NULL if not processed.
 ************************************************************/
function LicenseGetAll	(&$UploadtreePk, &$Lics, $GetField=0, $WantLic=NULL)
{
  global $DB;

  if (empty($DB)) { return; }
  if (empty($UploadtreePk)) { return NULL; }

  /* Number of licenses */
  if (empty($Lics[' Total ']) && empty($GetField)) { $Lics[' Total ']=0; }

  if ($Offset > 0) 
    $OffsetPhrase = " OFFSET $Offset";
  else
    $OffsetPhrase = "";

  if ($Max > 0)
    $LimitPhrase = " LIMIT $Max";
  else
    $LimitPhrase = "";

  /* Get the range */
  $SQL = "SELECT lft,rgt,upload_fk FROM uploadtree WHERE uploadtree_pk = $UploadtreePk;";
  $Results = $DB->Action($SQL);
  $Lft = $Results[0]['lft'];
  $Rgt = $Results[0]['rgt'];
  $UploadFk = $Results[0]['upload_fk'];

  /*  Get every license for every file in this subtree */
  /** If % match > 50%, then count it.  (Skip things like 5% match.)
      Anything less than 100% is '-style' **/
  $SQL = "SELECT
	CASE
	  WHEN lic_tokens IS NULL THEN licterm_name
	  WHEN tok_match = lic_tokens THEN licterm_name
	  ELSE '''' || licterm_name || '''-style'
	END AS licterm_name
	FROM uploadtree AS UT1,
	  licterm_name, licterm, agent_lic_meta, agent_lic_raw
	WHERE
	  UT1.lft BETWEEN $Lft and $Rgt
	  AND UT1.upload_fk=$UploadFk
	  AND licterm_name.pfile_fk=UT1.pfile_fk
	  AND licterm_pk=licterm_name.licterm_fk
	  AND agent_lic_meta_pk = licterm_name.agent_lic_meta_fk
	  AND agent_lic_meta.lic_fk = agent_lic_raw.lic_pk
	  AND (lic_tokens IS NULL OR
	    CAST(tok_match AS numeric)/CAST(lic_tokens AS numeric) >= 0.5)
	ORDER BY licterm_name
	;";

  $Results = $DB->Action($SQL);
  $Lics[' Total '] = 0;
  $LastName='';
  foreach($Results AS $Name)
    {
    if (empty($Name)) { continue; }
    $Lics[' Total ']++;
    $Name = $Name['licterm_name'];
    if ($Name == $LastName) { $Lics[$Name]++; }
    else { $Lics[$Name]=1; $LastName = $Name; }
    }

  return; 
} // LicenseGetAll()

/************************************************************
 LicenseGetAllFiles(): Return licenses for a uploadtree_pk.
 This is only for search-file-by-licgroup.php
 License Groups only use the the license stored in agent_lic_meta
 Can return empty array if there is no license.
 $Max: $Max # of returned records
 $Offset: offset into $Results of first returned rec
 Returns NULL if not processed.
 ************************************************************/
function LicenseGetAllFiles(&$UploadtreePk, &$Lics, &$WantLic, $Max, $Offset)
{
  global $Plugins;
  global $DB;

  if (empty($DB)) { return; }
  if (empty($UploadtreePk)) { return NULL; }

  /* Get the range */
  $SQL = "SELECT lft,rgt,upload_fk FROM uploadtree WHERE uploadtree_pk = $UploadtreePk;";
  $Results = $DB->Action($SQL);
  $Lft = $Results[0]['lft'];
  $Rgt = $Results[0]['rgt'];
  $UploadFk = $Results[0]['upload_fk'];

  /*  Get every license for every file in this subtree */
    /* SQL to get all files with a specific license */
  $Results = $DB->Action("select UT1.*,lic_fk,lic_id,tok_pfile,tok_license,tok_match,phrase_text
    FROM uploadtree as UT1 
    INNER JOIN agent_lic_meta
	ON UT1.upload_fk=$UploadFk
	AND UT1.lft BETWEEN $Lft and $Rgt
	AND agent_lic_meta.pfile_fk = UT1.pfile_fk
    INNER JOIN agent_lic_raw ON agent_lic_meta.lic_fk=agent_lic_raw.lic_pk
	AND ( $WantLic )
    ORDER BY UT1.ufile_name");
  $Count = count($Results);

    if ($Max == -1) $Max = $Count;

    /* Got canonical name */
    $Found = 0;
    for ($i=0; ($Found < $Max+$Offset) && $Results[$i]; $i++)
    {
      //if (empty($Results[$i]['lic_fk'])) { continue; }
      if ($Found >= $Offset)
      {
         $Lics[]=$Results[$i]; 
      }
      $Found++;
    }

  return; 
} // LicenseGetAllFiles()

?>
