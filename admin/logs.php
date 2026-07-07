<?php
/* Copyright (C) 2026		Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
dol_include_once('/lmdbsupplierorderlimit/lib/lmdbsupplierorderlimit.lib.php');
dol_include_once('/lmdbsupplierorderlimit/class/lmdbsupplierorderlimitlog.class.php');

$langs->loadLangs(array('admin', 'users', 'lmdbsupplierorderlimit@lmdbsupplierorderlimit'));

$page = GETPOSTINT('page');
$limit = GETPOSTINT('limit') > 0 ? GETPOSTINT('limit') : getDolGlobalInt('MAIN_SIZE_LISTE_LIMIT', 20);
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');

$searchUser = GETPOSTINT('search_user');
$searchSupplierOrder = GETPOST('search_supplier_order', 'restricthtml');
$searchDecision = GETPOST('search_decision', 'int');
$searchReason = GETPOST('search_reason', 'alphanohtml');
$searchEventType = GETPOST('search_event_type', 'alphanohtml');
$buttonSearch = GETPOSTISSET('button_search') || GETPOSTISSET('button_search_x') || GETPOSTISSET('button_search.x');
$buttonRemoveFilter = GETPOSTISSET('button_removefilter') || GETPOSTISSET('button_removefilter_x') || GETPOSTISSET('button_removefilter.x');

if (!$sortfield) {
	$sortfield = 't.date_creation';
}
if (!$sortorder) {
	$sortorder = 'DESC';
}

if (empty($page) || $page < 0 || $buttonSearch || $buttonRemoveFilter) {
	$page = 0;
}
$offset = $limit * max(0, $page);

if ($buttonRemoveFilter) {
	$searchUser = 0;
	$searchSupplierOrder = '';
	$searchDecision = '';
	$searchReason = '';
	$searchEventType = '';
	$searchDateStart = null;
	$searchDateEnd = null;
} else {
	$searchDateStart = lmdbsupplierorderlimitLogsGetPostedDate('search_date_start');
	$searchDateEnd = lmdbsupplierorderlimitLogsGetPostedDate('search_date_end', true);
}

if (!isModEnabled('lmdbsupplierorderlimit')) {
	accessforbidden();
}

if (!lmdbsupplierorderlimitUserCan($user, 'log', 'read')) {
	accessforbidden();
}

$filters = array();
if ($searchUser > 0) {
	$filters['fk_user_action'] = $searchUser;
}
if ($searchSupplierOrder !== '') {
	$filters['supplier_order'] = $searchSupplierOrder;
}
if ($searchDecision !== '') {
	$filters['decision'] = (int) $searchDecision;
}
if ($searchReason !== '') {
	$filters['reason_code'] = $searchReason;
}
if ($searchEventType !== '') {
	$filters['event_type'] = $searchEventType;
}
if ($searchDateStart !== null) {
	$filters['date_start'] = $searchDateStart;
}
if ($searchDateEnd !== null) {
	$filters['date_end'] = $searchDateEnd;
}

$logObject = new LmdbSupplierOrderLimitLog($db);
$num = $logObject->countAll($filters);
$records = $logObject->fetchAll($limit, $offset, $filters, $sortfield, $sortorder);
if (!is_array($records)) {
	setEventMessages($logObject->error, $logObject->errors, 'errors');
	$records = array();
	$num = 0;
}

$param = '';
if ($searchUser > 0) {
	$param .= '&search_user='.(int) $searchUser;
}
if ($searchSupplierOrder !== '') {
	$param .= '&search_supplier_order='.urlencode($searchSupplierOrder);
}
if ($searchDecision !== '') {
	$param .= '&search_decision='.(int) $searchDecision;
}
if ($searchReason !== '') {
	$param .= '&search_reason='.urlencode($searchReason);
}
if ($searchEventType !== '') {
	$param .= '&search_event_type='.urlencode($searchEventType);
}
foreach (array('search_date_start', 'search_date_end') as $datePrefix) {
	$year = GETPOSTINT($datePrefix.'year');
	$month = GETPOSTINT($datePrefix.'month');
	$day = GETPOSTINT($datePrefix.'day');
	if ($year > 0 && $month > 0 && $day > 0) {
		$param .= '&'.$datePrefix.'year='.(int) $year;
		$param .= '&'.$datePrefix.'month='.(int) $month;
		$param .= '&'.$datePrefix.'day='.(int) $day;
	}
}
$paramList = $param.'&limit='.(int) $limit;

$form = new Form($db);

llxHeader('', $langs->trans('LmdbSupplierOrderLimitLogs'));

$linkback = lmdbsupplierorderlimitBackToModuleListLink();
print load_fiche_titre($langs->trans('LmdbSupplierOrderLimitLogs'), $linkback, 'title_setup');

$head = lmdbsupplierorderlimitAdminPrepareHead();
print dol_get_fiche_head($head, 'logs', $langs->trans('LmdbSupplierOrderLimit'), -1, 'supplier_order');

print_barre_liste($langs->trans('LmdbSupplierOrderLimitLogs'), $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, $num, 'object_lmdbsupplierorderlimit', 0, '', '', $limit);

print '<form id="logfilter" method="GET" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'">';
print '<input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'">';
print '<input type="hidden" name="limit" value="'.((int) $limit).'">';
print '<table class="liste centpercent">';
print '<tr class="liste_titre_filter">';
print '<td class="liste_titre">'.$form->select_dolusers($searchUser, 'search_user', 1).'</td>';
print '<td class="liste_titre"><input class="flat maxwidth100" type="text" name="search_supplier_order" value="'.dol_escape_htmltag((string) $searchSupplierOrder).'"></td>';
print '<td class="liste_titre center">';
print '<select class="flat maxwidth100" name="search_decision" id="search_decision">';
print '<option value=""'.($searchDecision === '' ? ' selected' : '').'></option>';
print '<option value="1"'.($searchDecision !== '' && (int) $searchDecision === 1 ? ' selected' : '').'>'.$langs->trans('LmdbSupplierOrderLimitAllowed').'</option>';
print '<option value="0"'.($searchDecision !== '' && (int) $searchDecision === 0 ? ' selected' : '').'>'.$langs->trans('LmdbSupplierOrderLimitDenied').'</option>';
print '</select>'.ajax_combobox('search_decision');
print '</td>';
print '<td class="liste_titre"><input class="flat" type="text" name="search_event_type" value="'.dol_escape_htmltag($searchEventType).'"></td>';
print '<td class="liste_titre"><input class="flat" type="text" name="search_reason" value="'.dol_escape_htmltag($searchReason).'"></td>';
print '<td class="liste_titre">'.$form->selectDate($searchDateStart, 'search_date_start', 0, 0, 1, 'logfilter', 1, 1).' '.$form->selectDate($searchDateEnd, 'search_date_end', 0, 0, 1, 'logfilter', 1, 1).'</td>';
print '<td class="liste_titre center maxwidthsearch">'.$form->showFilterButtons().'</td>';
print '</tr>';
print '<tr class="liste_titre">';
print_liste_field_titre($langs->trans('User'), $_SERVER['PHP_SELF'], 'u.lastname', '', $paramList, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('LmdbSupplierOrderLimitSupplierOrder'), $_SERVER['PHP_SELF'], 'cf.ref', '', $paramList, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('LmdbSupplierOrderLimitDecision'), $_SERVER['PHP_SELF'], 't.decision', '', $paramList, '', $sortfield, $sortorder, 'center ');
print_liste_field_titre($langs->trans('LmdbSupplierOrderLimitEventType'), $_SERVER['PHP_SELF'], 't.event_type', '', $paramList, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('LmdbSupplierOrderLimitReason'), $_SERVER['PHP_SELF'], 't.reason_code', '', $paramList, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Date'), $_SERVER['PHP_SELF'], 't.date_creation', '', $paramList, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('LmdbSupplierOrderLimitMessage'), $_SERVER['PHP_SELF'], 't.message', '', $paramList, '', $sortfield, $sortorder);
print '</tr>';

if (count($records) === 0) {
	print '<tr class="oddeven"><td colspan="7"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}

foreach ($records as $record) {
	print '<tr class="oddeven">';
	print '<td>'.lmdbsupplierorderlimitRenderLogUserLink($db, $record).'</td>';
	print '<td>'.lmdbsupplierorderlimitRenderSupplierOrderLink($db, $record).'</td>';
	print '<td class="center"><span class="badge '.($record->decision ? 'badge-status4' : 'badge-status8').'">'.($record->decision ? $langs->trans('LmdbSupplierOrderLimitAllowed') : $langs->trans('LmdbSupplierOrderLimitDenied')).'</span></td>';
	print '<td>'.dol_escape_htmltag(lmdbsupplierorderlimitTranslateLogCode((string) $record->event_type, 'Event')).'</td>';
	print '<td>'.dol_escape_htmltag(lmdbsupplierorderlimitTranslateLogCode((string) $record->reason_code, 'Reason')).'</td>';
	print '<td>'.dol_print_date((int) $record->date_creation, 'dayhour').'</td>';
	print '<td>'.dol_escape_htmltag(lmdbsupplierorderlimitTranslateLogMessage($record)).'</td>';
	print '</tr>';
}

print '</table>';
print '</form>';

print dol_get_fiche_end();

llxFooter();
$db->close();

/**
 * Render the linked user with native photo.
 *
 * @param DoliDB                    $db     Database handler
 * @param LmdbSupplierOrderLimitLog $record Log record
 * @return string
 */
