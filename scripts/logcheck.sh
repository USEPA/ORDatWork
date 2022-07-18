#!/bin/bash
 
# checklogs.sh - send email alerts if specific strings are found in http error logs
#                Should be run from root cron once every 1-5 minutes and will send 
#                any found errors to email list
#
# Originally created by Jason Overstreet 2015-01-14
 
##################################################################
# Set Variables for the environment
##################################################################
 
DRUSHDIR="/usr/local/bin"
PRIMARYALIAS="@ord.prod"

CHECKLOGDIR="/var/www/utils/logs/checklogs"
DIRDATE="`date '+%Y%m%d'`"
 
#How many days of error logs to keep
KEEP_DAILY_X_DAYS=30
#Max number of times script should see itself running before sending an error
MAXRUNTIMES=5
 
EMAIL_NOTIFICATION="jason.overstreet@cgifederal.com,overstreet.jason@epa.gov,matkin.benjamin.k@epa.gov,talley.alex@epa.gov,dearie.jessica@epa.gov"
#EMAIL_NOTIFICATION="overstreet.jason@epa.gov"
 
#Log file(s) to search
#LOGFILEPATH="/var/log/httpd/*error_log"
LOGFILEPATH="/var/log/drupal.log"
 
#Enter stings (case sensitive) in quotes, separated by a space
#declare -a STRINGCHECK=("exhausted" "PHP Fatal error:")
declare -a STRINGCHECK=("InvalidArgumentException: The state ")

#Check Drush connections
BOOTSTRAP_CHECK=`${DRUSHDIR}/drush ${PRIMARYALIAS} st | grep "Drupal bootstrap" | cut -f2 -d: | tr -d '[:space:]'`
if [ "${BOOTSTRAP_CHECK}" != "Successful" ]
then
  echo "ALERT: Drupal connection error! Received ${BOOTSTRAP_CHECK}"
  exit
fi

 
##################################################################
# Check
##################################################################
 
echo "--------------------"
echo "checklogs.sh started at "`date`
echo "" 
 
#Create directory if it doesn't exist
if [ ! -d ${CHECKLOGDIR} ]
then
  mkdir -p ${CHECKLOGDIR};
fi
 
#Create file if it doesn't exist
if [ ! -f ${CHECKLOGDIR}/checklogs${DIRDATE} ]
then
  touch ${CHECKLOGDIR}/checklogs${DIRDATE}
fi
 
#Exit if already running
if [ -s ${CHECKLOGDIR}/CHECKLOG.SCRIPTRUNNING ]
then
  TIMESRUN=`cat ${CHECKLOGDIR}/CHECKLOG.SCRIPTRUNNING`
  if [ ${TIMESRUN} -gt ${MAXRUNTIMES} ]
  then
    echo "checklog script possibly stuck. Has run ${TIMESRUN} times." > ${CHECKLOGDIR}/checklogs.tmpmail.$$
    mail -s "ALERT for `uname -n`" ${EMAIL_NOTIFICATION} < ${CHECKLOGDIR}/checklogs.tmpmail.$$
    echo $[${TIMESRUN}+1] > ${CHECKLOGDIR}/CHECKLOG.SCRIPTRUNNING
    echo "Script run more than ${MAXRUNTIMES} times. Sending alert email."
    rm ${CHECKLOGDIR}/checklogs.tmpmail.$$
    exit
  else
    echo $[${TIMESRUN}+1] > ${CHECKLOGDIR}/CHECKLOG.SCRIPTRUNNING
    echo "Script already running"
    exit
  fi
else
  echo 0 > ${CHECKLOGDIR}/CHECKLOG.SCRIPTRUNNING
fi
 
#For each error string, check the error logs
for ERRSTRING in "${STRINGCHECK[@]}"
do
  grep "${ERRSTRING}" ${LOGFILEPATH} >> ${CHECKLOGDIR}/checklogs.tmp.$$
done
 
#Sort to make sure the diffs end up in the same place each time
sort -u ${CHECKLOGDIR}/checklogs.tmp.$$ > ${CHECKLOGDIR}/checklogs.tmp2.$$
 
#Check for differences between new errors and today's error file
diff ${CHECKLOGDIR}/checklogs.tmp2.$$ ${CHECKLOGDIR}/checklogs${DIRDATE} > ${CHECKLOGDIR}/checklogs.tmpdiff.$$
 
