<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
  LICENSE

 This file is part of Appliances plugin for GLPI.

 Appliances is free software: you can redistribute it and/or modify
 it under the terms of the GNU Affero General Public License as published by
 the Free Software Foundation, either version 3 of the License, or
 (at your option) any later version.

 Appliances is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with Appliances. If not, see <http://www.gnu.org/licenses/>.

 @package   appliances
 @author    Xavier CAILLAUD, Remi Collet, Nelly Mahu-Lasson
 @copyright Copyright (c) 2009-2016 Appliances plugin team
 @license   AGPL License 3.0 or (at your option) any later version
            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 @link      https://forge.glpi-project.org/projects/appliances
 @since     version 2.0
 --------------------------------------------------------------------------
 */

define("PLUGIN_APPLIANCES_RELATION_LOCATION",1);

function plugin_appliances_postinit() {
   global $CFG_GLPI, $PLUGIN_HOOKS;

   $PLUGIN_HOOKS['plugin_uninstall_after']['appliances'] = array();
   $PLUGIN_HOOKS['item_purge']['appliances']             = array();

   foreach (PluginAppliancesAppliance::getTypes(true) as $type) {
      $PLUGIN_HOOKS['plugin_uninstall_after']['appliances'][$type]
                                    = array('PluginAppliancesAppliance_Item','cleanForItem');

      $PLUGIN_HOOKS['item_purge']['appliances'][$type]
                                    = array('PluginAppliancesAppliance_Item','cleanForItem');

      CommonGLPI::registerStandardTab($type, 'PluginAppliancesAppliance_Item');
   }
}

function plugin_appliances_registerMethods() {
   global $WEBSERVICES_METHOD;

   // Not authenticated method
   $WEBSERVICES_METHOD['appliances.testAppliances']  = array('PluginAppliancesAppliance',
                                                             'methodTestAppliance');
   // Authenticated method
   $WEBSERVICES_METHOD['appliances.listAppliances']  = array('PluginAppliancesAppliance',
                                                             'methodListAppliances');

   $WEBSERVICES_METHOD['appliances.addAppliance']    = array('PluginAppliancesAppliance',
                                                             'methodAddAppliance');

   $WEBSERVICES_METHOD['appliances.deleteAppliance'] = array('PluginAppliancesAppliance',
                                                             'methodDeleteAppliance');

   $WEBSERVICES_METHOD['appliances.updateAppliance'] = array('PluginAppliancesAppliance',
                                                             'methodUpdateAppliance');

   $WEBSERVICES_METHOD['appliances.getAppliance']    = array('PluginAppliancesAppliance',
                                                             'methodGetAppliance');
}


function plugin_appliances_AssignToTicket($types) {

   if (Session::haveRight("plugin_appliances_open_ticket", "1")) {
      $types['PluginAppliancesAppliance'] = _n('Appliance', 'Appliances', 2, 'appliances');
   }
   return $types;
}


