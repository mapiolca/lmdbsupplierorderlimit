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
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');

$searchType = GETPOST('search_type', 'aZ09');
$searchEntities = GETPOST('search_entities', 'array:int');
$searchEntities = is_array($searchEntities) ? array_map('intval', $searchEntities) : array();
$searchUser = GETPOSTINT('search_user');
$searchGroup = GETPOSTINT('search_group');
$searchActive = GETPOST('search_active', 'int');
$buttonSearch = GETPOSTISSET('button_search') || GETPOSTISSET('button_search_x') || GETPOSTISSET('button_search.x');
$buttonRemoveFilter = GETPOSTISSET('button_removefilter') || GETPOSTISSET('button_removefilter_x') || GETPOSTISSET('button_removefilter.x');

if (!$sortfield) {
	$sortfield = 't.active';
}
if (!$sortorder) {
	$sortorder = 'DESC';
}

if (empty($page) || $page < 0 || $buttonSearch || $buttonRemoveFilter) {
	$page = 0;
}
$offset = $limit * max(0, $page);

if ($buttonRemoveFilter) {
	$searchType = '';
	$searchEntities = array();
	$searchUser = 0;
	$searchGroup = 0;
	$searchActive = '';
}

if (!isModEnabled('lmdbsupplierorderlimit') || !empty($user->socid)) {
	accessforbidden();
}

$permissiontoread = $user->hasRight('lmdbsupplierorderlimit', 'limit', 'read');
$permissiontowrite = $user->hasRight('lmdbsupplierorderlimit', 'limit', 'write');
$permissiontodelete = $user->hasRight('lmdbsupplierorderlimit', 'limit', 'delete');

if (!$permissiontoread) {
	accessforbidden();
}
if (in_array($action, array('create','update','disable','create_form','edit'), true) && !$permissiontowrite) { accessforbidden(); }
if (in_array($action, array('delete','confirm_delete'), true) && !$permissiontodelete) { accessforbidden(); }

$sensitiveActions = array('create', 'update', 'disable', 'delete');
// CSRF is enforced natively by main.inc.php because CSRFCHECK_WITH_TOKEN is defined above.

try { $entityLabels = LmdbSupplierOrderLimitScope::labels($db); } catch (Throwable $e) { accessforbidden($langs->trans('LimitTechnicalError')); }
$typeLabels = array();
foreach (LmdbSupplierOrderLimitPolicy::TYPES as $type => $key) { $typeLabels[$type] = $langs->trans($key); }
$form = new Form($db);
$contextpage = 'lmdbsupplierorderlimit_limits';
$arrayfields = array(
	't.limit_type' => array('label'=>'LimitType','checked'=>1),
	't.entity' => array('label'=>'LimitEnvironment','checked'=>1,'enabled'=>!empty($entityLabels)),
	'u.lastname' => array('label'=>'LmdbSupplierOrderLimitUser','checked'=>1),
	'ug.nom' => array('label'=>'LmdbSupplierOrderLimitGroup','checked'=>1),
	't.amount_ht' => array('label'=>'LmdbSupplierOrderLimitAmountHt','checked'=>1),
	't.unlimited' => array('label'=>'LmdbSupplierOrderLimitUnlimited','checked'=>1),
	't.active' => array('label'=>'Status','checked'=>1),
	't.date_start' => array('label'=>'Date','checked'=>1),
);
require DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';
$selectedfields = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $contextpage);
if (!$entityLabels) { $arrayfields['t.entity']['checked'] = 0; }
$visibleFields = array_filter($arrayfields, static function ($field) { return !empty($field['checked']); });
$colspan = count($visibleFields) + 1;
$token = newToken();
$formObjectWithErrors = null;
$modalToOpen = '';

