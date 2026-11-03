<?php
/**
 * Block Layout Mode Helper for Child Theme.
 *
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('laca_block_layout_class')) {
	/**
	 * Trả về CSS class tương ứng với containerMode của block hoặc fallback string.
	 *
	 * @param array|string $attributes Block attributes or fallback class string.
	 * @return string 'is-layout-fluid' | 'is-layout-boxed' | string
	 */
	function laca_block_layout_class(array|string $attributes = []): string
	{
		if (is_string($attributes)) {
			return $attributes;
		}
		$mode = $attributes['containerMode'] ?? 'inherit';
		if ($mode === 'boxed') {
			return 'is-layout-boxed';
		}
		if ($mode === 'fluid') {
			return 'is-layout-fluid';
		}
		return '';
	}
}
