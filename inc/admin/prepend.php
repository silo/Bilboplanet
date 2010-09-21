<?php
# -- BEGIN LICENSE BLOCK ----------------------------------
#
# This file is part of Dotclear 2.
#
# Copyright (c) 2003-2008 Olivier Meunier and contributors
# Licensed under the GPL version 2.0 license.
# See LICENSE file or
# http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
#
# -- END LICENSE BLOCK ------------------------------------

require_once dirname(__FILE__).'/../prepend.php';
// HTTP/1.1
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');

// HTTP/1.0
header("Pragma: no-cache");

define('BP_CONTEXT_ADMIN',true);

if ($core->auth->sessionExists())
{
	# If we have a session we launch it now
	try {
		if (!$core->auth->checkSession())
		{
			# Avoid loop caused by old cookie
			$p = $core->session->getCookieParameters(false,-600);
			$p[3] = '/';
			call_user_func_array('setcookie',$p);
			
			http::redirect('auth.php');
		}
	} catch (Exception $e) {
		__error(T_('Database error')
			,T_('There seems to be no Session table in your database. Is Bilboplanet completly installed?')
			,20);
	}
}

# Logout
if (isset($_GET['logout'])) {
	$core->session->destroy();
	if (isset($_COOKIE['bp_admin'])) {
		unset($_COOKIE['bp_admin']);
		setcookie('bp_admin',false,-600,'','');
	}
	if (!empty($_GET['logout'])) {
		http::redirect($_GET['logout']);
	}
	else {
		http::redirect('auth.php');
	}
	exit;
}

?>
