<?php
/**
 * Copyright (C) 2008 Ulteo SAS
 * http://www.ulteo.com
 * Author Jeremy DESVAGES <jeremy@ulteo.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; version 2
 * of the License.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 **/
require_once(dirname(__FILE__).'/../includes/core.inc.php');

if (isset($_SESSION['parameters']['client']) && $_SESSION['parameters']['client'] == 'browser') {
	load_gettext();
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
	<head>
		<title>Ulteo Open Virtual Desktop</title>

		<?php //<meta http-equiv="Content-Type" content="application/xhtml+xml; charset=utf-8" /> ?>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

		<link rel="shortcut icon" type="image/png" href="../media/image/favicon.ico" />

		<link rel="stylesheet" type="text/css" href="common.css" />

		<script type="text/javascript" src="../media/script/lib/prototype/prototype.js" charset="utf-8"></script>
		<script type="text/javascript" src="../media/script/common.js" charset="utf-8"></script>

		<script type="text/javascript" src="../media/script/lib/scriptaculous/scriptaculous.js" charset="utf-8"></script>
		<script type="text/javascript" src="../media/script/lib/scriptaculous/extensions.js" charset="utf-8"></script>

		<script type="text/javascript" src="../media/script/daemon.js" charset="utf-8"></script>
		<script type="text/javascript" src="../media/script/daemon_sharedapp.js" charset="utf-8"></script>

		<script type="text/javascript" charset="utf-8">
			var daemon;

			Event.observe(window, 'load', function() {
				daemon = new SharedApp('ulteo-applet.jar', 'org.ulteo.OvdApplet', 'ulteo-printing.jar', <?php echo ($_SESSION['debug'] == 1)?'1':'0'; ?>);
				daemon.access_id = '<?php echo $_SESSION['tokens'][$_GET['token']]['access_id']; ?>';
				daemon.loop();
			});
		</script>
	</head>

	<body>
		<div id="splashContainer">
			<table style="width: 100%" border="0" cellspacing="0" cellpadding="0">
				<tr>
					<td style="text-align: center" colspan="3">
						<img src="../media/image/ulteo.png" width="376" height="188" alt="" title="" />
					</td>
				</tr>
				<tr>
					<td style="text-align: left; vertical-align: middle">
						<span style="font-size: 1.35em; font-weight: bold; color: #686868"><?php echo _('Loading Share'); ?></span>
					</td>
					<td style="width: 20px"></td>
					<td style="text-align: left; vertical-align: middle">
						<img src="../media/image/rotate.gif" width="32" height="32" alt="" title="" />
					</td>
				</tr>
			</table>
		</div>

		<div id="debugContainer" class="no_debug info warning error" style="display: none;">
		</div>

		<div id="debugLevels" style="display: none;">
			<span class="debug"><input type="checkbox" id="level_debug" onclick="switchDebug('debug')" value="10" /> Debug</span>
			<span class="info"><input type="checkbox" id="level_info" onclick="switchDebug('info')" value="20" checked="checked" /> Info</span>
			<span class="warning"><input type="checkbox" id="level_warning" onclick="switchDebug('warning')" value="30" checked="checked" /> Warning</span>
			<span class="error"><input type="checkbox" id="level_error" onclick="switchDebug('error')" value="40" checked="checked" /> Error</span><br />
			<input type="button" onclick="clearDebug(); return false;" value="Clear" />
		</div>

		<div id="endContainer" style="display: none;">
			<table style="width: 100%" border="0" cellspacing="0" cellpadding="0">
				<tr>
					<td style="text-align: center">
						<img src="../media/image/ulteo.png" width="376" height="188" alt="" title="" />
					</td>
				</tr>
				<tr>
					<td style="text-align: center; vertical-align: middle">
						<span style="font-size: 1.1em; font-weight: bold; color: #686868"><?php echo _('Your application has ended, you can now close the window'); ?><br />
						<div id="errorContainer">
						</div>
						</span>
					</td>
				</tr>
			</table>
		</div>

		<div id="appletContainer" style="display: none;">
		</div>

		<div id="printerContainer">
		</div>
	</body>
</html>
<?php
	die();
}

header('Content-Type: text/xml; charset=utf-8');

$dom = new DomDocument('1.0', 'utf-8');
$aps_node = $dom->createElement('aps');
$aps_node->setAttribute('server', $_SERVER['SERVER_NAME']);
$dom->appendChild($aps_node);

$xml = $dom->saveXML();

echo $xml;
