/**************************************************************
 dbapi: Set of generic functions for communicating with a database.

 Copyright (C) 2010 Hewlett-Packard Development Company, L.P.
  
 This library is free software; you can redistribute it and/or
 modify it under the terms of the GNU Lesser General Public
 License version 2.1 as published by the Free Software Foundation.

 This library is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 Lesser General Public License for more details.

 You should have received a copy of the GNU Lesser General Public License
 along with this library; if not, write to the Free Software Foundation, Inc.0
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 **************************************************************/

#include <stdlib.h>
#include <stdio.h>
#include <errno.h>
#include <unistd.h>
#include <string.h>
#include <ctype.h>
#include "libfossdb.h"

#ifdef SVN_REV
char LibraryBuildVersion[]="Library libfossdb Build version: " SVN_REV ".\n";
#endif

#ifndef FOSSDB_CONF
#define FOSSDB_CONF "/etc/fossology/Db.conf"
#endif

/****
 ** This is a DB-specific structure
 ** It holds connections and results.
 ** All manipulations should use the DB API to access it.
 ** If the DB ever changes, just change this structure (for state)
 ** and the DB functions.  You should not need to change all code that
 ** uses this library.
 ******/
typedef struct dbapi
{
  PGconn   *Conn;         /* DB specific connection */
  PGresult *Res;          /* result from query */
  int       RowsAffected;
} dbapi;



/*****************************************************
 DBclose(): Close a handle to the DB.
 *****************************************************/
void	DBclose	(void *VDB)
{
  dbapi *DB;
  DB = (dbapi *)VDB;

  if (!DB) return;
  if (DB->Res) PQclear(DB->Res);
  if (DB->Conn) PQfinish(DB->Conn);
  free(DB);
} /* DBclose() */

/*****************************************************
 DBopen(): Open a handle to the DB.
 Returns handle, or NULL on failure.
 *****************************************************/
void *	DBopen	()
{
  FILE *Fconf;
  dbapi *DB;
  char *Env;
  char Line[1024];
  char CMD[10240];
  int i,CMDlen;
  int C;
  int PosEqual; /* index of "=" in Line */
  int PosSemi;  /* index of ";" in Line */

  /* Normally, this tries to open the file FOSSDB_CONF.
     This is a compile-time string.
     However, for debugging you can override the config file
     with the environment variable "FOSSDBCONF".
   */

  /* Env FOSSDBCONF = debugging override for the config file */
  Env = getenv("FOSSDBCONF");

  /*
  printf("Debug: ENV is:%s\n",Env);
  printf("Debug: FOSSDB_CONF is:%s\n",FOSSDB_CONF);
  */
  if (Env)
    {
    Fconf = fopen(Env,"r");
    }
  else Fconf = fopen(FOSSDB_CONF,"r");
  if (!Fconf) return(NULL);

  /* read the configuration file */
  memset(CMD,'\0',sizeof(CMD));
  CMDlen = 0;
  while(!feof(Fconf))
    {
    C='@';
    PosEqual=0;
    PosSemi=0;
    memset(Line,'\0',sizeof(Line));
    /* read a line of data */
    /* All lines are in the format: "field=value;" */
    /* Lines beginning with "#" are ignored. */
    for(i=0; (i<sizeof(Line)) && (C != '\n') && (C > 0); i++)
      {
      C = fgetc(Fconf);
      if ((C > 0) && (C != '\n')) Line[i]=C;
      if ((C=='=') && !PosEqual) PosEqual=i;
      else if ((C==';') && !PosSemi) PosSemi=i;
      }
    /* check for a valid line */
    if (PosSemi < PosEqual) PosEqual=0;
    if ((Line[0] != '#') && PosEqual && PosSemi)
      {
      /* looks good to me! */
      if (CMD[0] != '\0')
        {
        CMD[CMDlen++] = ' ';
        if (CMDlen >= sizeof(CMD)) { fclose(Fconf); return(NULL); }
	}
      Line[PosSemi] = '\0';
      for(i=0; i < PosEqual; i++)
        {
	if (!isspace(Line[i])) CMD[CMDlen++] = Line[i];
	if (CMDlen >= sizeof(CMD)) { fclose(Fconf); return(NULL); }
	}
      CMD[CMDlen++] = '=';
      if (CMDlen >= sizeof(CMD)) { fclose(Fconf); return(NULL); }
      for(i=PosEqual+1; Line[i] != '\0'; i++)
        {
	if (!isspace(Line[i])) CMD[CMDlen++] = Line[i];
	if (CMDlen >= sizeof(CMD)) { fclose(Fconf); return(NULL); }
	}
      }
    }

  /* done reading file */
  fclose(Fconf);
  if (CMD[0] == '\0') return(NULL);

  /* Perform the connection */
  /* everything worked -- save the connection */
  DB = (dbapi *)malloc(sizeof(dbapi));
  if (!DB) return(NULL);
  DB->Conn = PQconnectdb(CMD);
  if (PQstatus(DB->Conn) != CONNECTION_OK)
    {
    fprintf(stderr,"ERROR: Unable to connect to the database\n");
    fprintf(stderr,"  Connection string: '%s'\n",CMD);
    fprintf(stderr,"  Connection status: '%d'\n",PQstatus(DB->Conn));
    free(DB);
    return(NULL);
    }
  DB->Res = NULL;

  /* Got an open handle.  Set a delay of 2 minutes per command. */
  /* In milliseconds: 2*60*1000 = 120000 */
  /* Two minutes is too short for sqlagent, changing to 10 min 6/6/2008 bobg */
 // Use statement timeout in postgresql.conf 6/5/2009
 // DBaccess(DB,"SET statement_timeout = 600000;");
  return((void *)DB);
} /* DBopen() */

