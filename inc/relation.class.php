<?php
/*
 * @version $Id: relation.class.php 301 2020-10-15 16:21:43Z yllen $
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
 @copyright Copyright (c) 2009-2021 Appliances plugin team
 @license   AGPL License 3.0 or (at your option) any later version
            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 @link      https://forge.glpi-project.org/projects/appliances
 @since     version 2.0
 --------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * This is a special relation between
 *    a glpi_application_items
 *    a dropdwn (glpi_locations, glpi_domains, glpi_networks)
**/
class PluginAppliancesRelation extends CommonDBTM {


   static function getTypeTable($value) {

      switch ($value) {
         case 1 : // Location
            $name = "glpi_locations";
            break;

         case 2 : // Réseau
            $name = "glpi_networks";
            break;

         case 3 : // Domain
            $name = "glpi_domains";
            break;

         default:
            $name ="";
      }
      return $name;
   }


   static function getItemType($value) {

      switch ($value) {
         case 1 : // Location
            $name = "Location";
            break;

         case 2 : // Réseau
            $name = "Network";
            break;

         case 3 : // Domain
            $name = "Domain";
            break;

         default:
            $name ="";
      }
      return $name;
   }


   static function getTypeName($value=0) {

      switch ($value) {
         case 1 : // Location
            $name = __('Location');
            break;

         case 2 : // Réseau
            $name = _n('Network', 'Networks', 1);
            break;

         case 3 : // Domain
            $name = _n('Domain', 'Domains', 1);
            break;

         default :
            $name = "&nbsp;";
      }
      return $name;
   }


   /**
    * Show the relation for a device/applicatif
    *
    * Called from PluginAppliancesAppliance->showItem and PluginAppliancesAppliance::showAssociated
    *
    * @param $relationtype               type of the relation
    * @param $relID                      ID of the relation
    * @param $entity                     ID of the entity of the device
    * @param $canedit                    if user is allowed to edit the relation
    *    - canedit the device if called from the device form
    *    - must be false if called from the applicatif form
    *
   **/
   static function showList($relationtype, $relID, $entity, $canedit) {
      global $DB, $CFG_GLPI;

      if (!$relationtype) {
         return false;
      }

      $dbu = new DbUtils();

      // selects all the attached relations
      $itemtype = PluginAppliancesRelation::getItemType($relationtype);
      $title    = PluginAppliancesRelation::getTypeName($relationtype);

      $field    = 'name AS dispname';
      if ($itemtype == 'Location') {
         $field = 'completename AS dispname';
      }

      $sql_loc = ['SELECT'    => ['glpi_appliancerelations.id', $field],
                  'FROM'      => $dbu->getTableForItemType($itemtype),
                  'LEFT JOIN' => ['glpi_appliancerelations'
                                   => ['FKEY' => [$dbu->getTableForItemType($itemtype) => 'id',
                                                  'glpi_appliancerelations'   => 'relations_id']],
                                  'glpi_appliances_items'
                                   => ['FKEY' => ['glpi_appliancerelations'  => 'appliances_items_id',
                                                  'glpi_appliances_items'    => 'id']]],
                  'WHERE'     => ['glpi_appliances_items.id' => $relID]];

      $result_loc = $DB->request($sql_loc);
      $number_loc = count($result_loc);

      if ($canedit) {
         echo "<form method='post' name='relation' action='".
               $CFG_GLPI["root_doc"]."/plugins/appliances/front/optvalue.form.php'>";
         echo "<br><input type='hidden' name='deviceID' value='".$relID."'>";

         $i    = 0;
         $used = [];

         if ($number_loc >0) {
            echo "<table>";
            while ($i < $number_loc) {
               $res = $result_loc->next();
               echo "<tr><td class=top>";
               // when the value of the checkbox is changed, the corresponding hidden variable value
               // is also changed by javascript
               echo "<input type='checkbox' name='itemrelation[".$res["id"]. "]' value='1'></td><td>";
               echo $res["dispname"];
               echo "</td></tr>";
               $i++;
            }
            echo "</table>";
            echo "<input type='submit' name='dellieu' value='"._sx('button', 'Delete permanently')."'
                   class='submit'>".
                  "<br><br>";
         }

         echo "$title&nbsp;:&nbsp;";

         Dropdown::show($itemtype, ['name'   => "tablekey[" . $relID . "]",
                                    'entity' => $entity,
                                    'used'   => $used]);
         echo "&nbsp;&nbsp;&nbsp;<input type='submit' name='addlieu' value=\""._sx('button', 'Add').
               "\" class='submit'><br>&nbsp;";
         Html::closeForm();

      } else if ($number_loc > 0) {
         while ($res = $result_loc->next()) {
            echo $res["dispname"]."<br>";
         }
      } else {
         echo "&nbsp;";
      }
   }


   /**
    * Show for PDF the relation for a device/applicatif
    *
    * @param $pdf                object for the output
    * @param $relationtype       type of the relation
    * @param $relID              ID of the relation
    *
   **/
   static function showList_PDF($pdf, $relationtype, $relID) {
      global $DB;

      if (!$relationtype) {
         return false;
      }

      // selects all the attached relations
      $tablename = PluginAppliancesRelation::getTypeTable($relationtype);
      $title     = PluginAppliancesRelation::getTypeName($relationtype);

      $field    = 'name AS dispname';
      if ($tablename == 'Location') {
         $field = 'completename AS dispname';
      }

      $sql_loc = ['SELECT'    => ['glpi_plugin_appliances_relations.id', $field],
                  'FROM'      => $tablename,
                  'LEFT JOIN' => ['glpi_plugin_appliances_relations'
                                   => ['FKEY' => [$tablename                         => 'id',
                                                  'glpi_plugin_appliances_relations' => 'relations_id']],
                                   'glpi_plugin_appliances_appliances_items'
                                   => ['FKEY' => ['glpi_plugin_appliances_relations'        => 'plugin_appliances_appliances_items_id',
                                                  'glpi_plugin_appliances_appliances_items' => 'id']]],
                 'WHERE'     => ['glpi_plugin_appliances_appliances_items.id' => $relID]];

      $result_loc = $DB->request($sql_loc);

      $opts = [];
      while ($res = $result_loc->next()) {
         $opts[] = $res["dispname"];
      }
      $pdf->setColumnsSize(100);
      $pdf->displayLine(sprintf(__('%1$s: %2$s'),
                                "<b><i>".__('Item to link', 'appliances')."&nbsp;$title </i> </b>",
                                implode(', ',$opts)));
   }

}
