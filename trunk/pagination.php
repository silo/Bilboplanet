<?php
/******* BEGIN LICENSE BLOCK *****
* BilboPlanet - Un agrégateur de Flux RSS Open Source en PHP.
* BilboPlanet - An Open Source RSS feed aggregator written in PHP
* Copyright (C) 2009 By French Dev Team : Dev BilboPlanet
* Contact : dev@bilboplanet.org
* Website : www.bilboplanet.org
* Tracker : redmine.bilboplanet.org
* Blog : blog.bilboplanet.org
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
<div class="navigation">
<?php

# Si on consulte les articles d'un membre
if(!empty($_GET['num_membre']) && is_numeric(trim($_GET['num_membre']))) {
	$num_membre = trim($_GET['num_membre']);
}

$params = "";
if (isset($_GET) && isset($_GET['search']) && !empty($_GET['search'])){
	$params = $params."search=".$_GET['search']."&";
}
if (isset($_GET) && isset($_GET['tri']) && !empty($_GET['tri'])){
	$params = $params."tri=".$_GET['tri']."&";
}
if (isset($_GET) && isset($_GET['num_membre']) && !empty($_GET['num_membre'])){
	$params = $params."num_membre=".$_GET['num_membre']."&";
}
if (isset($_GET) && isset($_GET['populaires']) && !empty($_GET['populaires'])){
	$params = $params."populaires=".$_GET['populaires']."&";
}

# Affichage des liens permettant de changer de page
if($num_page == 0) {
		# Si on est sur la premiere page, on affiche seulement un lien vers la page suivante
	$suivante = $num_page + 1;
	# Affichage de l'url en fonction d'une demande de recherche ou non
	echo '<a href="?'.$params.'page='.$suivante.'" class="page_svt">'.T_('Next page').' &raquo;</a>';

} else {

	if(!$nb) {
		# Sinon si on est sur la derniere page, on affiche seulement un lien vers la page precedente
		$precedente = $num_page - 1;
		# Affichage de l'url en fonction d'une demande de recherche ou non
		echo '<a href="?'.$params.'page='.$precedente.'" class="page_prc"> &laquo; '.T_('Previous page').'</a>';
	} else {
		# Sinon on affiche tout
		$suivante = $num_page + 1;
		$precedente = $num_page - 1;

		# Affichage de l'url en fonction d'une demande de recherche ou non
		echo ' <a href="?'.$params.'page='.$precedente.'" class="page_prc"> &laquo; '.T_('Previous page').'</a>';
		echo '<a href="?'.$params.'page='.$suivante.'" class="page_svt">'.T_('Next page').' &raquo;</a> ';
	}
}
?>
</div>