/*****************************************************
 DBmove(): Move a DB structure.
 This is used to save results.
 It cannot be used for making new DB queries.
 *****************************************************/
void *	DBmove	(void *VDB)
{
  dbapi *DBnew, *DBold;
  DBold = (dbapi *)VDB;

  DBnew = (dbapi *)malloc(sizeof(dbapi));
  if (!DBnew) return(NULL);

  DBnew->Conn = NULL;
  DBnew->RowsAffected = DBold->RowsAffected;
  DBnew->Res = DBold->Res;
  DBold->Res = NULL;
  return((void *)DBnew);
} /* DBmove() */

/*****************************************************
 DBaccess(): Write to the DB and read results.
 Returns:
   1 = ok, got results (e.g., SELECT)
   0 = ok, no results (e.g., INSERT)
   -1 = constraint error
   -2 = other error
   -3 = timeout
   2 = ok, ignore duplicate constraint failure (23505)
 NOTE: For a huge DB request, this could take a while
 and could consume all memory.
 Callers should take care to not call unbounded SQL selects.
 (Say "select limit 1000" or something.)
 *****************************************************/
int	DBaccess	(void *VDB, char *SQL)
{
  dbapi *DB;
  int Status;
  DB = (dbapi *)VDB;

  if (!DB || !SQL) return(-1);
  if (DB->Res)
    {
    /* free old result */
    PQclear(DB->Res);
    DB->Res=NULL;
    }

  DB->Res = PQexec(DB->Conn,SQL);
  if (DB->Res == NULL)
	{
	printf("ERROR: DBaccess(%d): %s\n",
		PGRES_FATAL_ERROR,PQresultErrorMessage(DB->Res));
	printf("ERROR: DBaccess error: '%s'\n",SQL);
	return(-2);
	}
  Status = PQresultStatus(DB->Res);
  DB->RowsAffected = atoi(PQcmdTuples(DB->Res));
  switch(Status)
      {
      /* case: Ok, no reply data */
      case PGRES_COMMAND_OK:  /* query had no results */
      case PGRES_EMPTY_QUERY: /* query was empty */
      case PGRES_COPY_IN:
      case PGRES_COPY_OUT:
	PQclear(DB->Res);
	DB->Res=NULL;
	return(0);

      /* case: Ok, reply data */
      case PGRES_TUPLES_OK: /* got results (could be "0 rows") */
	return(1);

      /* case: Not Ok. */
      case PGRES_NONFATAL_ERROR:
      case PGRES_FATAL_ERROR:
	/* NOTE: Postgres returns a FATAL for unique violations.
	   This can be checked by testing the state against the
	   code "23xxx". */
	/* SOURCE:
	   http://www.postgresql.org/docs/7.4/static/errcodes-appendix.html
	   Class 23: Constraint violations
		23000	INTEGRITY CONSTRAINT VIOLATION
		23001	RESTRICT VIOLATION
		23502	NOT NULL VIOLATION
		23503	FOREIGN KEY VIOLATION
		23505	UNIQUE VIOLATION
		23514	CHECK VIOLATION
	   Class 25: Transaction errors
		25P02	IN FAILED SQL TRANSACTION
	   Class 57: Operator Intervention
		57014   QUERY CANCELED  (comes from a timeout)
	 */
	if (!strncmp("23505",PQresultErrorField(DB->Res,PG_DIAG_SQLSTATE),5))
	  {
	    return(2);	
	  } 
	if (!strncmp("23",PQresultErrorField(DB->Res,PG_DIAG_SQLSTATE),2) ||
	    !strncmp("25",PQresultErrorField(DB->Res,PG_DIAG_SQLSTATE),2))
	  {
	  PQclear(DB->Res);
	  DB->Res=NULL;
	  return(-1); /* constraint error */
	  }
	if (!strncmp("57014",PQresultErrorField(DB->Res,PG_DIAG_SQLSTATE),5))
	  {
	  printf("ERROR: DBaccess(%d): %s\n",
		PQresultStatus(DB->Res),PQresultErrorMessage(DB->Res));
	  printf("ERROR: DBaccess timeout: '%s'\n",SQL);
	  PQclear(DB->Res);
	  DB->Res=NULL;
	  return(-3); /* this is a timeout */
	  }
#if 0
	fprintf(stdout,"DBaccess error: %d '%s' '%s' :: '%s'\n",PQresultStatus(DB->Res),PQresStatus(PQresultStatus(DB->Res)),PQresultErrorField(DB->Res,PG_DIAG_SQLSTATE),SQL);
#endif
	/* Display the error */
	printf("ERROR: DBaccess(%d): %s\n",
		PQresultStatus(DB->Res),PQresultErrorMessage(DB->Res));
	PQclear(DB->Res);
	DB->Res=NULL;
	return(-1);
      default: /* error */
	/* Display the error */
	printf("ERROR: DBaccess(%d): %s\n",
		PQresultStatus(DB->Res),PQresultErrorMessage(DB->Res));
	fflush(stdout);
	PQclear(DB->Res);
	DB->Res=NULL;
	return(-2);
      }

  /* should never get here */
  return(0);
} /* DBaccess() */


