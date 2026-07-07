<?php
/* Copyright (C) 2026		Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

if (!defined('CSRFCHECK_WITH_TOKEN')) {
	define('CSRFCHECK_WITH_TOKEN', '1');
}

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/usergroup.class.php';
dol_include_once('/lmdbsupplierorderlimit/lib/lmdbsupplierorderlimit.lib.php');
dol_include_once('/lmdbsupplierorderlimit/class/lmdbsupplierorderlimitlimit.class.php');
dol_include_once('/lmdbsupplierorderlimit/class/lmdbsupplierorderlimitlog.class.php');

$langs->loadLangs(array('admin', 'users', 'lmdbsupplierorderlimit@lmdbsupplierorderlimit'));

$action = GETPOST('action', 'aZ09');
$id = GETPOSTINT('id');
$page = GETPOSTINT('page');
$limit = GETPOSTINT('limit') > 0 ? GETPOSTINT('limit') : getDolGlobalInt('MAIN_SIZE_LISTE_LIMIT', 20);
$offset = $limit * max(0, $page);

$searchUser = GETPOSTINT('search_user');
$searchGroup = GETPOSTINT('search_group');
$searchActive = GETPOST('search_active', 'int');

if (!isModEnabled('lmdbsupplierorderlimit')) {
	accessforbidden();
}

$permissiontoread = lmdbsupplierorderlimitUserCan($user, 'limit', 'read');
$permissiontowrite = lmdbsupplierorderlimitUserCan($user, 'limit', 'write');
$permissiontodelete = lmdbsupplierorderlimitUserCan($user, 'limit', 'delete');

if (!$permissiontoread) {
	accessforbidden();
}

$sensitiveActions = array('create', 'update', 'disable', 'delete');
// CSRF is enforced natively by main.inc.php because CSRFCHECK_WITH_TOKEN is defined above.

$form = new Form($db);
$token = newToken();

if (($action === 'create' || $action === 'update') && $permissiontowrite) {
	$object = new LmdbSupplierOrderLimitLimit($db);
	if ($action === 'update') {
		$fetchResult = $object->fetch($id);
		if ($fetchResult <= 0) {
			setEventMessages($langs->trans('LmdbSupplierOrderLimitRuleNotFound'), null, 'errors');
		}
	}

	$targetType = GETPOST('target_type', 'alpha');
	$object->id = $id;
	$object->rowid = $id;
	$object->fk_user = $targetType === 'user' ? GETPOSTINT('fk_user') : null;
	$object->fk_usergroup = $targetType === 'group' ? GETPOSTINT('fk_usergroup') : null;
	$object->amount_ht = GETPOST('amount_ht', 'restricthtml');
	$object->unlimited = GETPOSTINT('unlimited');
	$object->active = GETPOSTINT('active');
	$object->date_start = lmdbsupplierorderlimitAdminGetPostedDate('date_start');
	$object->date_end = lmdbsupplierorderlimitAdminGetPostedDate('date_end');
	$object->note_private = GETPOST('note_private', 'restricthtml');

	$result = $action === 'create' ? $object->create($user) : $object->update($user);
	if ($result > 0) {
		LmdbSupplierOrderLimitLog::createFromDecision($db, $user, $object, array(
			'allowed' => true,
			'reason' => $action === 'create' ? 'limit_create' : 'limit_update',
			'order_amount_ht' => null,
			'limit_amount_ht' => $object->amount_ht,
			'limit_unlimited' => $object->unlimited,
			'limit_source' => $object->fk_user ? 'user' : 'group',
			'fk_limit' => $object->id,
			'approval_level' => 0,
		), $action === 'create' ? 'limit_create' : 'limit_update', 'admin', $action === 'create' ? 'limit_create' : 'limit_update', true);
		setEventMessages($langs->trans($action === 'create' ? 'LmdbSupplierOrderLimitRuleCreated' : 'LmdbSupplierOrderLimitRuleUpdated'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	}

	setEventMessages($object->error, $object->errors, 'errors');
}

if ($action === 'disable' && $permissiontodelete) {
	$object = new LmdbSupplierOrderLimitLimit($db);
	$result = $object->fetch($id);
	if ($result > 0) {
		$result = $object->disable($user);
		if ($result > 0) {
			LmdbSupplierOrderLimitLog::createFromDecision($db, $user, $object, array(
				'allowed' => true,
				'reason' => 'limit_disable',
				'order_amount_ht' => null,
				'limit_amount_ht' => $object->amount_ht,
				'limit_unlimited' => $object->unlimited,
				'limit_source' => $object->fk_user ? 'user' : 'group',
				'fk_limit' => $object->id,
				'approval_level' => 0,
			), 'limit_disable', 'admin', 'limit_disable', true);
			setEventMessages($langs->trans('LmdbSupplierOrderLimitRuleDisabled'), null, 'mesgs');
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	} else {
		setEventMessages($langs->trans('LmdbSupplierOrderLimitRuleNotFound'), null, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

if ($action === 'confirm_delete' && GETPOST('confirm', 'alpha') === 'yes' && $permissiontodelete) {
	$object = new LmdbSupplierOrderLimitLimit($db);
	$result = $object->fetch($id);
	if ($result > 0) {
		$result = $object->delete($user);
		if ($result > 0) {
			LmdbSupplierOrderLimitLog::createFromDecision($db, $user, $object, array(
				'allowed' => true,
				'reason' => 'limit_delete',
				'order_amount_ht' => null,
				'limit_amount_ht' => $object->amount_ht,
				'limit_unlimited' => $object->unlimited,
				'limit_source' => $object->fk_user ? 'user' : 'group',
				'fk_limit' => $object->id,
				'approval_level' => 0,
			), 'limit_delete', 'admin', 'limit_delete', true);
			setEventMessages($langs->trans('LmdbSupplierOrderLimitRuleDeleted'), null, 'mesgs');
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	} else {
		setEventMessages($langs->trans('LmdbSupplierOrderLimitRuleNotFound'), null, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

$filters = array();
if ($searchUser > 0) {
	$filters['fk_user'] = $searchUser;
}
if ($searchGroup > 0) {
	$filters['fk_usergroup'] = $searchGroup;
}
if ($searchActive !== '') {
	$filters['active'] = (int) $searchActive;
}

$listObject = new LmdbSupplierOrderLimitLimit($db);
$num = $listObject->countAll($filters);
$records = $listObject->fetchAll($limit, $offset, $filters);
if (!is_array($records)) {
	setEventMessages($listObject->error, $listObject->errors, 'errors');
	$records = array();
	$num = 0;
}

$param = '';
if ($searchUser > 0) {
	$param .= '&search_user='.(int) $searchUser;
}
if ($searchGroup > 0) {
	$param .= '&search_group='.(int) $searchGroup;
}
if ($searchActive !== '') {
	$param .= '&search_active='.(int) $searchActive;
}

llxHeader('', $langs->trans('LmdbSupplierOrderLimitLimits'));

$linkback = lmdbsupplierorderlimitBackToModuleListLink();
print load_fiche_titre($langs->trans('LmdbSupplierOrderLimitLimits'), $linkback, 'title_setup');

$head = lmdbsupplierorderlimitAdminPrepareHead();
print dol_get_fiche_head($head, 'limits', $langs->trans('LmdbSupplierOrderLimit'), -1, 'supplier_order');

print_barre_liste($langs->trans('LmdbSupplierOrderLimitLimits'), $page, $_SERVER['PHP_SELF'], $param, '', '', '', $num, $num, 'object_lmdbsupplierorderlimit', 0, '', '', $limit);

if ($action === 'delete' && $permissiontodelete && $id > 0) {
	print $form->formconfirm(
		$_SERVER['PHP_SELF'].'?id='.(int) $id.$param,
		$langs->trans('Delete'),
		$langs->trans('ConfirmDeleteObject'),
		'confirm_delete',
		'',
		0,
		1
	);
}

print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'">';
print '<table class="liste centpercent">';
print '<tr class="liste_titre_filter">';
print '<td>'.$form->select_dolusers($searchUser, 'search_user', 1).'</td>';
print '<td>'.$form->select_dolgroups($searchGroup, 'search_group', 1).'</td>';
print '<td></td>';
print '<td></td>';
print '<td>'.$form->selectyesno('search_active', $searchActive, 1, false, 1).'</td>';
print '<td></td>';
print '<td class="center"><input type="submit" class="button small" value="'.$langs->trans('Search').'"></td>';
print '</tr>';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('LmdbSupplierOrderLimitUser').'</th>';
print '<th>'.$langs->trans('LmdbSupplierOrderLimitGroup').'</th>';
print '<th class="right">'.$langs->trans('LmdbSupplierOrderLimitAmountHt').'</th>';
print '<th class="center">'.$langs->trans('LmdbSupplierOrderLimitUnlimited').'</th>';
print '<th class="center">'.$langs->trans('Status').'</th>';
print '<th>'.$langs->trans('Date').'</th>';
print '<th class="right">'.$langs->trans('Action').'</th>';
print '</tr>';

if (count($records) === 0) {
	print '<tr class="oddeven"><td colspan="7"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}

foreach ($records as $record) {
	print '<tr class="oddeven">';
	print '<td>'.lmdbsupplierorderlimitRenderUserLink($db, $record).'</td>';
	print '<td>'.lmdbsupplierorderlimitRenderUserGroupLink($db, $record).'</td>';
	print '<td class="right">'.($record->amount_ht !== null ? price((float) $record->amount_ht) : '').'</td>';
	print '<td class="center">'.($record->unlimited ? $langs->trans('Yes') : $langs->trans('No')).'</td>';
	print '<td class="center">'.$record->getLibStatut(1).'</td>';
	print '<td>'.(!empty($record->date_start) ? dol_print_date((int) $record->date_start, 'day') : '').' - '.(!empty($record->date_end) ? dol_print_date((int) $record->date_end, 'day') : '').'</td>';
	print '<td class="right">';
	if ($permissiontowrite) {
		print '<a class="editfielda" href="'.$_SERVER['PHP_SELF'].'?action=edit&id='.(int) $record->id.'&token='.$token.$param.'">'.img_edit().'</a> ';
	}
	if ($permissiontodelete && !empty($record->active)) {
		print '<a class="reposition" href="'.$_SERVER['PHP_SELF'].'?action=disable&id='.(int) $record->id.'&token='.$token.$param.'">'.img_picto($langs->trans('Disable'), 'disable').'</a> ';
	}
	if ($permissiontodelete) {
		print '<a class="reposition" href="'.$_SERVER['PHP_SELF'].'?action=delete&id='.(int) $record->id.'&token='.$token.$param.'">'.img_delete().'</a>';
	}
	print '</td>';
	print '</tr>';
}

print '</table>';
print '</form>';

if ($permissiontowrite) {
	$editObject = new LmdbSupplierOrderLimitLimit($db);
	if ($action === 'edit' && $id > 0) {
		$editObject->fetch($id);
	}

	$targetType = $editObject->fk_user ? 'user' : ($editObject->fk_usergroup ? 'group' : 'user');
	print '<br>';
	print '<form id="limitform" method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.$token.'">';
	print '<input type="hidden" name="action" value="'.($editObject->id ? 'update' : 'create').'">';
	print '<input type="hidden" name="id" value="'.(int) $editObject->id.'">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td colspan="2">'.($editObject->id ? $langs->trans('Modify') : $langs->trans('New')).'</td></tr>';
	print '<tr><td class="titlefieldcreate">'.$langs->trans('LmdbSupplierOrderLimitTargetType').'</td><td>';
	print '<select name="target_type" id="target_type" class="flat minwidth200">';
	print '<option value="user"'.($targetType === 'user' ? ' selected' : '').'>'.$langs->trans('LmdbSupplierOrderLimitUser').'</option>';
	print '<option value="group"'.($targetType === 'group' ? ' selected' : '').'>'.$langs->trans('LmdbSupplierOrderLimitGroup').'</option>';
	print '</select>'.ajax_combobox('target_type');
	print '</td></tr>';
	print '<tr><td>'.$langs->trans('LmdbSupplierOrderLimitUser').'</td><td>'.$form->select_dolusers($editObject->fk_user, 'fk_user', 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('LmdbSupplierOrderLimitGroup').'</td><td>'.$form->select_dolgroups($editObject->fk_usergroup, 'fk_usergroup', 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('LmdbSupplierOrderLimitAmountHt').'</td><td><input class="flat right" type="text" name="amount_ht" value="'.dol_escape_htmltag((string) $editObject->amount_ht).'"></td></tr>';
	print '<tr><td>'.$langs->trans('LmdbSupplierOrderLimitUnlimited').'</td><td>'.$form->selectyesno('unlimited', (int) $editObject->unlimited, 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('LmdbSupplierOrderLimitActive').'</td><td>'.$form->selectyesno('active', (int) $editObject->active, 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('LmdbSupplierOrderLimitDateStart').'</td><td>'.$form->selectDate($editObject->date_start, 'date_start', 0, 0, 1, 'limitform', 1, 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('LmdbSupplierOrderLimitDateEnd').'</td><td>'.$form->selectDate($editObject->date_end, 'date_end', 0, 0, 1, 'limitform', 1, 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('LmdbSupplierOrderLimitNotePrivate').'</td><td><textarea class="flat centpercent" name="note_private" rows="3">'.dol_escape_htmltag((string) $editObject->note_private).'</textarea></td></tr>';
	print '<tr><td></td><td><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></td></tr>';
	print '</table>';
	print '</form>';
}

print dol_get_fiche_end();

llxFooter();
$db->close();

/**
 * Read a native Dolibarr selectDate() submission.
 *
 * @param string $prefix Date prefix
 * @return int|null
 */
