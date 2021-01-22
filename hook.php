<?php
/*
 * -------------------------------------------------------------------------

Copyright (C) 2018 by Raynet SAS a company of A.Raymond Network.

http://www.araymond.com
-------------------------------------------------------------------------

LICENSE

This file is part of MSurveys plugin for GLPI.

This file is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

GLPI is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with GLPI. If not, see <http://www.gnu.org/licenses/>.
--------------------------------------------------------------------------
 */


/**
 * Summary of plugin_cleanarchivedemails_install
 * @return boolean
 */
function plugin_cleanarchivedemails_install() {
    global $DB;

   if (!$DB->tableExists("glpi_plugin_cleanarchivedemails_mailcollectors")) {
      $query = "CREATE TABLE `glpi_plugin_cleanarchivedemails_mailcollectors` (
				`id` INT(11) NOT NULL AUTO_INCREMENT,
				`mailcollectors_id` INT(11) NOT NULL,
				`days_before_clean_accepted_folder` INT(11) NULL DEFAULT '-1',
				`days_before_clean_refused_folder` INT(11) NULL DEFAULT '-1',
				PRIMARY KEY (`id`),
         	UNIQUE INDEX `mailcollectors_id` (`mailcollectors_id`)
			)
			COLLATE='utf8_general_ci'
			ENGINE=InnoDB;
			";

      $DB->query($query) or die("error creating glpi_plugin_cleanarchivedemails_mailcollectors " . $DB->error());
   } else {
      $res = $DB->request([
                     'SELECT' => 'ENGINE',
                     'FROM'   => 'information_schema.TABLES',
                     'WHERE'  => [
                        'AND' => [
                           'TABLE_SCHEMA' => $DB->dbdefault,
                           'TABLE_NAME' => 'glpi_plugin_cleanarchivedemails_mailcollectors'
                        ]
                     ]
         ]);
      if ($res->numrows() == 1) {
         $row = $res->next();
         if ($row['ENGINE'] == 'MyISAM') {
            $query = "ALTER TABLE glpi_plugin_cleanarchivedemails_mailcollectors ENGINE = InnoDB";
            $DB->query($query) or die("error updating ENGINE in glpi_plugin_cleanarchivedemails_mailcollectors " . $DB->error());
         }
      }
   }
   // CRON
   CronTask::Register('PluginCleanarchivedemailsMailcollector', 'cleanarchivedemails', DAY_TIMESTAMP, ['param' => 5, 'state' => CronTask::STATE_DISABLE, 'mode' => CronTask::MODE_EXTERNAL]);

    return true;
}

/**
 * Summary of plugin_cleanarchivedemails_uninstall
 * @return boolean
 */
function plugin_cleanarchivedemails_uninstall() {
    global $DB;

   CronTask::Unregister('PluginCleanarchivedemailsMailcollector');

    return true;
}


/**
 * Summary of plugin_cleanarchivedemails_getAddSearchOptions
 * @param mixed $itemtype the type of the item
 * @return array
 */
function plugin_cleanarchivedemails_getAddSearchOptions($itemtype) {

    $tab = [];
   if ($itemtype == 'MailCollector') {
      $tab['cleanarchivedemails'] = __('Clean archived eMails', 'cleanarchivedemails');

      $tab[700]['table']      = 'glpi_plugin_cleanarchivedemails_mailcollectors';
      $tab[700]['field']      = 'days_before_clean_accepted_folder';
      $tab[700]['name']       = __('Days for accepted', 'cleanarchivedemails');
      $tab[700]['searchtype'] = 'equals';
      $tab[700]['massiveaction'] = false;
      $tab[700]['joinparams'] = ['jointype' => 'child'];

      $tab[701]['table']      = 'glpi_plugin_cleanarchivedemails_mailcollectors';
      $tab[701]['field']      = 'days_before_clean_refused_folder';
      $tab[701]['name']       = __('Days for refused', 'cleanarchivedemails');
      $tab[701]['searchtype'] = 'equals';
      $tab[701]['massiveaction'] = false;
      $tab[701]['joinparams'] = ['jointype' => 'child'];

   }
    return $tab;
}


function plugin_item_purge_cleanarchivedemails($item) {
   $clean = new PluginCleanarchivedemailsMailCollector;

   //if ($clean->getFromDBByQuery(" WHERE mailcollectors_id=".$item->getID())) {
   if ($clean->getFromDBByRequest([
       'WHERE'  => [
           'mailcollectors_id'  => $item->getID(),
       ],
   ])) {
      $clean->deleteFromDB(true);
   }
}


function plugin_pre_item_update_cleanarchivedemails($item) {

   if (intval($item->input['days_before_clean_accepted_folder']) < -1 || !is_numeric($item->input['days_before_clean_accepted_folder'])) {
      $item->input = [];
      return;
   }

   if (intval($item->input['days_before_clean_refused_folder']) < -1 || !is_numeric($item->input['days_before_clean_refused_folder'])) {
      $item->input = [];
      return;
   }

}
