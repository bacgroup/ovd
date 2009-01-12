<?php
/**
 * Copyright (C) 2008 Ulteo SAS
 * http://www.ulteo.com
 * Author Laurent CLOUET <laurent@ulteo.com>
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
require_once(dirname(__FILE__).'/../../includes/core.inc.php');

class UserDB_ldap {
	public $config;
	public function __construct () {
		$prefs = Preferences::getInstance();
		if (! $prefs)
			die_error('get Preferences failed',__FILE__,__LINE__);
		$this->config = $prefs->get('UserDB','ldap');

	}
	public function import($login_){
		Logger::debug('main','UserDB::ldap::import('.$login_.')');

		$ldap = new LDAP($this->config);
		$sr = $ldap->search($this->config['match']['login'].'='.$login_, NULL);
		if ($sr === false) {
			Logger::error('main','UserDB_ldap::getList ldap failed (mostly timeout on server)');
			return NULL;
		}
		$infos = $ldap->get_entries($sr);
		if ( $infos["count"] == 1){
			$info = $infos[0];
			$u = new User();
			foreach ($this->config['match'] as $attribut => $match_ldap){
				if (isset($info[$match_ldap][0]))
					$u->setAttribute($attribut,$info[$match_ldap][0]);
			}
			if ($u->hasAttribute('uid') == false)
				$u->setAttribute('uid',str2num($u->getAttribute('login')));
			return $u;
		}
		return NULL;
	}

	public function getList(){
		Logger::debug('main','UserDB::ldap::getList');
		$users = array();

		$ldap = new LDAP($this->config);
		$sr = $ldap->search($this->config['match']['login'].'=*', NULL);
		$infos = $ldap->get_entries($sr);
		foreach ($infos as $info){
			$u = new User();
			foreach ($this->config['match'] as $attribut => $match_ldap){
				if (isset($info[$match_ldap][0]))
					$u->setAttribute($attribut,$info[$match_ldap][0]);
			}
			if ($u->hasAttribute('uid') == false)
				$u->setAttribute('uid',str2num($u->getAttribute('login')));
			if ($this->isOK($u))
				$users []= $u;
			else {
				if ($u->getAttribute('login'))
					Logger::info('main', 'UserDB::mysql::getList user \''.$u->getAttribute('login').'\' not ok');
				else
					Logger::info('main', 'UserDB::mysql::getList user does not have login');
			}
		}
		return $users;
	}

	public function isWriteable(){
		return false;
	}

	public function canShowList(){
		return true;
	}

	public function needPassword(){
		return true;
	}


	public function isOK($user_){
		$minimun_attribute = array_unique(array_merge(array('login','displayname','uid'),get_needed_attributes_user_from_module_plugin()));
		if (is_object($user_)){
			foreach ($minimun_attribute as $attribute){
				if ($user_->hasAttribute($attribute) == false)
					return false;
				else {
					$a = $user_->getAttribute($attribute);
					if ( is_null($a) || $a == "")
						return false;
				}
			}
			return true;
		}
		else
			return false;
	}

	public function authenticate($user_,$password_){
		Logger::debug('main','UserDB::ldap::authenticate '.$user_->getAttribute('login'));
		$conf_ldap2 = $this->config;
		$conf_ldap2['login'] = $user_->getAttribute('login');
		$conf_ldap2['password'] = $password_;

		$LDAP2 = new LDAP($conf_ldap2);
		$a = $LDAP2->connect();
		$LDAP2->disconnect();
		if ( $a == false)
			Logger::debug('main','USERDB::LDAP::authenticate \''.$user_->getAttribute('login').'\' is not authenticate');
		else
			Logger::debug('main','USERDB::LDAP::authenticate '.$user_->getAttribute('login').' result '.$a);
		return $a;
	}

	public function configuration(){
		$ret = array();
		$c = new config_element('host', _('Server host address'), _('The address of your LDAP server.'), _('The address of your LDAP server.'), 'servldap.example.com', NULL, 1);
		$ret []= $c;
		$c = new config_element('port', _('Server port'), _('The port use by your LDAP server.'), _('The port use by your LDAP server.'),'389',NULL,1);
		$ret []= $c;
		$c = new config_element('login', _('User login'), _('The user login that must be used to access the database (to list users account).'), _('The user login that must be used to access the database (to list users account).'),'',NULL,1);
		$ret []= $c;
		$c = new config_element('password', _('User password'), _('The user password that must be used to access the database (to list users account).'), _('The user password that must be used to access the database (to list users account).'),'',NULL,1);
		$ret []= $c;
		$c = new config_element('suffix','suffix','suffix','suffix','dc=servldap,dc=example,dc=com',NULL,1);
		$ret []= $c;
		$c = new config_element('userbranch_label','userbranch','userbranch','userbranch','ou=People',NULL,1);
		$ret []= $c;
		$c = new config_element('uidprefix_label','uidprefix','uidprefix','uidprefix','uid',NULL,1);
		$ret []= $c;
		$c = new config_element('protocol_version_label', _('Protocol version'),  _('The protocol version used by your LDAP server.'), _('The protocol version used by your LDAP server.'), '3', NULL, 1);
		$ret []= $c;
		$c = new config_element('match',_('Matching'), _('Matching'), _('Matching'),array('login' => 'uid', 'uid' => 'uidnumber',  'displayname' => 'displayname'),NULL,4);
		$ret []= $c;
		return $ret;
	}
	
	public function prefsIsValid($prefs_) {
		$config_ldap = $prefs_->get('UserDB','ldap');
		$LDAP2 = new LDAP($config_ldap);
		$ret = $LDAP2->connect();
		$LDAP2->disconnect();
		
		return ($ret === true);
	}
	
	public static function prettyName() {
		return _('Lightweight Directory Access Protocol (LDAP)');
	}
}
