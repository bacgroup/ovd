<?php
/**
 * Copyright (C) 2009 Ulteo SAS
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
class UserGroupDB_ldap_memberof {
	public function __construct() {
	}
	
	public function __toString() {
		$ret = get_class($this).'()';
		return $ret;
	}
	
	public function import($id_) {
		Logger::debug('main',"UserGroupDB::ldap_memberof::import (id = $id_)");
		$prefs = Preferences::getInstance();
		if (! $prefs)
			die_error('get Preferences failed',__FILE__,__LINE__);
		
		$config_ldap = $prefs->get('UserDB','ldap');
		
		$config_ldap['match'] =  array('description' => 'description','name' => 'name');
		if (str_endswith(strtolower($id_),strtolower($config_ldap['suffix'])) === true) {
			$id2 = substr($id_,0, -1*strlen($config_ldap['suffix']) -1);
		}
		else
		{
			$id2 = $id_;
		}
		$expl = explode(',',$id2,2);
		if (count($expl) == 1) {
			$expl = array($id2, '');
		}
		$config_ldap['userbranch'] = $expl[1];

		$buf = $config_ldap['match'];
		$buf['id'] = $id_;
		$ldap = new LDAP($config_ldap);
		$sr = $ldap->search($expl[0], array_keys($config_ldap['match']));
		if ($sr === false) {
			Logger::error('main',"UserGroupDB::ldap_memberof::import search failed for ($id_)");
			return NULL;
		}
		$infos = $ldap->get_entries($sr);
		if ($infos === array()) {
			Logger::error('main',"UserGroupDB::ldap_memberof::import get_entries failed for ($id_)");
			return NULL;
		}
		$keys = array_keys($infos);
		$dn = $keys[0];
		$info = $infos[$dn];
		foreach ($config_ldap['match'] as $attribut => $match_ldap){
			if (isset($info[$match_ldap][0])) {
				$buf[$attribut] = $info[$match_ldap][0];
			}
		}
		$ug = new UsersGroup($buf['id'], $buf['name'], $buf['description'], true);
		return $ug;
	}
	
	public function isWriteable(){
		return false;
	}
	
	public function canShowList(){
		return true;
	}
	
	public function getList() {
		Logger::debug('main','UserGroupDB::ldap_memberof::getList');
		$prefs = Preferences::getInstance();
		if (! $prefs)
			die_error('get Preferences failed',__FILE__,__LINE__);
		
		$mods_enable = $prefs->get('general','module_enable');
		if (! in_array('UserDB',$mods_enable))
			die_error(_('Module UserDB must be enabled'),__FILE__,__LINE__);
		
		$userDB = UserDB::getInstance();
		
		$users = $userDB->getList();

		$groups = array();
		foreach ($users as $u) {
			if ($u->hasAttribute('memberof')) {
				$memberof = $u->getAttribute('memberof');
				if (! is_array($memberof))
					$memberof = array($memberof);
				foreach ($memberof as $group_name) {
					$ug = $this->import($group_name);
					if (is_object($ug))
						$groups[$group_name] = $ug;
				}
			}
		}
		return $groups;
	}
	
	public static function configuration() {
		return array();
	}
	
	public static function prefsIsValid($prefs_, &$log=array()) {
		// FIXME : liaison to ad
		return true;
	}
	
	public static function prettyName() {
		return _('LDAP using memberOf');
	}
	
	public static function isDefault() {
		return false;
	}
	
	public static function liaisonType() {
		return 'ldap_memberof';
	}
}
