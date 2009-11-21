<?php
/*
   ----------------------------------------------------------------------
   GLPI - Gestionnaire Libre de Parc Informatique
   Copyright (C) 2003-2008 by the INDEPNET Development Team.

   http://indepnet.net/   http://glpi-project.org/
   ----------------------------------------------------------------------

   LICENSE

   This file is part of GLPI.

   GLPI is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation; either version 2 of the License, or
   (at your option) any later version.

   GLPI is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with GLPI; if not, write to the Free Software
   Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
   ------------------------------------------------------------------------
 */

// ----------------------------------------------------------------------
// Original Author of file: GRISARD Jean Marc & CAILLAUD Xavier
// Purpose of file:
// ----------------------------------------------------------------------

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}


function plugin_appliances_deleteItem($ID) {
   global $DB;

   // CNAMTS BEGIN
   // delete the data when applicatif unlinked from a machine
   $query_app_id = "SELECT `appliances_id` app, `items_id` AS computer
                    FROM `glpi_plugin_appliances_appliances_items`
                    WHERE `id` = '$ID'";

   $result_app_id = $DB->query($query_app_id);
   $data_app_id = $DB->fetch_array($result_app_id);
   $applicatif_id = $data_app_id['app'];
   $computer_id = $data_app_id['computer'];

   $sql = "DELETE
           FROM `glpi_plugin_appliances_optvalues_items`
           WHERE `optvalues_id` IN (SELECT `id`
                                    FROM `glpi_plugin_appliances_optvalues`
                                    WHERE `appliances_id` = '$applicatif_id')
                 AND `appliances_items_id` ='$computer_id' ";
   $res = $DB->query($sql);
   // CNAMTS END

   // delete the relation if the device is deleted
   $sql = "DELETE
           FROM `glpi_plugin_appliances_relations`
           WHERE `appliances_items` = '$ID' ";
   $res = $DB->query($sql);

   $query = "DELETE
             FROM `glpi_plugin_appliances_appliances_items`
             WHERE `id` = '$ID';";
   $result = $DB->query($query);
}


function plugin_appliances_transferDropdown($ID,$entity) {
   global $DB;

   if ($ID >0) {
      $query = "SELECT *
                FROM `glpi_plugin_appliances_appliancetypes`
                WHERE `id` = '$ID'";

      if ($result = $DB->query($query)) {
         if ($DB->numrows($result)) {
            $data = $DB->fetch_array($result);
            $data = addslashes_deep($data);
            // Search if the location already exists in the destination entity
            $query = "SELECT `id`
                      FROM `glpi_plugin_appliances_appliancetypes
                      WHERE `entities_id` = '$entity'
                            AND `name` = '".$data['name']."'";

            if ($result_search = $DB->query($query)) {
               // Found : -> use it
               if ($DB->numrows($result_search) >0) {
                  $newID = $DB->result($result_search,0,'id');
                  return $newID;
               }
            }
            // Not found :
            $input = array();
            $input['tablename']   = 'glpi_plugin_appliances_appliancetypes';
            $input['entities_id'] = $entity;
            $input['value']       = $data['name'];
            $input['comment']     = $data['comment'];
            $input['type']        = "under";
            $input['value2']      = 0; // parentID
            $newID = addDropdown($input);
            return $newID;
         }
      }
   }
   return 0;
}


function plugin_appliances_addRelation($device,$relation) {
   global $DB;

   // check if the relation already exists
   if (!countElementsInTable('glpi_plugin_appliances_relations',
                             "appliances_items_id='$device' AND relations_id='$relation'")) {

      $sql = "INSERT
              INTO `glpi_plugin_appliances_relations`
              (`appliances_items_id`, `relations_id`)
              VALUES('$device', '$relation')";
      $res = $DB->query($sql);
   }
}


function plugin_appliances_delRelation($ID) {
   global $DB;

   $sql = "DELETE
           FROM `glpi_plugin_appliances_relations`
           WHERE `id` = '$ID'";
   $res = $DB->query($sql);
}

?>