<?php
/*
 * -------------------------------------------------------------------------
CleanArchivedEmails plugin

Copyright (C) 2018 by Raynet SAS a company of A.Raymond Network.

http://www.araymond.com
-------------------------------------------------------------------------

LICENSE

This file is part of CleanArchivedEmails plugin for GLPI.

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


// ----------------------------------------------------------------------
// Original Author of file: Olivier Moron
// ----------------------------------------------------------------------

/**
 * Init the hooks of the plugin
 * @return null
 */
function plugin_init_cleanarchivedemails() {
   global $PLUGIN_HOOKS;

   $PLUGIN_HOOKS['csrf_compliant']['cleanarchivedemails'] = true;

   Plugin::registerClass('PluginCleanarchivedemailsMailCollector', array('addtabon' => 'MailCollector'));

   $PLUGIN_HOOKS['item_purge']['cleanarchivedemails'] = ['MailCollector' => 'plugin_item_purge_cleanarchivedemails'];
   $PLUGIN_HOOKS['pre_item_update']['cleanarchivedemails'] = ['PluginCleanarchivedemailsMailCollector' => 'plugin_pre_item_update_cleanarchivedemails'];

}


/**
 * Get the name and the version of the plugin - Needed
 * @return array
 */
function plugin_version_cleanarchivedemails() {
    //global $LANG;
    return array('name'           => __('Archived eMail clean', 'cleanarchivedemails'), //$LANG['plugin_cleanarchivedemails']["name"],
                 'version'        => '1.0.0',
                 'author'         => 'Olivier Moron',
                 'license'        => 'GPLv2+',
                 'homepage'       => 'https://github.com/tomolimo/cleanarchivedemails/',
                 'minGlpiVersion' => '9.1');
}


/**
 * Optional : check prerequisites before install : may print errors or add to message after redirect
 * @return boolean
 */
function plugin_cleanarchivedemails_check_prerequisites() {

   if (version_compare(GLPI_VERSION, '9.1', 'lt')) {
      echo "This plugin requires GLPI >= 9.1";
      return false;
   }
    return true;
}


/**
 * Check configuration process for plugin : need to return true if succeeded
 * Can display a message only if failure and $verbose is true
 * @param boolean $verbose for verbose mode
 * @return boolean
 */
function plugin_cleanarchivedemails_check_config($verbose=false) {
    return true;
}

