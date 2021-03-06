<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../core/php/core.inc.php';

class network {

	public static function getUserLocation() {
		$client_ip = self::getClientIp();
		$jeedom_ip = self::getNetworkAccess('internal', 'ip', '', false);
		if (!filter_var($jeedom_ip, FILTER_VALIDATE_IP)) {
			return 'external';
		}
		$jeedom_ips = explode('.', $jeedom_ip);
		if (count($jeedom_ips) != 4) {
			return 'external';
		}
		$match = $jeedom_ips[0] . '.' . $jeedom_ips[1] . '.' . $jeedom_ips[2] . '.*';
		if (netMatch($match, $client_ip)) {
			return 'internal';
		} else {
			return 'external';
		}
	}

	public static function getClientIp() {
		if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			return $_SERVER['HTTP_X_FORWARDED_FOR'];
		} elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
			return $_SERVER['HTTP_X_REAL_IP'];
		} elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
			return $_SERVER['HTTP_CLIENT_IP'];
		} elseif (isset($_SERVER['REMOTE_ADDR'])) {
			return $_SERVER['REMOTE_ADDR'];
		}
		return '';
	}

	public static function getNetworkAccess($_mode = 'auto', $_protocole = '', $_default = '', $_test = true) {
		if ($_mode == 'auto') {
			$_mode = self::getUserLocation();
		}
		if ($_test && !self::test($_mode)) {
			self::checkConf($_mode);
		}
		if ($_mode == 'internal') {
			if (strpos(config::byKey('internalAddr', 'core', $_default), 'http://') !== false || strpos(config::byKey('internalAddr', 'core', $_default), 'https://') !== false) {
				config::save('internalAddr', str_replace(array('http://', 'https://'), '', config::byKey('internalAddr', 'core', $_default)));
			}
			if ($_protocole == 'ip' || $_protocole == 'dns') {
				return config::byKey('internalAddr', 'core', $_default);
			}
			if ($_protocole == 'ip:port' || $_protocole == 'dns:port') {
				return config::byKey('internalAddr') . ':' . config::byKey('internalPort', 'core', 80);
			}
			if ($_protocole == 'proto:ip' || $_protocole == 'proto:dns') {
				return config::byKey('internalProtocol') . config::byKey('internalAddr');
			}
			if ($_protocole == 'proto:ip:port' || $_protocole == 'proto:dns:port') {
				return config::byKey('internalProtocol') . config::byKey('internalAddr') . ':' . config::byKey('internalPort', 'core', 80);
			}
			if ($_protocole == 'proto:127.0.0.1:port:comp') {
				return trim(config::byKey('internalProtocol') . '127.0.0.1:' . config::byKey('internalPort', 'core', 80) . '/' . trim(config::byKey('internalComplement'), '/'), '/');
			}
			if ($_protocole == 'http:127.0.0.1:port:comp') {
				return trim('http://127.0.0.1:' . config::byKey('internalPort', 'core', 80) . '/' . trim(config::byKey('internalComplement'), '/'), '/');
			}
			return trim(config::byKey('internalProtocol') . config::byKey('internalAddr') . ':' . config::byKey('internalPort', 'core', 80) . '/' . trim(config::byKey('internalComplement'), '/'), '/');

		}
		if ($_mode == 'dnsjeedom') {
			return config::byKey('jeedom::url');
		}
		if ($_mode == 'external') {
			if ($_protocole == 'ip') {
				if (config::byKey('market::allowDNS') == 1 && config::byKey('jeedom::url') != '') {
					return getIpFromString(config::byKey('jeedom::url'));
				}
				return getIpFromString(config::byKey('externalAddr'));
			}
			if ($_protocole == 'ip:port') {
				if (config::byKey('market::allowDNS') == 1 && config::byKey('jeedom::url') != '') {
					$url = parse_url(config::byKey('jeedom::url'));
					if (isset($url['host'])) {
						if (isset($url['port'])) {
							return getIpFromString($url['host']) . ':' . $url['port'];
						} else {
							return getIpFromString($url['host']);
						}
					}
				}
				return config::byKey('externalAddr') . ':' . config::byKey('externalPort', 'core', 80);
			}
			if ($_protocole == 'proto:dns:port' || $_protocole == 'proto:ip:port') {
				if (config::byKey('market::allowDNS') == 1 && config::byKey('jeedom::url') != '') {
					$url = parse_url(config::byKey('jeedom::url'));
					$return = '';
					if (isset($url['scheme'])) {
						$return = $url['scheme'] . '://';
					}
					if (isset($url['host'])) {
						if (isset($url['port'])) {
							return $return . $url['host'] . ':' . $url['port'];
						} else {
							return $return . $url['host'];
						}
					}
				}
				return config::byKey('externalProtocol') . config::byKey('externalAddr') . ':' . config::byKey('externalPort', 'core', 80);
			}
			if ($_protocole == 'proto:dns' || $_protocole == 'proto:ip') {
				if (config::byKey('market::allowDNS') == 1 && config::byKey('jeedom::url') != '') {
					$url = parse_url(config::byKey('jeedom::url'));
					$return = '';
					if (isset($url['scheme'])) {
						$return = $url['scheme'] . '://';
					}
					if (isset($url['host'])) {
						if (isset($url['port'])) {
							return $return . $url['host'];
						} else {
							return $return . $url['host'];
						}
					}
				}
				return config::byKey('externalProtocol') . config::byKey('externalAddr');
			}
			if ($_protocole == 'dns:port') {
				if (config::byKey('market::allowDNS') == 1 && config::byKey('jeedom::url') != '') {
					$url = parse_url(config::byKey('jeedom::url'));
					if (isset($url['host'])) {
						if (isset($url['port'])) {
							return $url['host'] . ':' . $url['port'];
						} else {
							return $url['host'];
						}
					}
				}
				return config::byKey('externalAddr') . ':' . config::byKey('externalPort', 'core', 80);
			}
			if ($_protocole == 'proto') {
				if (config::byKey('market::allowDNS') == 1 && config::byKey('jeedom::url') != '') {
					$url = parse_url(config::byKey('jeedom::url'));
					if (isset($url['scheme'])) {
						return $url['scheme'] . '://';
					}
				}
				return config::byKey('externalProtocol');
			}
			if (config::byKey('market::allowDNS') == 1 && config::byKey('jeedom::url') != '') {
				return trim(config::byKey('jeedom::url') . '/' . trim(config::byKey('externalComplement', 'core', ''), '/'), '/');
			}
			return trim(config::byKey('externalProtocol') . config::byKey('externalAddr') . ':' . config::byKey('externalPort', 'core', 80) . '/' . trim(config::byKey('externalComplement'), '/'), '/');
		}
	}

	public static function checkConf($_mode = 'external') {
		if (config::byKey($_mode . 'Protocol') == '') {
			config::save($_mode . 'Protocol', 'http://');
		}
		if (config::byKey($_mode . 'Port') == '') {
			config::save($_mode . 'Port', 80);
		}
		if (config::byKey($_mode . 'Protocol') == 'https://' && config::byKey($_mode . 'Port') == 80) {
			config::save($_mode . 'Port', 443);
		}
		if (config::byKey($_mode . 'Protocol') == 'http://' && config::byKey($_mode . 'Port') == 443) {
			config::save($_mode . 'Port', 80);
		}
		if (trim(config::byKey($_mode . 'Complement')) == '/') {
			config::save($_mode . 'Complement', '');
		}
		if ($_mode == 'internal') {
			foreach (self::getInterfaces() as $interface) {
				if ($interface == 'lo') {
					continue;
				}
				$ip = self::getInterfaceIp($interface);
				if (!netMatch('127.0.*.*', $ip) && $ip != '' && filter_var($ip, FILTER_VALIDATE_IP)) {
					config::save('internalAddr', $ip);
					break;
				}
			}
		}
	}

	public static function test($_mode = 'external', $_timeout = 10) {
		if (config::byKey('network::disableMangement') == 1) {
			return true;
		}
		if ($_mode == 'internal' && netMatch('127.0.*.*', self::getNetworkAccess($_mode, 'ip', '', false))) {
			return false;
		}
		$url = trim(self::getNetworkAccess($_mode, '', '', false), '/') . '/here.html';
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_TIMEOUT, $_timeout);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_HEADER, false);
		if ($_mode == 'external') {
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		}
		$data = curl_exec($ch);
		if (curl_errno($ch)) {
			log::add('network', 'debug', 'Erreur sur ' . $url . ' => ' . curl_errno($ch));
			curl_close($ch);
			return false;
		}
		curl_close($ch);
		if (trim($data) != 'ok') {
			log::add('network', 'debug', 'Retour NOK sur ' . $url . ' => ' . $data);
			return false;
		}
		return true;
	}

