<?php
/******* BEGIN LICENSE BLOCK *****
* BilboPlanet - Un agr�gateur de Flux RSS Open Source en PHP.
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
require_once(dirname(__FILE__).'/lib/checkValidHTML.php');

function finished() {
	$log_file = fopen('../logs/cron_job.log', 'a');
	logMsg("The Cron is stopped and exited", $log_file);
}

function update($core, $print=false) {
	global $log, $blog_settings;
	$cron_file = dirname(__FILE__).'/cron_running.txt';
	$output = "";

	# Inclusion des fichiers necessaires
	require_once(dirname(__FILE__).'/lib/simplepie/simplepie.inc');

	# Duree de mise a jour
	$debut = explode(" ",microtime());
	$debut = $debut[1]+$debut[0]; 

	# Requete permettant de recuperer la liste des flux a parser
	$sql = "SELECT
			".$core->prefix."feed.user_id as user_id,
			feed_id,
			feed_url,
			site_url,
			feed_trust,
			feed_checked
		FROM ".$core->prefix."feed, ".$core->prefix."site, ".$core->prefix."user
		WHERE
			".$core->prefix."feed.site_id = ".$core->prefix."site.site_id
			AND ".$core->prefix."feed.user_id = ".$core->prefix."user.user_id
			AND user_status = 1
			AND site_status = 1
			AND feed_status = 1
		ORDER BY feed_checked ASC
		LIMIT 30";
	$rs = $core->con->select($sql);
	# Ouverture du fichier de log
	$file = fopen('../logs/update-'.date("Y-m-d").'.log', 'a');

	# Affichage des logs dans la partie admin
	$output .= "<fieldset><legend>Log File</legend>
		<div class='message'><p>Manual Update Log</p></div>";

	# On parcour l'ensemble des flux 
	$cpt = 0;
	while ($rs->fetch()) {
		# On verifie si on n'a pas demand� l'arr�t de l'algo
		if (file_exists(dirname(__FILE__).'/STOP')) {
			$log_msg = logMsg("STOP file detected, trying to shut down cron job", $file, 2, $print);
			if ($print) $output .= $log_msg;
			break;
		}

		# Si on est en mode debug
		if($log == "debug") {
			$log_msg = logMsg("Analyse du flux ".$rs->feed_url, $file, 4, $print);
			if ($print) $output .= $log_msg;
		}

		# On cree un objet SimplePie et on ajuste les parametres de base
		$feed = new SimplePie();
		$feed->set_feed_url($rs->feed_url);
		$feed->set_cache_location(dirname(__FILE__).'/../admin/cache');
		$feed->set_cache_duration($item_refresh);
		$feed->init();

		# Pour faire fonctionner les lecteurs flash, non recomande par simplepie
		$feed->strip_htmltags(false);

		# Si le flux ne contient pas  de donnee
		$item_nb = $feed->get_item_quantity();
		if ($feed->get_item_quantity() == 0) {

			# Affichage du message d'erreur
			$error = $feed->error();
			if (ereg($rs->feed_url, $error)) {
				$log_msg = logMsg("Aucun article trouve ".$error, $file, 3, $print);
				if ($print) $output .= $log_msg;
			} else {
				$log_msg = logMsg("Aucun article trouve sur $rs->feed_url: ".$error, $file, 3, $print);
				if ($print) $output .= $log_msg;
			}

		} else {

			# On traite chaque item du flux
			$items = $feed->get_items();
			$item_permalink = '';

			foreach ($items as $item) {
				# open log file and write activity down
				$fp = @fopen($cron_file,'wb');
				if ($fp === false) {
					throw new Exception(sprintf(__('Cannot write %s file.'),$cron_file));
				}
				fwrite($fp,time());
				fclose($fp);

				# Analyse the item
				#####################

				# Permalink
				$item_permalink = $item->get_permalink();
				# Content
				$item_content = strip_script($item->get_content());
				if (empty($item_content)) {
					$item_content = $item->get_description();
				}
				$item_content = traitementEncodage($item_content);
				# Title
				$item_title = traitementEncodage($item->get_title());
				if(strlen($item_title) > 254) {
					$item_title = substr($item_title, 0, 254);
				}
				# Date
				$item_date = date('Y-m-d H:i:s',$item->get_date('U'));

				if (empty($item_content)) {
					$log_msg = logMsg("Pas de contenu sur $rs->feed_url", $file, 3, $print);
					if ($print) $output .= $log_msg;
				} elseif(empty($item_permalink)) {
					$log_msg = logMsg("Erreur de decoupage du lien ".$item_permalink, $file, 3, $print);
					if ($print) $output .= $log_msg;

					# Si on est en mode debug
					if($log == "debug") {
						$log_msg = logMsg("Url du site: ".$rs->site_url, $file, 4, $print);
						if ($print) $output .= $log_msg;
						$log_msg = logMsg("Url du permalink: ".$item_permalink, $file, 4, $print);
						if ($print) $output .= $log_msg;
					}
				} else {
					# Check if item is already in the database
					$sql = "SELECT
							post_title,
							post_content,
							post_pubdate
						FROM ".$core->prefix."post
						WHERE `post_permalink` = '".addslashes($item_permalink)."'";
					$rs2 = $core->con->select($sql);

					# There is no such permalink, we can insert the new item
					if($rs2->count() == 0 && $item->get_date('U') < time()) {
						# Get ID
						$rs3 = $core->con->select(
							'SELECT MAX(post_id) '.
							'FROM '.$core->prefix.'post ' 
							);
						$next_post_id = (integer) $rs3->f(0) + 1;

						$cur = $core->con->openCursor($core->prefix.'post');
						$cur->post_id = $next_post_id;
						$cur->user_id = $rs->user_id;
						$cur->feed_id = $rs->feed_id;
						$cur->post_pubdate = $item_date;
						$cur->post_permalink = addslashes($item_permalink);
						$cur->post_title = $item_title;
						$cur->post_content = $item_content;
						$cur->post_status = $rs->feed_trust == 1 ? 1 : 2;
						$cur->created = array(' NOW() ');
						$cur->modified = array(' NOW() ');
						$cur->insert();

						$log_msg = logMsg("Article ajoute: ".$item_permalink, $file, 1, $print);
						if ($print) $output .= $log_msg;
						$cpt++;
					} # fin if(!found)

					# If post is already in database, check if update needed
					elseif($rs->count() == 1) {
						$title2 = addslashes($rs2->f('post_title'));
						$content2 = addslashes($rs2->f('post_content'));

						# Si l'article a ete modifie (soit la date, soit le titre, soit le contenu)
						if($item_date != $rs2->f('post_pubdate') && !empty($item_date)) {
							# On log si il y a eu des modifications trouvees
							$log_msg = logMsg("changement de date pour l'article: ".$item_permalink, $file, 2, $print);
							if ($print) $output .= $log_msg;

							# Update post in database
							$cur = $core->con->openCursor($core->prefix.'post');
							$cur->post_pubdate = $item_date;
							$cur->modified = array('NOW()');
							$cur->update("WHERE ".$core->prefix."post.post_permalink = '".addslashes($item_permalink)."'");
							# On informe que tout est ok
							$log_msg = logMsg("Date mise a jour: ".$item_permalink, $file, 1, $print);
							if ($print) $output .= $log_msg;
						}
						if((!empty($item_title) && strcmp($item_title, $title2) != 0)
							|| (!empty($item_content) && strcmp($item_content, $content2) != 0)) {
							if(strcmp($item_title, $title2) != 0) {
								$log_msg = logMsg("Changement de titre pour l'article: ".$item_permalink, $file, 2, $print);
								if ($print) $output .= $log_msg;

								# Update post in database
								$cur = $core->con->openCursor($core->prefix.'post');
								$cur->post_title = $item_title;
								$cur->modified = array('NOW()');
								$cur->update("WHERE ".$core->prefix."post.post_permalink = '".addslashes($item_permalink)."'");
							}
							if(strcmp($item_content, $content2) != 0) {
								$log_msg = logMsg("Changement du contenu pour l'article: ".$item_permalink, $file, 2, $print);
								if ($print) $output .= $log_msg;

								# Update post in database
								$cur = $core->con->openCursor($core->prefix.'post');
								$cur->post_content = $item_content;
								$cur->modified = array('NOW()');
								$cur->update("WHERE ".$core->prefix."post.post_permalink = '".addslashes($item_permalink)."'");
							}
						} # fin du if($date !=
						$cpt++;
					}
				} # fin du $item->get_content()
			} # fin du foreach
			# On fait un reset du foreach
			reset($items);

			# Le flux a ete mis a jour, on le marque a la derniere date
			$cur = $core->con->openCursor($core->prefix.'feed');
			$cur->feed_checked = array('NOW()');
			$cur->update("WHERE feed_id = '$rs->feed_id'");
		} # fin $feed->error()
		$feed->__destruct();
		# Destruction de l'objet feed avant de passer a un autre
		unset($feed);

		if ($blog_settings->get('auto_feed_disabling')) {
			$toolong = time() - 86400*5; # five days ago
			if (mysqldatetime_to_timestamp($rs->feed_checked) < $toolong) {
				# if feed was in error for too long, let's disable it
				$cur = $core->con->openCursor($core->prefix.'feed');
				$cur->feed_status = 2;
				$cur->update("WHERE feed_id = '$rs->feed_id'");
			}
		}

	} # fin du while

	# Duree de la mise a jour
	$fin = explode(" ",microtime());
	$fin = $fin[1]+$fin[0];
	$temps_passe = round($fin-$debut,2);

	# Message indiquant la fin de la mise a jour
	$log_msg = logMsg("$cpt articles mis a jour en $temps_passe secondes", $file, 2, $print);
	if ($print) $output .= $log_msg;

	# Fermeture du fichier de log
	fclose($file); 

	# On detruit les fichiers de cache des pages web pour les actualiser
	$cache_dir = dirname(__FILE__).'/../admin/cache';
	$dir_handle = @opendir($cache_dir) or die("Unable to open $cache_dir");
	while ($file = readdir($dir_handle)){
		if($file!="." && $file!=".." && $file!=".svn" && $file!=".DS_Store" && $file!=".htaccess"){
			unlink($cache_dir.'/'.$file);
		}
	}
	closedir($dir_handle);
	
//	exec('cd '.dirname(__FILE__).'/../admin/cache && rm -f *.cache');

	# On met a jour la date d'update
	updateDateMaj();
	
	$output .= "</fieldset>";
	return $output;
}

# Procedure qui log un message a l'ecran et dans un fichier de log
# types:
# type = 0 : ''
# type = 1 : SUCCESS
# type = 2 : INFO
# type = 3 : ERROR
# type = 4 : DEBUG
function logMsg($message, $fichier, $type=0, $print=false) {
	# On recupere la date
	$print_style = '';
	$date_log = '['.date("Y-m-d").' '.date("H:i:s").'] ';
	switch($type){
		case 1:
			$message_type='SUCCESS : ';
			$print_style = "[<font color=\"green\">SUCCESS</font>] ";
			break;
		case 2:
			$message_type='INFO    : ';
			$print_style = "[<font color=\"blue\">INFO</font>] ";
			break;
		case 3:
			$message_type='ERROR   : ';
			$print_style = "[<font color=\"red\">ERROR</font>] ";
			break;
		case 4:
			$message_type='DEBUG   : ';
			$print_style = "[<font color=\"pink\">DEBUG</font>] ";
			break;
		default:
			break;
	}
	# On log dans le fichier
	fwrite($fichier, $date_log.$message_type.$message."\n");
	# On log a l'ecran
	if ($print)
		return $print_style.$message."<br/>";
	else
		return $date_log.$message_type.$message."<br/>";
}


# Fonction qui effefctue un post traitement d'un article afin de l'enregistrer
# en base de donnees correctement
function traitementEncodage($chaine) {

	# On detecte l'encodage de la chaine
	$encodage = mb_detect_encoding($chaine);
	if($encodage == "ASCII") $encodage = "iso-8859-1"; # htmlentities ne connais pas l'ascii

	# On convertie tous les caracteres speciaux en code html
	$chaine = htmlentities($chaine,ENT_QUOTES,$encodage);
	$chaine = addslashes($chaine);

	# Fix le bug des articles relatif
	if(strpos($chaine, "!-- Generated by Simple Tags") > 0) {
		$chaine = substr($chaine, 0, strpos($chaine, "!-- Generated by Simple Tags")-8);
	}

	# On retourne le resultat
	return $chaine;
}

#-------------------------------------#
#   Fonctions pour les mises a jour   #
#-------------------------------------#

# Fonction qui met a jour la date a laquelle le planet a subit un update
function updateDateMaj() {

	# Nom du fichier
	$fichier = dirname(__FILE__).'/update.txt';

	# On recupere la date au format timestamp
	$date = time();
	$date_log = '['.date("Y-m-d").' '.date("H:i:s").'] ';

	# Ouverture du fichier en ecriture/creation
	$fp = @fopen($fichier,'wb');
	if ($fp === false) {
		throw new Exception(sprintf(__('Cannot write %s file.'),$fichier));
	}
	fwrite($fp,$date."\nLast update time : ".$date_log);
	fclose($fp);
}

# Fonction qui recuepere la date de mise a jour du planet
function getDateMaj() {

	# Nom du fichier
	$fichier = dirname(__FILE__).'/update.txt';

	# On test si le fichier est present
	if (file_exists($fichier)) {

		# Ouverture du fichier en lecture
		$file = fopen($fichier, "r");

		# Ecriture du timestamp
		$date = trim(fgets($file, 255));

		# Fermeture du fichier
		fclose($file);

	} else {

		# On informe que le fichier est introuvable
		echo "Error: file not found";
		echo "Creation d'un nouveau fichier";
		# On recupere la date au format timestamp
		$date = time();
		# Ouverture du fichier en ecriture/creation
		$fp = @fopen($fichier,'wb');
		if ($fp === false) {
			throw new Exception(sprintf(__('Cannot write %s file.'),$fichier));
		}
		fwrite($fp,$date);
		fclose($fp);
	}

	# On retourne la date de maj au bon format
	return date("d-m-Y", $date).'&nbsp;&agrave;&nbsp;'.date("H:i", $date);
}

# Fonction qui transforme des urls d'image relative en absolue d'un item
function completeUrl($item, $site) {

	# Tansfromation
	$modif = ereg_replace("^([^/]*)(//)?([^/]*)/.*$","\\1\\2\\3",$site."/");
	$item = ereg_replace("<img src=\"/","<img src=\"$modif/",$item);

	# On retourne le resultat
	return $item;
}

# Fonction qui teste si une url est accessible
function checkUrl($url) {
	# Activation de l'option au niveau de la configuration de php
	ini_set('allow_url_fopen', '1');

	# Ouverture / Fermeture de l'url a distance
	return  @fclose(@fopen($url, 'r'));
}

function strip_script($string) {
	do
	$string = eregi_replace("<script[^>]*>.*</script[^>]*>", "", $string);
	while (eregi_replace("<script[^>]*>.*</script[^>]*>", "", $string)==1);
	do
	$string = eregi_replace("<script[^>]*>", "", $string);
	while (eregi_replace("<script[^>]*>", "", $string)==1);
	return $string;
}

?>
