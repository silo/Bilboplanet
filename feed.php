<?php
/******* BEGIN LICENSE BLOCK *****
* BilboPlanet - An Open Source RSS feed aggregator written in PHP
* Copyright (C) 2010 By French Dev Team : Dev BilboPlanet
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

# Include
require_once(dirname(__FILE__).'/inc/prepend.php');

# Function to convert specific char for RSS feed
function convert_chars($string) {
	
	$string_convert = $string;
	
	$string_convert = str_replace('&','&#x26;', $string_convert);
	$string_convert = str_replace('<','&#x3C;', $string_convert);
	$string_convert = str_replace('>','&#x3E;', $string_convert);
	
	return $string_convert;
}

# Function to split string only on space char
function short_str( $str, $len, $cut = true ) {
	if ( strlen( $str ) <= $len ) {
		return $str;
	}
	if ($cut) {
		return substr( $str, 0, $len );
	} else {
		return substr( $str, 0, strrpos( substr( $str, 0, $len ), ' ' ) ) . ' [...]';
	}
}

function uuid($key = null, $prefix = '') {
	$key = ($key == null)? uniqid(rand()) : $key;
	$chars = md5($key);
	$uuid  = substr($chars,0,8) . '-';
	$uuid .= substr($chars,8,4) . '-';
	$uuid .= substr($chars,12,4) . '-';
	$uuid .= substr($chars,16,4) . '-';
	$uuid .= substr($chars,20,12);

	return $prefix . $uuid;
}

# Check content of $_GET
if (isset($_GET) && isset($_GET['type'])) {
	if ($_GET['type']=="rss"){
		header('Content-Type: application/rss+xml; charset=UTF-8');
		$params = "feed.php?type=rss";
	}
	elseif($_GET['type']=="atom") {
		header('Content-Type: application/atom+xml; charset=UTF-8');
		$params = "feed.php?type=atom";
	}

	# On active le cache
	debutCache();

	# Get informations about posts
	if (isset($_GET["popular"]) && !empty($_GET['popular'])){
		
		# Encode specific char
		$params .= "&popular=true";
		if ($_GET['type'] == "rss") {
			$params = convert_chars($params);
		} 
		elseif ($_GET['type'] == "atom") {
			$params = str_replace("&", "&amp;", $params);
		}
		
		# Compute date to timestamp format
		$semaine = time() - 3600*24*7;
		$title = convert_chars(html_entity_decode(stripslashes($blog_settings->get('planet_title')), ENT_QUOTES, 'UTF-8'))." - Popular";
		$sql = "SELECT
				".$core->prefix."user.user_id as user_id,
				user_fullname,
				user_email,
				post_id,
				post_pubdate,
				post_title,
				post_permalink,
				post_content
			FROM ".$core->prefix."post, ".$core->prefix."user
			WHERE ".$core->prefix."post.user_id = ".$core->prefix."user.user_id
			AND post_status = '1'
			AND user_status = '1'
			AND post_score > '0'
			AND post_pubdate > ".$semaine."
			ORDER BY post_score DESC 
			LIMIT 0, ".$blog_settings->get('planet_nb_art_flux');
	}
	else {
		$title = convert_chars(html_entity_decode(stripslashes($blog_settings->get('planet_title')), ENT_QUOTES, 'UTF-8'));
		$sql = "SELECT
				".$core->prefix."user.user_id as user_id,
				user_fullname,
				user_email,
				post_id,
				post_pubdate,
				post_title,
				post_permalink,
				post_content
			FROM ".$core->prefix."post, ".$core->prefix."user
			WHERE ".$core->prefix."post.user_id = ".$core->prefix."user.user_id
			AND post_status = '1'
			AND user_status = '1'
			AND post_score > ".$blog_settings->get('planet_votes_limit')
			." ORDER BY post_pubdate DESC
			LIMIT 0, ".$blog_settings->get('planet_nb_art_flux');
	}
	
	# Execute SQL request
	$post_list = $core->con->select($sql);
	$planet_desc = convert_chars(html_entity_decode(stripslashes($blog_settings->get('planet_desc')), ENT_QUOTES, 'UTF-8'));
	
	# Head of XML document
	echo '<?xml version="1.0" encoding="UTF-8" ?>'."\n";
	
	if ($_GET['type']=="rss"){
	# Header of RSS 2.0 content
?>
	<rss version="2.0"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wfw="http://wellformedweb.org/CommentAPI/"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	xmlns:atom="http://www.w3.org/2005/Atom"
	xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
	xmlns:slash="http://purl.org/rss/1.0/modules/slash/">
		<channel>
			<title><?php echo $title; ?></title>
			<atom:link href="<?php echo $blog_settings->get('planet_url')."/".$params;?>" rel="self" type="application/rss+xml" />
			<link><?php echo $blog_settings->get('planet_url'); ?></link>
			<description><?php echo $planet_desc;?></description>
			<lastBuildDate><?php echo date('r'); ?></lastBuildDate>
			<language><?php echo $blog_settings->get('planet_lang'); ?></language>
			<generator>Bilboplanet v<?php echo str_replace(array("\r\n", "\r", "\n"), null, $blog_settings->get('planet_version')); ?></generator>
			<webMaster><?php echo strtolower($blog_settings->get('author_mail'))." (".$blog_settings->get('author').")"; ?></webMaster>
<?php 
	}
	elseif($_GET['type']=="atom") {
	#Header of Atom Content
		$authority = parse_url($blog_settings->get('planet_url'));
		$authority = $authority['host'];

?>
	<feed xmlns="http://www.w3.org/2005/Atom">
	
		<title><?php echo $title; ?></title>
		<subtitle type="text"><?php echo $planet_desc; ?></subtitle>
		<updated><?php echo date('c') ?></updated>
		<id>tag:<?php 
		echo $authority.','.date('Y').':'.$blog_settings->get('planet_url');
		?></id>
		<author>
			<name><?php echo $blog_settings->get('author'); ?></name>
			<email><?php echo strtolower($blog_settings->get('author_mail')); ?></email>
			<uri><?php echo $blog_settings->get('planet_url'); ?></uri>
		</author>
		<link rel="alternate" type="text/html" href="<?php echo $blog_settings->get('planet_url'); ?>" />
		<link rel="self" href="<?php echo $blog_settings->get('planet_url')."/".$params; ?>" />
		<generator uri="http://www.bilboplanet.com" version="<?php echo str_replace(array("\r\n", "\r", "\n"), null, $blog_settings->get('planet_version')); ?>">
			Bilboplanet
		</generator>
		
<?php 
	}

	while ($post_list->fetch()) {
		# Convert value to UTF-8
		$titre = convert_iso_special_html_char(html_entity_decode(html_entity_decode($post_list->post_title, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8'));
		$nom = convert_iso_special_html_char(html_entity_decode(html_entity_decode($post_list->user_fullname, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8'));
		$item = convert_iso_special_html_char(html_entity_decode(html_entity_decode($post_list->post_content, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8'));

		$url = $post_list->post_permalink;
		if ($blog_settings->get('internal_links')) {
			$url = $blog_settings->get('planet_url').
				"/index.php?post_id=".$post_list->post_id.
				"&go=external";
		}
	$id = uuid($url, 'urn:uuid:');

	# Other link
	$links =  '<br/><i>'.sprintf('Original post of <a href="%s" title="Visit the source">%s</a>.',$url, $nom);
	$links .= '<br/>'.sprintf(T_('Vote for this post on <a href="%s" title="Go on the planet">%s</a>.'),$blog_settings->get('planet_url'), $blog_settings->get('planet_title')).'</i>';
	
	# Remove html tag to post content
	$desc = strip_tags($item);
	# Split string only on space char
	$desc = short_str($desc, 300, false);
	
	# Gravatar
	if($blog_settings->get('planet_avatar')) {
		$gravatar_email = strtolower($post_list->user_email);
		$gravatar_url = "http://www.gravatar.com/avatar.php?gravatar_id=".md5($gravatar_email)."&amp;default=".urlencode($blog_settings->get('planet_url')."themes/".$blog_settings->get('planet_theme')."/images/gravatar.png")."&amp;size=40";
		$gravatar = '<img src="'.$gravatar_url.'" alt="'.sprintf(T_('Gravatar of %s'),$post_list->user_fullname).'" class="gravatar" />';
	}

	if ($_GET['type']=="rss"){
		# Display item content
		echo "\t\t\t<item>\n";
		echo "\t\t\t\t<title>".$nom." : ".$titre."</title>\n";
		echo "\t\t\t\t<link>".htmlentities($url)."</link>\n";
		echo "\t\t\t\t<pubDate>".date("r", strtotime($post_list->post_pubdate))."</pubDate>\n";
		echo "\t\t\t\t<dc:creator>".$nom."</dc:creator>\n";
		echo "\t\t\t\t<description><![CDATA[".$desc."]]></description>\n";
		echo "\t\t\t\t<guid isPermaLink=\"true\">".htmlentities($url)."</guid>\n";
		
		if($blog_settings->get('planet_avatar')) {
			echo "\t\t\t\t<content:encoded><![CDATA[".$item."<p>".$gravatar.$links."</p>"."]]></content:encoded>\n";
		} else {
			echo "\t\t\t\t<content:encoded><![CDATA[".$item."<p>".$links."</p>"."]]></content:encoded>\n";
		}
		
		# End of Item
		echo "\t\t\t</item>\n"; 
	}
	elseif($_GET['type']=="atom") {
		# Affichage du contenu de l'item
		echo "\t\t<entry>\n";
		echo "\t\t\t<id>".$id."</id>\n";
			echo "\t\t\t<title>".$nom." : ".$titre."</title>\n";
			echo "\t\t\t<updated>".date("c", strtotime($post_list->post_pubdate))."</updated>\n";
			echo "\t\t\t<author>\n";
			echo "\t\t\t\t<name>".$nom."</name>\n";
			echo "\t\t\t</author>\n";
			echo "\t\t\t<link href=\"".htmlentities($url)."\" rel=\"alternate\" type=\"text/html\" title=\"".$titre."\" />\n";
			echo "\t\t\t<summary type=\"html\">".str_replace(array("\r\n", "\r", "\n"), " ", $desc)."</summary>\n";
			if($blog_settings->get('planet_avatar')) {
				echo "\t\t\t<content type=\"html\"><![CDATA[".$item."<p>".$gravatar.$links."</p>"."]]></content>\n";
			} else {
				echo "\t\t\t<content type=\"html\"><![CDATA[".$item."<p>".$links."</p>"."]]></content>\n";
			}
			echo "\t\t</entry>\n";
		}
	}

	if ($_GET['type']=="rss"){
		echo "\t\t</channel>\n";
		echo "\t</rss>";
	}
	elseif($_GET['type']=="atom") {
		echo "\t</feed>";
	}

	/* On termine le cache */
	finCache();
}
else http::redirect($blog_settings->get('planet_url')."/feed.php?type=rss");

$ga = $blog_settings->get('planet_ganalytics');
if(!empty($ga)) {
	ga($ga,'/feed/'.$_GET['type'],T_('Feed'));
}
?>