/*****************************************************
 DBaccess2(): Write to the DB and read results.
 Stripped down version of DBaccess without all the
 error assumptions and DB->Res resets.
 Use this if you want to screw the libfossdb dbms
 abstraction and get to PGresult.
 Returns directly from PQexec
 *****************************************************/
PGresult *DBaccess2(void *VDB, char *SQL)
{
  dbapi *DB;

  DB = (dbapi *)VDB;

  if (!DB) return(0);
  if (!SQL) return(0);

  /* execute command and return */
  DB->Res = PQexec(DB->Conn,SQL);
  return(DB->Res);
}

/* return the conn */
PGconn *DBgetconn(void *VDB)
{
  dbapi *DB;
  DB = (dbapi *)VDB;
  return (DB->Conn);
}


/*********************************************************************/
/*********************************************************************/
/** The following functions should be called after DBaccess() == 1. **/
/*********************************************************************/
/*********************************************************************/

/*****************************************************
 DBerrmsg(): Return the last error message
 or empty string if db not open or no result available
 *****************************************************/
char *DBerrmsg	(void *VDB)
{
  dbapi *DB;
  DB = (dbapi *)VDB;
  if (!DB || !DB->Res) return("");
  return(PQresultErrorMessage(DB->Res));
} /* DBerrmsg() */

/*****************************************************
 DBstatus(): Return the last result status
 or empty string if db not open or no result available
 *****************************************************/
char *DBstatus	(void *VDB)
{
  dbapi *DB;
  DB = (dbapi *)VDB;
  if (!DB || !DB->Res) return("");
  return (PQresStatus(PQresultStatus(DB->Res)));
} /* DBstatus() */

/*****************************************************
 DBdatasize(): Return the amount of data.
 *****************************************************/
int	DBdatasize	(void *VDB)
{
  dbapi *DB;
  DB = (dbapi *)VDB;
  if (!DB || !DB->Res) return(-1);
  return(PQntuples(DB->Res));
} /* DBdatasize() */

/*****************************************************
 DBcolsize(): Return the number of columns in the returned data.
 *****************************************************/
int	DBcolsize	(void *VDB)
{
  dbapi *DB;
  DB = (dbapi *)VDB;
  if (!DB || !DB->Res) return(-1);
  return(PQnfields(DB->Res));
} /* DBcolsize() */

/*****************************************************
 DBrowsaffected(): Return number of rows affected by
 the last operation.  (Good for INSERT, DELETE, or UPDATE.)
 Returns -1 on error.
 *****************************************************/