if (($action === 'create' || $action === 'update') && $permissiontowrite) {
	$object = new LmdbSupplierOrderLimitLimit($db);
	if ($action === 'update') {
		$fetchResult = $object->fetch($id);
		if ($fetchResult <= 0) {
			accessforbidden($langs->trans('LmdbSupplierOrderLimitRuleNotFound'));
		}
	}

	$fieldPrefixValue = GETPOST('field_prefix', 'aZ09');
	$fieldPrefix = $fieldPrefixValue !== '' ? $fieldPrefixValue.'_' : '';
	$targetType = GETPOST($fieldPrefix.'target_type', 'alpha');
	$object->id = $id;
	$object->rowid = $id;
	$object->fk_user = $targetType === 'user' ? GETPOSTINT($fieldPrefix.'fk_user') : null;
	$object->fk_usergroup = $targetType === 'group' ? GETPOSTINT($fieldPrefix.'fk_usergroup') : null;
	$object->limit_type = GETPOST($fieldPrefix.'limit_type', 'aZ09');
	$object->amount_ht = GETPOST($fieldPrefix.'amount_ht', 'restricthtml');
	$object->unlimited = GETPOSTINT($fieldPrefix.'unlimited');
	$object->active = GETPOSTINT($fieldPrefix.'active');
	$object->date_start = lmdbsupplierorderlimitAdminGetPostedDate($fieldPrefix.'date_start');
	$object->date_end = lmdbsupplierorderlimitAdminGetPostedDate($fieldPrefix.'date_end');
	$object->note_private = GETPOST($fieldPrefix.'note_private', 'restricthtml');

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

	$formObjectWithErrors = $object;
	$modalToOpen = $action === 'create' ? 'lmdbsupplierorderlimit-limit-modal-create' : 'lmdbsupplierorderlimit-limit-modal-edit-'.((int) $id);
	setEventMessages($object->error, $object->errors, 'errors');
}

