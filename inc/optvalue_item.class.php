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
 @copyright Copyright (c) 2009-2019 Appliances plugin team
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
 * Class PluginAppliancesOptvalue_Item
**/
class PluginAppliancesOptvalue_Item extends CommonDBTM {


   /**
    * Show the optional values for a item / applicatif
    *
    * @param $itemtype                type of the item
    * @param $items_id                ID of the item
    * @param $appliances_id           ID of the applicatif
    * @param $canedit                 if user is allowed to edit the values
    *    - canedit the device if called from the device form
    *    - must be false if called from the applicatif form
   **/
   static function showList ($itemtype, $items_id, $appliances_id, $canedit) {
      global $DB, $CFG_GLPI;

      $result_app_opt = $DB->request(['FROM'  => 'glpi_plugin_appliances_optvalues',
                                      'WHERE' => ['plugin_appliances_appliances_id' => $appliances_id],
                                      'ORDER' => 'vvalues']);
      $number  = count($result_app_opt);

      if ($canedit) {
         echo "<form method='post' action='".$CFG_GLPI["root_doc"].
               "/plugins/appliances/front/appliance.form.php'>";
         echo "<input type='hidden' name='number_champs' value='".$number."'>";
      }
      echo "<table>";

      for ($i=1 ; $i<=$number ; $i++) {
         if ($data_opt = $result_app_opt->next()) {
            $query_val = $DB->request(['SELECT' => 'vvalue',
                                       'FROM'   => 'glpi_plugin_appliances_optvalues_items',
                                       'WHERE'  => ['plugin_appliances_optvalues_id' => $data_opt["id"],
                                                    'items_id'                       => $items_id]]);
            $data_val = $query_val->next();
            $vvalue     = ($data_val? $data_val['vvalue'] : "");
            if (empty($vvalue) && !empty($data_opt['ddefault'])) {
               $vvalue = $data_opt['ddefault'];
            }

            echo "<tr><td>".$data_opt['champ']."&nbsp;</td><td>";
            if ($canedit) {
               echo "<input type='hidden' name='opt_id$i' value='".$data_opt["id"]."'>";
               echo "<input type='hidden' name='ddefault$i' value='".$data_opt["ddefault"]."'>";
               echo "<input type='text' name='vvalue$i' value='".$vvalue."'>";
            } else {
               echo $vvalue;
            }
            echo "</td></tr>";

         }
         echo "<input type='hidden' name='opt_id$i' value='-1'>";
      } // For

      echo "</table>";

      if ($canedit) {
         echo "<input type='hidden' name='itemtype' value='".$itemtype."'>";
         echo "<input type='hidden' name='items_id' value='".$items_id."'>";
         echo "<input type='hidden' name='plugin_appliances_appliances_id' value='".$appliances_id."'>";
         echo "<input type='hidden' name='number_champs' value='".$number."'>";
         echo "<input type='submit' name='add_opt_val' value='"._sx('button', 'Update')."'
                class='submit'>";
         Html::closeForm();
      }
   }


   /**
    * Show for PDF the optional value for a device / applicatif
    *
    * @param $pdf            object for the output
    * @param $ID             of the relation
    * @param $appliancesID   ID of the applicatif
   **/
   static function showList_PDF($pdf, $ID, $appliancesID) {
      global $DB;

      $result_app_opt = $DB->request(['FIELDS' => ['id', 'champ', 'ddefault', 'vvalues'],
                                      'FROM'   => 'glpi_plugin_appliances_optvalues',
                                      'WHERE'  => ['plugin_appliances_appliances_id' => $appliancesID],
                                      'ORDER'  => 'vvalues']);
      $number_champs = count($result_app_opt);

      if (!$number_champs) {
         return;
      }

      $opts = [];
      for ($i=1 ; $i<=$number_champs ; $i++) {
         if ($data_opt = $result_app_opt->next()) {
            $query_val = $DB->request(['SELECT' => 'vvalue',
                                       'FROM'   => 'glpi_plugin_appliances_optvalues_items',
                                       'WHERE'  => ['plugin_appliances_optvalues_id' => $data_opt["id"],
                                                    'items_id' => $ID]]);
            $data_val = $query_val->next();
            $vvalue = ($data_val ? $data_val['vvalue'] : "");
            if (empty($vvalue) && !empty($data_opt['ddefault'])) {
               $vvalue = $data_opt['ddefault'];
            }
            $opts[] = $data_opt['champ'].($vvalue?"=".$vvalue:'');
         }
      } // For

      $pdf->setColumnsSize(100);
      $pdf->displayLine(sprintf(__('%1$s: %2$s'), "<b><i>".__('User fields', 'appliances')."</i></b>",
                                implode(', ',$opts)));
   }


   /**
    * Update to optional values for an appliance / item
    *
    * @param $input array on input value (form)
   **/
   function updateList($input) {

      $number_champs = $input["number_champs"];
      for ($i=1 ; $i<=$number_champs ; $i++) {
         $opt_id   = "opt_id$i";
         $vvalue   = "vvalue$i";
         $ddefault = "ddefault$i";

         $query_app = $DB->request(['SELECT' => 'id',
                                    'FROM'   => 'glpi_plugin_appliances_optvalues_items',
                                    'WHERE'  => ['plugin_appliances_optvalues_id' => $input[$opt_id],
                                                 'itemtype'                       => $input['itemtype'],
                                                 'items_id'                       => $input['items_id']]]);

         if ($data = $query_app->next()) {
            // l'entrée existe déjà, il faut faire un update ou un delete
            if (empty($input[$vvalue])
                || ($input[$vvalue] == $input[$ddefault])) {
               $this->delete($data);
            } else {
               $data['vvalue'] = $input[$vvalue];
               $this->update($data);
            }

         } else if (!empty($input[$vvalue])
                    && ($input[$vvalue] != $input[$ddefault])) {
            // l'entrée n'existe pas
            // et la valeur saisie est non nulle -> on fait un insert
            $data = ['plugin_appliances_optvalues_id' => $input[$opt_id],
                     'itemtype'                       => $input['itemtype'],
                     'items_id'                       => $input['items_id'],
                     'vvalue'                         => $input[$vvalue]];
            $this->add($data);
         }
      } // For
   }

}