if [ -s ${CHECKLOGDIR}/checklogs.tmpdiff.$$ ]
then
  #Differences should only show up in the new file, not the old one 
  NEWCHECK=`grep "^<" ${CHECKLOGDIR}/checklogs.tmpdiff.$$`
  if [ ! -z "${NEWCHECK}" ]
  then 
    NEWCHECK2=`grep "^>" ${CHECKLOGDIR}/checklogs.tmpdiff.$$`
    if [ -z "${NEWCHECK2}" ]
    then
      echo "Errors found! Rebuilding cache and sending mail."
      grep "^<" ${CHECKLOGDIR}/checklogs.tmpdiff.$$ | cut -c2- > ${CHECKLOGDIR}/checklogs.tmpmail.$$
      echo "" >> ${CHECKLOGDIR}/checklogs.tmpmail.$$
      echo "" >> ${CHECKLOGDIR}/checklogs.tmpmail.$$
      echo "Last lines in ${LOGFILEPATH}" >> ${CHECKLOGDIR}/checklogs.tmpmail.$$
      tail -50 ${LOGFILEPATH}  >> ${CHECKLOGDIR}/checklogs.tmpmail.$$
      echo "" >> ${CHECKLOGDIR}/checklogs.tmpmail.$$
      echo "" >> ${CHECKLOGDIR}/checklogs.tmpmail.$$
      echo "" >> ${CHECKLOGDIR}/checklogs.tmpmail.$$
      # Rebuild cache
      echo "Rebuilding cache at "`date` >> ${CHECKLOGDIR}/checklogs.tmpmail.$$
      echo "" >> ${CHECKLOGDIR}/checklogs.tmpmail.$$
      ${DRUSHDIR}/drush ${PRIMARYALIAS} cr
      echo "" >> ${CHECKLOGDIR}/checklogs.tmpmail.$$
      echo "Finished rebuilding cache at "`date` >> ${CHECKLOGDIR}/checklogs.tmpmail.$$
      # Send Mail
      mail -s "ALERT! Errors found for `uname -n`" ${EMAIL_NOTIFICATION} < ${CHECKLOGDIR}/checklogs.tmpmail.$$
      mv ${CHECKLOGDIR}/checklogs.tmp2.$$ ${CHECKLOGDIR}/checklogs${DIRDATE}
    else
      echo "There are errors in the previous file that aren't in the current file." 
      # Send Mail
      echo "There are errors in the previous file that aren't in the current file." > ${CHECKLOGDIR}/checklogs.tmpmail2.$$
      cat ${CHECKLOGDIR}/checklogs.tmpmail2.$$ ${CHECKLOGDIR}/checklogs.tmpdiff.$$ > ${CHECKLOGDIR}/checklogs.tmpmail.$$
      mail -s "Error with checklogs.sh on `uname -n`" ${EMAIL_NOTIFICATION} < ${CHECKLOGDIR}/checklogs.tmpmail.$$
    fi
 
  else
    echo "There are errors in the previous file that aren't in the current file."
    # Send Mail
    echo "There are errors in the previous file that aren't in the current file." > ${CHECKLOGDIR}/checklogs.tmpmail2.$$
    cat ${CHECKLOGDIR}/checklogs.tmpmail2.$$ ${CHECKLOGDIR}/checklogs.tmpdiff.$$ > ${CHECKLOGDIR}/checklogs.tmpmail.$$
    mail -s "Error with checklogs.sh on `uname -n`" ${EMAIL_NOTIFICATION} < ${CHECKLOGDIR}/checklogs.tmpmail.$$
 
  fi
 
else
   echo "No new errors found"
fi
 
 
echo "checklogs.sh finished at "`date`
 
 
 
##################################################################
# Cleanup
##################################################################
 
echo "cleanup started at "`date`
 
 
#Find old daily files to remove them
for FILE in `find ${CHECKLOGDIR} -name "checklogs*gz" -mtime +${KEEP_DAILY_X_DAYS}`
do
   echo "removing ${FILE} since it is older than ${KEEP_DAILY_X_DAYS} days."
   rm -f ${FILE}
done
 
#Compress daily log file
for FILE in `find ${CHECKLOGDIR} -name "checklogs????????" -mtime +1 `
do
   gzip ${FILE}
done
 
echo "Removing locks and tmp files"
 
rm -f ${CHECKLOGDIR}/CHECKLOG.SCRIPTRUNNING
rm -f ${CHECKLOGDIR}/checklogs.tmp*