function plugin_appliances_install() {
   global $DB;

   if (TableExists("glpi_plugin_applicatifs_profiles")) {
      if (FieldExists("glpi_plugin_applicatifs_profiles","create_applicatifs")) { // version <1.3
         $DB->runFile(GLPI_ROOT ."/plugins/appliances/sql/update-1.3.sql");
      }
   }

   if (TableExists("glpi_plugin_applicatifs")) {
      if (!FieldExists("glpi_plugin_applicatifs","recursive")) { // version 1.3
         $DB->runFile(GLPI_ROOT ."/plugins/appliances/sql/update-1.4.sql");
      }
      if (!FieldExists("glpi_plugin_applicatifs","FK_groups")) { // version 1.4
         $DB->runFile(GLPI_ROOT ."/plugins/appliances/sql/update-1.5.0.sql");
      }
      if (!FieldExists("glpi_plugin_applicatifs","helpdesk_visible")) { // version 1.5.0
         $DB->runFile(GLPI_ROOT ."/plugins/appliances/sql/update-1.5.1.sql");
      }
      if (FieldExists("glpi_plugin_applicatifs","state")) { // empty 1.5.0 not in update 1.5.0
         $DB->query("ALTER TABLE `glpi_plugin_applicatifs` DROP `state`");
      }
      if (isIndex("glpi_plugin_applicatifs_optvalues_machines", "optvalue_ID")) { // in empty 1.5.0 not in update 1.5.0
         $DB->query("ALTER TABLE `glpi_plugin_applicatifs_optvalues_machines`
                     DROP KEY `optvalue_ID`");
      }
      $DB->runFile(GLPI_ROOT ."/plugins/appliances/sql/update-1.6.0.sql");

      Plugin::migrateItemType(array(1200 => 'PluginAppliancesAppliance'),
                              array("glpi_bookmarks", "glpi_bookmarks_users",
                                    "glpi_displaypreferences", "glpi_documents_items",
                                    "glpi_infocoms", "glpi_logs", "glpi_items_tickets"),
                              array("glpi_plugin_appliances_appliances_items",
                                    "glpi_plugin_appliances_optvalues_items"));

      Plugin::migrateItemType(array(4450 => "PluginRacksRack"),
                              array("glpi_plugin_appliances_appliances_items"));
   }

   if (!TableExists("glpi_plugin_appliances_appliances")) { // not installed
      $DB->runFile(GLPI_ROOT . '/plugins/appliances/sql/empty-2.0.0.sql');

   } else {
      $migration = new Migration(200);

      include_once(GLPI_ROOT."/plugins/appliances/inc/appliance.class.php");
      PluginAppliancesAppliance::updateSchema($migration);

      $migration->executeMigration();

   }
   // required cause autoload don't work for unactive plugin'
   include_once(GLPI_ROOT."/plugins/appliances/inc/profile.class.php");

   PluginAppliancesProfile::initProfile();
   PluginAppliancesProfile::createFirstAccess($_SESSION['glpiactiveprofile']['id']);
   $migration = new Migration("2.0.0");
   $migration->dropTable('glpi_plugin_appliances_profiles');

   return true;
}


function plugin_appliances_uninstall() {
   global $DB;

   $tables = array('glpi_plugin_appliances_appliances',
                   'glpi_plugin_appliances_appliances_items',
                   'glpi_plugin_appliances_appliancetypes',
                   'glpi_plugin_appliances_environments',
                   'glpi_plugin_appliances_relations',
                   'glpi_plugin_appliances_optvalues',
                   'glpi_plugin_appliances_optvalues_items');

   foreach($tables as $table) {
      $DB->query("DROP TABLE `$table`");
   }

   $itemtypes = array('Document_Item', 'DisplayPreference', 'Bookmark', 'Log', 'Notepad',
         'Item_Ticket', 'Contract_Item', 'Item_Problem');
   foreach ($itemtypes as $itemtype) {
      $item = new $itemtype;
      $item->deleteByCriteria(array('itemtype' => 'PluginAppliancesAppliance'));
   }

   $query = "DELETE
             FROM `glpi_dropdowntranslations`
             WHERE `itemtype` IN ('PluginAppliancesApplianceType', 'PluginAppliancesEnvironment')";
   $DB->query($query);

   $query = "DELETE
             FROM `glpi_profilerights`
             WHERE `name` IN ('plugin_appliances', 'plugin_appliances_open_ticket')";
   $DB->query($query);

   if ($temp = getItemForItemtype('PluginDatainjectionModel')) {
      $temp->deleteByCriteria(array('itemtype'=>'PluginAppliancesAppliance'));
   }
   include_once(GLPI_ROOT."/plugins/appliances/inc/profile.class.php");
   include_once(GLPI_ROOT."/plugins/appliances/inc/menu.class.php");
   //Delete rights associated with the plugin
   $profileRight = new ProfileRight();
   foreach (PluginAppliancesProfile::getAllRights() as $right) {
      $profileRight->deleteByCriteria(array('name' => $right['field']));
   }
   PluginAppliancesMenu::removeRightsFromSession();
   PluginAppliancesProfile::removeRightsFromSession();

   return true;
}


/**
 * Define Dropdown tables to be manage in GLPI :
**/
function plugin_appliances_getDropdown(){

   return array('PluginAppliancesApplianceType'  => __('Type of appliance', 'appliances'),
                'PluginAppliancesEnvironment'    => __('Environment', 'appliances'));
}


/**
 * Define dropdown relations
**/
function plugin_appliances_getDatabaseRelations() {

   $plugin = new Plugin();
   if ($plugin->isActivated("appliances")) {
      return array('glpi_plugin_appliances_appliancetypes'
                                     => array('glpi_plugin_appliances_appliances'
                                                => 'plugin_appliances_appliancetypes_id'),

                   'glpi_plugin_appliances_environments'
                                     => array('glpi_plugin_appliances_appliances'
                                                => 'plugin_appliances_environments_id'),

                   'glpi_entities'   => array('glpi_plugin_appliances_appliances'
                                                => 'entities_id',
                                              'glpi_plugin_appliances_appliancetypes'
                                                => 'entities_id'),

                   'glpi_plugin_appliances_appliances'
                                     => array('glpi_plugin_appliances_appliances_items'
                                                => 'plugin_appliances_appliances_id'),

                   '_virtual_device' => array('glpi_plugin_appliances_appliances_items'
                                                => array('items_id', 'itemtype')));
   }
   return array();
}


////// SEARCH FUNCTIONS ///////(){

/**
 * Define search option for types of the plugins
**/
function plugin_appliances_getAddSearchOptions($itemtype) {

   $sopt = array();
   if (Session::haveRight("plugin_appliances", READ)) {
      if (in_array($itemtype, PluginAppliancesAppliance::getTypes(true))) {
         $sopt[1210]['table']          = 'glpi_plugin_appliances_appliances';
         $sopt[1210]['field']          = 'name';
         $sopt[1210]['massiveaction']  = false;
         $sopt[1210]['name']           = sprintf(__('%1$s - %2$s'), __('Appliance', 'appliances'),
                                                    __('Name'));
         $sopt[1210]['forcegroupby']   = true;
         $sopt[1210]['datatype']       = 'itemlink';
         $sopt[1210]['itemlink_type']  = 'PluginAppliancesAppliance';
         $sopt[1210]['joinparams']     = array('beforejoin'
                                                => array('table'
                                                          => 'glpi_plugin_appliances_appliances_items',
                                                         'joinparams'
                                                          => array('jointype' => 'itemtype_item')));

         $sopt[1211]['table']         = 'glpi_plugin_appliances_appliancetypes';
         $sopt[1211]['field']         = 'name';
         $sopt[1211]['massiveaction'] = false;
         $sopt[1211]['name']          = sprintf(__('%1$s - %2$s'), __('Appliance', 'appliances'),
                                                   __('Type'));
         $sopt[1211]['forcegroupby']  =  true;
         $sopt[1211]['joinparams']    = array('beforejoin' => array(
                                                   array('table'
                                                          => 'glpi_plugin_appliances_appliances',
                                                         'joinparams'
                                                          => $sopt[1210]['joinparams'])));
      }
      if ($itemtype == 'Ticket') {
         $sopt[1212]['table']         = 'glpi_plugin_appliances_appliances';
         $sopt[1212]['field']         = 'name';
         $sopt[1212]['linkfield']     = 'items_id';
         $sopt[1212]['datatype']      = 'itemlink';
         $sopt[1212]['massiveaction'] = false;
         $sopt[1212]['name']          = __('Appliance', 'appliances')." - ".
                                        __('Name');
      }
   }
   return $sopt;
}


function plugin_appliances_forceGroupBy($type) {

   switch ($type) {
      case 'PluginAppliancesAppliance' :
         return true;
   }
   return false;
}


function plugin_appliances_giveItem($type, $ID, $data, $num) {
   global $DB, $CFG_GLPI;

   $searchopt = &Search::getOptions($type);
   $table = $searchopt[$ID]["table"];
   $field = $searchopt[$ID]["field"];

   switch ($table.'.'.$field) {
      case "glpi_plugin_appliances_appliances_items.items_id" :
         $appliances_id = $data['id'];
         $query_device  = "SELECT DISTINCT `itemtype`
                           FROM `glpi_plugin_appliances_appliances_items`
                           WHERE `plugin_appliances_appliances_id` = '".$appliances_id."'
                           ORDER BY `itemtype`";
         $result_device  = $DB->query($query_device);
         $number_device  = $DB->numrows($result_device);
         $out            = '';
         if ($number_device > 0) {
            for ($y=0 ; $y < $number_device ; $y++) {
               $column = "name";
               if ($type == 'Ticket') {
                  $column = "id";
               }
               $type = $DB->result($result_device, $y, "itemtype");
               if (!($item = getItemForItemtype($type))) {
                     continue;
               }
               $table = $item->getTable();
               if (!empty($table)) {
                  $query = "SELECT `".$table."`.`id`
                            FROM `glpi_plugin_appliances_appliances_items`, `".$table."`
                            LEFT JOIN `glpi_entities`
                              ON (`glpi_entities`.`id` = `".$table."`.`entities_id`)
                            WHERE `".$table."`.`id`
                                       = `glpi_plugin_appliances_appliances_items`.`items_id`
                                  AND `glpi_plugin_appliances_appliances_items`.`itemtype`
                                       = '".$type."'
                                  AND `glpi_plugin_appliances_appliances_items`.`plugin_appliances_appliances_id`
                                       = '".$appliances_id."'".
                                 getEntitiesRestrictRequest(" AND ", $table, '', '',
                                                            $item->maybeRecursive());

                  if ($item->maybeTemplate()) {
                     $query .= " AND `".$table."`.`is_template` = '0'";
                  }
                  $query .= " ORDER BY `glpi_entities`.`completename`,
                             `$table`.`$column`";

                  if ($result_linked = $DB->query($query)) {
                     if ($DB->numrows($result_linked)) {
                        while ($data = $DB->fetch_assoc($result_linked)) {
                           if ($item->getFromDB($data['id'])) {
                              $out .= $item->getTypeName(1)." - ".$item->getLink()."<br>";
                           }
                        }
                     }
                  }
               }
            }
         }
         return $out;

      case 'glpi_plugin_appliances_appliances.name':
         if ($type == 'Ticket') {
            $appliances_id = array();
            if ($data['raw']["ITEM_$num"] != '') {
               $appliances_id = explode('$$$$', $data['raw']["ITEM_$num"]);
            } else {
               $appliances_id = explode('$$$$', $data['raw']["ITEM_".$num."_2"]);
            }
            $ret = array();
            $paAppliance = new PluginAppliancesAppliance();
            foreach ($appliances_id as $ap_id) {
               $paAppliance->getFromDB($ap_id);
               $ret[] = $paAppliance->getLink();
            }
            return implode('<br>', $ret);
         }
         break;

   }
   return "";
}


////// SPECIFIC MODIF MASSIVE FUNCTIONS ///////

function plugin_appliances_MassiveActions($type) {

   if (in_array($type,PluginAppliancesAppliance::getTypes(true))) {
      return array('PluginAppliancesAppliance'.MassiveAction::CLASS_ACTION_SEPARATOR.'plugin_appliances_add_item' =>
                                                              __('Associate to appliance', 'appliances'));
   }
   return array();
}
/*
function plugin_appliances_MassiveActions($type) {

   switch ($type) {
      case 'PluginAppliancesAppliance' :
         return array('plugin_appliances_install'    => __('Associate', 'appliances'),
                      'plugin_appliances_desinstall' => __('Dissociate', 'appliances'),
                      'plugin_appliances_transfert'  => __('Transfer'));

      default :
         if (in_array($type, PluginAppliancesAppliance::getTypes(true))) {
            return array("plugin_appliances_add_item" => __('Associate to appliance', 'appliances'));
         }
   }
   return array();
}


function plugin_appliances_MassiveActionsDisplay($options) {

   switch ($options['itemtype']) {
      case 'PluginAppliancesAppliance' :
         switch ($options['action']) {
            // No case for add_document : use GLPI core one
            case "plugin_appliances_install" :
               Dropdown::showAllItems("item_item",0,0,-1,PluginAppliancesAppliance::getTypes());
               echo "<input type='submit' name='massiveaction' class='submit' ".
                     "value='"._x('button', 'Post')."'>";
               break;

            case "plugin_appliances_desinstall" :
               Dropdown::showAllItems("item_item",0,0,-1,PluginAppliancesAppliance::getTypes());
               echo "<input type='submit' name='massiveaction' class='submit' ".
                     "value='"._x('button', 'Post')."'>";
               break;

            case "plugin_appliances_transfert" :
               Entity::dropdown();
               echo "&nbsp;<input type='submit' name='massiveaction' class='submit' ".
                     "value='"._x('button', 'Post')."'>";
               break;
         }
         break;

      default :
         if (in_array($options['itemtype'], PluginAppliancesAppliance::getTypes(true))) {
            Dropdown::show('PluginAppliancesAppliance');
            echo "<input type='submit' name='massiveaction' class='submit\' ".
                  "value='"._x('button', 'Post')."'>";
         }
   }
   return "";
}


function plugin_appliances_MassiveActionsProcess($data) {
   global $DB;

   switch ($data['action']) {
      case "plugin_appliances_add_item" :
         if (in_array($data['itemtype'],PluginAppliancesAppliance::getTypes())) {
            $PluginItem = new PluginAppliancesAppliance_Item();
            foreach ($data["item"] as $key => $val) {
               if ($val == 1) {
                  $input = array('plugin_appliances_appliances_id'
                                             => $data['plugin_appliances_appliances_id'],
                                 'items_id'  => $key,
                                 'itemtype'  => $data['itemtype']);
                  if ($PluginItem->can(-1,'w',$input)) {
                     $PluginItem->add($input);
                  }
               }
            }
         }
         break;

      case "plugin_appliances_install" :
         if (in_array($data['itemtype'],PluginAppliancesAppliance::getTypes())) {
            $PluginItem = new PluginAppliancesAppliance_Item();
            foreach ($data["item"] as $key => $val) {
               if ($val == 1) {
                  $input = array('plugin_appliances_appliances_id' => $key,
                                 'items_id'                        => $data["item_item"],
                                 'itemtype'                        => $data['itemtype']);
                  if ($PluginItem->can(-1,'w',$input)) {
                     $newid = $PluginItem->add($input);
                  }
               }
            }
         }
         break;

      case "plugin_appliances_desinstall" :
         if (in_array($data['itemtype'],PluginAppliancesAppliance::getTypes())) {
            foreach ($data["item"] as $key => $val) {
               if ($val == 1) {
                  $query = "DELETE
                            FROM `glpi_plugin_appliances_appliances_items`
                            WHERE `itemtype` = '".$data['itemtype']."'
                                  AND `items_id` = '".$data['item_item']."'
                                  AND `plugin_appliances_appliances_id` = '".$key."'";
                  $DB->query($query);
               }
            }
         }
         break;

      case "plugin_appliances_transfert" :
         if ($data['itemtype'] == 'PluginAppliancesAppliance') {
            foreach ($data["item"] as $key => $val) {
               if ($val == 1) {
                  $appliance = new PluginAppliancesAppliance;
                  $appliance->getFromDB($key);

                  $type = PluginAppliancesApplianceType::transfer($appliance->fields["plugin_appliances_appliancetypes_id"],
                                                                  $data['entities_id']);
                  $values["id"]                                  = $key;
                  $values["plugin_appliances_appliancetypes_id"] = $type;
                  $values["entities_id"]                         = $data['entities_id'];
                  $appliance->update($values);
               }
            }
         }
         break;
   }
}
*/

function plugin_datainjection_populate_appliances() {
   global $INJECTABLE_TYPES;

   $INJECTABLE_TYPES['PluginAppliancesApplianceInjection'] = 'appliances';
}

/*

function plugin_appliances_addSelect($type,$id,$num) {

   $searchopt = &Search::getOptions($type);
   $table = $searchopt[$id]["table"];
   $field = $searchopt[$id]["field"];
//echo "add select : ".$table.".".$field."<br/>";
   switch ($type) {

      case 'Ticket':

         if ($table.".".$field == "glpi_plugin_appliances_appliances.name") {
            return " GROUP_CONCAT(DISTINCT `glpi_plugin_appliances_appliances`.`id` SEPARATOR '$$$$') AS ITEM_$num, "
                    . " GROUP_CONCAT(DISTINCT `glpi_plugin_appliances_appliances_bis`.`id` SEPARATOR '$$$$') AS ITEM_".$num."_2,";
         }
         break;
   }
}



function plugin_appliances_addLeftJoin($itemtype,$ref_table,$new_table,$linkfield,&$already_link_tables) {

   switch ($itemtype) {

      case 'Ticket':
         return " LEFT JOIN `glpi_plugin_appliances_appliances` AS glpi_plugin_appliances_appliances
            ON (`glpi_items_tickets`.`items_id` = `glpi_plugin_appliances_appliances`.`id`
                  AND `glpi_items_tickets`.`itemtype`='PluginAppliancesAppliance')

         LEFT JOIN `glpi_plugin_appliances_appliances_items`
            ON (`glpi_items_tickets`.`items_id` = `glpi_plugin_appliances_appliances_items`.`id`
                  AND `glpi_items_tickets`.`itemtype`='PluginAppliancesAppliance_Item')
         LEFT JOIN `glpi_plugin_appliances_appliances` AS glpi_plugin_appliances_appliances_bis
            ON (`glpi_plugin_appliances_appliances_items`.`plugin_appliances_appliances_id` = `glpi_plugin_appliances_appliances_bis`.`id`)";
         break;

   }
   return "";
}



function plugin_appliances_addWhere($link,$nott,$type,$id,$val,$searchtype) {

   $searchopt = &Search::getOptions($type);
   $table = $searchopt[$id]["table"];
   $field = $searchopt[$id]["field"];

   switch ($type) {

      case 'Ticket':
         if ($table.".".$field == "glpi_plugin_appliances_appliances.name") {
            $out = '';
            switch ($searchtype) {
               case "contains" :
                  $SEARCH = Search::makeTextSearch($val, $nott);
                  break;

               case "equals" :
                  if ($nott) {
                     $SEARCH = " <> '$val'";
                  } else {
                     $SEARCH = " = '$val'";
                  }
                  break;

               case "notequals" :
                  if ($nott) {
                     $SEARCH = " = '$val'";
                  } else {
                     $SEARCH = " <> '$val'";
                  }
                  break;

            }
            if (in_array($searchtype, array('equals', 'notequals'))) {
               if ($table != getTableForItemType($type) || $type == 'States') {
                  $out = " $link (`glpi_plugin_appliances_appliances`.`id`".$SEARCH;
               } else {
                  $out = " $link (`glpi_plugin_appliances_appliances`.`$field`".$SEARCH;
               }
               if ($searchtype=='notequals') {
                  $nott = !$nott;
               }
               // Add NULL if $val = 0 and not negative search
               // Or negative search on real value
               if ((!$nott && $val==0) || ($nott && $val != 0)) {
                  $out .= " OR `glpi_plugin_appliances_appliances`.`id` IS NULL";
               }
//               $out .= ')';
               $out1 = $out;
               $out = str_replace(" ".$link." (", " ".$link." ", $out);
            } else {
               $out = Search::makeTextCriteria("`glpi_plugin_appliances_appliances`.".$field,$val,$nott,$link);
               $out1 = $out;
               $out = preg_replace("/^ $link/", $link.' (', $out);
            }
            $out2 = $out." OR ";
            $out2 .= str_replace("`glpi_plugin_appliances_appliances`",
                                 "`glpi_plugin_appliances_appliances_bis`", $out1)." ";
            $out2 = str_replace("OR   AND", "OR", $out2);
            $out2 = str_replace("OR   OR", "OR", $out2);
            $out2 = str_replace("AND   OR", "OR", $out2);
            $out2 = str_replace("OR  AND", "OR", $out2);
            $out2 = str_replace("OR  OR", "OR", $out2);
            $out2 = str_replace("AND  OR", "OR", $out2);
            return $out2.")";
         }
         break;
   }
}*/

?>