if ($action === 'disable' && $permissiontowrite) {
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

$filters = array('limit_type' => $searchType, 'entities' => $searchEntities);
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
$totalnboflines = $listObject->countAll($filters);
if ($totalnboflines < 0) {
	setEventMessages($listObject->error, $listObject->errors, 'errors');
	$totalnboflines = 0;
}
$records = $listObject->fetchAll($limit, $offset, $filters, $sortfield, $sortorder);
if (!is_array($records)) {
	setEventMessages($listObject->error, $listObject->errors, 'errors');
	$records = array();
	$totalnboflines = 0;
}
$num = count($records);
if ($modalToOpen === '' && $permissiontowrite && $action === 'create_form') {
	$modalToOpen = 'lmdbsupplierorderlimit-limit-modal-create';
}
if ($modalToOpen === '' && $permissiontowrite && $action === 'edit' && $id > 0) {
	$modalToOpen = 'lmdbsupplierorderlimit-limit-modal-edit-'.((int) $id);
}

$param = '&search_type='.urlencode($searchType);
foreach ($searchEntities as $entityId) { $param .= '&search_entities[]='.(int) $entityId; }
if ($searchUser > 0) {
	$param .= '&search_user='.(int) $searchUser;
}
if ($searchGroup > 0) {
	$param .= '&search_group='.(int) $searchGroup;
}
if ($searchActive !== '') {
	$param .= '&search_active='.(int) $searchActive;
}
$paramList = $param.'&limit='.(int) $limit;
$listUrlParams = $param.'&sortfield='.urlencode($sortfield).'&sortorder='.urlencode($sortorder).'&page='.(int) $page.'&limit='.(int) $limit;

llxHeader('', $langs->trans('LmdbSupplierOrderLimitLimits'));

$linkback = lmdbsupplierorderlimitBackToModuleListLink();
print load_fiche_titre($langs->trans('LmdbSupplierOrderLimitLimits'), $linkback, 'title_setup');

$head = lmdbsupplierorderlimitAdminPrepareHead();
print dol_get_fiche_head($head, 'limits', $langs->trans('LmdbSupplierOrderLimit'), -1, 'supplier_order');

$newcardbutton = '';
if ($permissiontowrite) {
	$newcardbutton = dolGetButtonTitle(
		$langs->trans('New'),
		'',
		'fa fa-plus-circle',
		$_SERVER['PHP_SELF'].'?action=create_form&token='.$token.$listUrlParams,
		'lmdbsupplierorderlimit-open-create',
		1,
		array(
			'attr' => array(
				'class' => 'lmdbsupplierorderlimit-open-modal',
				'data-target' => '#lmdbsupplierorderlimit-limit-modal-create',
			),
		)
	);
}

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

print '<form id="limitfilter" method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$token.'">';
print '<input type="hidden" name="formfilteraction" value="list">';
print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'">';
print '<input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'">';
print '<input type="hidden" name="page" value="0">';

print_barre_liste($langs->trans('LmdbSupplierOrderLimitLimits'), $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, $totalnboflines, 'object_lmdbsupplierorderlimit', 0, $newcardbutton, '', $limit);

print '<div class="div-table-responsive-no-min"><table class="liste centpercent">';
print '<tr class="liste_titre_filter">';
foreach ($visibleFields as $field => $definition) {
	print '<td class="liste_titre">';
	if ($field === 't.limit_type') { print Form::selectarray('search_type', $typeLabels, $searchType, 1).ajax_combobox('search_type'); }
	elseif ($field === 't.entity') { print Form::multiselectarray('search_entities', $entityLabels, $searchEntities, 0, 0, 'minwidth200'); }
	elseif ($field === 'u.lastname') { print $form->select_dolusers($searchUser, 'search_user', 1, null, 0, '', '', (string) (getDolGlobalInt('MULTICOMPANY_TRANSVERSE_MODE') ? 1 : $conf->entity)); }
	elseif ($field === 'ug.nom') { print $form->select_dolgroups($searchGroup, 'search_group', 1, '', 0, '', array(), (int) $conf->entity); }
	elseif ($field === 't.active') { print Form::selectarray('search_active', array('1'=>$langs->trans('LmdbSupplierOrderLimitActive'),'0'=>$langs->trans('LmdbSupplierOrderLimitInactive')), $searchActive, 1).ajax_combobox('search_active'); }
	print '</td>';
}
print '<td class="liste_titre center maxwidthsearch">'.$form->showFilterButtons().'</td></tr><tr class="liste_titre">';
foreach ($visibleFields as $field => $definition) {
	print_liste_field_titre($langs->trans($definition['label']), $_SERVER['PHP_SELF'], $field, '', $paramList, '', $sortfield, $sortorder);
}
print '<td class="right">'.$selectedfields.'</td></tr>';
if (!$records) {
	print '<tr class="oddeven"><td colspan="'.$colspan.'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
foreach ($records as $record) {
	$localRule = (int) $record->entity === (int) $conf->entity;
	print '<tr class="oddeven">';
	foreach ($visibleFields as $field => $definition) {
		print '<td'.($field === 't.entity' ? ' align="center"' : '').'>';
		if ($field === 't.limit_type') { print dol_escape_htmltag($typeLabels[$record->limit_type] ?? $record->limit_type); }
		elseif ($field === 't.entity') {
			print '<div class="refidno multicompany-entity-card-container"><span class="fa fa-globe"></span><span class="multiselect-selected-title-text">'.dol_escape_htmltag($entityLabels[$record->entity] ?? '').'</span></div>';
		}
		elseif ($field === 'u.lastname') { print lmdbsupplierorderlimitRenderUserLink($db, $record); }
		elseif ($field === 'ug.nom') { print lmdbsupplierorderlimitRenderUserGroupLink($db, $record); }
		elseif ($field === 't.amount_ht') { print $record->limit_type === 'project_budget' ? $langs->trans('LimitNativeProjectBudget') : ($record->amount_ht !== null ? price($record->amount_ht) : ''); }
		elseif ($field === 't.unlimited') { print $langs->trans($record->unlimited ? 'Yes' : 'No'); }
		elseif ($field === 't.active') { print $record->getLibStatut(1); }
		elseif ($field === 't.date_start') { print ($record->date_start ? dol_print_date((int) $record->date_start, 'day') : '').' - '.($record->date_end ? dol_print_date((int) $record->date_end, 'day') : ''); }
		print '</td>';
	}
	print '<td class="right">';
	if ($permissiontowrite && $localRule) {
		print '<a class="editfielda lmdbsupplierorderlimit-open-modal" href="'.$_SERVER['PHP_SELF'].'?action=edit&id='.(int) $record->id.'&token='.$token.$listUrlParams.'" data-target="#lmdbsupplierorderlimit-limit-modal-edit-'.((int) $record->id).'">'.img_edit().'</a> ';
	}
	if ($permissiontowrite && $localRule && $record->active) {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=disable&id='.(int) $record->id.'&token='.$token.$listUrlParams.'">'.img_picto($langs->trans('Disable'), 'disable').'</a> ';
	}
	if ($permissiontodelete && $localRule) {
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=delete&id='.(int) $record->id.'&token='.$token.$listUrlParams.'">'.img_delete().'</a>';
	}
	print '</td></tr>';
}
print '</table></div>';
print '</form>';

if ($permissiontowrite) {
	$createObject = new LmdbSupplierOrderLimitLimit($db);
	if (is_object($formObjectWithErrors) && empty($formObjectWithErrors->id)) {
		$createObject = $formObjectWithErrors;
	}
	lmdbsupplierorderlimitPrintLimitModal($form, $createObject, $token, 'limitcreate', 'lmdbsupplierorderlimit-limit-modal-create', $langs->trans('New'));

	foreach ($records as $record) {
		if ((int) $record->entity !== (int) $conf->entity) { continue; }
		$editObject = $record;
		if (is_object($formObjectWithErrors) && (int) $formObjectWithErrors->id === (int) $record->id) {
			$editObject = $formObjectWithErrors;
		}
		lmdbsupplierorderlimitPrintLimitModal($form, $editObject, $token, 'limitedit'.((int) $record->id), 'lmdbsupplierorderlimit-limit-modal-edit-'.((int) $record->id), $langs->trans('Modify'));
	}

	lmdbsupplierorderlimitPrintLimitModalScript($modalToOpen);
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
 * Print a limit form inside a hidden dialog container.
 *
 * @param Form                        $form        Form helper
 * @param LmdbSupplierOrderLimitLimit $editObject  Limit object
 * @param string                      $token       CSRF token
 * @param string                      $fieldPrefix Unique HTML field prefix
 * @param string                      $modalId     Dialog HTML id
 * @param string                      $title       Dialog title
 * @return void
 */
function lmdbsupplierorderlimitPrintLimitModal($form, $editObject, $token, $fieldPrefix, $modalId, $title)
{
	global $langs, $typeLabels, $conf;

	$htmlPrefix = $fieldPrefix !== '' ? $fieldPrefix.'_' : '';
	$formId = $htmlPrefix.'limitform';
	$targetType = $editObject->fk_user ? 'user' : ($editObject->fk_usergroup ? 'group' : 'user');

	print '<div id="'.dol_escape_htmltag($modalId).'" class="lmdbsupplierorderlimit-modal" title="'.dol_escape_htmltag($title).'" style="display:none;">';
	print '<form id="'.dol_escape_htmltag($formId).'" method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.dol_escape_htmltag($token).'">';
	print '<input type="hidden" name="field_prefix" value="'.dol_escape_htmltag($fieldPrefix).'">';
	print '<input type="hidden" name="action" value="'.($editObject->id ? 'update' : 'create').'">';
	print '<input type="hidden" name="id" value="'.(int) $editObject->id.'">';
	print '<table class="noborder centpercent">';
	print '<tr><td class="fieldrequired">'.$langs->trans('LimitType').'</td><td>'.Form::selectarray($htmlPrefix.'limit_type', $typeLabels, $editObject->limit_type).ajax_combobox($htmlPrefix.'limit_type').'<br><span class="opacitymedium">'.$langs->trans('LimitNativeProjectBudgetHelp').'</span></td></tr>';
	print '<tr><td class="titlefieldcreate">'.$langs->trans('LmdbSupplierOrderLimitTargetType').'</td><td>';
	print '<select name="'.dol_escape_htmltag($htmlPrefix.'target_type').'" id="'.dol_escape_htmltag($htmlPrefix.'target_type').'" class="flat minwidth200">';
	print '<option value="user"'.($targetType === 'user' ? ' selected' : '').'>'.$langs->trans('LmdbSupplierOrderLimitUser').'</option>';
	print '<option value="group"'.($targetType === 'group' ? ' selected' : '').'>'.$langs->trans('LmdbSupplierOrderLimitGroup').'</option>';
	print '</select>'.ajax_combobox($htmlPrefix.'target_type');
	print '</td></tr>';
	print '<tr><td>'.$langs->trans('LmdbSupplierOrderLimitUser').'</td><td>'.$form->select_dolusers($editObject->fk_user, $htmlPrefix.'fk_user', 1, null, 0, '', '', (string) (getDolGlobalInt('MULTICOMPANY_TRANSVERSE_MODE') ? 1 : $conf->entity)).'</td></tr>';
	print '<tr><td>'.$langs->trans('LmdbSupplierOrderLimitGroup').'</td><td>'.$form->select_dolgroups($editObject->fk_usergroup, $htmlPrefix.'fk_usergroup', 1, '', 0, '', array(), (int) $conf->entity).'</td></tr>';
	print '<tr><td>'.$langs->trans('LmdbSupplierOrderLimitAmountHt').'</td><td><input class="flat right" type="text" name="'.dol_escape_htmltag($htmlPrefix.'amount_ht').'" value="'.dol_escape_htmltag((string) $editObject->amount_ht).'"></td></tr>';
	print '<tr><td>'.$langs->trans('LmdbSupplierOrderLimitUnlimited').'</td><td>'.$form->selectyesno($htmlPrefix.'unlimited', (int) $editObject->unlimited, 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('LmdbSupplierOrderLimitActive').'</td><td>'.$form->selectyesno($htmlPrefix.'active', (int) $editObject->active, 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('LmdbSupplierOrderLimitDateStart').'</td><td>'.$form->selectDate($editObject->date_start, $htmlPrefix.'date_start', 0, 0, 1, $formId, 1, 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('LmdbSupplierOrderLimitDateEnd').'</td><td>'.$form->selectDate($editObject->date_end, $htmlPrefix.'date_end', 0, 0, 1, $formId, 1, 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('LmdbSupplierOrderLimitNotePrivate').'</td><td><textarea class="flat centpercent" name="'.dol_escape_htmltag($htmlPrefix.'note_private').'" rows="3">'.dol_escape_htmltag((string) $editObject->note_private).'</textarea></td></tr>';
	print '<tr><td></td><td>';
	print '<input type="submit" class="button button-save" value="'.$langs->trans('Save').'">';
	print ' <button type="button" class="button button-cancel lmdbsupplierorderlimit-modal-close">'.$langs->trans('Cancel').'</button>';
	print '</td></tr>';
	print '</table>';
	print '</form>';
	print '</div>';
}

/**
 * Print dialog behavior for limit forms.
 *
 * @param string $modalToOpen Modal id to open after page load
 * @return void
 */
function lmdbsupplierorderlimitPrintLimitModalScript($modalToOpen)
{
	print '<script>';
	print 'jQuery(function($) {';
	print 'function openLimitDialog(selector) {';
	print 'var $dialog = $(selector);';
	print 'if (!$dialog.length) { return; }';
	print 'if (typeof $dialog.dialog === "function") {';
	print 'var dialogWidth = Math.min(Math.max($(window).width() - 40, 320), 900);';
	print 'var dialogHeight = Math.max($(window).height() - 40, 300);';
	print '$dialog.dialog({ modal: true, width: dialogWidth, maxHeight: dialogHeight, resizable: true, draggable: true });';
	print '} else {';
	print '$dialog.show();';
	print '}';
	print '}';
	print '$(document).on("click", ".lmdbsupplierorderlimit-open-modal", function(event) {';
	print 'event.preventDefault();';
	print 'openLimitDialog($(this).attr("data-target"));';
	print '});';
	print '$(document).on("click", ".lmdbsupplierorderlimit-modal-close", function() {';
	print 'var $dialog = $(this).closest(".lmdbsupplierorderlimit-modal");';
	print 'if (typeof $dialog.dialog === "function" && $dialog.hasClass("ui-dialog-content")) { $dialog.dialog("close"); } else { $dialog.hide(); }';
	print '});';
	if ($modalToOpen !== '') {
		print 'openLimitDialog("#'.dol_escape_js($modalToOpen).'");';
	}
	print '});';
	print '</script>';
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