function lmdbsupplierorderlimitAdminGetPostedDate($prefix)
{
	$year = GETPOSTINT($prefix.'year');
	$month = GETPOSTINT($prefix.'month');
	$day = GETPOSTINT($prefix.'day');

	if ($year <= 0 || $month <= 0 || $day <= 0) {
		return null;
	}

	return dol_mktime(0, 0, 0, $month, $day, $year);
}

/**
 * Render the linked user with the native user photo.
 *
 * @param DoliDB                      $db     Database handler
 * @param LmdbSupplierOrderLimitLimit $record Limit record
 * @return string
 */
function lmdbsupplierorderlimitRenderUserLink($db, $record)
{
	if (empty($record->fk_user)) {
		return '';
	}

	if ($record->user_login === null && $record->user_lastname === null && $record->user_firstname === null) {
		return '<span class="opacitymedium">#'.((int) $record->fk_user).'</span>';
	}

	$userstatic = new User($db);
	$userstatic->id = (int) $record->fk_user;
	$userstatic->rowid = (int) $record->fk_user;
	$userstatic->login = (string) $record->user_login;
	$userstatic->lastname = (string) $record->user_lastname;
	$userstatic->firstname = (string) $record->user_firstname;
	$userstatic->photo = (string) $record->user_photo;
	$userstatic->status = $record->user_status !== null ? (int) $record->user_status : 1;
	$userstatic->statut = $userstatic->status;
	$userstatic->email = (string) $record->user_email;
	$userstatic->admin = (int) $record->user_admin;
	$userstatic->entity = $record->user_entity !== null ? (int) $record->user_entity : 0;

	return $userstatic->getNomUrl(-1);
}

/**
 * Render the linked user group with the native group URL.
 *
 * @param DoliDB                      $db     Database handler
 * @param LmdbSupplierOrderLimitLimit $record Limit record
 * @return string
 */
function lmdbsupplierorderlimitRenderUserGroupLink($db, $record)
{
	if (empty($record->fk_usergroup)) {
		return '';
	}

	if ($record->group_name === null || $record->group_name === '') {
		return '<span class="opacitymedium">#'.((int) $record->fk_usergroup).'</span>';
	}

	$groupstatic = new UserGroup($db);
	$groupstatic->id = (int) $record->fk_usergroup;
	$groupstatic->rowid = (int) $record->fk_usergroup;
	$groupstatic->name = (string) $record->group_name;
	$groupstatic->ref = (string) $record->group_name;

	return $groupstatic->getNomUrl(1);
}