/*     * *********************DNS************************* */

	public static function dns_create() {
		if (config::byKey('dns::token') == '') {
			return;
		}
		if (config::byKey('market::allowDNS') != 1) {
			return;
		}
		try {
			$plugin = plugin::byId('openvpn');
			if (!is_object($plugin)) {
				$update = update::byLogicalId('openvpn');
				if (!is_object($update)) {
					$update = new update();
				}
				$update->setLogicalId('openvpn');
				$update->setSource('market');
				$update->setConfiguration('version', 'stable');
				$update->save();
				$update->doUpdate();
				$plugin = plugin::byId('openvpn');
			}
		} catch (Exception $e) {
			$update = update::byLogicalId('openvpn');
			if (!is_object($update)) {
				$update = new update();
			}
			$update->setLogicalId('openvpn');
			$update->setSource('market');
			$update->setConfiguration('version', 'stable');
			$update->save();
			$update->doUpdate();
			$plugin = plugin::byId('openvpn');
		}
		if (!$plugin->isActive()) {
			$plugin->setIsEnable(1);
		}
		if (!is_object($plugin)) {
			throw new Exception(__('Le plugin openvpn doit être installé', __FILE__));
		}
		if (!$plugin->isActive()) {
			throw new Exception(__('Le plugin openvpn doit être actif', __FILE__));
		}
		$openvpn = eqLogic::byLogicalId('dnsjeedom', 'openvpn');
		if (!is_object($openvpn)) {
			$openvpn = new openvpn();
			$openvpn->setName('DNS Jeedom');
		}
		$openvpn->setIsEnable(1);
		$openvpn->setLogicalId('dnsjeedom');
		$openvpn->setEqType_name('openvpn');
		$openvpn->setConfiguration('dev', 'tun');
		$openvpn->setConfiguration('proto', 'udp');
		$openvpn->setConfiguration('remote_host', 'vpn.dns' . config::byKey('dns::number', 'core', 1) . '.jeedom.com');
		$openvpn->setConfiguration('username', jeedom::getHardwareKey());
		$openvpn->setConfiguration('password', config::byKey('dns::token'));
		$openvpn->setConfiguration('compression', 'comp-lzo');
		$openvpn->setConfiguration('remote_port', 1194);
		$openvpn->setConfiguration('auth_mode', 'password');
		$openvpn->save();
		if (!file_exists(dirname(__FILE__) . '/../../plugins/openvpn/data')) {
			shell_exec('mkdir -p ' . dirname(__FILE__) . '/../../plugins/openvpn/data');
		}
		copy(dirname(__FILE__) . '/../../script/ca_dns.crt', dirname(__FILE__) . '/../../plugins/openvpn/data/ca_' . $openvpn->getConfiguration('key') . '.crt');
		return $openvpn;
	}

	public static function dns_start() {
		if (config::byKey('dns::token') == '') {
			return;
		}
		if (config::byKey('market::allowDNS') != 1) {
			return;
		}
		$openvpn = self::dns_create();
		$cmd = $openvpn->getCmd('action', 'start');
		if (!is_object($cmd)) {
			throw new Exception(__('La commande de start du DNS est introuvable', __FILE__));
		}
		$cmd->execCmd();
		$interface = $openvpn->getInterfaceName();
		if ($interface !== null && $interface != '' && $interface !== false) {
			shell_exec(system::getCmdSudo() . 'iptables -A INPUT -i ' . $interface . ' -p tcp  --destination-port 80 -j ACCEPT');
			shell_exec(system::getCmdSudo() . 'iptables -A INPUT -i ' . $interface . ' -j DROP');
		}
	}

	public static function dns_run() {
		if (config::byKey('dns::token') == '') {
			return false;
		}
		if (config::byKey('market::allowDNS') != 1) {
			return false;
		}
		try {
			$openvpn = self::dns_create();
		} catch (Exception $e) {
			return false;
		}
		$cmd = $openvpn->getCmd('info', 'state');
		if (!is_object($cmd)) {
			throw new Exception(__('La commande de statut du DNS est introuvable', __FILE__));
		}
		return $cmd->execCmd();
	}

	public static function dns_stop() {
		if (config::byKey('dns::token') == '') {
			return;
		}
		if (config::byKey('market::allowDNS') != 1) {
			return;
		}
		$openvpn = self::dns_create();
		$cmd = $openvpn->getCmd('action', 'stop');
		if (!is_object($cmd)) {
			throw new Exception(__('La commande de stop du DNS est introuvable', __FILE__));
		}
		$cmd->execCmd();
	}

