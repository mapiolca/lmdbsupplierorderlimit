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
dol_include_once('/lmdbsupplierorderlimit/lib/lmdbsupplierorderlimit.lib.php');
dol_include_once('/lmdbsupplierorderlimit/class/lmdbsupplierorderlimitlog.class.php');

$langs->loadLangs(array('admin', 'users', 'lmdbsupplierorderlimit@lmdbsupplierorderlimit'));

$page = GETPOSTINT('page');
$limit = GETPOSTINT('limit') > 0 ? GETPOSTINT('limit') : getDolGlobalInt('MAIN_SIZE_LISTE_LIMIT', 20);
$offset = $limit * max(0, $page);

$searchUser = GETPOSTINT('search_user');
$searchSupplierOrder = GETPOSTINT('search_supplier_order');
$searchDecision = GETPOST('search_decision', 'int');
$searchReason = GETPOST('search_reason', 'alphanohtml');
$searchEventType = GETPOST('search_event_type', 'alphanohtml');
$searchDateStart = lmdbsupplierorderlimitLogsGetPostedDate('search_date_start');
$searchDateEnd = lmdbsupplierorderlimitLogsGetPostedDate('search_date_end', true);

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
if ($searchSupplierOrder > 0) {
	$filters['fk_supplier_order'] = $searchSupplierOrder;
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
$records = $logObject->fetchAll($limit, $offset, $filters);
if (!is_array($records)) {
	setEventMessages($logObject->error, $logObject->errors, 'errors');
	$records = array();
	$num = 0;
}

$param = '';
if ($searchUser > 0) {
	$param .= '&search_user='.(int) $searchUser;
}
if ($searchSupplierOrder > 0) {
	$param .= '&search_supplier_order='.(int) $searchSupplierOrder;
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

$form = new Form($db);

llxHeader('', $langs->trans('LmdbSupplierOrderLimitLogs'));

$linkback = lmdbsupplierorderlimitBackToModuleListLink();
print load_fiche_titre($langs->trans('LmdbSupplierOrderLimitLogs'), $linkback, 'title_setup');

$head = lmdbsupplierorderlimitAdminPrepareHead();
print dol_get_fiche_head($head, 'logs', $langs->trans('LmdbSupplierOrderLimit'), -1, 'supplier_order');

print_barre_liste($langs->trans('LmdbSupplierOrderLimitLogs'), $page, $_SERVER['PHP_SELF'], $param, '', '', '', $num, $num, 'object_lmdbsupplierorderlimit', 0, '', '', $limit);

print '<form id="logfilter" method="GET" action="'.$_SERVER['PHP_SELF'].'">';
print '<table class="liste centpercent">';
print '<tr class="liste_titre_filter">';
print '<td>'.$form->select_dolusers($searchUser, 'search_user', 1).'</td>';
print '<td><input class="flat maxwidth75" type="text" name="search_supplier_order" value="'.dol_escape_htmltag((string) $searchSupplierOrder).'"></td>';
print '<td>'.$form->selectyesno('search_decision', $searchDecision, 1, false, 1).'</td>';
print '<td><input class="flat" type="text" name="search_event_type" value="'.dol_escape_htmltag($searchEventType).'"></td>';
print '<td><input class="flat" type="text" name="search_reason" value="'.dol_escape_htmltag($searchReason).'"></td>';
print '<td>'.$form->selectDate($searchDateStart, 'search_date_start', 0, 0, 1, 'logfilter', 1, 1).' '.$form->selectDate($searchDateEnd, 'search_date_end', 0, 0, 1, 'logfilter', 1, 1).'</td>';
print '<td class="center"><input type="submit" class="button small" value="'.$langs->trans('Search').'"></td>';
print '</tr>';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('User').'</th>';
print '<th>'.$langs->trans('LmdbSupplierOrderLimitSupplierOrder').'</th>';
print '<th class="center">'.$langs->trans('LmdbSupplierOrderLimitDecision').'</th>';
print '<th>'.$langs->trans('LmdbSupplierOrderLimitEventType').'</th>';
print '<th>'.$langs->trans('LmdbSupplierOrderLimitReason').'</th>';
print '<th>'.$langs->trans('Date').'</th>';
print '<th>'.$langs->trans('LmdbSupplierOrderLimitMessage').'</th>';
print '</tr>';

if (count($records) === 0) {
	print '<tr class="oddeven"><td colspan="7"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}

foreach ($records as $record) {
	print '<tr class="oddeven">';
	print '<td>#'.((int) $record->fk_user_action).'</td>';
	print '<td>'.($record->fk_supplier_order ? '#'.((int) $record->fk_supplier_order) : '').'</td>';
	print '<td class="center"><span class="badge '.($record->decision ? 'badge-status4' : 'badge-status8').'">'.($record->decision ? $langs->trans('LmdbSupplierOrderLimitAllowed') : $langs->trans('LmdbSupplierOrderLimitDenied')).'</span></td>';
	print '<td>'.dol_escape_htmltag((string) $record->event_type).'</td>';
	print '<td>'.dol_escape_htmltag((string) $record->reason_code).'</td>';
	print '<td>'.dol_print_date((int) $record->date_creation, 'dayhour').'</td>';
	print '<td>'.dol_escape_htmltag((string) $record->message).'</td>';
	print '</tr>';
}

print '</table>';
print '</form>';

print dol_get_fiche_end();

llxFooter();
$db->close();

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