function lmdbsupplierorderlimitRenderLogUserLink($db, $record)
{
	if (empty($record->fk_user_action)) {
		return '';
	}

	if ($record->user_login === null && $record->user_lastname === null && $record->user_firstname === null) {
		return '<span class="opacitymedium">#'.((int) $record->fk_user_action).'</span>';
	}

	$userstatic = new User($db);
	$userstatic->id = (int) $record->fk_user_action;
	$userstatic->rowid = (int) $record->fk_user_action;
	$userstatic->login = (string) $record->user_login;
	$userstatic->lastname = (string) $record->user_lastname;
	$userstatic->firstname = (string) $record->user_firstname;
	$userstatic->photo = (string) $record->user_photo;
	$userstatic->status = $record->user_status !== null ? (int) $record->user_status : 1;
	$userstatic->statut = $userstatic->status;
	$userstatic->email = (string) $record->user_email;
	$userstatic->admin = $record->user_admin !== null ? (int) $record->user_admin : 0;
	$userstatic->entity = $record->user_entity !== null ? (int) $record->user_entity : 0;

	return $userstatic->getNomUrl(-1);
}

/**
 * Render the linked supplier order with native URL.
 *
 * @param DoliDB                    $db     Database handler
 * @param LmdbSupplierOrderLimitLog $record Log record
 * @return string
 */