int	DBrowsaffected	(void *VDB)
{
  dbapi *DB;
  DB = (dbapi *)VDB;
  return(DB->RowsAffected);
} /* DBrowsaffected() */

/*****************************************************
 DBgetcolname(): Return the name of a column.
 *****************************************************/
char *	DBgetcolname	(void *VDB, int Col)
{
  dbapi *DB;
  DB = (dbapi *)VDB;
  if (!DB || !DB->Res) return(NULL);
  return(PQfname(DB->Res,Col));
} /* DBgetcolname() */

/*****************************************************
 DBgetcolnum(): Return the number of a column's name.
 Returns size or -1.
 *****************************************************/
int	DBgetcolnum	(void *VDB, char *ColName)
{
  dbapi *DB;
  DB = (dbapi *)VDB;
  if (!DB || !DB->Res) return(-1);
  return(PQfnumber(DB->Res,ColName));
} /* DBgetcolnum() */

/*****************************************************
 DBgetvalue(): Return the value of a row/column.
 NOTE: No difference between invalid and NULL value.
 NOTE: Fixed fields may be space-padded.
 *****************************************************/
char *	DBgetvalue	(void *VDB, int Row, int Col)
{
  dbapi *DB;
  DB = (dbapi *)VDB;
  if (!DB || !DB->Res || (Col < 0)) return(NULL);
  return(PQgetvalue(DB->Res,Row,Col));
} /* DBgetvalue() */

/*****************************************************
 DBisnull(): Return 1 of value is null, 0 if non-null.
 Returns -1 on error.
 *****************************************************/
int	DBisnull	(void *VDB, int Row, int Col)
{
  dbapi *DB;
  DB = (dbapi *)VDB;
  if (!DB || !DB->Res) return(-1);
  return(PQgetisnull(DB->Res,Row,Col));
} /* DBisnull() */

/*********************************************************************/
/*********************************************************************/
/*********************************************************************/
#ifdef MAIN
int	main	()
{
  void *DB;
  DB = DBopen();
  if (DB)
    {
    printf("Connected! (Holy Cow!)\n");
    DBclose(DB);
    }
}
#endif


/*********************************************************************
 * The following functions use libpq and NOT the above db abstraction.
 * The plan is to eventually replace all the above with direct libpq 
 * calls and the helper functions below.
 */
 
/*
 \file libfossdb.c
 \brief common libpq database functions
 */

/****************************************************
 checkPQresult

 check the result status of a postgres SELECT
 If an error occured, write the error to stdout

 @param PGresult *result
 @param char *sql     the sql query
 @param char * FileID is a file identifier string to write into 
                      the error message.  Typically the caller
                      will use __FILE__, but any identifier string
                      is ok.
 @param int LineNumb  the line number of the caller (__LINE__)

 @return 0 on OK, -1 on failure.
 On failure, result will be freed.

 NOTE: this function should be moved to a std library
****************************************************/
int checkPQresult(PGconn *pgConn, PGresult *result, char *sql, char *FileID, int LineNumb)
{
   if (!result) 
   {
     printf("FATAL: %s:%d, %s\nOn: %s\n", 
            FileID, LineNumb, PQerrorMessage(pgConn), sql);
     return -1;
   }

   /* If no error, return */
   if (PQresultStatus(result) == PGRES_TUPLES_OK) return 0;

   printf("ERROR: %s:%d, %s\nOn: %s\n", 
          FileID, LineNumb, PQresultErrorMessage(result), sql);
   PQclear(result);
   return (-1);
} /* checkPQresult */


/****************************************************
 checkPQcommand

 check the result status of a postgres commands (not select)
 If an error occured, write the error to stdout

 @param PGresult *result
 @param char *sql the sql query
 @param char * FileID the function name of the caller
 @param int LineNumb the line number of the caller

 @return 0 on OK, -1 on failure.
 On failure, result will be freed.

 NOTE: this function should be moved to a std library
****************************************************/
int checkPQcommand(PGconn *pgConn, PGresult *result, char *sql, char *FileID, int LineNumb)
{
   if (!result)
   {
     printf("FATAL: %s:%d, %s\nOn: %s\n", 
            FileID, LineNumb, PQerrorMessage(pgConn), sql);
     return -1;
   }

   /* If no error, return */
   if (PQresultStatus(result) == PGRES_COMMAND_OK) return 0;

   printf("ERROR: %s:%d, %s\nOn: %s\n", 
          FileID, LineNumb, PQresultErrorMessage(result), sql);
   PQclear(result);
   return (-1);
} /* checkPQcommand */

