<?php
/* Copyright (C) 2025  Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *  \file       custom/timesheetweek/core/class/cemailtemplate.class.php
 *  \ingroup    timesheetweek
 *  \brief      Backport of Dolibarr email template classes for versions prior to v23.
 */

// Load Dolibarr environment
if (!defined('DOL_DOCUMENT_ROOT')) {
	die('This file must be included inside Dolibarr.');
}

dol_include_once('/core/lib/functions.lib.php');

/**
 * Backport of the single e-mail template class used before Dolibarr v23.
 */
class CEmailTemplate
{
	/** @var DoliDB */
	public $db;
	public $id;
	public $rowid;
	public $entity = 1;
	public $module = '';
	public $type_template = '';
	public $label = '';
	public $lang = '';
	public $private = 0;
	public $fk_user;
	public $position = 0;
	public $active = 1;
	public $enabled = 1;
	public $joinfiles = 0;
	public $email_from = '';
	public $email_to = '';
	public $email_tocc = '';
	public $email_tobcc = '';
	public $topic = '';
	public $subject = '';
	public $content = '';
	public $code = '';
	public $error = '';
	public $errors = array();

	/**
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Fetch one template by id.
	 *
	 * @param int $id Template id
	 * @return int
	 */
	public function fetch($id)
	{
		$fields = $this->buildSelectFields();
		$sql = 'SELECT '.implode(', ', $fields).' FROM '.$this->getTableName().' WHERE rowid = '.((int) $id).' LIMIT 1';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$obj = $this->db->fetch_object($resql);
		if (!$obj) {
			return 0;
		}

		$this->setProperties($obj);

		return 1;
	}

	/**
	 * API compatible wrapper around fetch.
	 *
	 * @param int $id Template id
	 * @return int
	 */
	public function apifetch($id)
	{
		return $this->fetch($id);
	}

	/**
	 * Create a new template record.
	 *
	 * @param User $user Creator
	 * @return int
	 */
	public function create($user)
	{
		$fields = array('entity', 'module', 'type_template', 'label', 'lang', 'private', 'fk_user', 'position', 'active', 'enabled', 'joinfiles', 'email_from', 'email_to', 'email_tocc', 'email_tobcc', 'topic', 'content', 'code', 'subject');
		$columns = array();
		$values = array();

		foreach ($fields as $field) {
			if (!$this->columnExists($field)) {
				continue;
			}

			$value = $this->$field;
			if ($field === 'entity') {
				$value = (int) (isset($this->entity) ? $this->entity : $user->entity);
			}
			if ($field === 'fk_user') {
				$value = isset($this->fk_user) ? (int) $this->fk_user : (int) $user->id;
			}

			$columns[] = $field;
			$values[] = "'".$this->db->escape($value)."'";
		}

		if ($this->columnExists('datec')) {
			$columns[] = 'datec';
			$values[] = $this->db->idate(dol_now());
		}

		$sql = 'INSERT INTO '.$this->getTableName().' ('.implode(', ', $columns).') VALUES ('.implode(', ', $values).')';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$this->id = $this->db->last_insert_id($this->getTableName(), 'rowid');
		$this->rowid = $this->id;

		return $this->id > 0 ? $this->id : 1;
	}

	/**
	 * Build the list of existing columns to select.
	 *
	 * @return array
	 */
	protected function buildSelectFields()
	{
		$possible = array('rowid', 'entity', 'module', 'type_template', 'label', 'lang', 'private', 'fk_user', 'position', 'active', 'enabled', 'joinfiles', 'email_from', 'email_to', 'email_tocc', 'email_tobcc', 'topic', 'content', 'code', 'subject');
		$fields = array();

		foreach ($possible as $field) {
			if ($this->columnExists($field)) {
				$fields[] = $field;
			}
		}

		return empty($fields) ? array('*') : $fields;
	}

	/**
	 * Check if a column exists.
	 *
	 * @param string $column Column name
	 * @return bool
	 */
	protected function columnExists($column)
	{
		static $cache = array();
		if (isset($cache[$column])) {
			return $cache[$column];
		}

		$table = MAIN_DB_PREFIX.'c_email_templates';
		$sql = "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='".$this->db->escape($table)."' AND COLUMN_NAME='".$this->db->escape($column)."' LIMIT 1";
		$resql = $this->db->query($sql);
		$exists = false;
		if ($resql) {
			$exists = (bool) $this->db->fetch_object($resql);
		}

		$cache[$column] = $exists;

		return $exists;
	}