function lmdbsupplierorderlimitRenderSupplierOrderLink($db, $record)
{
	if (empty($record->fk_supplier_order)) {
		return '';
	}

	if ($record->supplier_order_ref === null || $record->supplier_order_ref === '') {
		return '<span class="opacitymedium">#'.((int) $record->fk_supplier_order).'</span>';
	}

	$orderstatic = new CommandeFournisseur($db);
	$orderstatic->id = (int) $record->fk_supplier_order;
	$orderstatic->rowid = (int) $record->fk_supplier_order;
	$orderstatic->ref = (string) $record->supplier_order_ref;
	$orderstatic->statut = $record->supplier_order_status !== null ? (int) $record->supplier_order_status : 0;
	$orderstatic->status = $orderstatic->statut;
	$orderstatic->entity = $record->supplier_order_entity !== null ? (int) $record->supplier_order_entity : 0;
	$orderstatic->total_ht = $record->supplier_order_total_ht !== null ? (string) $record->supplier_order_total_ht : '';

	return $orderstatic->getNomUrl(1);
}

/**
 * Translate a technical log code when a module translation exists.
 *
 * @param string $value  Technical code
 * @param string $prefix Translation prefix suffix
 * @return string
 */
function lmdbsupplierorderlimitTranslateLogCode($value, $prefix)
{
	global $langs;

	$value = trim($value);
	if ($value === '') {
		return '';
	}

	$keySuffix = lmdbsupplierorderlimitBuildLogTranslationSuffix($value);
	if ($keySuffix === '') {
		return $value;
	}

	$key = 'LmdbSupplierOrderLimitLog'.$prefix.$keySuffix;
	$translated = $langs->transnoentitiesnoconv($key);
	if ($translated !== $key) {
		return $translated;
	}

	return $value;
}

/**
 * Translate a log message while keeping real free-text messages unchanged.
 *
 * @param LmdbSupplierOrderLimitLog $record Log record
 * @return string
 */
function lmdbsupplierorderlimitTranslateLogMessage($record)
{
	$message = $record->message !== null ? trim((string) $record->message) : '';
	if ($message === '') {
		return '';
	}

	$translated = lmdbsupplierorderlimitTranslateLogCode($message, 'Message');
	if ($translated !== $message) {
		return $translated;
	}

	if ($record->reason_code !== null && $message === (string) $record->reason_code) {
		return lmdbsupplierorderlimitTranslateLogCode((string) $record->reason_code, 'Reason');
	}

	if ($record->event_type !== null && $message === (string) $record->event_type) {
		return lmdbsupplierorderlimitTranslateLogCode((string) $record->event_type, 'Event');
	}

	return $message;
}

/**
 * Convert a stored log code into a translation key suffix.
 *
 * @param string $value Technical code
 * @return string
 */
function lmdbsupplierorderlimitBuildLogTranslationSuffix($value)
{
	$normalized = preg_replace('/[^a-zA-Z0-9]+/', ' ', $value);
	if ($normalized === null) {
		return '';
	}

	return str_replace(' ', '', ucwords(strtolower(trim($normalized))));
}

/**
 * Read a native Dolibarr selectDate() submission.
 *
 * @param string $prefix Date prefix
 * @param bool   $endDay Return end of day
 * @return int|null
 */
function lmdbsupplierorderlimitLogsGetPostedDate($prefix, $endDay = false)
{
	$year = GETPOSTINT($prefix.'year');
	$month = GETPOSTINT($prefix.'month');
	$day = GETPOSTINT($prefix.'day');

	if ($year <= 0 || $month <= 0 || $day <= 0) {
		return null;
	}

	return $endDay ? dol_mktime(23, 59, 59, $month, $day, $year) : dol_mktime(0, 0, 0, $month, $day, $year);
}
