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


class PluginAppliancesOptvalue_Item extends CommonDBTM {

   // From CommonDBTM
   public $table = 'glpi_plugin_appliances_optvalues_items';
   public $type  = PLUGIN_APPLIANCES_OPTVALUES_ITEMS;

   function cleanDBonPurge($ID) {
      // TO DO
   }

   /**
    * Show the optional values for a item / applicatif
    *
    * @param $itemtype type of the item
    * @param $items_id ID of the item
    * @param $appliances_id, ID of the applicatif
    * @param $canedit, if user is allowed to edit the values
    *    - canedit the device if called from the device form
    *    - must be false if called from the applicatif form
    */
   static function showList ($itemtype, $items_id, $appliances_id, $canedit) {
      global $DB, $CFG_GLPI, $LANG;

      $query_app_opt = "SELECT *
                        FROM `glpi_plugin_appliances_optvalues`
                        WHERE `appliances_id` = '$appliances_id'
                        ORDER BY `vvalues`";

      $result_app_opt = $DB->query($query_app_opt);
      $number_champs = $DB->numrows($result_app_opt);

      if ($canedit)  {
         echo "<form method='post' action='".$CFG_GLPI["root_doc"].
               "/plugins/appliances/front/appliance.form.php'>";
         echo "<input type='hidden' name='number_champs' value='$number_champs'>";
      }
      echo "<table>";

      for ($i=1 ; $i<=$number_champs ; $i++) {
         if ($data_opt = $DB->fetch_array($result_app_opt)) {
            $query_val = "SELECT `vvalue`
                          FROM `glpi_plugin_appliances_optvalues_items`
                          WHERE `optvalues_id` = '".$data_opt["id"]."'
                                AND `items_id` = '$items_id'";

            $result_val = $DB->query($query_val);
            $data_val = $DB->fetch_array($result_val);
            $vvalue = ($data_val ? $data_val['vvalue'] : "");
            if (empty($vvalue) && !empty($data_opt['ddefault'])) {
               $vvalue = $data_opt['ddefault'];
            }

            echo "<tr><td>".$data_opt['champ']."&nbsp;: </td><td>";
            if ($canedit) {
               echo "<input type='hidden' name='opt_id$i' value='".$data_opt["id"]."'>";
               echo "<input type='hidden' name='ddefault$i' value='".$data_opt["ddefault"]."'>";
               echo "<input type='text' name='vvalue$i' value='".$vvalue."'>";
            } else {
               echo $vvalue;
            }
            echo "</td></tr>";
         } else {
            echo "<input type='hidden' name='opt_id$i' value='-1'>";
         }
      } // For

      echo "</table>";

      if ($canedit) {
         echo "<input type='hidden' name='itemtype' value='$itemtype'>";
         echo "<input type='hidden' name='items_id' value='$items_id'>";
         echo "<input type='hidden' name='appliances_id' value='$appliances_id'>";
         echo "<input type='hidden' name='number_champs' value='$number_champs'>";
         echo "<input type='submit' name='add_opt_val' value='".$LANG['buttons'][7]."' class='submit'>";
         echo "</form>";
      }
   }

   function updateList($input) {
      global $DB;

      $number_champs = $input["number_champs"];
      for ($i=1 ; $i<=$number_champs ; $i++) {
         $opt_id = "opt_id$i";
         $vvalue = "vvalue$i";
         $ddefault = "ddefault$i";

         $query_app = "SELECT `id`
                       FROM `glpi_plugin_appliances_optvalues_items`
                       WHERE `optvalues_id` = '".$input[$opt_id]."'
                             AND `itemtype` = '".$input['itemtype']."'
                             AND `items_id` = '".$input['items_id']."'";
         $result_app = $DB->query($query_app);

         if ($data = $DB->fetch_array($result_app)) {
            // l'entrée existe déjà, il faut faire un update ou un delete
            if (empty($input[$vvalue])
                || $input[$vvalue] == $input[$ddefault]) {
               $this->delete($data);
            } else {
               $data['vvalue'] = $input[$vvalue];
               $this->update($data);
            }
         } else if (!empty($input[$vvalue])
                    && $input[$vvalue] != $input[$ddefault]) {
            // l'entrée n'existe pas
            // et la valeur saisie est non nulle -> on fait un insert
            $data = array('optvalues_id' => $input[$opt_id],
                          'itemtype'     => $input['itemtype'],
                          'items_id'     => $input['items_id'],
                          'vvalue'       => $input[$vvalue]);
            $this->add($data);
         }
      } // For
   }

}

?>