	/**
	 * Hydrate class properties from database row.
	 *
	 * @param stdClass $row Data row
	 * @return void
	 */
	protected function setProperties($row)
	{
		foreach ($row as $key => $value) {
			$this->$key = $value;
		}

		$this->id = isset($row->rowid) ? $row->rowid : $this->id;
		$this->rowid = $this->id;

		if (empty($this->subject) && !empty($this->topic)) {
			$this->subject = $this->topic;
		}
	}

	/**
	 * Get the table name with prefix.
	 *
	 * @return string
	 */
	protected function getTableName()
	{
		return MAIN_DB_PREFIX.'c_email_templates';
	}
}

/**
 * Backport of the e-mail templates collection class.
 */
class CEmailTemplates extends CEmailTemplate
{
	/**
	 * Fetch a template by trigger code.
	 *
	 * @param string   $action Trigger code
	 * @param User     $user   Current user
	 * @param int|null $entity Entity id
	 * @return int
	 */
	public function fetchByTrigger($action, $user = null, $entity = null)
	{
		$fields = $this->buildSelectFields();
		$where = array();
		$entityId = is_null($entity) ? 1 : (int) $entity;

		if ($this->columnExists('code')) {
			$where[] = "code='".$this->db->escape($action)."'";
		} else {
			$where[] = "label='".$this->db->escape($action)."'";
		}

		if ($this->columnExists('entity')) {
			$where[] = 'entity IN ('.$entityId.', 0)';
		}

		$sql = 'SELECT '.implode(', ', $fields).' FROM '.$this->getTableName();
		if (!empty($where)) {
			$sql .= ' WHERE '.implode(' AND ', $where);
		}
		$sql .= ' ORDER BY entity DESC';
		$sql .= $this->columnExists('position') ? ' , position ASC' : '';
		$sql .= ' LIMIT 1';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$row = $this->db->fetch_object($resql);
		if (!$row) {
			return 0;
		}

		$this->setProperties($row);

		return 1;
	}

	/**
	 * Fetch all templates according to filters.
	 *
	 * @param string $sortorder Sort order
	 * @param string $sortfield Sort field
	 * @param int    $limit     Limit
	 * @param int    $offset    Offset
	 * @param array  $filter    Filter array
	 * @return array|int
	 */
	public function fetchAll($sortorder = '', $sortfield = '', $limit = 0, $offset = 0, $filter = array())
	{
		$fields = $this->buildSelectFields();
		$sql = 'SELECT '.implode(', ', $fields).' FROM '.$this->getTableName();

		$where = array();
		if (!empty($filter['entity']) && $this->columnExists('entity')) {
			$where[] = 'entity IN ('.implode(',', array_map('intval', (array) $filter['entity'])).')';
		}
		if (!empty($filter['type_template']) && $this->columnExists('type_template')) {
			$where[] = "type_template='".$this->db->escape($filter['type_template'])."'";
		}
		if (!empty($filter['module']) && $this->columnExists('module')) {
			$where[] = "module='".$this->db->escape($filter['module'])."'";
		}
		if (isset($filter['active']) && $this->columnExists('active')) {
			$where[] = 'active='.(int) $filter['active'];
		}
		if (isset($filter['enabled']) && $this->columnExists('enabled')) {
			$where[] = 'enabled='.(int) $filter['enabled'];
		}

		if (!empty($where)) {
			$sql .= ' WHERE '.implode(' AND ', $where);
		}

		if (!empty($sortfield) && $this->columnExists($sortfield)) {
			$order = !empty($sortorder) ? $sortorder : 'ASC';
			$sql .= ' ORDER BY '.$this->db->escape($sortfield).' '.$this->db->escape($order);
		}

		if ((int) $limit > 0) {
			$sql .= ' '.$this->db->plimit((int) $limit, (int) $offset);
		}

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$templates = array();
		while ($row = $this->db->fetch_object($resql)) {
			$template = new CEmailTemplate($this->db);
			$template->setProperties($row);
			$templates[] = $template;
		}

		return $templates;
	}
}
