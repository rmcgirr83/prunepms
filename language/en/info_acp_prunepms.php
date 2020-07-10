<?php
/**
*
* Prune PMs extension for the phpBB Forum Software package.
*
* @copyright (c) 2020 Rich McGirr (RMcGirr83)
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

/**
* DO NOT CHANGE
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

// DEVELOPERS PLEASE NOTE
//
// All language files should use UTF-8 as their encoding and the files must not contain a BOM.
//
// Placeholders can now contain order information, e.g. instead of
// 'Page %s of %s' you can (and should) write 'Page %1$s of %2$s', this allows
// translators to re-order the output of data while ensuring it remains correct
//
// You do not need this where single placeholders are used, e.g. 'Message %d' is fine
// equally where a string contains only two placeholders which are used to wrap text
// in a url you again do not need to specify an order e.g., 'Click %sHERE%s' is fine
//
// Some characters you may want to copy&paste:
// ’ » “ ” …
//

$lang = array_merge($lang, array(
	'ACP_PPMS_TITLE'	=> 'Prune PMs',

	// ACP Module
	'ACP_PPMS'					=> 'Prune Private Messages',

	// Module Lang
	'ACP_PPMS_EXPLAIN'		=> 'Here you can prune private messages on the board. Choose a date and all private messages prior to that date will be removed.',
	'PPMS_BEFORE_DATE'			=> 'Delete PMs before date',
	'PPMS_BEFORE_DATE_EXPLAIN'	=> 'Enter a date in DD-MM-YYYY format.',

	'PPMS_PRUNE_FAILURE'			=> 'No private messages fit the selected criteria',
	'PPMS_STATS'					=> 'Private Message statistics',
	'PPMS_TOTAL_STATS'		=> 'There are a total of <i>%d</i> private messages in the database having dates of <i>%2$s</i> through <i>%3$s</i>.',
	'PPMS_TOTAL_STATS_NO_AMS'	=> 'There are a total of <i>%d</i> private messages in the database having dates of <i>%2$s</i> through <i>%3$s</i>. This number excludes administrators and moderators.',
	'PPMS_NO_STATS'			=> 'There are <i>%d</i> private messages to be deleted with a date before %2$s.',
	'PPMS_NO_STATS_NO_AMS'	=> 'There are <i>%d</i> private messages to be deleted with a date before %2$s. This number excludes administrators and moderators.',
	'PPMS_CHANGE_DATE'			=> 'The number of private messages to delete is very large.  You should click on "No" and change the date to reduce server load.',
	'PPMS_MSG_BLOCKS'			=> '%d private messages before %s',
	'PPMS_WARN'					=> '<strong>Running this script will remove private messages from your database prior to the date as specified below. </strong><br>It is <strong>strongly</strong> recommended you make a backup as the process is irreversible. The author of this script takes <strong>NO RESPONSIBILITY</strong> for actions you preform using this script.<br><strong>YOU’VE BEEN WARNED!</strong>',
	'PPMS_REFRESH'				=> 'Refresh',

	'PPMS_CONFIRM_WARN'			=> 'Clicking on `Yes` below will delete the number of private messages stated.  This process is <i>irreversible</i>, so please ensure this is your intention.',

	'PPMS_IGNORE_ADMINS_AND_MODS'			=> 'Ignore Administrators and Moderators',
	'PPMS_IGNORE_ADMINS_AND_MODS_EXPLAIN'	=> 'If set “Yes” PMs to/from Administrators and Moderators will be ignored',

	'PPMS_INVALID_DATE'		=> 	'The date has to be formatted <kbd>DD-MM-YYYY</kbd>.',

	'PPMS_MESSAGES_DELETED'	=> 'Selected private messages have been deleted.',
	'PPMS_PRUNE'			=> 'Prune',
	'PPMS_TO_PURGE'			=> '<strong><i>%d</i></strong> private messages with a date prior to <i>%s</i> will be purged!!',
	// ACP Logs
	'LOG_PPMS_DELETED'		=> '<strong>Prune PMs  - %d private messages were deleted on %2$s.</strong>',
	'PPMS_DELETED_SUCCESS'	=> 'Private messages were successfully deleted',
));
