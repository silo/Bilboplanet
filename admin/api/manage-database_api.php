<?php
/******* BEGIN LICENSE BLOCK *****
* BilboPlanet - Un agrégateur de Flux RSS Open Source en PHP.
* BilboPlanet - An Open Source RSS feed aggregator written in PHP
* Copyright (C) 2009 By French Dev Team : Dev BilboPlanet
* Contact : dev@bilboplanet.com
* Website : www.bilboplanet.com
* Tracker : redmine.bilboplanet.com
* Blog : blog.bilboplanet.com
* 
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU Affero General Public License as
* published by the Free Software Foundation, either version 3 of the
* License, or (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU Affero General Public License for more details.
*
* You should have received a copy of the GNU Affero General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
***** END LICENSE BLOCK *****/
?>
<?php 
if(isset($_POST['action'])) {
	switch (trim($_POST['action'])){

##########################################################
# EXPORT DATABASE
##########################################################
	case 'export':
		$JSON_array = array();
		if (isset($_POST['list'])) {
			$tables = $_POST['list'];
			foreach ($tables as $table) {
				# Creation de la ligne de titres
				$header = array();
				$result = mysql_query("SHOW COLUMNS FROM ".$core->prefix.$table."");
				$i = 0;
				if (mysql_num_rows($result) > 0) {
					while ($row = mysql_fetch_assoc($result)) {
						$header[] = $row['Field'];
						$i++;
					}
				}
				$JSON_array[$table]['head'] = $header;
				$JSON_array[$table]['name'] = $table;

				# Creation du contenu
				$values = mysql_query("SELECT * FROM ".$core->prefix.$table."");
				while ($rowr = mysql_fetch_row($values)) {
					$line = array();
					for ($j=1;$j<$i;$j++) {
						$line[] = $rowr[$j];
					}
					$JSON_array[$table]['content'][$rowr[0]] = $line;
				}
			}

			$content = json_encode($JSON_array);
			$compressed = gzencode($content, 9);

			# On recupere la date du jour, l'heure et la minute
			$date = date("Y-m-d_H-i",time());
			$snapshot_path = dirname(__FILE__)."/../cache";
			$filename = 'planet-export.'.$date.'.json.gz';
			$snapshot_file = $snapshot_path.'/'.$filename;
			unlink($snapshot_file);
			$fp = @fopen($snapshot_file,'wb');
			if ($fp === false) {
				throw new Exception(sprintf(T_('Unable to write %s file.'),$snapshot_file));
			}
			fwrite($fp,$compressed);
			fclose($fp);
			chmod($snapshot_file, 0777);

			$output = '<div class="flash notice">'.T_("A snapshot of your current database was successfully created. Please download the following file and put it in some safe place.").'</div>';
			$output .= '<p class="file"><a href="cache/'.$filename.'" target="_blank" type="octet-stream">'.$filename.'</a></p>';
		}
		else {
			$output = '<div class="flash error">'.T_("Please select at least one table to export").'</div>';
		}
		print $output;
		break;

##########################################################
# DEFAULT RETURN
##########################################################
	default:
		print '<div class="flash error">'.T_('User bad call').'</div>';
		break;
	}
} else {
	print 'forbidden';
}
?>
