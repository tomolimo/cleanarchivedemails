<?php

/**
 * mailcollector short summary.
 *
 * mailcollector description.
 *
 * @author morono
 */

use Laminas\Mail;

class PluginCleanarchivedemailsMailCollector extends CommonDBTM {

   static $rightname       = 'config';


   /**
    * Summary of connect
    * @param array $config is the array returned by Toolbox::parseMailServerConnectString($host)
    * @param string $login 
    * @param string $passwd 
    * @throws Exception 
    * @return bool|Mail\Protocol\Imap|null
    */
   static function connect(array $config, string $login, string $passwd) {

      try {
         $protocol = Toolbox::getMailServerProtocolInstance($config['type']);
         if ($config['validate-cert'] === false) {
            $protocol->setNoValidateCert(true);
         }
         $ssl = false;
         if ($config['ssl']) {
            $ssl = 'SSL';
         }
         if ($config['tls']) {
            $ssl = 'TLS';
         }
         $protocol->connect($config['address'], $config['port'], $ssl);
         $protocol->login($login, $passwd);

         if ($protocol === null) {
            throw new \Exception(sprintf(__('Unsupported mail server type:%s.'), $config['type']));
         }
         return $protocol;
      } catch (\Exception $e) {
         return false;
      }
   }


   /**
   * Summary of getTabNameForItem
   * @param CommonGLPI $item
   * @param mixed $withtemplate
   */
   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

      $host = Toolbox::parseMailServerConnectString($item->fields["host"]);

      if ($host['type'] == "imap") {
         return __('IMAP folder purge', 'cleanarchivedemails', 'cleanarchivedemails');
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
         $clean = new self;
         if (!$clean->getFromDBByRequest([
              'WHERE'  => [
                 'mailcollectors_id'  => $item->getID(),
               ],
            ])) {
            $ret = $clean->add(['mailcollectors_id' => $item->getID()]);
            $clean->getFromDB($ret); // to reload all fields from DB.
         }
         $clean->showForm($item);
      }
      return true;
   }


   /**
   * Summary of showForm
   * @param  $item
   * @return bool
   */
   function showForm($item, $options = []) {

      $this->showFormHeader();

      echo "<input type=hidden name=mailcollectors_id value='".$item->getID()."'/>";
      echo "<tr class='tab_bg_1'>";
      echo "<td colspan=2>".__("Number of days to keep eMails in 'Accepted mail archive folder' (set -1 for infinite)", 'cleanarchivedemails')."</td><td colspan=2>";
      echo "<input type='text' name='days_before_clean_accepted_folder' value='".$this->fields['days_before_clean_accepted_folder']."'>";
      echo "</td></tr>\n";
      echo "<tr class='tab_bg_1'>";
      echo "<td colspan=2>".__("Number of days to keep eMails in 'Refused mail archive folder' (set -1 for infinite)", 'cleanarchivedemails')."</td><td colspan=2>";
      echo "<input type='text' name='days_before_clean_refused_folder' value='".$this->fields['days_before_clean_refused_folder']."'>";
      echo "</td></tr>\n";

      $this->showFormButtons(['candel'=>false]);

      return false;
   }


    /**
     * summary of cronInfo
     *      Gives localized information about 1 cron task
     * @param $name of the task
     * @return array of strings
     */
   static function cronInfo($name) {

      switch ($name) {
         case 'cleanarchivedemails' :
              return ['description' => __('Clean archived emails from Receiver IMAP folders', 'cleanarchivedemails')];
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
      $filter_condition = [
         'OR' => [
            'days_before_clean_accepted_folder' => ['<>', '-1'],
            'days_before_clean_refused_folder' => ['<>', '-1'],
         ]
      ];
      $cleans = $dbu->getAllDataFromTable('glpi_plugin_cleanarchivedemails_mailcollectors', $filter_condition);
      $mailcollector = new MailCollector;
      foreach ($cleans as $clean) {
         $mailcollector->getFromDB($clean['mailcollectors_id']);
         if ($mailcollector->fields['is_active'] == 1) {
            $config = Toolbox::parseMailServerConnectString($mailcollector->fields["host"]);
            if ($config['type'] == 'imap') {
               $protocol = self::connect($config, $mailcollector->fields["login"], Toolbox::sodiumDecrypt($mailcollector->fields['passwd']));
               
               $volume = self::deleteEmailsFromFolder($protocol, $mailcollector->fields[MailCollector::ACCEPTED_FOLDER], $clean['days_before_clean_accepted_folder']);
               if ($volume > 0) {
                  $task->addVolume($volume);
                  $actionCode = 1;
               }
               $volume = self::deleteEmailsFromFolder($protocol, $mailcollector->fields[MailCollector::REFUSED_FOLDER], $clean['days_before_clean_refused_folder']);
               if ($volume > 0) {
                  $task->addVolume($volume);
                  $actionCode = 1;
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
    * Summary of deleteEmailsFromFolder
    * @param Mail\Protocol\Imap $protocol 
    * @param string $folder 
    * @param int $days 
    * @throws Exception 
    * @return int
    */
   static function deleteEmailsFromFolder(Mail\Protocol\Imap $protocol, string $folder, int $days) {

      $volume = 0;
      if ($days > -1 && $folder != "") {
         // select folder to perform the search
         $protocol->select($folder);

         $emails_to_delete = $protocol->search(['BEFORE', date("d-M-Y", strToTime("-$days days"))]);
         if (is_array($emails_to_delete)) {
            foreach ($emails_to_delete as $msg_id) {
               // mark email as deleted
               if (!$protocol->store([Mail\Storage::FLAG_DELETED], $msg_id, null, '+')) {
                  throw new  \Exception('cannot set deleted flag');
               }

               $volume++;
            }

            // expunge here
            if (! $protocol->expunge()) {
               throw new \Exception('message marked as deleted, but could not expunge');
            }
         }

      }

      return $volume;
   }

}
