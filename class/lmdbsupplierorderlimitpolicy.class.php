<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/** Deterministic financial rules, without database or permission side effects. */
class LmdbSupplierOrderLimitPolicy
{
	/** @var array<string,string> */
	public const TYPES = array('order' => 'LimitTypeOrder', 'day' => 'LimitTypeDay', 'month' => 'LimitTypeMonth', 'year' => 'LimitTypeYear', 'project_budget' => 'LimitTypeProjectBudget');

	/**
	 * Validate a nonnegative total and its DECIMAL(24,8) storage range.
	 * @param string|int|float|null $value
	 */
	public static function amount($value): ?string
	{
		if ($value === null || $value === '' || !is_scalar($value)) {
			return null;
		}
		if (is_bool($value) || (!is_numeric($value) && !preg_match('/^[0-9\s.,]+$/uD', (string) $value))) { return null; }
		// price2num() is permissive for text; validate its unrounded result before total normalization.
		$numeric = (string) price2num($value);
		if (!preg_match('/^[0-9]+(?:\.[0-9]+)?$/D', $numeric)) { return null; }
		$value = (string) price2num($numeric, 'MT');
		if (!preg_match('/^([0-9]{1,16})(?:\.([0-9]{1,8}))?$/D', $value, $parts)) {
			return null;
		}
		return (ltrim($parts[1], '0') ?: '0').'.'.str_pad($parts[2] ?? '', 8, '0');
	}

	/** Compare already validated nonnegative decimal strings, without floating point conversion. */
	public static function compare(string $left, string $right): int
	{
		$a = explode('.', $left, 2);
		$b = explode('.', $right, 2);
		$a[0] = ltrim($a[0], '0') ?: '0';
		$b[0] = ltrim($b[0], '0') ?: '0';
		return (strlen($a[0]) <=> strlen($b[0])) ?: (strcmp($a[0], $b[0]) <=> 0) ?: (strcmp(str_pad($a[1] ?? '', 8, '0'), str_pad($b[1] ?? '', 8, '0')) <=> 0);
	}

	/** Add validated nonnegative DECIMAL values without a bcmath dependency. */
	public static function add(string $left, string $right): string
	{
		$a = explode('.', $left, 2);
		$b = explode('.', $right, 2);
		$a = $a[0].str_pad($a[1] ?? '', 8, '0');
		$b = $b[0].str_pad($b[1] ?? '', 8, '0');
		$length = max(strlen($a), strlen($b));
		$a = str_pad($a, $length, '0', STR_PAD_LEFT);
		$b = str_pad($b, $length, '0', STR_PAD_LEFT);
		$out = '';
		$carry = 0;
		for ($i = $length - 1; $i >= 0; $i--) {
			$n = (int) $a[$i] + (int) $b[$i] + $carry;
			$out = ($n % 10).$out;
			$carry = intdiv($n, 10);
		}
		$out = str_pad(($carry ? '1' : '').$out, 9, '0', STR_PAD_LEFT);
		return (ltrim(substr($out, 0, -8), '0') ?: '0').'.'.substr($out, -8);
	}

	/**
	 * Return inclusive start / exclusive end. Rolling windows include now but not their expired boundary.
	 * @return array{start:int,end:int}
	 */
	public static function period(string $type, string $mode, int $now, string $timezone): array
	{
		$durations = array('day' => 86400, 'month' => 2592000, 'year' => 31536000);
		if (!isset($durations[$type]) || !in_array($mode, array('civil', 'rolling'), true)) {
			throw new InvalidArgumentException('invalid_period');
		}
		if ($mode === 'rolling') {
			return array('start' => $now - $durations[$type] + 1, 'end' => $now + 1);
		}
		$date = (new DateTimeImmutable('@'.$now))->setTimezone(new DateTimeZone($timezone));
		$start = $date->setTime(0, 0);
		if ($type === 'month') {
			$start = $start->modify('first day of this month');
		} elseif ($type === 'year') {
			$start = $start->setDate((int) $date->format('Y'), 1, 1);
		}
		return array('start' => $start->getTimestamp(), 'end' => $start->modify('+1 '.$type)->getTimestamp());
	}

	/**
	 * Active, accessible candidates only. Local beats shared per beneficiary; user beats groups per nature.
	 * @param list<array{rowid:int,entity:int,fk_user:int,fk_usergroup:int,limit_type:string,amount_ht:?string,unlimited:int}> $rows
	 * @return array<string,array{rowid:int,entity:int,fk_user:int,fk_usergroup:int,limit_type:string,amount_ht:?string,unlimited:int,source:string}>
	 */
	public static function select(array $rows, int $entity): array
	{
		$beneficiaries = array();
		foreach ($rows as $row) {
			if (!isset(self::TYPES[$row['limit_type']])) {
				throw new RuntimeException('invalid_rule');
			}
			if (!$row['unlimited'] && $row['limit_type'] !== 'project_budget' && self::amount($row['amount_ht']) === null) {
				throw new RuntimeException('invalid_amount');
			}
			$row['source'] = $row['fk_user'] > 0 ? 'user' : 'group';
			$key = $row['limit_type'].':'.$row['source'].':'.($row['fk_user'] ?: $row['fk_usergroup']);
			$old = $beneficiaries[$key] ?? null;
			if ($old === null || ($row['entity'] === $entity && $old['entity'] !== $entity)
				|| (($row['entity'] === $entity) === ($old['entity'] === $entity) && self::prefer($row, $old))) {
				$beneficiaries[$key] = $row;
			}
		}
		$result = array();
		foreach ($beneficiaries as $row) {
			$old = $result[$row['limit_type']] ?? null;
			if ($old === null || ($row['source'] === 'user' && $old['source'] === 'group')
				|| ($row['source'] === $old['source'] && self::prefer($row, $old))) {
				$result[$row['limit_type']] = $row;
			}
		}
		return $result;
	}

	/**
	 * @param array<string,int|string|null> $row
	 * @param array<string,int|string|null> $old
	 */
	private static function prefer(array $row, array $old): bool
	{
		if ($row['unlimited'] !== $old['unlimited']) {
			return $row['unlimited'] > $old['unlimited'];
		}
		$comparison = self::compare((string) ($row['amount_ht'] ?? '0'), (string) ($old['amount_ht'] ?? '0'));
		return $comparison > 0 || ($comparison === 0 && $row['rowid'] < $old['rowid']);
	}
}