/*     * *********************Network management************************* */

	public static function getInterfaceIp($_interface) {
		$ip = trim(shell_exec(system::getCmdSudo() . "ip addr show " . $_interface . " | grep \"inet .*" . $_interface . "\" | awk '{print $2}' | cut -d '/' -f 1"));
		if (filter_var($ip, FILTER_VALIDATE_IP)) {
			return $ip;
		}
		return false;
	}

	public static function getInterfaceMac($_interface) {
		$valid_mac = "([0-9A-F]{2}[:-]){5}([0-9A-F]{2})";
		$mac = trim(shell_exec(system::getCmdSudo() . "ip addr show " . $_interface . " 2>&1 | grep ether | awk '{print $2}'"));
		if (preg_match("/" . $valid_mac . "/i", $mac)) {
			return $mac;
		}
		return false;
	}

	public static function getInterfaces() {
		$result = explode("\n", shell_exec(system::getCmdSudo() . "ip -o link show | awk -F': ' '{print $2}'"));
		foreach ($result as $value) {
			if (trim($value) == '') {
				continue;
			}
			$return[] = $value;
		}
		return $return;
	}

	public static function cron5() {
		if (config::byKey('network::disableMangement') == 1) {
			return;
		}
		if (!network::test('internal')) {
			network::checkConf('internal');
		}
		if (!network::test('external')) {
			network::checkConf('external');
		}
		if (!jeedom::isCapable('sudo') || jeedom::getHardwareName() == 'docker') {
			return;
		}
		exec(system::getCmdSudo() . 'ping -n -c 1 -t 255 8.8.8.8 2>&1 > /dev/null', $output, $return_val);
		if ($return_val == 0) {
			return;
		}
		$gw = shell_exec("ip route show default | awk '/default/ {print $3}'");
		if ($gw == '') {
			log::add('network', 'error', __('Soucis réseaux detecté, redemarrage du réseaux', __FILE__));
			exec(system::getCmdSudo() . 'service networking restart');
			return;
		}
		exec(system::getCmdSudo() . 'ping -n -c 1 -t 255 ' . $gw . ' 2>&1 > /dev/null', $output, $return_val);
		if ($return_val == 0) {
			return;
		}
		log::add('network', 'error', __('Soucis réseaux detecté, redemarrage du réseaux', __FILE__));
		exec(system::getCmdSudo() . 'service networking restart');
	}
}
