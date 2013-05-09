<?php
/**
 * Wedge
 *
 * This file contains a standard way of displaying lists in Wedge.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

function createList($listOptions)
{
	global $context, $theme, $options, $txt, $settings;

	assert(isset($listOptions['id']));
	assert(isset($listOptions['columns']));
	assert(is_array($listOptions['columns']));
	assert((empty($listOptions['items_per_page']) || (isset($listOptions['get_count']['function'], $listOptions['base_href']) && is_numeric($listOptions['items_per_page']))));
	assert((empty($listOptions['default_sort_col']) || isset($listOptions['columns'][$listOptions['default_sort_col']])));
	assert((!isset($listOptions['form']) || isset($listOptions['form']['href'])));

	// All the context data will be easily accessible by using a reference.
	$context[$listOptions['id']] = array();
	$list_context =& $context[$listOptions['id']];

	// Figure out the sort.
	if (empty($listOptions['default_sort_col']))
	{
		$list_context['sort'] = array();
		$sort = '1=1';
	}
	else
	{
		$request_var_sort = isset($listOptions['request_vars']['sort']) ? $listOptions['request_vars']['sort'] : 'sort';
		$request_var_desc = isset($listOptions['request_vars']['desc']) ? $listOptions['request_vars']['desc'] : 'desc';
		if (isset($_REQUEST[$request_var_sort], $listOptions['columns'][$_REQUEST[$request_var_sort]], $listOptions['columns'][$_REQUEST[$request_var_sort]]['sort']))
			$list_context['sort'] = array(
				'id' => $_REQUEST[$request_var_sort],
				'desc' => isset($_REQUEST[$request_var_desc], $listOptions['columns'][$_REQUEST[$request_var_sort]]['sort']['reverse']),
			);
		else
			$list_context['sort'] = array(
				'id' => $listOptions['default_sort_col'],
				'desc' => (!empty($listOptions['default_sort_dir']) && $listOptions['default_sort_dir'] == 'desc') || (!empty($listOptions['columns'][$listOptions['default_sort_col']]['sort']['default']) && substr($listOptions['columns'][$listOptions['default_sort_col']]['sort']['default'], -4, 4) == 'desc') ? true : false,
			);

		// Set the database column sort.
		$sort = $listOptions['columns'][$list_context['sort']['id']]['sort'][$list_context['sort']['desc'] ? 'reverse' : 'default'];
	}

	$list_context['start_var_name'] = isset($listOptions['start_var_name']) ? $listOptions['start_var_name'] : 'start';
	// In some cases the full list must be shown, regardless of the amount of items.
	if (empty($listOptions['items_per_page']))
	{
		$list_context['start'] = 0;
		$list_context['items_per_page'] = 0;
	}
	// With items per page set, calculate total number of items and page index.
	else
	{
		// First get an impression of how many items to expect.
		if (isset($listOptions['get_count']['file']))
			loadSource($listOptions['get_count']['file']);
		$list_context['total_num_items'] = call_user_func_array($listOptions['get_count']['function'], empty($listOptions['get_count']['params']) ? array() : $listOptions['get_count']['params']);

		// Default the start to the beginning...sounds logical.
		$list_context['start'] = isset($_REQUEST[$list_context['start_var_name']]) ? (int) $_REQUEST[$list_context['start_var_name']] : 0;
		$list_context['items_per_page'] = $listOptions['items_per_page'];

		// Then create a page index.
		$list_context['page_index'] = template_page_index($listOptions['base_href'] . (empty($list_context['sort']) ? '' : ';' . $request_var_sort . '=' . $list_context['sort']['id'] . ($list_context['sort']['desc'] ? ';' . $request_var_desc : '')) . ($list_context['start_var_name'] != 'start' ? ';' . $list_context['start_var_name'] . '=%1$d' : ''), $list_context['start'], $list_context['total_num_items'], $list_context['items_per_page'], $list_context['start_var_name'] != 'start');
	}

	// Prepare the headers of the table.
	$list_context['headers'] = array();
	foreach ($listOptions['columns'] as $column_id => $column)
		$list_context['headers'][] = array(
			'id' => $column_id,
			'label' => isset($column['header']['eval']) ? eval($column['header']['eval']) : (isset($column['header']['value']) ? $column['header']['value'] : ''),
			'href' => empty($listOptions['default_sort_col']) || empty($column['sort']) ? '' : $listOptions['base_href'] . ';' . $request_var_sort . '=' . $column_id . ($column_id === $list_context['sort']['id'] && !$list_context['sort']['desc'] && isset($column['sort']['reverse']) ? ';' . $request_var_desc : '') . (empty($list_context['start']) ? '' : ';' . $list_context['start_var_name'] . '=' . $list_context['start']),
			'sort_image' => empty($listOptions['default_sort_col']) || empty($column['sort']) || $column_id !== $list_context['sort']['id'] ? null : ($list_context['sort']['desc'] ? 'down' : 'up'),
			'class' => isset($column['header']['class']) ? $column['header']['class'] : '',
			'style' => isset($column['header']['style']) ? $column['header']['style'] : '',
			'colspan' => isset($column['header']['colspan']) ? $column['header']['colspan'] : '',
		);

	// We know the amount of columns, might be useful for the template.
	$list_context['num_columns'] = count($listOptions['columns']);
	$list_context['width'] = isset($listOptions['width']) ? $listOptions['width'] : '0';

	// Get the file with the function for the item list.
	if (isset($listOptions['get_items']['file']))
		loadSource($listOptions['get_items']['file']);

	// Call the function and include which items we want and in what order.
	$list_items = call_user_func_array($listOptions['get_items']['function'], array_merge(array($list_context['start'], $list_context['items_per_page'], $sort), empty($listOptions['get_items']['params']) ? array() : $listOptions['get_items']['params']));

	// Loop through the list items to be shown and construct the data values.
	$list_context['rows'] = array();
	foreach ($list_items as $item_id => $list_item)
	{
		$cur_row = array();
		foreach ($listOptions['columns'] as $column_id => $column)
		{
			$cur_data = array();

			// A value straight from the database?
			if (isset($column['data']['db']))
				$cur_data['value'] = $list_item[$column['data']['db']];

			// Take the value from the database and make it HTML safe.
			elseif (isset($column['data']['db_htmlsafe']))
				$cur_data['value'] = htmlspecialchars($list_item[$column['data']['db_htmlsafe']]);

			// Using sprintf is probably the most readable way of injecting data.
			elseif (isset($column['data']['sprintf']))
			{
				$params = array();
				foreach ($column['data']['sprintf']['params'] as $sprintf_param => $htmlsafe)
					$params[] = $htmlsafe ? htmlspecialchars($list_item[$sprintf_param]) : $list_item[$sprintf_param];
				$cur_data['value'] = vsprintf($column['data']['sprintf']['format'], $params);
			}

			// The most flexible way probably is applying a custom function.
			elseif (isset($column['data']['function']))
				$cur_data['value'] = $column['data']['function']($list_item);

			// A modified value (inject the database values).
			elseif (isset($column['data']['eval']))
				$cur_data['value'] = eval(preg_replace('~%([a-zA-Z0-9_-]+)%~', '$list_item[\'$1\']', $column['data']['eval']));

			// A nicely formatted number.
			elseif (isset($column['data']['comma_format']))
				$cur_data['value'] = comma_format($list_item[$column['data']['comma_format']]);

			// A straight DB-containing-timestamp formatted time (allowing for timezones etc.)
			elseif (isset($column['data']['timeformat']))
				$cur_data['value'] = timeformat($list_item[$column['data']['timeformat']]);

			// In case you want the time as it is in the display, 'on <date>, <time>' or '<Today> at <time>'.
			elseif (isset($column['data']['on_timeformat']))
				$cur_data['value'] = on_timeformat($list_item[$column['data']['timeformat']]);

			// A literal value.
			elseif (isset($column['data']['value']))
				$cur_data['value'] = $column['data']['value'];

			// Empty value.
			else
				$cur_data['value'] = '';

			// Set a style class for this column?
			if (isset($column['data']['class']))
				$cur_data['class'] = $column['data']['class'];

			// Fully customized styling for the cells in this column only.
			if (isset($column['data']['style']))
				$cur_data['style'] = $column['data']['style'];

			// Add the data cell properties to the current row.
			$cur_row['values'][$column_id] = $cur_data;
		}

		// We might want to apply row-wide styling. We set up in the master definition that we want to use a given column
		// of results as the row style or additional class for that row and name the column from the result for it.
		if (isset($listOptions['row_style'], $list_item[$listOptions['row_style']]))
			$cur_row['style'] = $list_item[$listOptions['row_style']];
		if (isset($listOptions['row_class'], $list_item[$listOptions['row_class']]))
			$cur_row['class'] = $list_item[$listOptions['row_class']];

		// Insert the row into the list.
		$list_context['rows'][$item_id] = $cur_row;
	}

	// The title is currently optional.
	if (isset($listOptions['title']))
		$list_context['title'] = $listOptions['title'];
	if (isset($listOptions['cat']))
		$list_context['cat'] = $listOptions['cat'];

	// In case there's a form, share it with the template context.
	if (isset($listOptions['form']))
	{
		$list_context['form'] = $listOptions['form'];

		if (!isset($list_context['form']['hidden_fields']))
			$list_context['form']['hidden_fields'] = array();

		// Always add a session check field.
		$list_context['form']['hidden_fields'][$context['session_var']] = $context['session_id'];

		// Include the starting page as hidden field?
		if (!empty($list_context['form']['include_start']) && !empty($list_context['start']))
			$list_context['form']['hidden_fields'][$list_context['start_var_name']] = $list_context['start'];

		// If sorting needs to be the same after submitting, add the parameter.
		if (!empty($list_context['form']['include_sort']) && !empty($list_context['sort']))
		{
			$list_context['form']['hidden_fields']['sort'] = $list_context['sort']['id'];
			if ($list_context['sort']['desc'])
				$list_context['form']['hidden_fields']['desc'] = 1;
		}
	}

	// Wanna say something nice in case there are no items?
	if (isset($listOptions['no_items_label']))
	{
		$list_context['no_items_label'] = $listOptions['no_items_label'];
		$list_context['no_items_align'] = isset($listOptions['no_items_align']) ? $listOptions['no_items_align'] : '';
	}

	// A list can sometimes need a few extra rows above and below.
	if (isset($listOptions['additional_rows']))
	{
		$list_context['additional_rows'] = array();
		foreach ($listOptions['additional_rows'] as $row)
		{
			if (empty($row))
				continue;

			// Supported row positions: top_of_list, after_title,
			// above_column_headers, below_table_data, bottom_of_list.
			if (!isset($list_context['additional_rows'][$row['position']]))
				$list_context['additional_rows'][$row['position']] = array();
			$list_context['additional_rows'][$row['position']][] = $row;
		}
	}

	// Add an option for inline JavaScript.
	if (isset($listOptions['javascript']))
		$list_context['javascript'] = $listOptions['javascript'];

	// Make sure the template is loaded.
	loadTemplate('GenericList');
}
