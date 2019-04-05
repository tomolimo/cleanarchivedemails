<?php

/**
 * mailcollector short summary.
 *
 * mailcollector description.
 *
 * @version 1.0
 * @author morono
 */
class PluginCleanarchivedemailsMailCollector extends CommonDBTM {

    static $rightname       = 'config';

    /**
     * Summary of getTabNameForItem
     * @param CommonGLPI $item
     * @param mixed $withtemplate
     */
    function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
        //global $LANG;

        $host = Toolbox::parseMailServerConnectString($item->fields["host"]);

        if ($host['type'] == "imap") {
            return __('IMAP folder purge', 'cleanarchivedemails', 'cleanarchivedemails'); //$LANG['plugin_cleanarchivedemails']['mailcollector']['tabname'];
        }
        return '';
    }

    /**
     * Summary of displayTabContentForItem
     * @param CommonGLPI $item         is the item
     * @param mixed      $tabnum       is the tab num
     * @param mixed      $withtemplate has template
     * @return mixed
     */
    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {

        if ($item->getType() == 'MailCollector') {
            self::showForm($item);
        }
        return true;
    }


    static function showForm($item) {
        //global $LANG;

        $clean = new self;

        // if no record for $item then add one to DB;
        // if (!$clean->getFromDBByQuery(" WHERE mailcollectors_id=".$item->getID())) {
        if (!$clean->getFromDBByRequest([
            'WHERE'  => [
                'mailcollectors_id'  => $item->getID(),                
            ],             
        ]))
        {
            $ret = $clean->add(['mailcollectors_id' => $item->getID()]);
            $clean->getFromDB($ret); // to reload all fields from DB.
        }

        $clean->showFormHeader();

        //      echo "<input type=hidden name=id value='".$clean->getID()."'/>";
        echo "<input type=hidden name=mailcollectors_id value='".$item->getID()."'/>";
        echo "<tr class='tab_bg_1'>";
        //      echo "<td colspan=2>".$LANG['plugin_cleanarchivedemails']['mailcollector']['nbdaysOK']."&nbsp;:</td><td colspan=2>";
        echo "<td colspan=2>".__("Number of days to keep eMails in 'Accepted mail archive folder' (set -1 for infinite)", 'cleanarchivedemails')."</td><td colspan=2>";
        echo "<input type='text' name='days_before_clean_accepted_folder' value='".$clean->fields['days_before_clean_accepted_folder']."'>";
        echo "</td></tr>\n";
        echo "<tr class='tab_bg_1'>";
        //      echo "<td colspan=2>".$LANG['plugin_cleanarchivedemails']['mailcollector']['nbdaysNOK']."&nbsp;:</td><td colspan=2>";
        echo "<td colspan=2>".__("Number of days to keep eMails in 'Refused mail archive folder' (set -1 for infinite)", 'cleanarchivedemails')."</td><td colspan=2>";
        echo "<input type='text' name='days_before_clean_refused_folder' value='".$clean->fields['days_before_clean_refused_folder']."'>";
        echo "</td></tr>\n";

        $clean->showFormButtons(['candel'=>false]);

        return false;
    }

    /**
     * summary of cronInfo
     *      Gives localized information about 1 cron task
     * @param $name of the task
     * @return array of strings
     */
    static function cronInfo($name) {
        //global $LANG;

        switch ($name) {
            case 'cleanarchivedemails' :
                return ['description' => __('Clean archived emails from Receiver IMAP folders', 'cleanarchivedemails')]; // $LANG['plugin_cleanarchivedemails']['cron']['cleanarchivedemails']['description'] );
        }
        return [];
    }

    /**
     * summary of cronCleanArchivedEmails
     *       Execute 1 task managed by the plugin
     * @param: $task CronTask class for log / stat
     * @return integer
     *    >0 : done
     *    <0 : to be run again (not finished)
     *     0 : nothing to do
     */
    static function cronCleanArchivedEmails($task) {

        $actionCode = 0; // by default
        $error = false;
        $task->setVolume(0); // start with zero
        $dbu = new DbUtils();

        // browse the MailCollector list and if IMAP and number of days to keep emails is  >= 0 then delete old emails
        $filter_condition=[
         'OR' =>
            [
                'days_before_clean_accepted_folder' => ['<>','-1'],
                'days_before_clean_refused_folder' => ['<>','-1'],
            ]
        ];
        $cleans = $dbu->getAllDataFromTable( 'glpi_plugin_cleanarchivedemails_mailcollectors', $filter_condition);
        foreach ($cleans as $clean) {
            $mailgate = new MailCollector;
            $mailgate->getFromDB( $clean['mailcollectors_id'] );
            if ($mailgate->fields['is_active'] == 1) {
                $host = Toolbox::parseMailServerConnectString($mailgate->fields["host"]);
                if ($host['type'] == 'imap') {
                    $volume = self::deleteEmailsFromMailbox( $mailgate, MailCollector::ACCEPTED_FOLDER, $clean['days_before_clean_accepted_folder']);
                    if ($volume > 0) {
                        $task->addVolume($volume);
                        $actionCode=1;
                    }
                    $volume = self::deleteEmailsFromMailbox( $mailgate, MailCollector::REFUSED_FOLDER, $clean['days_before_clean_refused_folder']);
                    if ($volume > 0) {
                        $task->addVolume($volume);
                        $actionCode=1;
                    }
                }
            }
        }

        if ($error) {
            return -1;
        } else {
            return $actionCode;
        }

    }

    /**
     * Summary of deleteEmailsFromMailbox
     * @param mixed $mailgate the MailCollector object
     * @param mixed $mailbox the name of the mailbox,
     *                must be either MailCollector::ACCEPTED_FOLDER or MailCollector::REFUSED_FOLDER
     * @param mixed $days the quantity of days to keep emails
     * @return double|integer quantity of deleted emails
     */
    static function deleteEmailsFromMailbox($mailgate, $mailbox, $days) {

        $volume = 0;
        if ($days > -1 && $mailgate->fields[$mailbox] != '') {
            preg_match('/^(?\'constring\'{.*?})/', $mailgate->fields['host'], $matches);
            $connect = $matches['constring'];

            // browse the mailbox and delete email older than $days in given folder
            // Connect to the Mail Box
            $mailgate->fields['host'] = $connect.$mailgate->fields[$mailbox];
            $mailgate->connect();
            // search and mark old emails for deletion
            $emails_to_delete = imap_search( $mailgate->marubox, "BEFORE \"".date ( "d M Y", strToTime ( "-$days days" ) )."\"", SE_UID );
            if ($emails_to_delete !== false) {
                foreach ($emails_to_delete as $msguid) {
                    // mark email as deleted
                    imap_delete($mailgate->marubox, $msguid, FT_UID);
                    $volume++;
                }
            }

            $mailgate->close_mailbox();   // Expunge and Close Mail Box
        }

        return $volume;
    }

}
