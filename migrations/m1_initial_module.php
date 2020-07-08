<?php
/**
*
* Prune PMs extension for the phpBB Forum Software package.
*
* @copyright (c) 2020 Rich McGirr (RMcGirr83)
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

namespace rmcgirr83\prunepms\migrations;

/**
* Migration stage 1: Initial module
*/
class m1_initial_module extends \phpbb\db\migration\migration
{
	/**
	* Add or update data in the database
	*
	* @return array Array of table data
	* @access public
	*/
	public function update_data()
	{
		return [
			['module.add', [
				'acp',
				'ACP_CAT_USERS',
				[
					'module_basename'	=> '\rmcgirr83\prunepms\acp\main_module',
					'auth'				=> 'ext_rmcgirr83/prunepms && acl_a_user',
					'modes'				=> ['main'],
				],
			]],
		];
	}
}
